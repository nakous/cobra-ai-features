<?php

namespace CobraAI\Features\Credits;

use function CobraAI\{
    cobra_ai_db
};


class CreditAdmin
{
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Admin menu hooks
     */
    private $menu_slug = 'cobra-ai-credits';
    private $capability = 'manage_options';
    private $parent_slug = 'cobra-ai-dashboard';
    /**
     * Views path
     */
    private $views_path;

    /**
     * Constructor
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->views_path = dirname(__FILE__) . '/../views/';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'add_menu_items']);


        // AJAX handlers
        add_action('wp_ajax_cobra_ai_add_credit', [$this, 'handle_add_credit']);
        add_action('wp_ajax_cobra_ai_edit_credit', [$this, 'handle_edit_credit']);
        add_action('wp_ajax_cobra_ai_delete_credit', [$this, 'handle_delete_credit']);

        // User profile
        add_action('show_user_profile', [$this, 'add_credit_profile_fields'],0);
        add_action('edit_user_profile', [$this, 'add_credit_profile_fields'],0);
        add_action('personal_options_update', [$this, 'save_credit_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_credit_profile_fields']);

        add_action('admin_post_cobra_ai_add_credit', [$this, 'handle_add_credit']);
        add_action('admin_post_cobra_ai_edit_credit', [$this, 'handle_edit_credit']);
        add_action('admin_post_cobra_ai_bulk_add_credits', [$this, 'handle_bulk_add_credits']);
        // Users list modifications
        add_filter('manage_users_columns', [$this, 'modify_user_columns']);
        add_filter('manage_users_custom_column', [$this, 'custom_user_column_content'], 10, 3);
        add_filter('bulk_actions-users', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-users', [$this, 'handle_bulk_actions'], 10, 3);
    }

    /**
     * Add menu items
     */
    public function add_menu_items(): void
    {
        add_submenu_page(
            $this->parent_slug,           // Parent slug
            __('Credits Management', 'cobra-ai'),    // Page title
            __('Credits', 'cobra-ai'),              // Menu title
            $this->capability,                      // Capability
            $this->menu_slug . '-manager',                       // Menu slug
            [$this, 'render_credits_page']          // Callback function
        );
    }

