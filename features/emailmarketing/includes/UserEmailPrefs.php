<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class UserEmailPrefs
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // READ PREFS
    // -------------------------------------------------------------------------

    public function get_prefs(int $user_id): array
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );

        if (!$row) {
            $this->init_prefs($user_id);
            return $this->get_prefs($user_id);
        }

        $row['prefs'] = !empty($row['prefs']) ? json_decode($row['prefs'], true) : [];

        return $row;
    }

    private function init_prefs(int $user_id): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $wpdb->insert(
            $table,
            [
                'user_id'           => $user_id,
                'unsubscribed_all'  => 0,
                'bounced'           => 0,
                'bounce_count'      => 0,
                'prefs'             => '{}',
                'unsubscribe_token' => $this->generate_token($user_id),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s']
        );
    }

    // -------------------------------------------------------------------------
    // BLOCK CHECKS
    // -------------------------------------------------------------------------

    public function is_blocked(int $user_id): bool
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT unsubscribed_all, bounced FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        if (!$row) {
            return false;
        }

        return (bool) $row->unsubscribed_all || (bool) $row->bounced;
    }

    public function is_type_enabled(int $user_id, string $email_type): bool
    {
        $prefs = $this->get_prefs($user_id);

        if ((bool) $prefs['unsubscribed_all'] || (bool) $prefs['bounced']) {
            return false;
        }

        $type_prefs = $prefs['prefs'] ?? [];

        // If explicitly disabled in user prefs → block
        if (isset($type_prefs[$email_type]) && $type_prefs[$email_type] === false) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // UNSUBSCRIBE
    // -------------------------------------------------------------------------

    public function set_unsubscribed(int $user_id, string $type): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        if ($type === 'all') {
            $wpdb->update(
                $table,
                ['unsubscribed_all' => 1],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
        } else {
            $prefs = $this->get_prefs($user_id);
            $type_prefs = $prefs['prefs'] ?? [];
            $type_prefs[$type] = false;

            $wpdb->update(
                $table,
                ['prefs' => wp_json_encode($type_prefs)],
                ['user_id' => $user_id],
                ['%s'],
                ['%d']
            );
        }

        do_action('cobra_emailmarketing_unsubscribed', $user_id, $type);
    }

    public function unblock_user(int $user_id): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $wpdb->update(
            $table,
            ['bounced' => 0, 'unsubscribed_all' => 0, 'bounce_count' => 0],
            ['user_id' => $user_id],
            ['%d', '%d', '%d'],
            ['%d']
        );
    }

    // -------------------------------------------------------------------------
    // BOUNCE
    // -------------------------------------------------------------------------

    public function mark_bounced(int $user_id): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        // Ensure row exists
        $this->get_prefs($user_id);

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET bounced = 1, bounce_count = bounce_count + 1 WHERE user_id = %d",
                $user_id
            )
        );

        // Also update email_log
        $log_table = $this->feature->get_table_name('email_log');
        $user       = get_user_by('id', $user_id);
        if ($user) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$log_table} SET status = 'bounced' WHERE email_to = %s AND status = 'sent' ORDER BY sent_at DESC LIMIT 1",
                    $user->user_email
                )
            );
        }
    }

    public function mark_spam(int $user_id): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $this->get_prefs($user_id);

        $wpdb->update(
            $table,
            ['unsubscribed_all' => 1],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        );

        // Update log
        $log_table = $this->feature->get_table_name('email_log');
        $user       = get_user_by('id', $user_id);
        if ($user) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$log_table} SET status = 'spam' WHERE email_to = %s AND status = 'sent' ORDER BY sent_at DESC LIMIT 1",
                    $user->user_email
                )
            );
        }
    }

    // -------------------------------------------------------------------------
    // TOKEN / URL
    // -------------------------------------------------------------------------

    public function generate_token(int $user_id): string
    {
        return hash('sha256', $user_id . wp_generate_password(32, false));
    }

    public function get_unsubscribe_url(int $user_id, string $type = 'all'): string
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        $token = $wpdb->get_var(
            $wpdb->prepare("SELECT unsubscribe_token FROM {$table} WHERE user_id = %d", $user_id)
        );

        if (empty($token)) {
            $token = $this->generate_token($user_id);
            $this->get_prefs($user_id); // ensure row exists
            $wpdb->update($table, ['unsubscribe_token' => $token], ['user_id' => $user_id], ['%s'], ['%d']);
        }

        $page_id = (int) ($this->feature->get_settings('general.unsubscribe_page') ?? 0);
        $base    = $page_id ? get_permalink($page_id) : home_url('/');

        return add_query_arg([
            'cobra_unsubscribe' => 1,
            'token'             => $token,
            'type'              => $type,
        ], $base);
    }

    // -------------------------------------------------------------------------
    // REQUEST HANDLER (called on init)
    // -------------------------------------------------------------------------

    public function handle_unsubscribe_request(): void
    {
        if (!isset($_GET['cobra_unsubscribe']) || empty($_GET['token'])) {
            return;
        }

        $token = sanitize_text_field($_GET['token']);
        $type  = sanitize_key($_GET['type'] ?? 'all');

        global $wpdb;
        $table   = $this->feature->get_table_name('email_prefs');
        $user_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM {$table} WHERE unsubscribe_token = %s", $token)
        );

        if (!$user_id) {
            // Invalid token — let the page render with error
            set_transient('cobra_unsub_result_' . $token, 'invalid', 60);
            return;
        }

        $this->set_unsubscribed($user_id, $type);
        set_transient('cobra_unsub_result_' . $token, 'success', 60);
    }

    // -------------------------------------------------------------------------
    // BLOCKED USERS LIST (for admin)
    // -------------------------------------------------------------------------

    public function get_blocked_users(int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_prefs');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.user_id, p.bounced, p.unsubscribed_all, p.bounce_count, p.updated_at,
                        u.user_email, u.display_name
                 FROM {$table} p
                 LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
                 WHERE p.bounced = 1 OR p.unsubscribed_all = 1
                 ORDER BY p.updated_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }
}
