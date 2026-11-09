<?php

namespace CobraAI\Features\Emailmarketing;

use CobraAI\FeatureBase;

defined('ABSPATH') || exit;

class Feature extends FeatureBase
{
    protected string $feature_id  = 'emailmarketing';
    protected string $name        = 'Email Marketing';
    protected string $description = 'Workflow email automatique : onboarding, relances, rapport hebdomadaire, milestones. Générique et réutilisable sur tout site.';
    protected string $version     = '1.0.0';
    protected string $author      = 'Nakous Mustapha';
    protected array  $requires    = ['smtp'];
    protected bool   $has_settings = true;
    protected bool   $has_admin    = true;

    // Physical directory name (differs from feature_id slug used by loader)
    private const DIR_NAME = 'emailmarketing';

    /**
     * Override setup_paths to use the real directory name.
     * FeatureBase uses $this->feature_id ('email-marketing') which would give
     * 'features/email-marketing/' — but the actual folder is 'emailmarketing/'.
     */
    protected function setup_paths(): void
    {
        $this->path         = COBRA_AI_FEATURES_DIR . self::DIR_NAME . '/';
        $this->url          = COBRA_AI_URL . 'features/' . self::DIR_NAME . '/';
        $this->assets_url   = $this->url . 'assets/';
        $this->templates_path = $this->path . 'templates/';
    }

    // Handler instances — nullable until setup() is called
    public ?EmailSender         $sender          = null;
    public ?EmailQueue          $queue           = null;
    public ?TemplateEngine      $templates       = null;
    public ?CronManager         $cron            = null;
    public ?BounceHandler       $bounce          = null;
    public ?UserEmailPrefs      $prefs           = null;
    public ?TemplateRepository  $tpl_repo        = null;
    public ?TriggerRepository   $trig_repo       = null;
    public ?TriggerEngine       $trigger_engine  = null;
    public ?CampaignRepository  $campaign_repo   = null;
    public ?CampaignDispatcher  $dispatcher      = null;

    // -------------------------------------------------------------------------
    // SETUP
    // -------------------------------------------------------------------------

