<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * CRUD operations for cobra_email_campaigns table.
 */
class CampaignRepository
{
    private Feature $feature;
    private string  $table;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->table   = $feature->get_table_name('email_campaigns');
    }

    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    /** @return array<int, array> All campaigns ordered by created_at DESC */
    public function get_all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC",
            ARRAY_A
        );

        return $rows ?: [];
    }

    /** @return array|null */
    public function get_by_id(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get campaigns that are scheduled and due to be dispatched.
     *
     * @return array<int, array>
     */
    public function get_due_campaigns(): array
    {
        global $wpdb;

        $now  = current_time('mysql');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = 'scheduled' AND scheduled_at <= %s",
                $now
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    // -------------------------------------------------------------------------
    // WRITE
    // -------------------------------------------------------------------------

    /**
     * Insert or update a campaign.
     * Returns the ID on success, false on failure.
     *
     * @param array $data
     * @return int|false
     */
    public function save(array $data)
    {
        global $wpdb;

        $id = isset($data['id']) ? (int) $data['id'] : 0;

        $audience_filters = isset($data['audience_filters']) && is_array($data['audience_filters'])
            ? wp_json_encode($data['audience_filters'])
            : (isset($data['audience_filters']) ? $data['audience_filters'] : '{}');

        $row = [
            'name'             => sanitize_text_field($data['name'] ?? ''),
            'subject'          => sanitize_text_field($data['subject'] ?? ''),
            'body'             => wp_kses_post($data['body'] ?? ''),
            'template_id'      => (int) ($data['template_id'] ?? 0),
            'ai_prompt'        => sanitize_textarea_field($data['ai_prompt'] ?? ''),
            'audience_type'    => sanitize_key($data['audience_type'] ?? 'all'),
            'audience_filters' => $audience_filters,
            'scheduled_at'     => !empty($data['scheduled_at']) ? sanitize_text_field($data['scheduled_at']) : null,
            'status'           => sanitize_key($data['status'] ?? 'draft'),
            'updated_at'       => current_time('mysql'),
        ];

        $formats = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $row, ['id' => $id], $formats, ['%d']);
            return $result !== false ? $id : false;
        }

        $row['created_at'] = current_time('mysql');
        $row['sent_count']  = 0;
        $row['total_count'] = 0;
        $formats[] = '%s'; // created_at
        $formats[] = '%d'; // sent_count
        $formats[] = '%d'; // total_count

        $result = $wpdb->insert($this->table, $row, $formats);
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update only status (and optionally counts).
     *
     * @param int    $id
     * @param string $status
     * @param array  $extra  e.g. ['sent_count' => 42, 'total_count' => 100]
     */
    public function update_status(int $id, string $status, array $extra = []): bool
    {
        global $wpdb;

        $data    = array_merge(['status' => sanitize_key($status), 'updated_at' => current_time('mysql')], $extra);
        $formats = array_merge(['%s', '%s'], array_fill(0, count($extra), '%d'));

        $result = $wpdb->update($this->table, $data, ['id' => $id], $formats, ['%d']);
        return $result !== false;
    }

    /**
     * Delete a campaign. Returns true on success.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        // Only draft or cancelled campaigns can be deleted
        $campaign = $this->get_by_id($id);
        if (!$campaign) {
            return false;
        }
        if (in_array($campaign['status'], ['sending', 'sent'], true)) {
            return false;
        }

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        return $result !== false;
    }
}
