<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class CronManager
{
    private Feature $feature;

    // Milestone meta key prefix
    const MILESTONE_META_PREFIX = '_cobra_em_milestone_';

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // CRON REGISTRATION
    // -------------------------------------------------------------------------

    public function register_crons(): void
    {
        // Queue processor — hourly
        if (!wp_next_scheduled('cobra_emailmarketing_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'cobra_emailmarketing_process_queue');
        }

        // Re-engagement check — daily
        if (!wp_next_scheduled('cobra_emailmarketing_re_engagement')) {
            wp_schedule_event(time(), 'daily', 'cobra_emailmarketing_re_engagement');
        }

        // Weekly report — schedule at next monday 8h
        if (!wp_next_scheduled('cobra_emailmarketing_weekly_report')) {
            wp_schedule_event($this->next_monday_8h(), 'weekly', 'cobra_emailmarketing_weekly_report');
        }

        // Campaign dispatcher — hourly
        if (!wp_next_scheduled('cobra_emailmarketing_dispatch_campaigns')) {
            wp_schedule_event(time(), 'hourly', 'cobra_emailmarketing_dispatch_campaigns');
        }
    }

    public function deregister_crons(): void
    {
        wp_clear_scheduled_hook('cobra_emailmarketing_process_queue');
        wp_clear_scheduled_hook('cobra_emailmarketing_re_engagement');
        wp_clear_scheduled_hook('cobra_emailmarketing_weekly_report');
        wp_clear_scheduled_hook('cobra_emailmarketing_dispatch_campaigns');
    }

    private function next_monday_8h(): int
    {
        $hour = (int) ($this->feature->get_settings('cron.weekly_report_hour') ?? 8);
        $day  = $this->feature->get_settings('cron.weekly_report_day') ?? 'monday';

        return strtotime("next {$day} " . sprintf('%02d:00:00', $hour));
    }

    // -------------------------------------------------------------------------
    // CRON CALLBACKS
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // CAMPAIGN DISPATCHER
    // -------------------------------------------------------------------------

    public function dispatch_scheduled_campaigns(): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        if (!$this->feature->campaign_repo || !$this->feature->dispatcher) {
            return;
        }

        $campaigns = $this->feature->campaign_repo->get_due_campaigns();

        foreach ($campaigns as $campaign) {
            $this->feature->dispatcher->dispatch((int) $campaign['id']);
        }
    }

    public function process_queue(): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $this->feature->queue->process_due();
    }

    public function send_weekly_reports(): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $settings = $this->feature->get_settings();
        if (!($settings['emails']['weekly_report']['enabled'] ?? true)) {
            return;
        }

        $users = $this->get_active_users();

        foreach ($users as $user_id) {
            // Check user has activity this week
            $stats = $this->feature->templates->build_weekly_vars($user_id);
            if ((int) $stats['sessions_count'] < 1) {
                continue;
            }

            $this->feature->sender->send($user_id, 'weekly_report', $stats);
        }
    }

    public function check_re_engagement(): void
    {
        if (!$this->feature->is_globally_enabled()) {
            return;
        }

        $settings = $this->feature->get_settings();

        // 7-day re-engagement
        if ($settings['emails']['re_engagement_7j']['enabled'] ?? true) {
            foreach ($this->get_inactive_users(7, 29) as $user_id) {
                if (!$this->already_sent_recently($user_id, 're_engagement_7j', 7)) {
                    $this->feature->queue->enqueue($user_id, 're_engagement_7j', time());
                }
            }
        }

        // 30-day re-engagement
        if ($settings['emails']['re_engagement_30j']['enabled'] ?? true) {
            foreach ($this->get_inactive_users(30, 89) as $user_id) {
                if (!$this->already_sent_recently($user_id, 're_engagement_30j', 30)) {
                    $this->feature->queue->enqueue($user_id, 're_engagement_30j', time());
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // MILESTONES
    // -------------------------------------------------------------------------

    public function check_milestones(int $user_id, array $stats): void
    {
        $milestones = $this->detect_milestones($user_id, $stats);

        foreach ($milestones as $milestone) {
            // Avoid sending the same milestone twice
            $meta_key = self::MILESTONE_META_PREFIX . $milestone['id'];
            if (get_user_meta($user_id, $meta_key, true)) {
                continue;
            }

            update_user_meta($user_id, $meta_key, current_time('mysql'));

            $this->feature->sender->send($user_id, 'milestone', [
                'milestone_label' => $milestone['label'],
                'milestone_score' => $milestone['score'],
                'quiz_name'       => $milestone['quiz_name'],
            ]);
        }
    }

    private function detect_milestones(int $user_id, array $stats): array
    {
        global $wpdb;

        $milestones  = [];
        $stats_table = $wpdb->prefix . 'canvas_quiz_statistics';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return $milestones;
        }

        $total_completed = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$stats_table} WHERE user_id = %d AND status = 'completed'",
                $user_id
            )
        );

        $score_pct = isset($stats['total_correct'], $stats['question_number']) && $stats['question_number'] > 0
            ? round($stats['total_correct'] / $stats['question_number'] * 100, 0)
            : 0;

        $serie_name = '';
        if (!empty($stats['serie_id'])) {
            $series_table = $wpdb->prefix . 'canvas_quiz_series';
            $serie_name   = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT title FROM {$series_table} WHERE id = %d", $stats['serie_id'])
            );
        }

        // First quiz
        if ($total_completed === 1) {
            $milestones[] = [
                'id'        => 'first_quiz',
                'label'     => '🎉 Première série complétée !',
                'score'     => $score_pct,
                'quiz_name' => $serie_name,
            ];
        }

        // First success (>= 80%)
        if ($score_pct >= 80 && !get_user_meta($user_id, self::MILESTONE_META_PREFIX . 'first_success', true)) {
            $milestones[] = [
                'id'        => 'first_success',
                'label'     => '🏆 Première série réussie avec succès !',
                'score'     => $score_pct,
                'quiz_name' => $serie_name,
            ];
        }

        // Perfect score
        if ($score_pct === 100) {
            $milestones[] = [
                'id'        => 'perfect_' . ($stats['serie_id'] ?? 0),
                'label'     => '💯 Score parfait !',
                'score'     => 100,
                'quiz_name' => $serie_name,
            ];
        }

        // 10 sessions
        if ($total_completed === 10) {
            $milestones[] = [
                'id'        => 'sessions_10',
                'label'     => '🔥 10 séries complétées — tu es régulier(e) !',
                'score'     => $score_pct,
                'quiz_name' => $serie_name,
            ];
        }

        // 50 sessions
        if ($total_completed === 50) {
            $milestones[] = [
                'id'        => 'sessions_50',
                'label'     => '🚀 50 séries complétées — incroyable !',
                'score'     => $score_pct,
                'quiz_name' => $serie_name,
            ];
        }

        return $milestones;
    }

    // -------------------------------------------------------------------------
    // USER QUERIES
    // -------------------------------------------------------------------------

    private function get_active_users(): array
    {
        $users = get_users([
            'fields'       => 'ID',
            'role__not_in' => ['pending'],
            'number'       => -1,
        ]);

        // Filter out blocked users
        return array_filter($users, function ($uid) {
            return !$this->feature->prefs->is_blocked((int) $uid);
        });
    }

    /**
     * Users inactive for at least $min_days but less than $max_days.
     */
    private function get_inactive_users(int $min_days, int $max_days): array
    {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'canvas_quiz_statistics';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return [];
        }

        $from = date('Y-m-d H:i:s', strtotime("-{$max_days} days"));
        $to   = date('Y-m-d H:i:s', strtotime("-{$min_days} days"));

        // Users who had their last session between $max and $min days ago
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT st.user_id
                 FROM {$stats_table} st
                 WHERE st.status = 'completed'
                 GROUP BY st.user_id
                 HAVING MAX(st.date_taken) BETWEEN %s AND %s",
                $from,
                $to
            )
        );

        return array_filter(array_map('intval', $user_ids), function ($uid) {
            return !$this->feature->prefs->is_blocked($uid);
        });
    }

    private function already_sent_recently(int $user_id, string $email_type, int $days): bool
    {
        global $wpdb;
        $table = $this->feature->get_table_name('email_log');
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE user_id = %d AND email_type = %s AND status = 'sent' AND sent_at >= %s",
                $user_id,
                $email_type,
                $since
            )
        );

        return $count > 0;
    }
}
