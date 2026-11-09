<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * CRUD operations for cobra_email_templates table.
 */
class TemplateRepository
{
    private Feature $feature;
    private string  $table;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->table   = $feature->get_table_name('email_templates');
    }

    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    /** @return array<int, array> All templates ordered by is_system DESC, label ASC */
    public function get_all(bool $enabled_only = false): array
    {
        global $wpdb;

        $where = $enabled_only ? 'WHERE enabled = 1' : '';

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} {$where} ORDER BY is_system DESC, label ASC",
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

    /** @return array|null */
    public function get_by_slug(string $slug): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug),
            ARRAY_A
        );

        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // WRITE
    // -------------------------------------------------------------------------

    /**
     * Insert or update a template.
     * Returns the ID on success, false on failure.
     *
     * @param array $data Keys: slug, label, subject, body, is_system, enabled. Include 'id' to update.
     * @return int|false New/existing ID or false on DB error.
     */
    public function save(array $data)
    {
        global $wpdb;

        $id = isset($data['id']) ? (int) $data['id'] : 0;

        $row = [
            'slug'      => sanitize_key($data['slug'] ?? ''),
            'label'     => sanitize_text_field($data['label'] ?? ''),
            'subject'   => sanitize_text_field($data['subject'] ?? ''),
            'body'      => wp_kses_post($data['body'] ?? ''),
            'is_system' => (int) ($data['is_system'] ?? 0),
            'enabled'   => (int) ($data['enabled'] ?? 1),
        ];

        $formats = ['%s', '%s', '%s', '%s', '%d', '%d'];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $row, ['id' => $id], $formats, ['%d']);
            return $result !== false ? $id : false;
        }

        $result = $wpdb->insert($this->table, $row, $formats);
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Delete a template. System templates cannot be deleted.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $existing = $this->get_by_id($id);
        if (!$existing || $existing['is_system']) {
            return false;
        }

        return (bool) $wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    // -------------------------------------------------------------------------
    // MIGRATION — insert the 8 system templates from current settings/defaults
    // -------------------------------------------------------------------------

    /**
     * Seed the 8 system templates from PHP defaults (idempotent — skips existing slugs).
     */
    public function seed_system_templates(): void
    {
        $default_data = $this->feature->get_default_email_data();
        $subjects     = $default_data['emails']    ?? [];
        $bodies       = $default_data['templates'] ?? [];

        // Current saved settings may have user-customised subjects/bodies
        $saved     = $this->feature->get_settings();
        $saved_sub = $saved['emails']    ?? [];
        $saved_tpl = $saved['templates'] ?? [];

        $system = [
            'onboarding_j0'    => 'Onboarding J+0 — Bienvenue',
            'onboarding_j2'    => 'Onboarding J+2 — Prise en main',
            'onboarding_j7'    => 'Onboarding J+7 — Bilan',
            're_engagement_7j' => 'Re-engagement 7 jours inactif',
            're_engagement_30j'=> 'Re-engagement 30 jours inactif',
            'weekly_report'    => 'Rapport hebdomadaire',
            'tips'             => 'Conseils personnalisés',
            'milestone'        => 'Félicitations (étapes clés)',
        ];

        foreach ($system as $slug => $label) {
            // Skip if already in DB
            if ($this->get_by_slug($slug) !== null) {
                continue;
            }

            $subject = $saved_sub[$slug]['subject']
                    ?? $subjects[$slug]['subject']
                    ?? '';

            $body = $saved_tpl[$slug]
                 ?? $bodies[$slug]
                 ?? '';

            $enabled = isset($saved_sub[$slug]['enabled'])
                ? (int) $saved_sub[$slug]['enabled']
                : 1;

            $this->save([
                'slug'      => $slug,
                'label'     => $label,
                'subject'   => $subject,
                'body'      => $body,
                'is_system' => 1,
                'enabled'   => $enabled,
            ]);
        }
    }
}
