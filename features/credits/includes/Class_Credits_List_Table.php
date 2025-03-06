<?php

namespace CobraAI\Features\Credits;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Class_Credits_List_Table
 * 
 * @package CobraAI\Features\Credits\Includes
 */
class Class_Credits_List_Table extends \WP_List_Table {
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Constructor
     */
    public function __construct() {
        global $cobra_ai;
        $this->feature = $cobra_ai->get_feature('credits');

        parent::__construct([
            'singular' => 'credit',
            'plural'   => 'credits',
            'ajax'     => false
        ]);
    }

    /**
     * Get table columns
     */
    public function get_columns(): array {
        return [
            'cb'              => '<input type="checkbox" />',
            'user'            => __('User', 'cobra-ai'),
            'credit_type'     => __('Type', 'cobra-ai'),
            'amount'          => __('Amount', 'cobra-ai'),
            'consumed'        => __('Consumed', 'cobra-ai'),
            'remaining'       => __('Remaining', 'cobra-ai'),
            'status'          => __('Status', 'cobra-ai'),
            'start_date'      => __('Start Date', 'cobra-ai'),
            'expiration_date' => __('Expiration', 'cobra-ai'),
            'actions'         => __('Actions', 'cobra-ai')
        ];
    }

    /**
     * Get sortable columns
     */
    protected function get_sortable_columns(): array {
        return [
            'user'            => ['user_id', true],
            'credit_type'     => ['credit_type', false],
            'amount'          => ['credit', true],
            'consumed'        => ['consumed', false],
            'status'          => ['status', false],
            'start_date'      => ['start_date', true],
            'expiration_date' => ['expiration_date', false]
        ];
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void {
        // Set up pagination
        $per_page = $this->get_items_per_page('credits_per_page', 20);
        $current_page = $this->get_pagenum();

        // Get search terms
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        // Get filters
        $filters = [
            'user_search' => isset($_REQUEST['user_search']) ? sanitize_text_field($_REQUEST['user_search']) : '',
            'credit_type' => isset($_REQUEST['credit_type']) ? sanitize_text_field($_REQUEST['credit_type']) : '',
            'status'      => isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : ''
        ];

        // Get sorting parameters
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'start_date';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

        // Get total items count
        $total_items = $this->get_total_items_count($filters);

        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        // Get items
        $this->items = $this->get_credits($current_page, $per_page, $orderby, $order, $filters);

        // Set columns
        $this->_column_headers = [
            $this->get_columns(),
            [], // Hidden columns
            $this->get_sortable_columns(),
            'user' // Primary column
        ];
    }

    /**
     * Get credits from database
     */
    private function get_credits(int $page, int $per_page, string $orderby, string $order, array $filters): array {
        global $wpdb;
        
        $table = $this->feature->get_table_name('credits');
        $offset = ($page - 1) * $per_page;
        $where = [];
        $params = [];

        // Build where clause based on filters
        if (!empty($filters['user_search'])) {
            $user_ids = $this->search_users($filters['user_search']);
            if (!empty($user_ids)) {
                $placeholders = array_fill(0, count($user_ids), '%d');
                $where[] = 'user_id IN (' . implode(',', $placeholders) . ')';
                $params = array_merge($params, $user_ids);
            }
        }

        if (!empty($filters['credit_type'])) {
            $where[] = 'credit_type = %s';
            $params[] = $filters['credit_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        // Build query
        $query = "SELECT c.*, u.user_email, u.display_name 
                 FROM $table c
                 LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID";

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        // Add ordering
        $query .= $wpdb->prepare(" ORDER BY %s %s", $orderby, $order);

        // Add limit
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        // Execute query with parameters
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get total items count
     */
    private function get_total_items_count(array $filters): int {
        global $wpdb;
        
        $table = $this->feature->get_table_name('credits');
        $where = [];
        $params = [];

        if (!empty($filters['user_search'])) {
            $user_ids = $this->search_users($filters['user_search']);
            if (!empty($user_ids)) {
                $placeholders = array_fill(0, count($user_ids), '%d');
                $where[] = 'user_id IN (' . implode(',', $placeholders) . ')';
                $params = array_merge($params, $user_ids);
            }
        }

        if (!empty($filters['credit_type'])) {
            $where[] = 'credit_type = %s';
            $params[] = $filters['credit_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        $query = "SELECT COUNT(*) FROM $table";

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return (int)$wpdb->get_var($query);
    }

    /**
     * Search users by name or email
     */
    private function search_users(string $search): array {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($search) . '%';
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} 
            WHERE user_email LIKE %s 
            OR user_nicename LIKE %s 
            OR display_name LIKE %s",
            $search, $search, $search
        ));
    }

    /**
     * Render column content
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'user':
                return sprintf(
                    '<strong>%s</strong><br><em>%s</em>',
                    esc_html($item->display_name),
                    esc_html($item->user_email)
                );

            case 'credit_type':
                $credit_types = $this->feature->get_credit_types();
                return esc_html($credit_types[$item->credit_type] ?? $item->credit_type);

            case 'amount':
                $settings = $this->feature->get_settings();
                return sprintf(
                    '%s %s',
                    number_format_i18n($item->credit, 2),
                    esc_html($settings['general']['credit_symbol'])
                );

            case 'consumed':
                $settings = $this->feature->get_settings();
                return sprintf(
                    '%s %s',
                    number_format_i18n($item->consumed, 2),
                    esc_html($settings['general']['credit_symbol'])
                );

            case 'remaining':
                $settings = $this->feature->get_settings();
                $remaining = $item->credit - $item->consumed;
                return sprintf(
                    '%s %s',
                    number_format_i18n($remaining, 2),
                    esc_html($settings['general']['credit_symbol'])
                );

            case 'status':
                return $this->get_status_label($item->status);

            case 'start_date':
                return get_date_from_gmt($item->start_date, 'Y-m-d H:i:s');

            case 'expiration_date':
                return $item->expiration_date 
                    ? get_date_from_gmt($item->expiration_date, 'Y-m-d H:i:s')
                    : __('Never', 'cobra-ai');

            case 'actions':
                return $this->get_row_actions($item);

            // default:
            //     return print_r($item, true);
        }
    }

    /**
     * Get status label with color
     */
    private function get_status_label(string $status): string {
        $labels = [
            'active'   => ['class' => 'status-active', 'label' => __('Active', 'cobra-ai')],
            'pending'  => ['class' => 'status-pending', 'label' => __('Pending', 'cobra-ai')],
            'expired'  => ['class' => 'status-expired', 'label' => __('Expired', 'cobra-ai')],
            'deleted'  => ['class' => 'status-deleted', 'label' => __('Deleted', 'cobra-ai')]
        ];

        $status_info = $labels[$status] ?? ['class' => '', 'label' => $status];

        return sprintf(
            '<span class="credit-status %s">%s</span>',
            esc_attr($status_info['class']),
            esc_html($status_info['label'])
        );
    }

    /**
     * Get row actions
     */
    private function get_row_actions($item): string {
        $actions = [];

        if (current_user_can('manage_options')) {
            // Edit action
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-credits',
                    'action' => 'edit',
                    'id' => $item->id
                ], admin_url('admin.php'))),
                __('Edit', 'cobra-ai')
            );

            // Delete action
            $actions['delete'] = sprintf(
                '<a href="%s" class="delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url(wp_nonce_url(add_query_arg([
                    'action' => 'delete',
                    'id' => $item->id
                ]), 'delete_credit_' . $item->id)),
                esc_js(__('Are you sure you want to delete this credit?', 'cobra-ai')),
                __('Delete', 'cobra-ai')
            );
        }

        return $this->row_actions($actions);
    }

    /**
     * Get bulk actions
     */
    protected function get_bulk_actions(): array {
        return [
            'delete' => __('Delete', 'cobra-ai'),
            'expire' => __('Mark as Expired', 'cobra-ai')
        ];
    }

    /**
     * Process bulk actions
     */
    protected function process_bulk_action(): void {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        // Check nonce
        check_admin_referer('bulk-credits');

        // Get selected items
        $credit_ids = isset($_REQUEST['credit']) ? array_map('intval', $_REQUEST['credit']) : [];

        if (empty($credit_ids)) {
            return;
        }

        switch ($action) {
            case 'delete':
                foreach ($credit_ids as $credit_id) {
                    $this->feature->remove_credit($credit_id);
                }
                break;

            case 'expire':
                global $wpdb;
                $table = $this->feature->get_table_name('credits');
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET status = 'expired' WHERE id IN (" . 
                    implode(',', array_fill(0, count($credit_ids), '%d')) . ")",
                    $credit_ids
                ));
                break;
        }

        wp_redirect(add_query_arg(['page' => 'cobra-ai-credits'], admin_url('admin.php')));
        exit;
    }

    /**
     * Column checkbox
     */
    protected function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="credit[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Add custom CSS for status labels
     */
    protected function display_tablenav($which): void {
        if ($which === 'top') {
            $this->add_status_styles();
        }
        parent::display_tablenav($which);
    }

    /**
     * Add CSS styles for status labels
     */
    private function add_status_styles(): void {
        ?>
        <style>
            .credit-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .status-active {
                background-color: #dff0d8;
                color: #3c763d;
            }
            .status-pending {
                background-color: #fcf8e3;
                color: #8a6d3b;
            }
            .status-expired {
                background-color: #f2dede;
                color: #a94442;
            }
            .status-deleted {
                background-color: #f5f5f5;
                color: #777;
            }
        </style>
        <?php
    }
}