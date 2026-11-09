<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * Dynamic trigger engine.
 *
 * On init() it loads all enabled triggers from the DB and:
 *  - For trigger_type = 'hook'        → registers an add_action() on hook_name
 *  - For trigger_type = 'cron_delay'  → registers add_action() on delay_ref_hook
 *                                        and schedules a delayed send via EmailQueue
 *  - For trigger_type = 'cron_schedule' → handled by CronManager, not here
 *  - For trigger_type = 'manual'       → no automatic hook, admin-triggered only
 */
class TriggerEngine
{
    private Feature            $feature;
    private TriggerRepository  $trig_repo;
    private TemplateRepository $tpl_repo;
    private ConditionEvaluator $conditions;

    public function __construct(Feature $feature)
    {
        $this->feature    = $feature;
        $this->trig_repo  = $feature->trig_repo;
        $this->tpl_repo   = $feature->tpl_repo;
        $this->conditions = new ConditionEvaluator($feature);
    }

    // -------------------------------------------------------------------------
    // REGISTRATION
    // -------------------------------------------------------------------------

    /**
     * Register dynamic hooks for all enabled hook/cron_delay triggers.
     * Call this from Feature::init_hooks() at priority 5 (after WP is loaded).
     */
    public function register(): void
    {
        global $wpdb;

        // Guard: table might not exist yet (before first activation)
        $triggers_table = $this->feature->get_table_name('email_triggers');
        $table_exists   = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $triggers_table)
        );
        if (!$table_exists) {
            return;
        }

        $triggers = $this->trig_repo->get_all(true);

        foreach ($triggers as $trigger) {
            switch ($trigger['trigger_type']) {
                case 'hook':
                    $this->register_hook_trigger($trigger);
                    break;

                case 'cron_delay':
                    $this->register_cron_delay_trigger($trigger);
                    break;

                // cron_schedule and manual are handled elsewhere
            }
        }
    }

    // -------------------------------------------------------------------------
    // HOOK TRIGGER
    // -------------------------------------------------------------------------

    /**
     * Attach a WordPress action for a hook-type trigger.
     * The callback extracts the user_id from the hook arguments.
     *
     * @param array $trigger  Trigger row from DB.
     */
    private function register_hook_trigger(array $trigger): void
    {
        $hook_name = $trigger['hook_name'];
        if (empty($hook_name)) {
            return;
        }

        $trigger_id    = (int) $trigger['id'];
        $arg_index     = (int) ($trigger['hook_user_arg_index'] ?? 0);
        $accepted_args = $arg_index + 2; // accept enough args to reach the user_id position

        add_action(
            $hook_name,
            function () use ($trigger_id, $arg_index) {
                $hook_args = func_get_args();
                $user_id   = (int) ($hook_args[$arg_index] ?? 0);
                if ($user_id <= 0) {
                    return;
                }
                $this->dispatch_hook($user_id, $trigger_id, $hook_args);
            },
            10,
            $accepted_args
        );
    }

    // -------------------------------------------------------------------------
    // CRON_DELAY TRIGGER
    // -------------------------------------------------------------------------

    /**
     * Attach a WordPress action for the reference hook, then schedule a delayed send.
     *
     * @param array $trigger  Trigger row from DB.
     */
    private function register_cron_delay_trigger(array $trigger): void
    {
        $ref_hook = $trigger['delay_ref_hook'];
        if (empty($ref_hook)) {
            return;
        }

        $trigger_id    = (int) $trigger['id'];
        $arg_index     = (int) ($trigger['hook_user_arg_index'] ?? 0);
        $accepted_args = $arg_index + 2;

        add_action(
            $ref_hook,
            function () use ($trigger_id, $arg_index) {
                $hook_args = func_get_args();
                $user_id   = (int) ($hook_args[$arg_index] ?? 0);
                if ($user_id <= 0) {
                    return;
                }
                $this->schedule_delayed($user_id, $trigger_id, $hook_args);
            },
            10,
            $accepted_args
        );
    }

    // -------------------------------------------------------------------------
    // DISPATCH
    // -------------------------------------------------------------------------

    /**
     * Immediately process a hook trigger for one user.
     *
     * @param int   $user_id
     * @param int   $trigger_id
     * @param array $hook_args
     */
    public function dispatch_hook(int $user_id, int $trigger_id, array $hook_args = []): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $trigger = $this->trig_repo->get_by_id($trigger_id);
        if (!$trigger || !$trigger['enabled']) {
            return;
        }

        // Check user preferences (unsubscribed / bounced)
        if (!$this->feature->prefs->can_receive($user_id, $trigger['template_slug'] ?? '')) {
            return;
        }

        // Evaluate conditions
        if (!$this->conditions->evaluate($user_id, $trigger['conditions'], $hook_args)) {
            return;
        }

        // Check send mode
        if (!$this->check_send_mode($user_id, $trigger)) {
            return;
        }

        // Enqueue immediately (scheduled_at = now)
        $this->feature->queue->enqueue_trigger(
            $user_id,
            $trigger['template_slug'] ?? '',
            time(),
            $trigger_id,
            $hook_args
        );
    }

    /**
     * Schedule a delayed send (cron_delay trigger).
     *
     * @param int   $user_id
     * @param int   $trigger_id
     * @param array $hook_args
     */
    public function schedule_delayed(int $user_id, int $trigger_id, array $hook_args = []): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $trigger = $this->trig_repo->get_by_id($trigger_id);
        if (!$trigger || !$trigger['enabled']) {
            return;
        }

        if (!$this->feature->prefs->can_receive($user_id, $trigger['template_slug'] ?? '')) {
            return;
        }

        if (!$this->conditions->evaluate($user_id, $trigger['conditions'], $hook_args)) {
            return;
        }

        $delay_seconds = (int) ($trigger['delay_days'] ?? 0) * DAY_IN_SECONDS;
        $scheduled_at  = time() + $delay_seconds;

        $this->feature->queue->enqueue_trigger(
            $user_id,
            $trigger['template_slug'] ?? '',
            $scheduled_at,
            $trigger_id,
            $hook_args
        );
    }

    /**
     * Process a cron_schedule trigger for a batch of users.
     * Called by CronManager for scheduled/audience-based sends.
     *
     * @param array $trigger      Trigger row from DB (already fetched by CronManager).
     * @param int[] $user_ids     Audience user IDs to check.
     */
    public function dispatch_cron_batch(array $trigger, array $user_ids): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $trigger_id   = (int) $trigger['id'];
        $template_slug = $trigger['template_slug'] ?? '';

        foreach ($user_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id <= 0) {
                continue;
            }

            if (!$this->feature->prefs->can_receive($user_id, $template_slug)) {
                continue;
            }

            if (!$this->conditions->evaluate($user_id, $trigger['conditions'])) {
                continue;
            }

            if (!$this->check_send_mode($user_id, $trigger)) {
                continue;
            }

            $this->feature->queue->enqueue_trigger(
                $user_id,
                $template_slug,
                time(),
                $trigger_id
            );
        }
    }

    // -------------------------------------------------------------------------
    // SEND MODE
    // -------------------------------------------------------------------------

    /**
     * Check whether the trigger's send_mode allows sending to this user right now.
     *
     * @param int   $user_id
     * @param array $trigger  DB row.
     * @return bool
     */
    private function check_send_mode(int $user_id, array $trigger): bool
    {
        $send_mode    = $trigger['send_mode']    ?? 'once_per_user';
        $cooldown     = (int) ($trigger['cooldown_days'] ?? 0);
        $template_slug = $trigger['template_slug'] ?? '';
        $trigger_id   = (int) $trigger['id'];

        switch ($send_mode) {

            case 'always':
                return true;

            case 'once_per_user':
                return !$this->user_received_trigger($user_id, $trigger_id);

            case 'cooldown':
                if ($cooldown <= 0) {
                    return true;
                }
                $last_sent = $this->last_sent_time($user_id, $trigger_id);
                if (!$last_sent) {
                    return true;
                }
                $next_allowed = $last_sent + ($cooldown * DAY_IN_SECONDS);
                return time() >= $next_allowed;

            default:
                return true;
        }
    }

    /**
     * Check if a user has already received mail for this trigger (any time).
     *
     * @param int $user_id
     * @param int $trigger_id
     * @return bool
     */
    private function user_received_trigger(int $user_id, int $trigger_id): bool
    {
        global $wpdb;

        $log_table = $this->feature->get_table_name('email_log');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table}
                 WHERE user_id = %d
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.trigger_id')) = %s
                   AND status = 'sent'",
                $user_id,
                (string) $trigger_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Return Unix timestamp of the last sent mail for this trigger/user, or null.
     *
     * @param int $user_id
     * @param int $trigger_id
     * @return int|null
     */
    private function last_sent_time(int $user_id, int $trigger_id): ?int
    {
        global $wpdb;

        $log_table = $this->feature->get_table_name('email_log');

        $row = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT sent_at FROM {$log_table}
                 WHERE user_id = %d
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.trigger_id')) = %s
                   AND status = 'sent'
                 ORDER BY sent_at DESC
                 LIMIT 1",
                $user_id,
                (string) $trigger_id
            )
        );

        return $row ? (int) strtotime($row) : null;
    }
}
