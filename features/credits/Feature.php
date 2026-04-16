<?php

namespace CobraAI\Features\Credits;

use CobraAI\FeatureBase;
use function CobraAI\{
    cobra_ai_db
};

class Feature extends FeatureBase
{
    /**
     * Feature properties
     */
    protected string $feature_id = 'credits';
    protected string $name = 'Credits System';
    protected string $description = 'Manage user credits with multiple credit types, expiration, and tracking';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    protected bool $has_admin = true;
    protected string $min_wp_version = '5.8';
    protected string $min_php_version = '7.4';

    /**
     * Feature components
     */
    private $admin;
    public $manager;
    private $cron;
    private ?StripePaymentsBridge $stripe_bridge = null;

    public function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->tables = [
            'credits' => [
                'name' => $wpdb->prefix . 'cobra_credits',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'user_id' => 'bigint(20) NOT NULL',
                    'credit_type' => 'varchar(50) NOT NULL',
                    'type_id' => 'varchar(50) NOT NULL',
                    'comment' => 'text',
                    'credit' => 'decimal(10,2) NOT NULL DEFAULT 0',
                    'consumed' => 'decimal(10,2) NOT NULL DEFAULT 0',
                    'status' => "enum('pending','active','deleted','expired') NOT NULL DEFAULT 'pending'",
                    'start_date' => 'datetime NOT NULL',
                    'expiration_date' => 'datetime DEFAULT NULL',
                    'meta' => 'longtext DEFAULT NULL',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'user_id' => '(user_id)',
                        'credit_type' => '(credit_type)',
                        'status' => '(status)'
                    ]
                ]
            ]
        ];
    }
    /**
     * Setup feature
     */
    protected function setup(): void
    {
       

        // Define feature tables

        require_once $this->path . 'includes/Class_Credits_List_Table.php';
        require_once $this->path . 'includes/CreditAdmin.php';
        // Load required files
        require_once $this->path . 'includes/CreditManager.php';
        require_once $this->path . 'includes/CreditType.php';
        require_once $this->path . 'includes/CreditCron.php';
        require_once $this->path . 'includes/StripePaymentsBridge.php';
    }

    /**
     * Initialize feature
     */
    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Credit management hooks
        add_action('cobra_ai_credit_added', [$this, 'handle_credit_added'], 10, 3);
        add_action('cobra_ai_credit_removed', [$this, 'handle_credit_removed'], 10, 2);
        add_action('cobra_ai_credit_updated', [$this, 'handle_credit_updated'], 10, 2);
        add_action('cobra_ai_credit_expired', [$this, 'handle_credit_expired'], 10, 1);

        // Manager and cron must be instantiated BEFORE the admin so that
        // admin handlers can call $this->feature->manager->...
        $this->manager = new CreditManager($this);
        $this->cron = new CreditCron($this);

        // Make sure the schema is up to date on existing installs.
        $this->maybe_upgrade_schema();

        // Admin hooks
        if (is_admin()) {
            add_filter('manage_users_columns', [$this, 'add_credit_column']);
            add_filter('manage_users_custom_column', [$this, 'credit_column_content'], 10, 3);
            add_filter('user_row_actions', [$this, 'add_credit_action'], 10, 2);
            $this->admin = new CreditAdmin($this);
        }

        // [cobra_account] profile tab integration (provided by the register feature)
        add_action('cobra_register_profile_tab', [$this, 'cobra_credits_account_custom_tab']);
        add_action('cobra_register_profile_tab_content', [$this, 'cobra_credits_account_custom_tab_content'], 10, 2);

        // Initialize CreditType
        CreditType::init();

        // Stripe Payments bridge — only activate if stripepayments is active and setting enabled
        $this->maybe_init_stripe_bridge();
    }

    /**
     * Conditionally activate the StripePayments ↔ Credits bridge.
     */
    private function maybe_init_stripe_bridge(): void
    {
        $settings = $this->get_settings();
        if (empty($settings['stripe_integration']['enabled'])) {
            return;
        }

        $active_features = get_option('cobra_ai_enabled_features', []);
        if (!in_array('stripepayments', $active_features, true)) {
            return;
        }

        $this->stripe_bridge = new StripePaymentsBridge($this);
        $this->stripe_bridge->register();
    }

    /**
     * Render the "My credits" tab header inside [cobra_account].
     */
    public function cobra_credits_account_custom_tab(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $settings = $this->get_settings();
        if (empty($settings['display']['show_in_profile'])) {
            return;
        }
        ?>
        <li>
            <a href="#credits" data-tab="credits">
                <?php esc_html_e('My credits', 'cobra-ai'); ?>
            </a>
        </li>
        <?php
    }

    /**
     * Render the "My credits" tab body inside [cobra_account].
     */
    public function cobra_credits_account_custom_tab_content(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $settings = $this->get_settings();
        if (empty($settings['display']['show_in_profile'])) {
            return;
        }

        $user_id       = get_current_user_id();
        $symbol        = $settings['general']['credit_symbol'] ?? '';
        $total         = $this->get_user_credit_total($user_id);
        $credit_types  = $this->manager ? $this->manager->get_user_credit_types($user_id) : [];
        $type_labels   = $this->get_credit_types();
        $history_limit = (int) ($settings['display']['history_per_page'] ?? 10);
        $history       = $this->get_user_credit_history($user_id, [
            'limit'   => $history_limit,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ]);
        $next_exp = $this->manager ? $this->manager->get_next_expiration($user_id) : null;
        ?>
        <div class="cobra-tab-content cobra-credits-tab" id="credits-content">
            <h3><?php esc_html_e('My credits', 'cobra-ai'); ?></h3>

            <div class="cobra-credits-balance">
                <span class="cobra-credits-balance__label"><?php esc_html_e('Available balance', 'cobra-ai'); ?></span>
                <span class="cobra-credits-balance__value">
                    <?php echo esc_html(number_format_i18n($total, 2)); ?>
                    <?php echo esc_html($symbol); ?>
                </span>
            </div>

            <?php if (!empty($credit_types)) : ?>
                <h4><?php esc_html_e('Breakdown by type', 'cobra-ai'); ?></h4>
                <table class="cobra-credits-breakdown widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Total', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Consumed', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Available', 'cobra-ai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credit_types as $type => $amounts) :
                            $label = $type_labels[$type] ?? ucfirst($type);
                        ?>
                            <tr>
                                <td><?php echo esc_html($label); ?></td>
                                <td><?php echo esc_html(number_format_i18n($amounts['total'], 2)); ?></td>
                                <td><?php echo esc_html(number_format_i18n($amounts['consumed'], 2)); ?></td>
                                <td><strong><?php echo esc_html(number_format_i18n($amounts['available'], 2)); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($next_exp) : ?>
                <p class="cobra-credits-next-expiration">
                    <?php
                    printf(
                        /* translators: %s: date of the next credit expiration */
                        esc_html__('Next expiration: %s', 'cobra-ai'),
                        esc_html(date_i18n(get_option('date_format'), strtotime($next_exp)))
                    );
                    ?>
                </p>
            <?php endif; ?>

            <h4><?php esc_html_e('Recent activity', 'cobra-ai'); ?></h4>
            <?php if (empty($history)) : ?>
                <p><?php esc_html_e('No credit activity yet.', 'cobra-ai'); ?></p>
            <?php else : ?>
                <table class="cobra-credits-history widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Type', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Amount', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Used', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Status', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Expires', 'cobra-ai'); ?></th>
                            <th><?php esc_html_e('Comment', 'cobra-ai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row) :
                            $label = $type_labels[$row->credit_type] ?? ucfirst($row->credit_type);
                        ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->created_at))); ?></td>
                                <td><?php echo esc_html($label); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) $row->credit, 2)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) $row->consumed, 2)); ?></td>
                                <td>
                                    <span class="cobra-credits-status cobra-credits-status--<?php echo esc_attr($row->status); ?>">
                                        <?php echo esc_html(ucfirst($row->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    echo $row->expiration_date
                                        ? esc_html(date_i18n(get_option('date_format'), strtotime($row->expiration_date)))
                                        : '&mdash;';
                                    ?>
                                </td>
                                <td><?php echo esc_html($row->comment); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * One-shot schema upgrade for installs that predate the `meta` column.
     * Cheap to run — gated by an option so it only touches the DB once.
     */
    private function maybe_upgrade_schema(): void
    {
        $current = (string) get_option('cobra_ai_credits_db_version', '1.0.0');
        if (version_compare($current, '1.1.0', '>=')) {
            return;
        }

        global $wpdb;
        $table = $this->get_table_name('credits');

        $has_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'meta'",
            DB_NAME,
            $table
        ));

        if ((int) $has_meta === 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN meta LONGTEXT DEFAULT NULL AFTER expiration_date");
        }

        update_option('cobra_ai_credits_db_version', '1.1.0');
    }

    /**
     * Activate feature: install tables, set defaults, schedule cron.
     */
    public function activate(): bool
    {
        $ok = parent::activate();
        if ($ok) {
            // Make sure manager/cron exist even if init_hooks hasn't run yet
            if (!$this->cron) {
                require_once $this->path . 'includes/CreditCron.php';
                $this->cron = new CreditCron($this);
            }
            $this->cron->schedule_tasks();
        }
        return $ok;
    }

    /**
     * Deactivate feature: clear cron, then run base deactivation.
     */
    public function deactivate(): bool
    {
        if ($this->cron) {
            $this->cron->clear_schedules();
        }
        return parent::deactivate();
    }

    /**
     * Get feature default options
     */

    protected function get_feature_default_options(): array
    {
        return [
            'general' => [
                'credit_types' => ['subscription', 'paid', 'free', 'coupon'],
                'credit_unit' => 'points',
                'credit_name' => 'credit',
                'credit_symbol' => 'pts',
                'type_order' => ['subscription', 'paid', 'free', 'coupon']
            ],
            'notifications' => [
                'enable_expiration_notice' => true,
                'expiration_notice_days' => 7,
                'notification_email_template' => ''
            ],
            'display' => [
                'show_in_profile' => true,
                'show_in_admin_list' => true,
                'history_per_page' => 10
            ],
            'expiration' => [
                'default_duration' => 30, // days
                'grace_period' => 0, // days
                'auto_expire' => true
            ],
            'stripe_integration' => [
                'enabled' => false,
            ]
        ];
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array
    {
        $errors = [];

        if (isset($settings['general'])) {
            if (empty($settings['general']['credit_types'])) {
                $errors[] = __('At least one credit type must be selected', 'cobra-ai');
            }
            if (isset($settings['general']['credit_unit'])
                && !in_array($settings['general']['credit_unit'], ['points', 'currency'], true)) {
                $errors[] = __('Invalid credit unit selected', 'cobra-ai');
            }
        }

        if (isset($settings['expiration'])) {
            if (isset($settings['expiration']['default_duration']) && $settings['expiration']['default_duration'] < 0) {
                $errors[] = __('Default duration cannot be negative', 'cobra-ai');
            }
            if (isset($settings['expiration']['grace_period']) && $settings['expiration']['grace_period'] < 0) {
                $errors[] = __('Grace period cannot be negative', 'cobra-ai');
            }
        }

        if (!empty($errors)) {
            update_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors', $errors);
            return $this->get_settings();
        }

        delete_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors');
        return $settings;
    }

    /**
     * Credit Management Methods
     */

    /**
     * Add credits to user. Thin wrapper around CreditManager::add_credit so that
     * there is a single source of truth for inserts, validation, and balance updates.
     *
     * @return int|false Inserted credit ID, or false on failure.
     */
    public function add_credit(int $user_id, float $amount, string $type, string $comment = '', ?string $expiration = null)
    {
        if (!$this->manager) {
            return false;
        }
        return $this->manager->add_credit($user_id, $amount, $type, [
            'comment' => $comment,
            'expiration_date' => $expiration,
        ]);
    }

    /**
     * Remove credit by ID
     */
    public function remove_credit(int $credit_id): bool
    {
        try {
            global $wpdb;
            $table = $this->get_table_name('credits');

            // Get credit info before deletion
            $credit = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id, credit_type, credit FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$credit) {
                return false;
            }

            $updated = $wpdb->update(
                $table,
                ['status' => 'deleted'],
                ['id' => $credit_id],
                ['%s'],
                ['%d']
            );

            if ($updated) {
                $this->update_user_balance((int)$credit->user_id);
                do_action('cobra_ai_credit_removed', $credit_id, $credit);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log('error', 'Failed to remove credit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user total available credits. Reads cached balance written by
     * CreditManager::update_user_balance(); falls back to a live query if missing.
     */
    public function get_user_credit_total(int $user_id): float
    {
        $cached = get_user_meta($user_id, '_cobra_ai_credit_balance', true);

        if ($cached !== '') {
            return (float)$cached;
        }

        global $wpdb;
        $table = $this->get_table_name('credits');

        $total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credit - consumed) FROM $table
            WHERE user_id = %d AND status = 'active'
            AND (expiration_date IS NULL OR expiration_date > %s)",
            $user_id,
            current_time('mysql')
        ));

        update_user_meta($user_id, '_cobra_ai_credit_balance', $total);
        return $total;
    }

    /**
     * Get user credit history
     */
    public function get_user_credit_history(int $user_id, array $args = []): array
    {
        global $wpdb;
        $table = $this->get_table_name('credits');

        $defaults = [
            'status' => null,
            'type' => null,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'limit' => 10,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['user_id = %d'];
        $params = [$user_id];

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['type'];
        }

        $query = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Process expired credits. Delegates to the cron runner so we have one code path.
     */
    public function process_expired_credits(): void
    {
        if ($this->cron) {
            $this->cron->run_expiration_check();
        }
    }

    /**
     * Validate credit type against the active list configured in settings.
     */
    protected function validate_credit_type(string $type): bool
    {
        $valid_types = $this->get_settings('general')['credit_types'] ?? [];
        return in_array($type, $valid_types, true);
    }

    /**
     * Get available credit types
     */
    public function get_credit_types(): array
    {
        return apply_filters('cobra_ai_credit_types', [
            'subscription' => __('Subscription', 'cobra-ai'),
            'paid' => __('Paid', 'cobra-ai'),
            'free' => __('Free', 'cobra-ai'),
            'coupon' => __('Coupon', 'cobra-ai'),
            'gift' => __('Gift', 'cobra-ai'),
            'reward' => __('Reward', 'cobra-ai'),
            'discount' => __('Discount', 'cobra-ai'),
            'bonus' => __('Bonus', 'cobra-ai')
        ]);
    }

    /**
     * Admin Interface Methods
     */

    /**
     * Add credit column to users list
     */
    public function add_credit_column($columns)
    {
        $columns['credits'] = __('Credits', 'cobra-ai');
        return $columns;
    }

    /**
     * Display credit column content
     */
    public function credit_column_content($value, $column_name, $user_id)
    {
        if ($column_name === 'credits') {
            $total = $this->get_user_credit_total($user_id);
            $settings = $this->get_settings();
            return sprintf(
                '%s %s',
                number_format_i18n($total, 2),
                esc_html($settings['general']['credit_symbol'])
            );
        }
        return $value;
    }

    /**
     * Add credit action to user row
     */
    public function add_credit_action($actions, $user)
    {
        if (current_user_can('edit_users')) {
            $actions['add_credit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-credits-manager',
                    'action' => 'add',
                    'user_id' => $user->ID
                ], admin_url('admin.php'))),
                __('Add Credit', 'cobra-ai')
            );
        }
        return $actions;
    }


    /**
     * Handle credit added event. Matches the action signature emitted by
     * CreditManager::add_credit(): ($credit_id, $data, $user_id).
     */
    public function handle_credit_added($credit_id, $data, $user_id): void
    {
        try {
            $amount = is_array($data) ? ($data['credit'] ?? 0) : 0;
            $type   = is_array($data) ? ($data['credit_type'] ?? '') : '';

            cobra_ai_db()->log('info', sprintf(
                'Credit added: %s %s to user #%d',
                $amount,
                $type,
                $user_id
            ), [
                'credit_id' => $credit_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type
            ]);

            // Balance is already updated by CreditManager; this is a safety refresh.
            $this->update_user_balance((int)$user_id);

            do_action('cobra_ai_after_credit_added', $credit_id, $user_id, $amount, $type);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit addition: ' . $e->getMessage(), [
                'credit_id' => $credit_id,
                'user_id' => $user_id
            ]);
        }
    }

    /**
     * Handle credit removed event
     */
    public function handle_credit_removed($credit_id, $credit_data): void
    {
        try {
            // Log the credit removal
            cobra_ai_db()->log('info', sprintf(
                'Credit removed: #%d from user #%d',
                $credit_id,
                $credit_data->user_id
            ), [
                'credit_id' => $credit_id,
                'credit_data' => $credit_data
            ]);

            // Update user credit balance
            $this->update_user_balance($credit_data->user_id);

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_removed', $credit_id, $credit_data);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit removal: ' . $e->getMessage(), [
                'credit_id' => $credit_id
            ]);
        }
    }

    /**
     * Handle credit updated event
     */
    public function handle_credit_updated($credit_id, $data): void
    {
        try {
            // Log the credit update
            cobra_ai_db()->log('info', sprintf(
                'Credit updated: #%d',
                $credit_id
            ), [
                'credit_id' => $credit_id,
                'update_data' => $data
            ]);

            // Update user balance if relevant fields changed
            if (isset($data['user_id'])) {
                $this->update_user_balance($data['user_id']);
            }

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_updated', $credit_id, $data);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit update: ' . $e->getMessage(), [
                'credit_id' => $credit_id,
                'data' => $data
            ]);
        }
    }

    /**
     * Handle credit expired event
     */
    public function handle_credit_expired($credit_id): void
    {
        try {
            global $wpdb;
            $table = $this->get_table_name('credits');

            // Get credit data
            $credit = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$credit) {
                throw new \Exception('Credit not found');
            }

            // Log the expiration
            cobra_ai_db()->log('info', sprintf(
                'Credit expired: #%d for user #%d',
                $credit_id,
                $credit->user_id
            ), [
                'credit_id' => $credit_id,
                'credit_data' => $credit
            ]);

            // Update user balance
            $this->update_user_balance($credit->user_id);

            // Send notification if enabled
            $settings = $this->get_settings();
            if (!empty($settings['notifications']['enable_expiration_notice'])) {
                $this->send_expiration_notification($credit);
            }

            // Trigger notifications if needed
            do_action('cobra_ai_after_credit_expired', $credit_id, $credit);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error handling credit expiration: ' . $e->getMessage(), [
                'credit_id' => $credit_id
            ]);
        }
    }

    /**
     * Update user credit balance. Public so CreditAdmin can refresh balances;
     * delegates to CreditManager when available to keep a single code path.
     */
    public function update_user_balance(int $user_id): void
    {
        try {
            if ($this->manager) {
                $this->manager->update_user_balance($user_id);
                return;
            }

            global $wpdb;
            $table = $this->get_table_name('credits');

            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(credit - consumed)
                FROM $table
                WHERE user_id = %d
                AND status = 'active'
                AND (expiration_date IS NULL OR expiration_date > %s)",
                $user_id,
                current_time('mysql')
            ));

            update_user_meta($user_id, '_cobra_ai_credit_balance', (float)$total);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error updating user balance: ' . $e->getMessage(), [
                'user_id' => $user_id
            ]);
        }
    }

    /**
     * Send expiration notification
     */
    private function send_expiration_notification($credit): void
    {
        try {
            $user = get_user_by('id', $credit->user_id);
            if (!$user) {
                throw new \Exception('User not found');
            }

            $settings = $this->get_settings();

            // Build email content
            $subject = sprintf(
                __('[%s] Credit Expired', 'cobra-ai'),
                get_bloginfo('name')
            );

            $message = sprintf(
                __('Your credit of %s %s has expired.', 'cobra-ai'),
                number_format_i18n($credit->credit - $credit->consumed, 2),
                $settings['general']['credit_symbol']
            );

            // Send email
            wp_mail(
                $user->user_email,
                $subject,
                $message,
                [
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>'
                ]
            );
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Error sending expiration notification: ' . $e->getMessage(), [
                'credit_id' => $credit->id,
                'user_id' => $credit->user_id
            ]);
        }
    }


    /**
     * Add credit fields to user profile
     */
    // public function add_user_profile_fields($user): void {
    //     if (!current_user_can('manage_options')) {
    //         return;
    //     }

    //     $settings = $this->get_settings();
    //     if (empty($settings['display']['show_in_profile'])) {
    //         return;
    //     }

    //     $total_credits = $this->get_user_credit_total($user->ID);
    //     $credit_history = $this->get_user_credit_history($user->ID, [
    //         'limit' => 5,
    //         'orderby' => 'created_at',
    //         'order' => 'DESC'
    //     ]);

    //     // Load user profile view
    //     include $this->path . 'views/user-profile-credits.php';
    // }

    /**
     * Get user total credits
     */
    // public function get_user_credit_total(int $user_id): float {
    //     global $wpdb;
    //     $table = $this->get_table_name('credits');

    //     $total = $wpdb->get_var($wpdb->prepare(
    //         "SELECT SUM(credit - consumed) 
    //         FROM $table 
    //         WHERE user_id = %d 
    //         AND status = 'active' 
    //         AND (expiration_date IS NULL OR expiration_date > %s)",
    //         $user_id,
    //         current_time('mysql')
    //     ));

    //     return (float)$total ?: 0;
    // }

    // /**
    //  * Get user credit history
    //  */
    // public function get_user_credit_history(int $user_id, array $args = []): array {
    //     global $wpdb;
    //     $table = $this->get_table_name('credits');

    //     $defaults = [
    //         'limit' => 10,
    //         'offset' => 0,
    //         'orderby' => 'created_at',
    //         'order' => 'DESC'
    //     ];

    //     $args = wp_parse_args($args, $defaults);

    //     $query = $wpdb->prepare(
    //         "SELECT * FROM $table 
    //         WHERE user_id = %d 
    //         ORDER BY {$args['orderby']} {$args['order']}
    //         LIMIT %d OFFSET %d",
    //         $user_id,
    //         $args['limit'],
    //         $args['offset']
    //     );

    //     return $wpdb->get_results($query) ?: [];
    // }

    /**
     * Get user credit types with balances
     */
    public function get_user_credit_types(int $user_id): array
    {
        global $wpdb;
        $table = $this->get_table_name('credits');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                credit_type,
                SUM(credit) as total_credit,
                SUM(consumed) as total_consumed,
                SUM(credit - consumed) as available
            FROM $table 
            WHERE user_id = %d 
            AND status = 'active'
            AND (expiration_date IS NULL OR expiration_date > %s)
            GROUP BY credit_type",
            $user_id,
            current_time('mysql')
        ));

        $credit_types = [];
        foreach ($results as $row) {
            $credit_types[$row->credit_type] = [
                'total' => (float)$row->total_credit,
                'consumed' => (float)$row->total_consumed,
                'available' => (float)$row->available
            ];
        }

        return $credit_types;
    }
}
