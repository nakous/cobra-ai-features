<?php

namespace CobraAI\Features\Credits;

use function CobraAI\{
    cobra_ai_db
};


class CreditCron {
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Schedule hooks
     */
    private const SCHEDULES = [
        'expiration_check' => [
            'interval' => 'daily',
            'hook' => 'cobra_ai_process_credit_expirations'
        ],
        'expiration_notice' => [
            'interval' => 'daily',
            'hook' => 'cobra_ai_send_expiration_notices'
        ],
        'balance_sync' => [
            'interval' => 'hourly',
            'hook' => 'cobra_ai_sync_credit_balances'
        ],
        'cleanup' => [
            'interval' => 'weekly',
            'hook' => 'cobra_ai_cleanup_credit_data'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct($feature) {
        $this->feature = $feature;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Register schedules
        add_filter('cron_schedules', [$this, 'register_schedules']);

        // Schedule hooks
        foreach (self::SCHEDULES as $task => $config) {
            add_action($config['hook'], [$this, "run_{$task}"]);
        }

        // Admin init hook for manual cron runs
        add_action('admin_init', [$this, 'handle_manual_cron']);
    }

    /**
     * Register custom cron schedules
     */
    public function register_schedules($schedules): array {
        // Add custom intervals if needed
        $schedules['weekly'] = [
            'interval' => 7 * DAY_IN_SECONDS,
            'display' => __('Once Weekly', 'cobra-ai')
        ];

        return $schedules;
    }

    /**
     * Schedule tasks
     */
    public function schedule_tasks(): void {
        foreach (self::SCHEDULES as $task => $config) {
            if (!wp_next_scheduled($config['hook'])) {
                wp_schedule_event(
                    time(), 
                    $config['interval'], 
                    $config['hook']
                );
            }
        }
    }

    /**
     * Clear scheduled tasks
     */
    public function clear_schedules(): void {
        foreach (self::SCHEDULES as $config) {
            $timestamp = wp_next_scheduled($config['hook']);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $config['hook']);
            }
        }
    }

    /**
     * Process credit expirations
     */
    public function run_expiration_check(): void {
        try {
            cobra_ai_db()->log('info', 'Starting credit expiration check');

            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            // Get expired credits
            $expired = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table 
                WHERE status = 'active' 
                AND expiration_date IS NOT NULL 
                AND expiration_date <= %s",
                current_time('mysql')
            ));

            if (empty($expired)) {
                cobra_ai_db()->log('info', 'No expired credits found');
                return;
            }

            $processed = 0;
            foreach ($expired as $credit) {
                // Update credit status
                $updated = $wpdb->update(
                    $table,
                    [
                        'status' => 'expired',
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $credit->id],
                    ['%s', '%s'],
                    ['%d']
                );

                if ($updated) {
                    $processed++;
                    
                    // Update user balance
                    do_action('cobra_ai_update_user_balance', $credit->user_id);
                    
                    // Trigger expiration action
                    do_action('cobra_ai_credit_expired', $credit);
                }
            }

