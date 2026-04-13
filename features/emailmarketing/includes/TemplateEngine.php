<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class TemplateEngine
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // RENDER
    // -------------------------------------------------------------------------

    /**
     * Render a complete email (layout + content) for a given user and type.
     *
     * @param int    $user_id
     * @param string $email_type
     * @param array  $extra_vars  Caller-provided variables (e.g. weekly stats)
     * @return array ['subject' => string, 'body' => string]
     */
    public function render(int $user_id, string $email_type, array $extra_vars = []): array
    {
        $settings = $this->feature->get_settings();

        $layout  = $settings['templates']['layout']     ?? $this->feature->default_layout();
        $footer  = $settings['templates']['footer']     ?? $this->feature->default_footer();
        $content = $settings['templates'][$email_type]  ?? '';
        $subject = $settings['emails'][$email_type]['subject'] ?? '';

        // Build all variables
        $global_vars = $this->get_global_vars($user_id, $email_type, $settings);
        $type_vars   = $this->get_type_vars($user_id, $email_type, $extra_vars);
        $all_vars    = array_merge($global_vars, $type_vars, $extra_vars);

        // Render subject
        $subject = $this->replace_vars($subject, $all_vars);

        // Render content body
        $content = $this->replace_vars($content, $all_vars);

        // Render footer
        $footer = $this->replace_vars($footer, $all_vars);

        // Inject content + footer into layout
        $all_vars['content'] = $content;
        $all_vars['footer']  = $footer;
        $all_vars['subject'] = $subject;
        $body = $this->replace_vars($layout, $all_vars);

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    // -------------------------------------------------------------------------
    // VARIABLE RESOLUTION
    // -------------------------------------------------------------------------

    private function get_global_vars(int $user_id, string $email_type, array $settings): array
    {
        $user   = get_user_by('id', $user_id);
        $prenom = $user ? (get_user_meta($user_id, 'firstname', true) ?: $user->display_name) : '';

        $from_name  = !empty($settings['general']['from_name'])  ? $settings['general']['from_name']  : get_bloginfo('name');
        $site_name  = get_bloginfo('name');
        $site_url   = home_url('/');

        $account_page = (int) ($settings['pages']['account'] ?? 0);
        $account_url  = $account_page ? get_permalink($account_page) : $site_url;

        return [
            'prenom'          => esc_html($prenom),
            'email'           => $user ? esc_html($user->user_email) : '',
            'site_name'       => esc_html($site_name),
            'site_url'        => esc_url($site_url),
            'from_name'       => esc_html($from_name),
            'account_url'     => esc_url($account_url),
            'unsubscribe_url' => $this->feature->prefs->get_unsubscribe_url($user_id, 'all'),
            'unsubscribe_url_type' => $this->feature->prefs->get_unsubscribe_url($user_id, $email_type),
        ];
    }

    private function get_type_vars(int $user_id, string $email_type, array $extra): array
    {
        switch ($email_type) {
            case 'weekly_report':
                return $this->build_weekly_vars($user_id);

            case 'tips':
                $permit_type = $extra['permit_type'] ?? $this->get_main_permit_type($user_id);
                return [
                    'permit_type'  => esc_html(ucfirst($permit_type)),
                    'tips_content' => $extra['tips_content'] ?? $this->get_generic_tip($permit_type),
                ];

            case 'milestone':
                return [
                    'milestone_label' => esc_html($extra['milestone_label'] ?? ''),
                    'milestone_score' => esc_html($extra['milestone_score'] ?? ''),
                    'quiz_name'       => esc_html($extra['quiz_name'] ?? ''),
                ];

            default:
                return [];
        }
    }

    // -------------------------------------------------------------------------
    // WEEKLY STATS
    // -------------------------------------------------------------------------

    public function build_weekly_vars(int $user_id): array
    {
        $stats      = $this->get_weekly_stats($user_id, 7);
        $prev_stats = $this->get_weekly_stats($user_id, 14, 7);

        $sessions_count = (int) ($stats['sessions_count'] ?? 0);
        $score_moyen    = $stats['score_moyen']    ?? 0;
        $sessions_prev  = (int) ($prev_stats['sessions_count'] ?? 0);
        $score_prev     = $prev_stats['score_moyen'] ?? 0;

        // Deltas
        $sessions_delta = $this->format_delta($sessions_count - $sessions_prev);
        $score_delta    = $this->format_delta((float) $score_moyen - (float) $score_prev, '%');

        // Best / worst series
        $best  = $this->get_best_serie($user_id, 7);
        $worst = $this->get_worst_serie($user_id, 7);

        // Permit type
        $permit_type = $this->get_main_permit_type($user_id);

        return [
            'sessions_count'     => $sessions_count,
            'sessions_delta'     => $sessions_delta,
            'score_moyen'        => round((float) $score_moyen, 1),
            'score_delta'        => $score_delta,
            'permit_type'        => esc_html(ucfirst($permit_type)),
            'meilleure_serie'    => esc_html($best['title'] ?? '—'),
            'meilleure_score'    => $best['score'] ?? '—',
            'serie_faible'       => esc_html($worst['title'] ?? '—'),
            'serie_faible_score' => $worst['score'] ?? '—',
        ];
    }

    private function get_weekly_stats(int $user_id, int $days_ago, int $days_from = 0): array
    {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'canvas_quiz_statistics';

        // Check table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return ['sessions_count' => 0, 'score_moyen' => 0];
        }

        $from = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
        $to   = $days_from > 0
            ? date('Y-m-d H:i:s', strtotime("-{$days_from} days"))
            : date('Y-m-d H:i:s');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS sessions_count,
                    ROUND(SUM(total_correct) / NULLIF(SUM(question_number), 0) * 100, 1) AS score_moyen
                 FROM {$stats_table}
                 WHERE user_id = %d
                   AND status = 'completed'
                   AND date_taken BETWEEN %s AND %s",
                $user_id,
                $from,
                $to
            ),
            ARRAY_A
        );

        return $row ?: ['sessions_count' => 0, 'score_moyen' => 0];
    }

    private function get_best_serie(int $user_id, int $days): array
    {
        global $wpdb;

        $stats_table  = $wpdb->prefix . 'canvas_quiz_statistics';
        $series_table = $wpdb->prefix . 'canvas_quiz_series';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return [];
        }

        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (array) $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.title,
                        ROUND(st.total_correct / NULLIF(st.question_number, 0) * 100, 0) AS score
                 FROM {$stats_table} st
                 LEFT JOIN {$series_table} s ON s.id = st.serie_id
                 WHERE st.user_id = %d AND st.status = 'completed' AND st.date_taken >= %s
                 ORDER BY score DESC
                 LIMIT 1",
                $user_id,
                $from
            )
        );
    }

    private function get_worst_serie(int $user_id, int $days): array
    {
        global $wpdb;

        $stats_table  = $wpdb->prefix . 'canvas_quiz_statistics';
        $series_table = $wpdb->prefix . 'canvas_quiz_series';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return [];
        }

        $from = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (array) $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.title,
                        ROUND(st.total_correct / NULLIF(st.question_number, 0) * 100, 0) AS score
                 FROM {$stats_table} st
                 LEFT JOIN {$series_table} s ON s.id = st.serie_id
                 WHERE st.user_id = %d AND st.status = 'completed' AND st.date_taken >= %s
                   AND st.question_number > 0
                 ORDER BY score ASC
                 LIMIT 1",
                $user_id,
                $from
            )
        );
    }

    public function get_main_permit_type(int $user_id): string
    {
        global $wpdb;

        $stats_table  = $wpdb->prefix . 'canvas_quiz_statistics';
        $series_table = $wpdb->prefix . 'canvas_quiz_series';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") !== $stats_table) {
            return '';
        }

        $from = date('Y-m-d H:i:s', strtotime('-30 days'));

        $type = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT s.type
                 FROM {$stats_table} st
                 LEFT JOIN {$series_table} s ON s.id = st.serie_id
                 WHERE st.user_id = %d AND st.date_taken >= %s AND s.type IS NOT NULL
                 GROUP BY s.type
                 ORDER BY COUNT(*) DESC
                 LIMIT 1",
                $user_id,
                $from
            )
        );

        return (string) ($type ?? '');
    }

    private function get_generic_tip(string $permit_type): string
    {
        $tips = [
            'voiture'     => 'Concentre-toi sur les règles de priorité aux intersections — c\'est souvent là que les candidats perdent le plus de points.',
            'moto'        => 'En moto, la distance de freinage est cruciale. Entraîne-toi à estimer les distances sur les séries dédiées.',
            'bateau'      => 'Les règles de balisage et de navigation côtière sont incontournables. Révise les cartouches de balise.',
            'avion'       => 'Mémorise les procédures radio et les règles VFR/IFR. La régularité des révisions est essentielle.',
            'poidslourd'  => 'Les règles de tachygraphe et les temps de conduite sont très fréquents à l\'examen.',
            'bus'         => 'La sécurité des passagers est au cœur des questions. Revois les obligations spécifiques aux transports en commun.',
        ];

        return $tips[$permit_type] ?? 'La régularité est la clé. 15 minutes par jour valent mieux qu\'une longue session hebdomadaire.';
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    public function replace_vars(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $template = str_replace(
                ['{{' . $key . '}}', '{' . $key . '}'],
                (string) $value,
                $template
            );
        }

        return $template;
    }

    private function format_delta(float $delta, string $unit = ''): string
    {
        if ($delta > 0) {
            return '<span style="color:#34a853;">+' . $delta . $unit . '</span>';
        }
        if ($delta < 0) {
            return '<span style="color:#d93025;">' . $delta . $unit . '</span>';
        }

        return '<span style="color:#888;">=' . $unit . '</span>';
    }
}
