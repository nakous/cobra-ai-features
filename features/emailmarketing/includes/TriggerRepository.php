<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * CRUD operations for cobra_email_triggers table.
 */
class TriggerRepository
{
    private Feature $feature;
    private string  $table;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->table   = $feature->get_table_name('email_triggers');
    }

    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    /** @return array<int, array> */
    public function get_all(bool $enabled_only = false): array
    {
        global $wpdb;

        $where = $enabled_only ? 'WHERE t.enabled = 1' : '';

        $rows = $wpdb->get_results(
            "SELECT t.*, tpl.slug AS template_slug, tpl.label AS template_label
             FROM {$this->table} t
             LEFT JOIN {$this->feature->get_table_name('email_templates')} tpl ON tpl.id = t.template_id
             {$where}
             ORDER BY t.is_system DESC, t.label ASC",
            ARRAY_A
        );

        return array_map([$this, 'decode_conditions'], $rows ?: []);
    }

    /** @return array|null */
    public function get_by_id(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT t.*, tpl.slug AS template_slug, tpl.label AS template_label
                 FROM {$this->table} t
                 LEFT JOIN {$this->feature->get_table_name('email_templates')} tpl ON tpl.id = t.template_id
                 WHERE t.id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ? $this->decode_conditions($row) : null;
    }

    /** @return array<int, array> Triggers listening to this WordPress hook name */
    public function get_by_hook(string $hook_name): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, tpl.slug AS template_slug
                 FROM {$this->table} t
                 LEFT JOIN {$this->feature->get_table_name('email_templates')} tpl ON tpl.id = t.template_id
                 WHERE t.trigger_type IN ('hook','cron_delay')
                   AND t.hook_name = %s
                   AND t.enabled = 1",
                $hook_name
            ),
            ARRAY_A
        );

        return array_map([$this, 'decode_conditions'], $rows ?: []);
    }

    /** @return array<int, array> Active triggers of a given type (for cron dispatch) */
    public function get_by_type(string $trigger_type): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, tpl.slug AS template_slug
                 FROM {$this->table} t
                 LEFT JOIN {$this->feature->get_table_name('email_templates')} tpl ON tpl.id = t.template_id
                 WHERE t.trigger_type = %s AND t.enabled = 1",
                $trigger_type
            ),
            ARRAY_A
        );

        return array_map([$this, 'decode_conditions'], $rows ?: []);
    }

    // -------------------------------------------------------------------------
    // WRITE
    // -------------------------------------------------------------------------

    /**
     * Insert or update a trigger.
     *
     * @param array $data Expected keys:
     *   label, template_id, trigger_type, hook_name, hook_user_arg_index,
     *   delay_days, delay_ref_hook, cron_day, cron_hour, cron_audience,
     *   send_mode, cooldown_days, conditions (array|null), is_system, enabled.
     *   Include 'id' to update an existing row.
     * @return int|false New/existing ID or false on DB error.
     */
    public function save(array $data)
    {
        global $wpdb;

        $id = isset($data['id']) ? (int) $data['id'] : 0;

        $allowed_types     = ['hook', 'cron_delay', 'cron_schedule', 'manual'];
        $allowed_send_mode = ['once_per_user', 'cooldown', 'always'];

        $trigger_type = in_array($data['trigger_type'] ?? '', $allowed_types, true)
            ? $data['trigger_type']
            : 'hook';

        $send_mode = in_array($data['send_mode'] ?? '', $allowed_send_mode, true)
            ? $data['send_mode']
            : 'once_per_user';

        $conditions = isset($data['conditions']) && is_array($data['conditions'])
            ? wp_json_encode($data['conditions'])
            : (isset($data['conditions']) ? $data['conditions'] : null);

        $row = [
            'label'               => sanitize_text_field($data['label'] ?? ''),
            'template_id'         => (int) ($data['template_id'] ?? 0),
            'trigger_type'        => $trigger_type,
            'hook_name'           => sanitize_text_field($data['hook_name'] ?? ''),
            'hook_user_arg_index' => max(0, (int) ($data['hook_user_arg_index'] ?? 0)),
            'delay_days'          => max(0, (int) ($data['delay_days'] ?? 0)),
            'delay_ref_hook'      => sanitize_text_field($data['delay_ref_hook'] ?? ''),
            'cron_day'            => sanitize_key($data['cron_day'] ?? ''),
            'cron_hour'           => min(23, max(0, (int) ($data['cron_hour'] ?? 8))),
            'cron_audience'       => sanitize_key($data['cron_audience'] ?? 'all_active'),
            'send_mode'           => $send_mode,
            'cooldown_days'       => max(0, (int) ($data['cooldown_days'] ?? 0)),
            'conditions'          => $conditions,
            'is_system'           => (int) ($data['is_system'] ?? 0),
            'enabled'             => (int) ($data['enabled'] ?? 1),
        ];

        $formats = ['%s','%d','%s','%s','%d','%d','%s','%d','%d','%s','%s','%d','%s','%d','%d'];

        if ($id > 0) {
            $result = $wpdb->update($this->table, $row, ['id' => $id], $formats, ['%d']);
            return $result !== false ? $id : false;
        }

        $result = $wpdb->insert($this->table, $row, $formats);
        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Delete a trigger. System triggers cannot be deleted.
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
    // MIGRATION — seed the 8 system triggers
    // -------------------------------------------------------------------------

    /**
     * Seed the 8 system triggers (idempotent — matches by label).
     * Must be called AFTER seed_system_templates() so template IDs exist.
     */
    public function seed_system_triggers(TemplateRepository $tpl_repo): void
    {
        $saved_settings = $this->feature->get_settings();
        $cron           = $saved_settings['cron'] ?? [];

        $weekly_day  = $cron['weekly_report_day']  ?? 'monday';
        $weekly_hour = (int) ($cron['weekly_report_hour'] ?? 8);

        /**
         * Definition of the 8 system triggers.
         * 'slug' is used to look up the template_id.
         */
        $system = [
            [
                'label'               => 'Onboarding J+0 — À la confirmation du compte',
                'template_slug'       => 'onboarding_j0',
                'trigger_type'        => 'hook',
                'hook_name'           => 'cobra_register_user_confirmed',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => '',
                'cron_hour'           => 8,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'once_per_user',
                'cooldown_days'       => 0,
                'conditions'          => null,
            ],
            [
                'label'               => 'Onboarding J+2 — Prise en main',
                'template_slug'       => 'onboarding_j2',
                'trigger_type'        => 'cron_delay',
                'hook_name'           => 'cobra_register_user_confirmed',
                'hook_user_arg_index' => 0,
                'delay_days'          => 2,
                'delay_ref_hook'      => 'cobra_register_user_confirmed',
                'cron_day'            => '',
                'cron_hour'           => 8,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'once_per_user',
                'cooldown_days'       => 0,
                'conditions'          => null,
            ],
            [
                'label'               => 'Onboarding J+7 — Bilan',
                'template_slug'       => 'onboarding_j7',
                'trigger_type'        => 'cron_delay',
                'hook_name'           => 'cobra_register_user_confirmed',
                'hook_user_arg_index' => 0,
                'delay_days'          => 7,
                'delay_ref_hook'      => 'cobra_register_user_confirmed',
                'cron_day'            => '',
                'cron_hour'           => 8,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'once_per_user',
                'cooldown_days'       => 0,
                'conditions'          => null,
            ],
            [
                'label'               => 'Re-engagement — inactif depuis 7 jours',
                'template_slug'       => 're_engagement_7j',
                'trigger_type'        => 'cron_schedule',
                'hook_name'           => '',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => 'daily',
                'cron_hour'           => 8,
                'cron_audience'       => 'inactive_7',
                'send_mode'           => 'cooldown',
                'cooldown_days'       => 7,
                'conditions'          => null,
            ],
            [
                'label'               => 'Re-engagement — inactif depuis 30 jours',
                'template_slug'       => 're_engagement_30j',
                'trigger_type'        => 'cron_schedule',
                'hook_name'           => '',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => 'daily',
                'cron_hour'           => 8,
                'cron_audience'       => 'inactive_30',
                'send_mode'           => 'cooldown',
                'cooldown_days'       => 30,
                'conditions'          => null,
            ],
            [
                'label'               => 'Rapport hebdomadaire',
                'template_slug'       => 'weekly_report',
                'trigger_type'        => 'cron_schedule',
                'hook_name'           => '',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => $weekly_day,
                'cron_hour'           => $weekly_hour,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'cooldown',
                'cooldown_days'       => 6,
                'conditions'          => null,
            ],
            [
                'label'               => 'Conseils personnalisés (manuel)',
                'template_slug'       => 'tips',
                'trigger_type'        => 'manual',
                'hook_name'           => '',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => '',
                'cron_hour'           => 8,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'always',
                'cooldown_days'       => 0,
                'conditions'          => null,
            ],
            [
                'label'               => 'Félicitations — étape clé atteinte',
                'template_slug'       => 'milestone',
                'trigger_type'        => 'hook',
                'hook_name'           => 'canvas_quiz_session_completed',
                'hook_user_arg_index' => 0,
                'delay_days'          => 0,
                'delay_ref_hook'      => '',
                'cron_day'            => '',
                'cron_hour'           => 8,
                'cron_audience'       => 'all_active',
                'send_mode'           => 'once_per_user',
                'cooldown_days'       => 0,
                'conditions'          => null,
            ],
        ];

        foreach ($system as $def) {
            // Look up the template ID by slug
            $tpl = $tpl_repo->get_by_slug($def['template_slug']);
            if (!$tpl) {
                continue; // template not seeded yet — should not happen
            }

            // Skip if a system trigger for this template already exists
            $existing = $this->find_system_by_template((int) $tpl['id']);
            if ($existing !== null) {
                continue;
            }

            unset($def['template_slug']);
            $def['template_id'] = (int) $tpl['id'];
            $def['is_system']   = 1;
            $def['enabled']     = 1;

            $this->save($def);
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function find_system_by_template(int $template_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE template_id = %d AND is_system = 1 LIMIT 1",
                $template_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    private function decode_conditions(array $row): array
    {
        if (isset($row['conditions']) && is_string($row['conditions']) && $row['conditions'] !== '') {
            $decoded = json_decode($row['conditions'], true);
            $row['conditions'] = is_array($decoded) ? $decoded : null;
        } else {
            $row['conditions'] = null;
        }

        return $row;
    }
}