            cobra_ai_db()->log('info', sprintf(
                'Processed %d expired credits out of %d total',
                $processed,
                count($expired)
            ));

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error processing credit expirations: ' . $e->getMessage());
        }
    }

    /**
     * Send expiration notices
     */
    public function run_expiration_notice(): void {
        try {
            cobra_ai_db()->log('info', 'Starting expiration notice check');

            $settings = $this->feature->get_settings();
            if (empty($settings['notifications']['enable_expiration_notice'])) {
                return;
            }

            $notice_days = (int)$settings['notifications']['expiration_notice_days'];
            if ($notice_days <= 0) {
                return;
            }

            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            // Get credits about to expire
            $expiring = $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, u.user_email, u.display_name 
                FROM $table c
                JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.status = 'active' 
                AND c.expiration_date IS NOT NULL 
                AND c.expiration_date BETWEEN NOW() 
                AND DATE_ADD(NOW(), INTERVAL %d DAY)
                AND (c.credit - c.consumed) > 0",
                $notice_days
            ));

            if (empty($expiring)) {
                cobra_ai_db()->log('info', 'No credits found requiring expiration notice');
                return;
            }

            // Group by user
            $user_credits = [];
            foreach ($expiring as $credit) {
                if (!isset($user_credits[$credit->user_id])) {
                    $user_credits[$credit->user_id] = [
                        'user' => $credit,
                        'credits' => []
                    ];
                }
                $user_credits[$credit->user_id]['credits'][] = $credit;
            }

            // Send notices
            $sent = 0;
            foreach ($user_credits as $user_id => $data) {
                $sent += $this->send_expiration_notice($data['user'], $data['credits']);
            }

            cobra_ai_db()->log('info', sprintf(
                'Sent %d expiration notices to %d users',
                $sent,
                count($user_credits)
            ));

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error sending expiration notices: ' . $e->getMessage());
        }
    }

    /**
     * Sync credit balances
     */
    public function run_balance_sync(): void {
        try {
            cobra_ai_db()->log('info', 'Starting credit balance sync');

            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            // Get users with credits
            $users = $wpdb->get_col(
                "SELECT DISTINCT user_id FROM $table 
                WHERE status = 'active'"
            );

            if (empty($users)) {
                return;
            }

            $processed = 0;
            foreach ($users as $user_id) {
                // Calculate total available credits
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(credit - consumed) 
                    FROM $table 
                    WHERE user_id = %d 
                    AND status = 'active'
                    AND (expiration_date IS NULL OR expiration_date > NOW())",
                    $user_id
                ));

                update_user_meta($user_id, '_cobra_ai_credit_balance', (float)$total);
                $processed++;
            }

            cobra_ai_db()->log('info', sprintf(
                'Synced credit balances for %d users',
                $processed
            ));

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error syncing credit balances: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup old credit data
     */
    public function run_cleanup(): void {
        try {
            cobra_ai_db()->log('info', 'Starting credit data cleanup');

            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            $settings = $this->feature->get_settings();
            $retention_days = isset($settings['cleanup']['retention_days']) 
                ? (int)$settings['cleanup']['retention_days'] 
                : 365;

            // Archive old expired/deleted credits
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table 
                WHERE status IN ('expired', 'deleted') 
                AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            ));

            cobra_ai_db()->log('info', sprintf(
                'Cleaned up %d old credit records',
                $deleted
            ));

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error cleaning up credit data: ' . $e->getMessage());
        }
    }

    /**
     * Send expiration notice to user
     */
    private function send_expiration_notice($user, array $credits): int {
        if (empty($credits)) {
            return 0;
        }

        $settings = $this->feature->get_settings();
        
        // Prepare email content
        $subject = sprintf(
            __('[%s] Credits Expiring Soon', 'cobra-ai'),
            get_bloginfo('name')
        );

        $message = $this->get_expiration_notice_content($user, $credits);

        // Send email
        $sent = wp_mail(
            $user->user_email,
            $subject,
            $message,
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
            ]
        );

        return $sent ? 1 : 0;
    }

    /**
     * Get expiration notice email content
     */
    private function get_expiration_notice_content($user, array $credits): string {
        $settings = $this->feature->get_settings();
        $template = !empty($settings['notifications']['expiration_notice_template'])
            ? $settings['notifications']['expiration_notice_template']
            : $this->get_default_notice_template();

        // Prepare credit list
        $credit_list = '';
        foreach ($credits as $credit) {
            $remaining = $credit->credit - $credit->consumed;
            $credit_list .= sprintf(
                '<li>%s %s (%s: %s)</li>',
                number_format_i18n($remaining, 2),
                esc_html($settings['general']['credit_symbol']),
                __('Expires', 'cobra-ai'),
                get_date_from_gmt($credit->expiration_date, get_option('date_format'))
            );
        }

        // Replace placeholders
        $replacements = [
            '{site_name}' => get_bloginfo('name'),
            '{user_name}' => $user->display_name,
            '{credit_list}' => $credit_list,
            '{credits_page_url}' => admin_url('admin.php?page=cobra-ai-credits')
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Get default notice template
     */
    private function get_default_notice_template(): string {
        return '
            <p>' . __('Hello {user_name},', 'cobra-ai') . '</p>
            
            <p>' . __('This is a reminder that you have credits that will expire soon on {site_name}:', 'cobra-ai') . '</p>
            
            <ul>{credit_list}</ul>
            
            <p>' . __('To avoid losing these credits, please use them before they expire.', 'cobra-ai') . '</p>
            
            <p><a href="{credits_page_url}">' . __('View your credits', 'cobra-ai') . '</a></p>
            
            <p>' . __('Best regards,', 'cobra-ai') . '<br>{site_name}</p>
        ';
    }

    /**
     * Handle manual cron run
     */
    public function handle_manual_cron(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = $_GET['cobra_ai_cron'] ?? '';
        if (empty($action) || !isset(self::SCHEDULES[$action])) {
            return;
        }

        check_admin_referer('cobra_ai_run_cron_' . $action);

        $method = "run_{$action}";
        if (method_exists($this, $method)) {
            $this->$method();
        }

        wp_redirect(add_query_arg(
            ['page' => 'cobra-ai-credits', 'cron_run' => $action],
            admin_url('admin.php')
        ));
        exit;
    }
}