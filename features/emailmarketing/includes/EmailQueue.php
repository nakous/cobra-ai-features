<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class EmailQueue
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // ENQUEUE
    // -------------------------------------------------------------------------

    /**
     * Add an email to the queue (no duplicate pending allowed per user+type).
     *
     * @param int    $user_id
     * @param string $email_type
     * @param int    $timestamp    Unix timestamp for when to send
     * @param array  $payload      Extra vars to pass to the template at send time
     * @return bool
     */
    public function enqueue(int $user_id, string $email_type, int $timestamp, array $payload = []): bool
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        // Don't enqueue if same type already pending for this user
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND email_type = %s AND status = 'pending'",
                $user_id,
                $email_type
            )
        );

        if ($existing > 0) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_id'      => $user_id,
                'email_type'   => $email_type,
                'scheduled_at' => date('Y-m-d H:i:s', $timestamp),
                'status'       => 'pending',
                'payload'      => wp_json_encode($payload),
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    // -------------------------------------------------------------------------
    // PROCESS DUE EMAILS
    // -------------------------------------------------------------------------

    /**
     * Find and send all due pending emails.
     * Called by CronManager on the hourly cron.
     */
    public function process_due(): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'pending'
                   AND scheduled_at <= %s
                 ORDER BY scheduled_at ASC
                 LIMIT 50",
                current_time('mysql')
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $this->process_row($row);
        }
    }

    private function process_row(array $row): void
    {
        global $wpdb;
        $table    = $this->feature->get_table_name('email_queue');
        $user_id  = (int) $row['user_id'];
        $type     = $row['email_type'];
        $payload  = !empty($row['payload']) ? (array) json_decode($row['payload'], true) : [];

        // J+2 condition: skip if user already has quiz sessions
        if ($type === 'onboarding_j2' && $this->user_has_sessions($user_id)) {
            $this->mark($row['id'], 'cancelled');
            return;
        }

        // Mark as processing (optimistic lock)
        $updated = $wpdb->update(
            $table,
            ['status' => 'sent'],
            ['id' => $row['id'], 'status' => 'pending'],
            ['%s'],
            ['%d', '%s']
        );

        if (!$updated) {
            // Already processed by another process
            return;
        }

        $success = $this->feature->sender->send($user_id, $type, $payload);

        if (!$success) {
            $wpdb->update(
                $table,
                ['status' => 'failed'],
                ['id' => $row['id']],
                ['%s'],
                ['%d']
            );
        }
    }

    // -------------------------------------------------------------------------
    // CANCEL
    // -------------------------------------------------------------------------

    public function cancel(int $user_id, string $email_type): bool
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        return (bool) $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['user_id' => $user_id, 'email_type' => $email_type, 'status' => 'pending'],
            ['%s'],
            ['%d', '%s', '%s']
        );
    }

    public function cancel_all_for_user(int $user_id): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['user_id' => $user_id, 'status' => 'pending'],
            ['%s'],
            ['%d', '%s']
        );
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function mark(int $queue_id, string $status): void
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $queue_id],
            ['%s'],
            ['%d']
        );
    }

    private function user_has_sessions(int $user_id): bool
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'canvas_quiz_statistics';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return false;
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$stats_table} WHERE user_id = %d AND status = 'completed'",
                $user_id
            )
        );

        return $count > 0;
    }

    // -------------------------------------------------------------------------
    // QUEUE STATUS (for admin)
    // -------------------------------------------------------------------------

    public function get_pending_count(): int
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_queue');

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
        );
    }
}
