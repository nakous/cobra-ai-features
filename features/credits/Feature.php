<?php

namespace CobraAI\Features\Credits;

use CobraAI\FeatureBase;
use function CobraAI\{
    cobra_ai_db
};

class Feature extends FeatureBase
{
    /**
     * Feature properties
     */
    protected $feature_id = 'credits';
    protected $name = 'Credits System';
    protected $description = 'Manage user credits with multiple credit types, expiration, and tracking';
    protected $version = '1.0.0';
    protected $author = 'Cobra AI';
    protected $has_settings = true;
    protected $has_admin = true;
    protected $min_wp_version = '5.8';
    protected $min_php_version = '7.4';

    /**
     * Feature components
     */
    private $admin;
    public $manager;
    private $cron;

    public function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->tables = [
            'credits' => [
                'name' => $wpdb->prefix . 'cobra_credits',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'user_id' => 'bigint(20) NOT NULL',
                    'credit_type' => 'varchar(50) NOT NULL',
                    'type_id' => 'varchar(50) NOT NULL',
                    'comment' => 'text',
                    'credit' => 'decimal(10,2) NOT NULL DEFAULT 0',
                    'consumed' => 'decimal(10,2) NOT NULL DEFAULT 0',
                    'status' => "enum('pending','active','deleted','expired') NOT NULL DEFAULT 'pending'",
                    'start_date' => 'datetime NOT NULL',
                    'expiration_date' => 'datetime DEFAULT NULL',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'user_id' => '(user_id)',
                        'credit_type' => '(credit_type)',
                        'status' => '(status)'
                    ]
                ]
            ]
        ];
    }
    /**
     * Setup feature
     */
    protected function setup(): void
    {
       

        // Define feature tables

        require_once $this->path . 'includes/Class_Credits_List_Table.php';
        require_once $this->path . 'includes/CreditAdmin.php';
        // Load required files
        require_once $this->path . 'includes/CreditManager.php';
        require_once $this->path . 'includes/CreditType.php';
        require_once $this->path . 'includes/CreditCron.php';
    }

    /**
     * Initialize feature
     */
    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Credit management hooks

        add_action('cobra_ai_credit_added', [$this, 'handle_credit_added'], 10, 4);
        add_action('cobra_ai_credit_removed', [$this, 'handle_credit_removed'], 10, 2);
        add_action('cobra_ai_credit_updated', [$this, 'handle_credit_updated'], 10, 2);
        add_action('cobra_ai_credit_expired', [$this, 'handle_credit_expired'], 10, 1);

        // User related hooks
        // add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        // add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        // add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        // add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);

        // Cron hooks
        add_action('cobra_ai_daily_credit_check', [$this, 'process_expired_credits']);

        // Admin hooks
        if (is_admin()) {
            add_filter('manage_users_columns', [$this, 'add_credit_column']);
            add_filter('manage_users_custom_column', [$this, 'credit_column_content'], 10, 3);
            add_filter('user_row_actions', [$this, 'add_credit_action'], 10, 2);
            $this->admin = new CreditAdmin($this);
        }


        $this->manager = new CreditManager($this);
        $this->cron = new CreditCron($this);

        // Initialize CreditType
        CreditType::init();
    }

    /**
     * Get feature default options
     */

    protected function get_feature_default_options(): array
    {
        return [
            'general' => [
                'credit_types' => ['subscription', 'paid', 'free', 'coupon'],
                'credit_unit' => 'points',
                'credit_name' => 'credit',
                'credit_symbol' => 'pts',
                'type_order' => ['subscription', 'paid', 'free', 'coupon']
            ],
            'notifications' => [
                'enable_expiration_notice' => true,
                'expiration_notice_days' => 7,
                'notification_email_template' => ''
            ],
            'display' => [
                'show_in_profile' => true,
                'show_in_admin_list' => true,
                'history_per_page' => 10
            ],
            'expiration' => [
                'default_duration' => 30, // days
                'grace_period' => 0, // days
                'auto_expire' => true
            ]
        ];
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array
    {
        $errors = [];

        // Validate credit types
        if (empty($settings['general']['credit_types'])) {
            $errors[] = __('At least one credit type must be selected', 'cobra-ai');
        }

        // Validate credit unit
        if (!in_array($settings['general']['credit_unit'], ['points', 'currency'])) {
            $errors[] = __('Invalid credit unit selected', 'cobra-ai');
        }

        // Validate expiration settings
        if ($settings['expiration']['default_duration'] < 0) {
            $errors[] = __('Default duration cannot be negative', 'cobra-ai');
        }

        if ($settings['expiration']['grace_period'] < 0) {
            $errors[] = __('Grace period cannot be negative', 'cobra-ai');
        }

        // Store validation errors if any
        if (!empty($errors)) {
            update_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors', $errors);
            return $this->get_settings(); // Return current settings
        }

        delete_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors');
        return $settings;
    }

    /**
     * Credit Management Methods
     */

    /**
     * Add credits to user
     */
    public function add_credit(int $user_id, float $amount, string $type, string $comment = '', ?string $expiration = null): bool
    {
        try {
            if (!$this->validate_credit_type($type)) {
                throw new \Exception(__('Invalid credit type', 'cobra-ai'));
            }

            global $wpdb;
            $table = $this->get_table_name('credits');

            $data = [
                'user_id' => $user_id,
                'credit_type' => $type,
                'type_id' => uniqid($type . '_'),
                'comment' => $comment,
                'credit' => $amount,
                'status' => 'active',
                'start_date' => current_time('mysql'),
                'expiration_date' => $expiration
            ];

            $inserted = $wpdb->insert($table, $data);

            if ($inserted) {
                $credit_id = $wpdb->insert_id;
                $this->update_user_credit_cache($user_id);
                do_action('cobra_ai_credit_added', $user_id, $amount, $type, $credit_id);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to add credit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove credit by ID
     */
    public function remove_credit(int $credit_id): bool
    {
        try {
            global $wpdb;
            $table = $this->get_table_name('credits');

            // Get credit info before deletion
            $credit = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id, credit_type, credit FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$credit) {
                return false;
            }

            $updated = $wpdb->update(
                $table,
                ['status' => 'deleted'],
                ['id' => $credit_id],
                ['%s'],
                ['%d']
            );

            if ($updated) {
                $this->update_user_credit_cache($credit->user_id);
                do_action('cobra_ai_credit_removed', $credit_id, $credit);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to remove credit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user total credits
     */
    public function get_user_credit_total(int $user_id): float
    {
        $cached = get_user_meta($user_id, '_cobra_ai_credit_total', true);

        if ($cached !== '') {
            return (float)$cached;
        }

        global $wpdb;
        $table = $this->get_table_name('credits');

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credit - consumed) FROM $table 
            WHERE user_id = %d AND status = 'active' 
            AND (expiration_date IS NULL OR expiration_date > NOW())",
            $user_id
        ));

        update_user_meta($user_id, '_cobra_ai_credit_total', $total ?: 0);
        return (float)$total ?: 0;
    }

    /**
     * Get user credit history
     */
    public function get_user_credit_history(int $user_id, array $args = []): array
    {
        global $wpdb;
        $table = $this->get_table_name('credits');

        $defaults = [
            'status' => null,
            'type' => null,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'limit' => 10,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['user_id = %d'];
        $params = [$user_id];

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['type'];
        }

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Process expired credits
     */
    public function process_expired_credits(): void
    {
        global $wpdb;
        $table = $this->get_table_name('credits');

        // Get expired credits
        $expired = $wpdb->get_results(
            "SELECT id, user_id FROM $table 
            WHERE status = 'active' 
            AND expiration_date IS NOT NULL 
            AND expiration_date <= NOW()"
        );

        foreach ($expired as $credit) {
            $wpdb->update(
                $table,
                ['status' => 'expired'],
                ['id' => $credit->id],
                ['%s'],
                ['%d']
            );

            $this->update_user_credit_cache($credit->user_id);
            do_action('cobra_ai_credit_expired', $credit->id);
        }
    }

    /**
     * Utility Methods
     */

    /**
     * Validate credit type
     */
    protected function validate_credit_type(string $type): bool
    {
        $valid_types = $this->get_settings('general')['credit_types'];
        return in_array($type, $valid_types);
    }

    /**
     * Update user credit cache
     */
    protected function update_user_credit_cache(int $user_id): void
    {
        delete_user_meta($user_id, '_cobra_ai_credit_total');
        $this->get_user_credit_total($user_id); // Regenerate cache
    }

    /**
     * Get available credit types
     */
    public function get_credit_types(): array
    {
        return apply_filters('cobra_ai_credit_types', [
            'subscription' => __('Subscription', 'cobra-ai'),
            'paid' => __('Paid', 'cobra-ai'),
            'free' => __('Free', 'cobra-ai'),
            'coupon' => __('Coupon', 'cobra-ai'),
            'gift' => __('Gift', 'cobra-ai'),
            'reward' => __('Reward', 'cobra-ai'),
            'discount' => __('Discount', 'cobra-ai'),
            'bonus' => __('Bonus', 'cobra-ai')
        ]);
    }

    /**
     * Admin Interface Methods
     */

    /**
     * Add credit column to users list
     */
    public function add_credit_column($columns)
    {
        $columns['credits'] = __('Credits', 'cobra-ai');
        return $columns;
    }

    /**
     * Display credit column content
     */
    public function credit_column_content($value, $column_name, $user_id)
    {
        if ($column_name === 'credits') {
            $total = $this->get_user_credit_total($user_id);
            $settings = $this->get_settings();
            return sprintf(
                '%s %s',
                number_format_i18n($total, 2),
                esc_html($settings['general']['credit_symbol'])
            );
        }
        return $value;
    }

    /**
     * Add credit action to user row
     */
    public function add_credit_action($actions, $user)
    {
        if (current_user_can('edit_users')) {
            $actions['add_credit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-credits',
                    'action' => 'add',
                    'user_id' => $user->ID
                ], admin_url('admin.php'))),
                __('Add Credit', 'cobra-ai')
            );
        }
        return $actions;
    }


    /**
     * Handle credit added event
     */
    public function handle_credit_added($user_id, $amount, $type, $credit_id): void
    {
        try {
            // Log the credit addition
            cobra_ai_db()->log('info', sprintf(
                'Credit added: %s %s to user #%d',
                $amount,
                $type,
                $user_id
            ), [
                'credit_id' => $credit_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type
            ]);

            // Update user credit balance in meta
            $this->update_user_balance($user_id);

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_added', $credit_id, $user_id, $amount, $type);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit addition: ' . $e->getMessage(), [
                'credit_id' => $credit_id,
                'user_id' => $user_id
            ]);
        }
    }

    /**
     * Handle credit removed event
     */
    public function handle_credit_removed($credit_id, $credit_data): void
    {
        try {
            // Log the credit removal
            cobra_ai_db()->log('info', sprintf(
                'Credit removed: #%d from user #%d',
                $credit_id,
                $credit_data->user_id
            ), [
                'credit_id' => $credit_id,
                'credit_data' => $credit_data
            ]);

            // Update user credit balance
            $this->update_user_balance($credit_data->user_id);

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_removed', $credit_id, $credit_data);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit removal: ' . $e->getMessage(), [
                'credit_id' => $credit_id
            ]);
        }
    }

    /**
     * Handle credit updated event
     */
    public function handle_credit_updated($credit_id, $data): void
    {
        try {
            // Log the credit update
            cobra_ai_db()->log('info', sprintf(
                'Credit updated: #%d',
                $credit_id
            ), [
                'credit_id' => $credit_id,
                'update_data' => $data
            ]);

            // Update user balance if relevant fields changed
            if (isset($data['user_id'])) {
                $this->update_user_balance($data['user_id']);
            }

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_updated', $credit_id, $data);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit update: ' . $e->getMessage(), [
                'credit_id' => $credit_id,
                'data' => $data
            ]);
        }
    }

    /**
     * Handle credit expired event
     */
    public function handle_credit_expired($credit_id): void
    {
        try {
            global $wpdb;
            $table = $this->get_table_name('credits');

            // Get credit data
            $credit = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$credit) {
                throw new \Exception('Credit not found');
            }

            // Log the expiration
            cobra_ai_db()->log('info', sprintf(
                'Credit expired: #%d for user #%d',
                $credit_id,
                $credit->user_id
            ), [
                'credit_id' => $credit_id,
                'credit_data' => $credit
            ]);

            // Update user balance
            $this->update_user_balance($credit->user_id);

            // Send notification if enabled
            $settings = $this->get_settings();
            if (!empty($settings['notifications']['enable_expiration_notice'])) {
                $this->send_expiration_notification($credit);
            }

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_expired', $credit_id, $credit);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit expiration: ' . $e->getMessage(), [
                'credit_id' => $credit_id
            ]);
        }
    }

    /**
     * Update user credit balance
     */
    private function update_user_balance(int $user_id): void
    {
        try {
            global $wpdb;
            $table = $this->get_table_name('credits');

            // Calculate total available credits
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(credit - consumed) 
                FROM $table 
                WHERE user_id = %d 
                AND status = 'active'
                AND (expiration_date IS NULL OR expiration_date > %s)",
                $user_id,
                current_time('mysql')
            ));

            // Update user meta
            update_user_meta($user_id, '_cobra_ai_credit_balance', (float)$total);

            // Log balance update
            cobra_ai_db()->log('debug', sprintf(
                'Updated credit balance for user #%d: %s',
                $user_id,
                $total
            ));
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error updating user balance: ' . $e->getMessage(), [
                'user_id' => $user_id
            ]);
        }
    }

    /**
     * Send expiration notification
     */
    private function send_expiration_notification($credit): void
    {
        try {
            $user = get_user_by('id', $credit->user_id);
            if (!$user) {
                throw new \Exception('User not found');
            }

            $settings = $this->get_settings();

            // Build email content
            $subject = sprintf(
                __('[%s] Credit Expired', 'cobra-ai'),
                get_bloginfo('name')
            );

            $message = sprintf(
                __('Your credit of %s %s has expired.', 'cobra-ai'),
                number_format_i18n($credit->credit - $credit->consumed, 2),
                $settings['general']['credit_symbol']
            );

            // Send email
            wp_mail(
                $user->user_email,
                $subject,
                $message,
                [
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
                ]
            );
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error sending expiration notification: ' . $e->getMessage(), [
                'credit_id' => $credit->id,
                'user_id' => $credit->user_id
            ]);
        }
    }


    /**
     * Add credit fields to user profile
     */
    // public function add_user_profile_fields($user): void {
    //     if (!current_user_can('manage_options')) {
    //         return;
    //     }

    //     $settings = $this->get_settings();
    //     if (empty($settings['display']['show_in_profile'])) {
    //         return;
    //     }

    //     $total_credits = $this->get_user_credit_total($user->ID);
    //     $credit_history = $this->get_user_credit_history($user->ID, [
    //         'limit' => 5,
    //         'orderby' => 'created_at',
    //         'order' => 'DESC'
    //     ]);

    //     // Load user profile view
    //     include $this->path . 'views/user-profile-credits.php';
    // }

    /**
     * Get user total credits
     */
    // public function get_user_credit_total(int $user_id): float {
    //     global $wpdb;
    //     $table = $this->get_table_name('credits');

    //     $total = $wpdb->get_var($wpdb->prepare(
    //         "SELECT SUM(credit - consumed) 
    //         FROM $table 
    //         WHERE user_id = %d 
    //         AND status = 'active' 
    //         AND (expiration_date IS NULL OR expiration_date > %s)",
    //         $user_id,
    //         current_time('mysql')
    //     ));

    //     return (float)$total ?: 0;
    // }

    // /**
    //  * Get user credit history
    //  */
    // public function get_user_credit_history(int $user_id, array $args = []): array {
    //     global $wpdb;
    //     $table = $this->get_table_name('credits');

    //     $defaults = [
    //         'limit' => 10,
    //         'offset' => 0,
    //         'orderby' => 'created_at',
    //         'order' => 'DESC'
    //     ];

    //     $args = wp_parse_args($args, $defaults);

    //     $query = $wpdb->prepare(
    //         "SELECT * FROM $table 
    //         WHERE user_id = %d 
    //         ORDER BY {$args['orderby']} {$args['order']}
    //         LIMIT %d OFFSET %d",
    //         $user_id,
    //         $args['limit'],
    //         $args['offset']
    //     );

    //     return $wpdb->get_results($query) ?: [];
    // }

    /**
     * Get user credit types with balances
     */
    public function get_user_credit_types(int $user_id): array
    {
        global $wpdb;
        $table = $this->get_table_name('credits');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                credit_type,
                SUM(credit) as total_credit,
                SUM(consumed) as total_consumed,
                SUM(credit - consumed) as available
            FROM $table 
            WHERE user_id = %d 
            AND status = 'active'
            AND (expiration_date IS NULL OR expiration_date > %s)
            GROUP BY credit_type",
            $user_id,
            current_time('mysql')
        ));

        $credit_types = [];
        foreach ($results as $row) {
            $credit_types[$row->credit_type] = [
                'total' => (float)$row->total_credit,
                'consumed' => (float)$row->total_consumed,
                'available' => (float)$row->available
            ];
        }

        return $credit_types;
    }
}
