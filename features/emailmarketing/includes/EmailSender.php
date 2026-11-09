<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class EmailSender
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // MAIN SEND
    // -------------------------------------------------------------------------

    /**
     * Send an email to a user.
     *
     * @param int    $user_id
     * @param string $email_type
     * @param array  $extra_vars  Extra template variables (e.g. weekly stats)
     * @return bool
     */
    public function send(int $user_id, string $email_type, array $extra_vars = []): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            $this->log($user_id, $email_type, '', '', 'failed', ['reason' => 'user_not_found']);
            return false;
        }

        // --- Global feature toggle
        if (!$this->feature->is_globally_enabled()) {
            return false;
        }

        // --- Email type enabled in settings
        $enabled = $this->feature->get_settings("emails.{$email_type}.enabled");
        if ($enabled === false) {
            $this->log($user_id, $email_type, $user->user_email, '', 'skipped', ['reason' => 'type_disabled']);
            return false;
        }

        // --- User prefs / block check
        if (!$this->feature->prefs->is_type_enabled($user_id, $email_type)) {
            $this->log($user_id, $email_type, $user->user_email, '', 'skipped', ['reason' => 'user_blocked_or_unsubscribed']);
            return false;
        }

        // --- Frequency limit
        if (!$this->can_send_today($user_id)) {
            $this->log($user_id, $email_type, $user->user_email, '', 'skipped', ['reason' => 'frequency_limit']);
            return false;
        }

        // --- External filter (allow third-party to cancel)
        $should_send = apply_filters('cobra_emailmarketing_should_send', true, $email_type, $user_id);
        if (!$should_send) {
            $this->log($user_id, $email_type, $user->user_email, '', 'skipped', ['reason' => 'filtered_out']);
            return false;
        }

        // --- Build email
        $rendered = $this->feature->templates->render($user_id, $email_type, $extra_vars);
        $subject  = $rendered['subject'];
        $body     = $rendered['body'];

        if (empty($body)) {
            $this->log($user_id, $email_type, $user->user_email, $subject, 'failed', ['reason' => 'empty_template']);
            return false;
        }

        // --- Headers
        $settings   = $this->feature->get_settings();
        $from_email = !empty($settings['general']['from_email']) ? $settings['general']['from_email'] : get_option('admin_email');
        $from_name  = !empty($settings['general']['from_name'])  ? $settings['general']['from_name']  : get_bloginfo('name');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        // --- Send
        $result = wp_mail($user->user_email, $subject, $body, $headers);

        $status = $result ? 'sent' : 'failed';
        $this->log($user_id, $email_type, $user->user_email, $subject, $status, $extra_vars);

        if ($result) {
            do_action('cobra_emailmarketing_email_sent', $email_type, $user_id, array_merge(['subject' => $subject], $extra_vars));
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // TEST EMAIL (bypass anti-spam, send to custom address)
    // -------------------------------------------------------------------------

    public function send_test(int $user_id, string $email_type, string $to): bool
    {
        $rendered = $this->feature->templates->render($user_id, $email_type, []);
        $subject  = '[TEST] ' . $rendered['subject'];
        $body     = $rendered['body'];

        $settings   = $this->feature->get_settings();
        $from_email = !empty($settings['general']['from_email']) ? $settings['general']['from_email'] : get_option('admin_email');
        $from_name  = !empty($settings['general']['from_name'])  ? $settings['general']['from_name']  : get_bloginfo('name');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        return wp_mail($to, $subject, $body, $headers);
    }

    // -------------------------------------------------------------------------
    // ANTI-SPAM
    // -------------------------------------------------------------------------

    private function can_send_today(int $user_id): bool
    {
        $limit = (int) ($this->feature->get_settings('general.frequency_limit') ?? 1);
        if ($limit <= 0) {
            return true;
        }

        global $wpdb;
        $table = $this->feature->get_table_name('email_log');
        $today = date('Y-m-d');

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE user_id = %d
                   AND status IN ('sent')
                   AND DATE(sent_at) = %s",
                $user_id,
                $today
            )
        );

        return $count < $limit;
    }

    // -------------------------------------------------------------------------
    // LOGGING
    // -------------------------------------------------------------------------

    public function log(
        int    $user_id,
        string $email_type,
        string $email_to,
        string $subject,
        string $status,
        array  $metadata = []
    ): void {
        global $wpdb;
        $table = $this->feature->get_table_name('email_log');

        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'email_type' => $email_type,
                'email_to'   => $email_to,
                'subject'    => $subject,
                'status'     => $status,
                'sent_at'    => current_time('mysql'),
                'metadata'   => wp_json_encode($metadata),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    // -------------------------------------------------------------------------
    // LOG QUERIES (for admin)
    // -------------------------------------------------------------------------

    public function get_log(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_log');

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['email_type'])) {
            $where[]  = 'email_type = %s';
            $params[] = $filters['email_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[]  = 'DATE(sent_at) >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]  = 'DATE(sent_at) <= %s';
            $params[] = $filters['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $params[]  = $limit;
        $params[]  = $offset;

        $sql = "SELECT l.*, u.display_name
                FROM {$table} l
                LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
                WHERE {$where_sql}
                ORDER BY l.sent_at DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    public function count_log(array $filters = []): int
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_log');

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['email_type'])) {
            $where[]  = 'email_type = %s';
            $params[] = $filters['email_type'];
        }

        $where_sql = implode(' AND ', $where);
        $sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        return empty($params)
            ? (int) $wpdb->get_var($sql)
            : (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }
}