    /**
     * Render credits management page
     */
    public function render_credits_page(): void
    {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'add':
                $this->render_add_credit_page();
                break;
            case 'edit':
                $this->render_edit_credit_page();
                break;
            case 'bulk_add':
                $this->render_bulk_add_form();
                break;
            default:
                $this->render_credits_list();
                break;
        }
    }
    /**
     * Render edit credit page
     */
    private function render_edit_credit_page(): void
    {
        // Get credit ID
        $credit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$credit_id) {
            wp_die(__('Invalid credit ID', 'cobra-ai'));
        }

        // Get credit data
        global $wpdb;
        $table = $this->feature->get_table_name('credits');
        $credit = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.display_name, u.user_email 
             FROM $table c
             JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.id = %d",
            $credit_id
        ));

        if (!$credit) {
            wp_die(__('Credit not found', 'cobra-ai'));
        }

        $data = [
            'credit' => $credit,
            'credit_types' => $this->feature->get_credit_types(),
            'settings' => $this->feature->get_settings()
        ];
        
        // Load edit view
        $this->load_view('edit-credit.php', $data);
    }
    /**
     * Render credits list
     */
    private function render_credits_list(): void
    {
        // Create credits list table instance
        require_once dirname(__FILE__) . '/Class_Credits_List_Table.php';
        $credits_table = new Class_Credits_List_Table();
        $credits_table->prepare_items();

        $data = [
            'menu_slug' => $this->menu_slug,
            'credits_table' => $credits_table,
            'user_search' => isset($_GET['user_search']) ? sanitize_text_field($_GET['user_search']) : '',
            'credit_type' => isset($_GET['credit_type']) ? sanitize_text_field($_GET['credit_type']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'credit_types' => $this->feature->get_credit_types()
        ];

        $this->load_view('credits-list.php', $data);
    }
    /**
     * Handle edit credit form submission
     */
    public function handle_edit_credit(): void
    {
        check_admin_referer('cobra_ai_edit_credit');

        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have permission to perform this action.', 'cobra-ai'));
        }

        $credit_id = intval($_POST['credit_id']);
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = floatval($_POST['amount']);
        $consumed = floatval($_POST['consumed']);
        $status = sanitize_text_field($_POST['status']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $expiration_date = !empty($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;
        $comment = sanitize_textarea_field($_POST['comment']);

        try {
            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            // Get current credit data
            $current_credit = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$current_credit) {
                throw new \Exception(__('Credit not found', 'cobra-ai'));
            }

            // Update credit
            $result = $wpdb->update(
                $table,
                [
                    'credit_type' => $credit_type,
                    'credit' => $amount,
                    'consumed' => $consumed,
                    'status' => $status,
                    'start_date' => $start_date,
                    'expiration_date' => $expiration_date,
                    'comment' => $comment,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $credit_id],
                ['%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Update user balance if needed
            if (
                $current_credit->credit !== $amount ||
                $current_credit->consumed !== $consumed ||
                $current_credit->status !== $status
            ) {
                $this->feature->update_user_balance($current_credit->user_id);
            }

            // Redirect back with success message
            wp_redirect(add_query_arg(
                ['page' => 'cobra-ai-credits', 'message' => 'credit_updated'],
                admin_url('admin.php')
            ));
            exit;
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }
    /**
     * Render add credit page
     */
    private function render_add_credit_page(): void
    {
        $data = [
            'user_id' => isset($_GET['user_id']) ? intval($_GET['user_id']) : 0,
            'user' => null,
            'credit_types' => $this->feature->get_credit_types(),
            'users' => get_users(['fields' => ['ID', 'display_name', 'user_email']])
        ];

        if ($data['user_id']) {
            $data['user'] = get_user_by('id', $data['user_id']);
            $data['settings'] = $this->feature->get_settings();
        }

        $this->load_view('add-credit.php', $data);
    }

    /**
     * Add credit profile fields
     */
    public function add_credit_profile_fields($user): void
    {
        if (!current_user_can($this->capability)) {
            return;
        }

        $data = [
            'user' => $user,
            'total_credits' => $this->feature->get_user_credit_total($user->ID),
            'credit_history' => $this->feature->get_user_credit_history($user->ID, [
                'limit' => 5,
                'orderby' => 'created_at',
                'order' => 'DESC'
            ]),
            'menu_slug' => $this->menu_slug,
            'credit_types' => $this->feature->get_credit_types(),
            'settings' => $this->feature->get_settings()
        ];

        $this->load_view('user-profile-credits.php', $data);
    }

    /**
     * Load a view file
     */
    private function load_view(string $view, array $data = []): void
    {
        extract($data);
        include $this->views_path . $view;
    }

    // ... rest of the methods remain the same

    /**
     * Handle add credit form submission
     */
    public function handle_add_credit(): void
    {
        try {
            // Log start of handler
            cobra_ai_db()->log('debug', 'Starting handle_add_credit');

            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cobra_ai_add_credit')) {
                throw new \Exception('Security check failed');
            }

            // Check permissions
            if (!current_user_can($this->capability)) {
                throw new \Exception('Permission denied');
            }

            // Get and validate form data
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            if (!$user_id) {
                throw new \Exception('Invalid user ID');
            }

            $credit_type = isset($_POST['credit_type']) ? sanitize_text_field($_POST['credit_type']) : '';
            if (empty($credit_type)) {
                throw new \Exception('Credit type is required');
            }

            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
            if ($amount <= 0) {
                throw new \Exception('Invalid amount');
            }

            $expiration_date = !empty($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;
            $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';

            // Log data being processed
            cobra_ai_db()->log('debug', 'Processing credit addition', [
                'user_id' => $user_id,
                'credit_type' => $credit_type,
                'amount' => $amount,
                'expiration_date' => $expiration_date
            ]);

            // Add credit using the feature instance
            $credit_id = $this->feature->add_credit(
                $user_id,
                $amount,
                $credit_type,
                $comment,
                $expiration_date
            );

            if (!$credit_id) {
                throw new \Exception('Failed to add credit');
            }

            // Log success
            cobra_ai_db()->log('info', 'Credit added successfully', [
                'credit_id' => $credit_id,
                'user_id' => $user_id
            ]);

            // Redirect back to credits list with success message
            wp_redirect(add_query_arg(
                ['page' => $this->menu_slug."-manager", 'message' => 'credit_added'],
                admin_url('admin.php')
            ));
            exit;
        } catch (\Exception $e) {
            // Log error
            cobra_ai_db()->log('error', 'Error adding credit: ' . $e->getMessage());

            // Redirect back with error
            wp_redirect(add_query_arg(
                [
                    'page' => $this->menu_slug,
                    'action' => 'add',
                    'error' => urlencode($e->getMessage())
                ],
                admin_url('admin.php')
            ));
            exit;
        }
    }



    /**
     * Modify user columns
     */
    public function modify_user_columns($columns): array
    {
        $columns['credits'] = __('Credits', 'cobra-ai');
        return $columns;
    }


    /**
     * Handle credit deletion
     */
    public function handle_delete_credit(): void
    {
        // Check nonce
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        $credit_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

        if (!wp_verify_nonce($nonce, 'delete_credit_' . $credit_id)) {
            wp_die(__('Security check failed', 'cobra-ai'));
        }

        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have permission to perform this action.', 'cobra-ai'));
        }

        try {
            global $wpdb;
            $table = $this->feature->get_table_name('credits');

            // Get credit info before deletion
            $credit = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM $table WHERE id = %d",
                $credit_id
            ));

            if (!$credit) {
                throw new \Exception(__('Credit not found', 'cobra-ai'));
            }

            // Update credit status to deleted
            $result = $wpdb->update(
                $table,
                [
                    'status' => 'deleted',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $credit_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Update user balance
            $this->feature->update_user_balance($credit->user_id);

            // Redirect with success message
            wp_redirect(add_query_arg(
                ['page' => 'cobra-ai-credits', 'message' => 'credit_deleted'],
                admin_url('admin.php')
            ));
            exit;
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }

    /**
     * Custom user column content for credits
     */
    public function custom_user_column_content($value, $column_name, $user_id): string
    {
        if ($column_name !== 'credits') {
            return $value;
        }

        $settings = $this->feature->get_settings();
        $total_credits = $this->feature->get_user_credit_total($user_id);
        $credit_types = $this->feature->manager->get_user_credit_types($user_id);

        $output = sprintf(
            '<strong>%s %s</strong>',
            number_format_i18n($total_credits, 2),
            esc_html($settings['general']['credit_symbol'])
        );

        if (!empty($credit_types)) {
            $output .= '<div class="credit-types-breakdown">';
            foreach ($credit_types as $type => $amounts) {
                if ($amounts['available'] > 0) {
                    $output .= sprintf(
                        '<div class="credit-type"><span class="type">%s:</span> %s %s</div>',
                        esc_html($this->feature->get_credit_types()[$type]),
                        number_format_i18n($amounts['available'], 2),
                        esc_html($settings['general']['credit_symbol'])
                    );
                }
            }
            $output .= '</div>';
        }

        // Add quick actions
        $actions = [];
        if (current_user_can($this->capability)) {
            $actions[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-credits-manager',
                    'action' => 'add',
                    'user_id' => $user_id
                ], admin_url('admin.php'))),
                __('Add Credit', 'cobra-ai')
            );

            $actions[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-credits-manager',
                    'user_id' => $user_id
                ], admin_url('admin.php'))),
                __('View History', 'cobra-ai')
            );
        }

        if (!empty($actions)) {
            $output .= '<div class="row-actions">' . implode(' | ', $actions) . '</div>';
        }

        return $output;
    }

    /**
     * Add bulk actions to users list
     */
    public function add_bulk_actions($actions): array
    {
        if (current_user_can($this->capability)) {
            $actions['add_credits'] = __('Add Credits', 'cobra-ai');
        }
        return $actions;
    }

    /**
     * Handle bulk actions for users list
     */
    public function handle_bulk_actions($redirect_to, $action, $user_ids): string
    {
        if ($action !== 'add_credits' || !current_user_can($this->capability)) {
            return $redirect_to;
        }

        // Store user IDs in session for the bulk credit form
        if (!session_id()) {
            session_start();
        }
        $_SESSION['cobra_ai_bulk_credit_users'] = array_map('intval', $user_ids);

        // Redirect to bulk credit form
        return add_query_arg([
            'page' => 'cobra-ai-credits',
            'action' => 'bulk_add'
        ], admin_url('admin.php'));
    }

    /**
     * Render bulk add credits form
     */
    private function render_bulk_add_form(): void
    {
        if (!session_id()) {
            session_start();
        }

        $user_ids = $_SESSION['cobra_ai_bulk_credit_users'] ?? [];
        if (empty($user_ids)) {
            wp_die(__('No users selected', 'cobra-ai'));
        }

        $users = get_users(['include' => $user_ids]);
        $data = [
            'users' => $users,
            'credit_types' => $this->feature->get_credit_types(),
            'settings' => $this->feature->get_settings()
        ];

        $this->load_view('bulk-add-credits.php', $data);
    }

    /**
     * Handle bulk add credits submission
     */
    public function handle_bulk_add_credits(): void
    {
        check_admin_referer('cobra_ai_bulk_add_credits');

        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have permission to perform this action.', 'cobra-ai'));
        }

        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];
        $credit_type = sanitize_text_field($_POST['credit_type']);
        $amount = floatval($_POST['amount']);
        $expiration_date = !empty($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;
        $comment = sanitize_textarea_field($_POST['comment']);

        try {
            $success_count = 0;
            foreach ($user_ids as $user_id) {
                $result = $this->feature->add_credit(
                    $user_id,
                    $amount,
                    $credit_type,
                    $comment,
                    $expiration_date
                );

                if ($result) {
                    $success_count++;
                }
            }

            // Redirect with results
            wp_redirect(add_query_arg([
                'page' => 'cobra-ai-credits',
                'message' => 'bulk_credits_added',
                'success_count' => $success_count,
                'total_count' => count($user_ids)
            ], admin_url('admin.php')));
            exit;
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }
 
    /**
     * Save credit fields in user profile
     */
    public function save_credit_profile_fields($user_id): void {
        // Verify permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Verify nonce if you have custom fields to save
        if (isset($_POST['cobra_ai_credit_nonce']) && 
            wp_verify_nonce($_POST['cobra_ai_credit_nonce'], 'cobra_ai_credit_profile_' . $user_id)) {

            // Handle any custom credit-related field saves
            if (isset($_POST['cobra_ai_credit_settings'])) {
                $settings = array_map('sanitize_text_field', $_POST['cobra_ai_credit_settings']);
                update_user_meta($user_id, '_cobra_ai_credit_settings', $settings);
            }

            // Handle any credit preferences
            if (isset($_POST['cobra_ai_credit_preferences'])) {
                $preferences = array_map('sanitize_text_field', $_POST['cobra_ai_credit_preferences']);
                update_user_meta($user_id, '_cobra_ai_credit_preferences', $preferences);
            }

            // Log the update
            cobra_ai_db()->log('info', sprintf(
                'Updated credit profile settings for user #%d',
                $user_id
            ));
        }
    }
}