    protected function setup(): void
    {
        global $wpdb;

        $this->tables = [
            'email_templates' => [
                'name'   => $wpdb->prefix . 'cobra_email_templates',
                'schema' => [
                    'id'         => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                    'slug'       => "VARCHAR(80) NOT NULL DEFAULT ''",
                    'label'      => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'subject'    => "VARCHAR(500) NOT NULL DEFAULT ''",
                    'body'       => 'LONGTEXT NOT NULL',
                    'is_system'  => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'enabled'    => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY'  => '(id)',
                    'UNIQUE KEY'   => ['slug_unique' => '(slug)'],
                ],
            ],
            'email_triggers' => [
                'name'   => $wpdb->prefix . 'cobra_email_triggers',
                'schema' => [
                    'id'                  => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                    'label'               => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'template_id'         => 'BIGINT(20) NOT NULL DEFAULT 0',
                    'trigger_type'        => "ENUM('hook','cron_delay','cron_schedule','manual') NOT NULL DEFAULT 'hook'",
                    'hook_name'           => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'hook_user_arg_index' => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0',
                    'delay_days'          => 'INT(11) NOT NULL DEFAULT 0',
                    'delay_ref_hook'      => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'cron_day'            => "VARCHAR(20) NOT NULL DEFAULT ''",
                    'cron_hour'           => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 8',
                    'cron_audience'       => "VARCHAR(50) NOT NULL DEFAULT 'all_active'",
                    'send_mode'           => "ENUM('once_per_user','cooldown','always') NOT NULL DEFAULT 'once_per_user'",
                    'cooldown_days'       => 'INT(11) NOT NULL DEFAULT 0',
                    'conditions'          => 'LONGTEXT',
                    'is_system'           => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'enabled'             => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'created_at'          => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY'         => [
                        'idx_template_id' => '(template_id)',
                        'idx_trigger_type' => '(trigger_type)',
                        'idx_enabled'      => '(enabled)',
                    ],
                ],
            ],
            'email_log' => [
                'name'   => $wpdb->prefix . 'cobra_email_log',
                'schema' => [
                    'id'         => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                    'user_id'    => 'BIGINT(20) NOT NULL',
                    'email_type' => "VARCHAR(80) NOT NULL DEFAULT ''",
                    'email_to'   => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'subject'    => "VARCHAR(500) NOT NULL DEFAULT ''",
                    'status'     => "ENUM('sent','failed','bounced','spam','skipped') NOT NULL DEFAULT 'sent'",
                    'sent_at'    => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'metadata'   => 'LONGTEXT',
                    'PRIMARY KEY' => '(id)',
                    'KEY'        => [
                        'idx_user_id'    => '(user_id)',
                        'idx_email_type' => '(email_type)',
                        'idx_status'     => '(status)',
                        'idx_sent_at'    => '(sent_at)',
                    ],
                ],
            ],
            'email_queue' => [
                'name'   => $wpdb->prefix . 'cobra_email_queue',
                'schema' => [
                    'id'           => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                    'user_id'      => 'BIGINT(20) NOT NULL',
                    'email_type'   => "VARCHAR(50) NOT NULL DEFAULT ''",
                    'scheduled_at' => 'DATETIME NOT NULL',
                    'status'       => "ENUM('pending','sent','cancelled','failed') NOT NULL DEFAULT 'pending'",
                    'payload'      => 'LONGTEXT',
                    'created_at'   => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY'  => '(id)',
                    'KEY'          => [
                        'idx_user_id'      => '(user_id)',
                        'idx_scheduled_at' => '(scheduled_at)',
                        'idx_status'       => '(status)',
                    ],
                ],
            ],
            'email_campaigns' => [
                'name'   => $wpdb->prefix . 'cobra_email_campaigns',
                'schema' => [
                    'id'               => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                    'name'             => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'subject'          => "VARCHAR(500) NOT NULL DEFAULT ''",
                    'body'             => 'LONGTEXT NOT NULL',
                    'template_id'      => 'BIGINT(20) NOT NULL DEFAULT 0',
                    'ai_prompt'        => 'TEXT',
                    'audience_type'    => "ENUM('all','role','meta') NOT NULL DEFAULT 'all'",
                    'audience_filters' => 'LONGTEXT',
                    'scheduled_at'     => 'DATETIME',
                    'status'           => "ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft'",
                    'sent_count'       => 'INT(11) NOT NULL DEFAULT 0',
                    'total_count'      => 'INT(11) NOT NULL DEFAULT 0',
                    'created_at'       => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at'       => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'idx_status'       => '(status)',
                        'idx_scheduled_at' => '(scheduled_at)',
                    ],
                ],
            ],
            'email_prefs' => [
                'name'   => $wpdb->prefix . 'cobra_email_prefs',
                'schema' => [
                    'user_id'           => 'BIGINT(20) NOT NULL',
                    'unsubscribed_all'  => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'bounced'           => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'bounce_count'      => 'INT(11) NOT NULL DEFAULT 0',
                    'prefs'             => 'LONGTEXT',
                    'unsubscribe_token' => 'VARCHAR(64)',
                    'updated_at'        => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY'       => '(user_id)',
                    'KEY'               => [
                        'idx_unsubscribed' => '(unsubscribed_all)',
                        'idx_bounced'      => '(bounced)',
                        'idx_token'        => '(unsubscribe_token)',
                    ],
                ],
            ],
        ];

        require_once $this->path . 'includes/TemplateRepository.php';
        require_once $this->path . 'includes/TriggerRepository.php';
        require_once $this->path . 'includes/CampaignRepository.php';
        require_once $this->path . 'includes/CampaignDispatcher.php';
        require_once $this->path . 'includes/ConditionEvaluator.php';
        require_once $this->path . 'includes/TriggerEngine.php';
        require_once $this->path . 'includes/UserEmailPrefs.php';
        require_once $this->path . 'includes/TemplateEngine.php';
        require_once $this->path . 'includes/EmailSender.php';
        require_once $this->path . 'includes/EmailQueue.php';
        require_once $this->path . 'includes/CronManager.php';
        require_once $this->path . 'includes/BounceHandler.php';

        $this->tpl_repo       = new TemplateRepository($this);
        $this->trig_repo      = new TriggerRepository($this);
        $this->campaign_repo  = new CampaignRepository($this);
        $this->dispatcher     = new CampaignDispatcher($this);
        $this->prefs          = new UserEmailPrefs($this);
        $this->templates      = new TemplateEngine($this);
        $this->sender         = new EmailSender($this);
        $this->queue          = new EmailQueue($this);
        $this->cron           = new CronManager($this);
        $this->bounce         = new BounceHandler($this);
        $this->trigger_engine = new TriggerEngine($this);
    }

    // -------------------------------------------------------------------------
    // HOOKS
    // -------------------------------------------------------------------------

    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Cron callbacks must be registered on every load
        add_action('cobra_emailmarketing_process_queue',   [$this->cron, 'process_queue']);
        add_action('cobra_emailmarketing_weekly_report',   [$this->cron, 'send_weekly_reports']);
        add_action('cobra_emailmarketing_re_engagement',   [$this->cron, 'check_re_engagement']);

        // Schedule crons if not already scheduled
        $this->cron->register_crons();

        // Dynamic trigger engine — registers hook/cron_delay actions from DB
        // Priority 5 ensures it runs after setup but before most plugin hooks
        add_action('init', [$this->trigger_engine, 'register'], 5);

        // Seed system data after (re-)activation — idempotent
        add_action('cobra_ai_feature_activated_emailmarketing', [$this, 'migrate_system_data']);

        // Also run migration on every load if tables exist but are empty (e.g. after manual table drop+reinstall)
        add_action('init', [$this, 'maybe_migrate_system_data'], 1);

        // Bounce webhook
        add_action('init', [$this->bounce, 'handle_webhook_request']);

        // Unsubscribe page
        add_action('init', [$this->prefs, 'handle_unsubscribe_request']);

        // Shortcode
        add_shortcode('cobra_emailmarketing_unsubscribe', [$this, 'render_unsubscribe_shortcode']);

        // AJAX
        add_action('wp_ajax_cobra_emailmarketing_test',              [$this, 'ajax_test_email']);
        add_action('wp_ajax_cobra_emailmarketing_reset_template',    [$this, 'ajax_reset_template']);
        add_action('wp_ajax_cobra_emailmarketing_export_log',        [$this, 'ajax_export_log']);
        add_action('wp_ajax_cobra_emailmarketing_unblock_user',      [$this, 'ajax_unblock_user']);
        add_action('wp_ajax_cobra_emailmarketing_test_brevo',        [$this, 'ajax_test_brevo']);
        add_action('wp_ajax_cobra_emailmarketing_preview',           [$this, 'ajax_preview']);
        add_action('wp_ajax_cobra_emailmarketing_clear_failed_log',  [$this, 'ajax_clear_failed_log']);
        add_action('wp_ajax_cobra_emailmarketing_clear_all_log',     [$this, 'ajax_clear_all_log']);
        add_action('wp_ajax_cobra_emailmarketing_delete_template',         [$this, 'ajax_delete_template']);
        add_action('wp_ajax_cobra_emailmarketing_delete_trigger',            [$this, 'ajax_delete_trigger']);
        add_action('wp_ajax_cobra_emailmarketing_delete_campaign',           [$this, 'ajax_delete_campaign']);
        add_action('wp_ajax_cobra_emailmarketing_send_campaign_now',         [$this, 'ajax_send_campaign_now']);
        add_action('wp_ajax_cobra_emailmarketing_generate_campaign_content', [$this, 'ajax_generate_campaign_content']);

        // Admin POST (form submissions that redirect after save)
        add_action('admin_post_cobra_ai_save_email_template',  [$this, 'handle_save_template']);
        add_action('admin_post_cobra_ai_save_email_trigger',   [$this, 'handle_save_trigger']);
        add_action('admin_post_cobra_ai_save_email_campaign',  [$this, 'handle_save_campaign']);

        // Campaigns cron callback
        add_action('cobra_emailmarketing_dispatch_campaigns', [$this->cron, 'dispatch_scheduled_campaigns']);
    }

    public function is_globally_enabled(): bool
    {
        return (bool) ($this->get_settings('general.enabled') ?? true);
    }

    // -------------------------------------------------------------------------
    // SHORTCODE
    // -------------------------------------------------------------------------

    public function render_unsubscribe_shortcode(array $atts): string
    {
        ob_start();
        $feature = $this;
        include $this->path . 'views/unsubscribe.php';
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX HANDLERS
    // -------------------------------------------------------------------------

    public function ajax_test_email(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $email_type = sanitize_key($_POST['email_type'] ?? 'onboarding_j0');
        $to         = sanitize_email($_POST['to'] ?? get_option('admin_email'));
        $user_id    = (int) ($_POST['user_id'] ?? get_current_user_id());

        $result = $this->sender->send_test($user_id, $email_type, $to);

        // Log the test send so it appears in the history tab
        $this->sender->log(
            $user_id,
            'test_' . $email_type,
            $to,
            '[TEST] ' . $email_type,
            $result ? 'sent' : 'failed',
            ['test' => true]
        );

        if ($result) {
            wp_send_json_success(sprintf(__('Email de test envoyé à %s.', 'cobra-ai'), $to));
        } else {
            wp_send_json_error(__('Échec de l\'envoi. Vérifiez la configuration SMTP.', 'cobra-ai'));
        }
    }

    public function ajax_reset_template(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $type = sanitize_key($_POST['template_type'] ?? '');
        if (empty($type)) {
            wp_send_json_error(__('Type de template invalide.', 'cobra-ai'));
        }

        $defaults = $this->get_feature_default_options();
        $content  = $defaults['templates'][$type] ?? '';

        wp_send_json_success(['content' => $content]);
    }

    public function ajax_export_log(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        global $wpdb;
        $table = $this->get_table_name('email_log');
        $rows  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sent_at DESC LIMIT 5000", ARRAY_A);

        if (empty($rows)) {
            wp_send_json_error(__('Aucune donnée à exporter.', 'cobra-ai'));
        }

        $csv  = implode(',', array_keys($rows[0])) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\n";
        }

        wp_send_json_success(['csv' => $csv, 'count' => count($rows)]);
    }

    public function ajax_clear_failed_log(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        global $wpdb;
        $table   = $this->get_table_name('email_log');
        $deleted = $wpdb->delete($table, ['status' => 'failed'], ['%s']);

        wp_send_json_success(['deleted' => (int) $deleted]);
    }

    public function ajax_clear_all_log(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        global $wpdb;
        $table   = $this->get_table_name('email_log');
        $deleted = $wpdb->query("TRUNCATE TABLE {$table}");

        wp_send_json_success(['deleted' => (int) $deleted]);
    }

    public function ajax_unblock_user(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error(__('Utilisateur invalide.', 'cobra-ai'));
        }

        $this->prefs->unblock_user($user_id);
        wp_send_json_success(__('Utilisateur débloqué.', 'cobra-ai'));
    }

    public function ajax_preview(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission refusée.');
        }

        $type = sanitize_key($_POST['type'] ?? 'onboarding_j0');
        $html = wp_unslash($_POST['html'] ?? '');  // raw HTML from editor

        $user_id  = get_current_user_id();
        $settings = $this->get_settings();

        // Demo variables
        $demo_vars = $this->get_demo_vars($type, $user_id);

        $footer_tpl = $settings['templates']['footer'] ?? $this->default_footer();
        $footer     = $this->templates->replace_vars($footer_tpl, $demo_vars);

        // If editing layout directly, render it standalone
        if ($type === 'layout') {
            $demo_vars['content'] = '<p style="color:#888;text-align:center;padding:40px;">'
                . '<em>← Le contenu de chaque email sera injecté ici ({{content}})</em></p>';
            $demo_vars['footer'] = $footer;
            $body    = $this->templates->replace_vars($html, $demo_vars);
            $subject = $settings['emails']['onboarding_j0']['subject'] ?? 'Aperçu layout';
        } elseif ($type === 'footer') {
            // Preview footer standalone (wrapped in a simple container)
            $body    = '<div style="background:#f8f8f8;padding:20px 40px;font-family:Arial,sans-serif;">'
                . $this->templates->replace_vars($html, $demo_vars) . '</div>';
            $subject = 'Aperçu pied de page';
        } else {
            // Render content through the saved layout
            $layout  = $settings['templates']['layout'] ?? $this->default_layout();
            $content = $this->templates->replace_vars($html, $demo_vars);
            $subject = $settings['emails'][$type]['subject'] ?? $type;
            $subject = $this->templates->replace_vars($subject, $demo_vars);

            $demo_vars['content'] = $content;
            $demo_vars['footer']  = $footer;
            $demo_vars['subject'] = $subject;
            $body = $this->templates->replace_vars($layout, $demo_vars);
        }

        wp_send_json_success([
            'subject' => $subject,
            'body'    => $body,
        ]);
    }

    private function get_demo_vars(string $type, int $user_id): array
    {
        $user   = get_user_by('id', $user_id);
        $prenom = $user ? (get_user_meta($user_id, 'firstname', true) ?: $user->display_name) : 'Marie';

        $base = [
            'prenom'               => $prenom,
            'email'                => $user ? $user->user_email : 'exemple@email.com',
            'site_name'            => get_bloginfo('name'),
            'site_url'             => home_url('/'),
            'account_url'          => home_url('/mon-compte/'),
            'unsubscribe_url'      => home_url('/?cobra_unsubscribe=1&token=demo&type=all'),
            'unsubscribe_url_type' => home_url('/?cobra_unsubscribe=1&token=demo&type=' . $type),
            'subject'              => '',
        ];

        $type_vars = [
            'weekly_report' => [
                'sessions_count'     => 7,
                'sessions_delta'     => '<span style="color:#34a853;">+2</span>',
                'score_moyen'        => 82.5,
                'score_delta'        => '<span style="color:#34a853;">+4.5%</span>',
                'permit_type'        => 'Voiture',
                'meilleure_serie'    => 'Série 12 — Priorités',
                'meilleure_score'    => 96,
                'serie_faible'       => 'Série 8 — Signalisation',
                'serie_faible_score' => 63,
            ],
            'tips' => [
                'permit_type'  => 'Voiture',
                'tips_content' => 'Concentre-toi sur les règles de priorité aux intersections — c\'est souvent là que les candidats perdent le plus de points.',
            ],
            'milestone' => [
                'milestone_label' => '🏆 Première série réussie avec succès !',
                'milestone_score' => 88,
                'quiz_name'       => 'Série 12 — Priorités',
            ],
        ];

        return array_merge($base, $type_vars[$type] ?? []);
    }

    public function ajax_test_brevo(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $result = $this->bounce->test_api_connection();
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    // -------------------------------------------------------------------------
    // AJAX — Templates CRUD
    // -------------------------------------------------------------------------

    public function ajax_delete_template(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $id = (int) ($_POST['template_id'] ?? 0);
        if (!$id) {
            wp_send_json_error(__('ID invalide.', 'cobra-ai'));
        }

        $ok = $this->tpl_repo->delete($id);
        if ($ok) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Impossible de supprimer ce template (système ou introuvable).', 'cobra-ai'));
        }
    }

    // -------------------------------------------------------------------------
    // AJAX — Triggers CRUD
    // -------------------------------------------------------------------------

    public function ajax_delete_trigger(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $id = (int) ($_POST['trigger_id'] ?? 0);
        if (!$id) {
            wp_send_json_error(__('ID invalide.', 'cobra-ai'));
        }

        $ok = $this->trig_repo->delete($id);
        if ($ok) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Impossible de supprimer ce déclencheur (système ou introuvable).', 'cobra-ai'));
        }
    }

    // -------------------------------------------------------------------------
    // ADMIN POST — Template save (form submission + redirect)
    // -------------------------------------------------------------------------

    public function handle_save_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission refusée.', 'cobra-ai'));
        }
        check_admin_referer('cobra_ai_save_email_template');

        $page_slug   = 'cobra-ai-' . $this->get_feature_id();
        $template_id = (int) ($_POST['template_id'] ?? 0);

        $data = [
            'slug'    => sanitize_key($_POST['tpl_slug']    ?? ''),
            'label'   => sanitize_text_field($_POST['tpl_label']   ?? ''),
            'subject' => sanitize_text_field($_POST['tpl_subject'] ?? ''),
            'body'    => wp_kses_post(wp_unslash($_POST['tpl_body'] ?? '')),
            'enabled' => isset($_POST['tpl_enabled']) ? 1 : 0,
        ];

        if ($template_id > 0) {
            $data['id'] = $template_id;
        }

        $saved_id = $this->tpl_repo->save($data);

        if ($saved_id) {
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'templates', 'tpl_saved' => '1'],
                admin_url('admin.php')
            ));
        } else {
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'templates', 'error' => 'save_failed'],
                admin_url('admin.php')
            ));
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // ADMIN POST — Trigger save (form submission + redirect)
    // -------------------------------------------------------------------------

    public function handle_save_trigger(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission refusée.', 'cobra-ai'));
        }
        check_admin_referer('cobra_ai_save_email_trigger');

        $page_slug  = 'cobra-ai-' . $this->get_feature_id();
        $trigger_id = (int) ($_POST['trigger_id'] ?? 0);

        // Decode conditions from JSON
        $conditions_raw = wp_unslash($_POST['trg_conditions'] ?? '{}');
        $conditions     = json_decode($conditions_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($conditions['groups'])) {
            $conditions = null;
        }

        $data = [
            'label'               => sanitize_text_field($_POST['trg_label']               ?? ''),
            'template_id'         => (int) ($_POST['trg_template_id']                      ?? 0),
            'trigger_type'        => sanitize_key($_POST['trg_trigger_type']               ?? 'hook'),
            'hook_name'           => sanitize_text_field($_POST['trg_hook_name']           ?? ''),
            'hook_user_arg_index' => (int) ($_POST['trg_hook_user_arg_index']              ?? 0),
            'delay_days'          => (int) ($_POST['trg_delay_days']                       ?? 0),
            'delay_ref_hook'      => sanitize_text_field($_POST['trg_delay_ref_hook']      ?? ''),
            'cron_day'            => sanitize_key($_POST['trg_cron_day']                   ?? ''),
            'cron_hour'           => (int) ($_POST['trg_cron_hour']                        ?? 8),
            'cron_audience'       => sanitize_key($_POST['trg_cron_audience']              ?? 'all_active'),
            'send_mode'           => sanitize_key($_POST['trg_send_mode']                  ?? 'once_per_user'),
            'cooldown_days'       => (int) ($_POST['trg_cooldown_days']                    ?? 0),
            'conditions'          => $conditions,
            'enabled'             => isset($_POST['trg_enabled']) ? 1 : 0,
        ];

        if ($trigger_id > 0) {
            $data['id'] = $trigger_id;
        }

        $saved_id = $this->trig_repo->save($data);

        if ($saved_id) {
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'triggers', 'trg_saved' => '1'],
                admin_url('admin.php')
            ));
        } else {
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'triggers', 'error' => 'save_failed'],
                admin_url('admin.php')
            ));
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX — Campaigns CRUD
    // -------------------------------------------------------------------------

    public function ajax_delete_campaign(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $id = (int) ($_POST['campaign_id'] ?? 0);
        if (!$id) {
            wp_send_json_error(__('ID invalide.', 'cobra-ai'));
        }

        $ok = $this->campaign_repo->delete($id);
        if ($ok) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Impossible de supprimer cette campagne (envoi en cours ou déjà envoyée).', 'cobra-ai'));
        }
    }

    public function ajax_send_campaign_now(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $id = (int) ($_POST['campaign_id'] ?? 0);
        if (!$id) {
            wp_send_json_error(__('ID invalide.', 'cobra-ai'));
        }

        $ok = $this->dispatcher->dispatch($id);
        if ($ok) {
            wp_send_json_success(['message' => __('Emails mis en file d\'attente avec succès.', 'cobra-ai')]);
        } else {
            wp_send_json_error(__('Impossible d\'envoyer la campagne.', 'cobra-ai'));
        }
    }

    public function ajax_generate_campaign_content(): void
    {
        check_ajax_referer('cobra-ai-admin-emailmarketing', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission refusée.', 'cobra-ai'));
        }

        $prompt   = sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? ''));
        $post_ids = array_map('intval', (array) ($_POST['post_ids'] ?? []));

        if (empty($prompt)) {
            wp_send_json_error(['message' => __('Prompt vide.', 'cobra-ai')]);
        }

        $result = $this->dispatcher->generate_with_ai($prompt, $post_ids);

        if ($result['success']) {
            wp_send_json_success(['content' => $result['content']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    // -------------------------------------------------------------------------
    // ADMIN POST — Campaign save
    // -------------------------------------------------------------------------

    public function handle_save_campaign(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission refusée.', 'cobra-ai'));
        }
        check_admin_referer('cobra_ai_save_email_campaign');

        $page_slug   = 'cobra-ai-' . $this->get_feature_id();
        $campaign_id = (int) ($_POST['campaign_id'] ?? 0);

        // Build audience filters
        $audience_type    = sanitize_key($_POST['cpg_audience_type'] ?? 'all');
        $audience_filters = [];

        if ($audience_type === 'role') {
            $audience_filters['roles'] = array_map('sanitize_key', (array) ($_POST['cpg_roles'] ?? []));
        } elseif ($audience_type === 'meta') {
            $audience_filters['meta_key']     = sanitize_key($_POST['cpg_meta_key'] ?? '');
            $audience_filters['meta_compare'] = sanitize_text_field($_POST['cpg_meta_compare'] ?? '=');
            $audience_filters['meta_value']   = sanitize_text_field($_POST['cpg_meta_value'] ?? '');
        }

        $audience_filters['verified_only'] = !empty($_POST['cpg_verified_only']);

        // Scheduled at
        $send_when    = sanitize_key($_POST['cpg_send_when'] ?? 'now');
        $scheduled_at = null;
        if ($send_when === 'schedule' && !empty($_POST['cpg_scheduled_at'])) {
            $scheduled_at = date('Y-m-d H:i:s', strtotime(sanitize_text_field($_POST['cpg_scheduled_at'])));
        }

        $status = sanitize_key($_POST['cpg_status'] ?? 'draft');

        // If "now" + "scheduled/send" button → dispatch immediately after save
        $dispatch_now = ($send_when === 'now' && $status === 'scheduled');
        if ($dispatch_now) {
            $status = 'draft'; // will be updated to sending/sent by dispatcher
        }

        $data = [
            'name'             => sanitize_text_field($_POST['cpg_name'] ?? ''),
            'subject'          => sanitize_text_field($_POST['cpg_subject'] ?? ''),
            'body'             => wp_kses_post(wp_unslash($_POST['cpg_body'] ?? '')),
            'template_id'      => (int) ($_POST['cpg_template_id'] ?? 0),
            'ai_prompt'        => sanitize_textarea_field($_POST['cpg_ai_prompt'] ?? ''),
            'audience_type'    => $audience_type,
            'audience_filters' => $audience_filters,
            'scheduled_at'     => $scheduled_at,
            'status'           => $status,
        ];

        if ($campaign_id > 0) {
            $data['id'] = $campaign_id;
        }

        $saved_id = $this->campaign_repo->save($data);

        if (!$saved_id) {
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'campaigns', 'error' => urlencode(__('Erreur lors de l\'enregistrement.', 'cobra-ai'))],
                admin_url('admin.php')
            ));
            exit;
        }

        // Dispatch immediately if "Envoyer maintenant"
        if ($dispatch_now) {
            $this->dispatcher->dispatch($saved_id);
            wp_safe_redirect(add_query_arg(
                ['page' => $page_slug, 'tab' => 'campaigns', 'cpg_sent' => '1'],
                admin_url('admin.php')
            ));
            exit;
        }

        wp_safe_redirect(add_query_arg(
            ['page' => $page_slug, 'tab' => 'campaigns', 'cpg_saved' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_admin_assets($hook): void
    {
        parent::enqueue_admin_assets($hook);

        // Add current_user_id to the already-enqueued JS data
        if (strpos((string) $hook, 'cobra-ai-emailmarketing') !== false) {
            // Register Select2 if not already registered (WooCommerce does it, but it may not be active)
            if (!wp_script_is('select2', 'registered')) {
                wp_register_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
            }
            if (!wp_style_is('select2', 'registered')) {
                wp_register_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            }
            wp_enqueue_style('select2');
            wp_enqueue_script('select2');

            wp_localize_script('cobra-ai-emailmarketing-admin', 'cobraAIAdminEmailmarketing', [
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('cobra-ai-admin-emailmarketing'),
                'current_user_id' => get_current_user_id(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // ACTIVATION / DEACTIVATION
    // -------------------------------------------------------------------------

    public function deactivate(): bool
    {
        $this->cron->deregister_crons();
        return parent::deactivate();
    }

    /**
     * Called after tables are created (on activate / version upgrade).
     * Seeds system templates and triggers into the new DB tables.
     * Safe to call multiple times — idempotent.
     */
    public function migrate_system_data(): void
    {
        if ($this->tpl_repo === null || $this->trig_repo === null) {
            return;
        }

        $this->tpl_repo->seed_system_templates();
        $this->trig_repo->seed_system_triggers($this->tpl_repo);
    }

    /**
     * Run migration on every load if the templates table exists but is empty.
     * This handles the case where someone wiped the table manually.
     */
    public function maybe_migrate_system_data(): void
    {
        if ($this->tpl_repo === null) {
            return;
        }

        global $wpdb;

        // Always check the campaigns table regardless of the transient
        // (it was added after the initial release and may not exist on existing installs)
        $cpg_table = $this->get_table_name('email_campaigns');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cpg_table)) !== $cpg_table) {
            $this->setup();
            if (!empty($this->tables)) {
                $db = \CobraAI\Database::get_instance();
                $db->register_feature_tables($this->feature_id, $this->tables);
                $db->install_feature_tables($this->feature_id);
            }
            // Reset transient so the rest of the checks run too
            delete_transient('cobra_em_system_seeded');
        }

        // Use a transient to avoid a DB query on every request for the rest
        if (get_transient('cobra_em_system_seeded')) {
            return;
        }

        // Ensure templates table exists
        $tpl_table    = $this->get_table_name('email_templates');
        $table_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $tpl_table)
        );

        if (!$table_exists) {
            $this->setup();
            if (!empty($this->tables)) {
                $db = \CobraAI\Database::get_instance();
                $db->register_feature_tables($this->feature_id, $this->tables);
                $db->install_feature_tables($this->feature_id);
            }
        }

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tpl_table} WHERE is_system = 1"
        );

        if ($count < 8) {
            $this->migrate_system_data();
        }

        set_transient('cobra_em_system_seeded', 1, DAY_IN_SECONDS);
    }

    // -------------------------------------------------------------------------
    // SETTINGS
    // -------------------------------------------------------------------------

    /**
     * Public accessor for default email subjects and template bodies.
     * Used by TemplateRepository::seed_system_templates().
     *
     * @return array{emails: array, templates: array}
     */
    public function get_default_email_data(): array
    {
        $defaults = $this->get_feature_default_options();
        return [
            'emails'    => $defaults['emails']    ?? [],
            'templates' => $defaults['templates'] ?? [],
        ];
    }

    protected function get_feature_default_options(): array
    {
        return [
            'general' => [
                'enabled'          => true,
                'from_name'        => '',
                'from_email'       => '',
                'frequency_limit'  => 1,
                'unsubscribe_page' => 0,
            ],
            'emails' => [
                'onboarding_j0'    => ['enabled' => true, 'subject' => 'Bienvenue sur {{site_name}} !'],
                'onboarding_j2'    => ['enabled' => true, 'subject' => 'Prêt à démarrer sur {{site_name}} ?'],
                'onboarding_j7'    => ['enabled' => true, 'subject' => 'Bilan de ta 1ère semaine'],
                're_engagement_7j' => ['enabled' => true, 'subject' => 'Tu nous manques !'],
                're_engagement_30j'=> ['enabled' => true, 'subject' => 'Dernière chance de reprendre'],
                'weekly_report'    => ['enabled' => true, 'subject' => 'Ta progression cette semaine 📊'],
                'tips'             => ['enabled' => true, 'subject' => 'Un conseil pour bien avancer'],
                'milestone'        => ['enabled' => true, 'subject' => '{{milestone_label}}'],
            ],
            'templates' => [
                'layout'           => $this->default_layout(),
                'footer'           => $this->default_footer(),
                'onboarding_j0'    => $this->default_tpl_onboarding_j0(),
                'onboarding_j2'    => $this->default_tpl_onboarding_j2(),
                'onboarding_j7'    => $this->default_tpl_onboarding_j7(),
                're_engagement_7j' => $this->default_tpl_reengagement_7j(),
                're_engagement_30j'=> $this->default_tpl_reengagement_30j(),
                'weekly_report'    => $this->default_tpl_weekly_report(),
                'tips'             => $this->default_tpl_tips(),
                'milestone'        => $this->default_tpl_milestone(),
            ],
            'brevo' => [
                'enabled'       => false,
                'api_key'       => '',
                'webhook_token' => '',
            ],
            'cron' => [
                'weekly_report_day'      => 'monday',
                'weekly_report_hour'     => 8,
                'queue_process_interval' => 'hourly',
            ],
        ];
    }

    public function update_settings(array $settings): bool
    {
        // Sync layout/footer to shared option so all features share them
        if (isset($settings['templates']['layout'])) {
            \CobraAI\SharedEmailLayout::save_layout(wp_kses_post($settings['templates']['layout']));
        }
        if (isset($settings['templates']['footer'])) {
            \CobraAI\SharedEmailLayout::save_footer(wp_kses_post($settings['templates']['footer']));
        }

        // Checkboxes not submitted = unchecked → force to false before merge
        $email_types = ['onboarding_j0', 'onboarding_j2', 'onboarding_j7',
                        're_engagement_7j', 're_engagement_30j', 'weekly_report',
                        'tips', 'milestone'];

        if (!isset($settings['general']['enabled'])) {
            $settings['general']['enabled'] = false;
        }
        foreach ($email_types as $type) {
            if (!isset($settings['emails'][$type]['enabled'])) {
                $settings['emails'][$type]['enabled'] = false;
            }
        }

        // Deep-merge: submitted settings override existing, missing keys stay intact
        $existing = $this->get_settings() ?: [];
        $merged   = $this->deep_merge($existing, $settings);

        return parent::update_settings($merged);
    }

    private function deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    protected function validate_settings(array $settings): array
    {
        if (!empty($settings['general']['from_email']) && !is_email($settings['general']['from_email'])) {
            $settings['general']['from_email'] = '';
        }

        if (isset($settings['brevo']['api_key'])) {
            $settings['brevo']['api_key'] = sanitize_text_field($settings['brevo']['api_key']);
        }

        return $settings;
    }

    protected function is_html_allowed_field(string $field): bool
    {
        return in_array($field, [
            'layout', 'onboarding_j0', 'onboarding_j2', 'onboarding_j7',
            're_engagement_7j', 're_engagement_30j', 'weekly_report', 'tips', 'milestone',
        ], true);
    }

    // -------------------------------------------------------------------------
    // DEFAULT HTML TEMPLATES
    // -------------------------------------------------------------------------

    public function default_layout(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{subject}}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <!-- Header -->
      <tr><td style="background:#1a73e8;padding:30px 40px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{{site_name}}</h1>
      </td></tr>
      <!-- Content -->
      <tr><td style="padding:40px;color:#333333;font-size:15px;line-height:1.6;">
        {{content}}
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f8f8f8;padding:20px 40px;border-top:1px solid #eeeeee;">
        {{footer}}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    public function default_footer(): string
    {
        return '<p style="margin:0 0 6px;font-size:12px;color:#888888;text-align:center;">
  Cet email a été envoyé par <strong>{{site_name}}</strong>.
</p>
<p style="margin:0 0 6px;font-size:12px;color:#888888;text-align:center;">
  <a href="{{site_url}}" style="color:#888888;">{{site_url}}</a>
</p>
<p style="margin:0;font-size:12px;color:#888888;text-align:center;">
  <a href="{{unsubscribe_url}}" style="color:#888888;text-decoration:underline;">Se désinscrire</a>
</p>';
    }

    public function default_tpl_onboarding_j0(): string
    {
        return '<h2 style="color:#1a73e8;margin-top:0;">Bienvenue {{prenom}} ! 🎉</h2>
<p>Ton compte sur <strong>{{site_name}}</strong> est maintenant actif.</p>
<p>Tu peux dès maintenant commencer à t\'entraîner pour ton permis. Voici comment démarrer :</p>
<ol>
  <li>Connecte-toi à ton compte</li>
  <li>Choisis ton type de permis</li>
  <li>Lance ta première série de questions</li>
</ol>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#1a73e8;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Commencer maintenant
  </a>
</p>
<p style="color:#666;">Bonne chance dans ta préparation !</p>';
    }

    public function default_tpl_onboarding_j2(): string
    {
        return '<h2 style="color:#1a73e8;margin-top:0;">Et si tu testais le mode examen ? 🚗</h2>
<p>Bonjour {{prenom}},</p>
<p>Il y a 2 jours tu as créé ton compte sur <strong>{{site_name}}</strong>. As-tu déjà essayé ton premier quiz ?</p>
<p>Le <strong>mode examen</strong> simule les conditions réelles du permis : 40 questions, 35 minutes, résultat immédiat.</p>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#1a73e8;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Lancer un examen blanc
  </a>
</p>
<p style="color:#666;">Chaque série complétée te rapproche de ton objectif.</p>';
    }

    public function default_tpl_onboarding_j7(): string
    {
        return '<h2 style="color:#1a73e8;margin-top:0;">Ton bilan après 7 jours ✅</h2>
<p>Bonjour {{prenom}},</p>
<p>Cela fait une semaine que tu as rejoint <strong>{{site_name}}</strong>. Voici quelques conseils pour progresser efficacement :</p>
<ul>
  <li><strong>Régularité :</strong> 15 minutes par jour valent mieux qu\'une session de 2h par semaine.</li>
  <li><strong>Révision des erreurs :</strong> Concentre-toi sur les séries où tu as le plus de fautes.</li>
  <li><strong>Mode examen :</strong> Teste-toi régulièrement en conditions réelles.</li>
</ul>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#1a73e8;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Reprendre l\'entraînement
  </a>
</p>';
    }

    public function default_tpl_reengagement_7j(): string
    {
        return '<h2 style="color:#e8791a;margin-top:0;">Tu nous manques {{prenom}} ! 😊</h2>
<p>Cela fait 7 jours que tu n\'as pas pratiqué sur <strong>{{site_name}}</strong>.</p>
<p>La régularité est la clé pour réussir ton permis. Même 10 minutes aujourd\'hui feront la différence !</p>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#e8791a;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Reprendre maintenant
  </a>
</p>
<p style="color:#666;font-size:13px;">Si tu as des questions, réponds simplement à cet email.</p>';
    }

    public function default_tpl_reengagement_30j(): string
    {
        return '<h2 style="color:#d93025;margin-top:0;">Dernière chance de ne pas lâcher ! 💪</h2>
<p>Bonjour {{prenom}},</p>
<p>30 jours sans pratiquer... Tu étais pourtant bien parti(e) !</p>
<p>Ton objectif permis est toujours atteignable. Il n\'est jamais trop tard pour reprendre.</p>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#d93025;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Je reprends aujourd\'hui
  </a>
</p>';
    }

    public function default_tpl_weekly_report(): string
    {
        return '<h2 style="color:#1a73e8;margin-top:0;">Ta progression cette semaine 📊</h2>
<p>Bonjour {{prenom}},</p>
<p>Voici ton bilan pour la semaine écoulée :</p>
<table width="100%" cellpadding="12" cellspacing="0" style="border-collapse:collapse;margin:20px 0;border-radius:6px;overflow:hidden;">
  <tr style="background:#f0f4ff;">
    <td style="border:1px solid #dde3f0;font-weight:bold;">Sessions complétées</td>
    <td style="border:1px solid #dde3f0;text-align:center;font-size:20px;font-weight:bold;color:#1a73e8;">{{sessions_count}}</td>
    <td style="border:1px solid #dde3f0;text-align:center;color:#666;">{{sessions_delta}}</td>
  </tr>
  <tr>
    <td style="border:1px solid #dde3f0;font-weight:bold;">Score moyen</td>
    <td style="border:1px solid #dde3f0;text-align:center;font-size:20px;font-weight:bold;color:#1a73e8;">{{score_moyen}}%</td>
    <td style="border:1px solid #dde3f0;text-align:center;color:#666;">{{score_delta}}</td>
  </tr>
  <tr style="background:#f0f4ff;">
    <td style="border:1px solid #dde3f0;font-weight:bold;">Permis principal</td>
    <td style="border:1px solid #dde3f0;text-align:center;font-weight:bold;" colspan="2">{{permit_type}}</td>
  </tr>
  <tr>
    <td style="border:1px solid #dde3f0;font-weight:bold;">Meilleure série</td>
    <td style="border:1px solid #dde3f0;" colspan="2">{{meilleure_serie}} — <strong>{{meilleure_score}}%</strong></td>
  </tr>
  <tr style="background:#fff5f5;">
    <td style="border:1px solid #dde3f0;font-weight:bold;">À améliorer</td>
    <td style="border:1px solid #dde3f0;" colspan="2">{{serie_faible}} — <strong>{{serie_faible_score}}%</strong></td>
  </tr>
</table>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#1a73e8;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Continuer l\'entraînement
  </a>
</p>';
    }

    public function default_tpl_tips(): string
    {
        return '<h2 style="color:#1a73e8;margin-top:0;">Conseil pour ton permis {{permit_type}} 💡</h2>
<p>Bonjour {{prenom}},</p>
<p>{{tips_content}}</p>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#1a73e8;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Pratiquer maintenant
  </a>
</p>';
    }

    public function default_tpl_milestone(): string
    {
        return '<h2 style="color:#34a853;margin-top:0;">{{milestone_label}} 🏆</h2>
<p>Bravo {{prenom}} !</p>
<p>Tu viens d\'atteindre un nouvel objectif sur <strong>{{site_name}}</strong>.</p>
<p style="font-size:18px;text-align:center;padding:20px;background:#f0fff4;border-radius:8px;border-left:4px solid #34a853;">
  <strong>{{quiz_name}}</strong> — Score : <strong>{{milestone_score}}%</strong>
</p>
<p>Continue comme ça, tu es sur la bonne voie !</p>
<p style="text-align:center;margin:30px 0;">
  <a href="{{site_url}}" style="background:#34a853;color:#ffffff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">
    Continuer
  </a>
</p>';
    }
}
