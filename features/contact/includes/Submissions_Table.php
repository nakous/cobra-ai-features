<?php

namespace CobraAI\Features\Contact;

if (!defined('ABSPATH')) {
    exit;
}

// Include WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Submissions_Table extends \WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false
        ]);
    }
    
    /**
     * Get table columns
     */
    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => __('Name', 'cobra-ai'),
            'email'      => __('Email', 'cobra-ai'),
            'subject'    => __('Subject', 'cobra-ai'),
            'message'    => __('Message', 'cobra-ai'),
            'status'     => __('Status', 'cobra-ai'),
            'created_at' => __('Date', 'cobra-ai')
        ];
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return [
            'name'       => ['name', false],
            'email'      => ['email', false],
            'subject'    => ['subject', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', true]
        ];
    }
    
    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
            case 'email':
            case 'subject':
                return esc_html($item[$column_name]);
            case 'message':
                return wp_trim_words(esc_html($item[$column_name]), 10);
            case 'status':
                return $this->get_status_label($item[$column_name]);
            case 'created_at':
                return human_time_diff(strtotime($item[$column_name]), current_time('timestamp')) . ' ' . __('ago', 'cobra-ai');
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Get status label with color coding
     */
    private function get_status_label($status) {
        $labels = [
            'unread' => '<span class="status-unread">' . __('Unread', 'cobra-ai') . '</span>',
            'read'   => '<span class="status-read">' . __('Read', 'cobra-ai') . '</span>',
            'replied' => '<span class="status-replied">' . __('Replied', 'cobra-ai') . '</span>'
        ];
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Column name
     */
    public function column_name($item) {
        // Build row actions
        $actions = [
            'view'   => sprintf('<a href="%s">%s</a>', 
                admin_url('admin.php?page=cobra-contact-view-submission&id=' . $item['id']),
                __('View', 'cobra-ai')
            ),
            'delete' => sprintf('<a href="#" class="delete-submission" data-id="%s" data-nonce="%s">%s</a>',
                $item['id'],
                wp_create_nonce('cobra_contact_admin'),
                __('Delete', 'cobra-ai')
            )
        ];
        
        // Return name with row actions
        return sprintf(
            '%1$s %2$s',
            $item['status'] === 'unread' ? '<strong>' . esc_html($item['name']) . '</strong>' : esc_html($item['name']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'bulk-delete' => __('Delete', 'cobra-ai'),
            'bulk-mark-read' => __('Mark as Read', 'cobra-ai')
        ];
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        // Security check
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce'])) {
            $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            $action = 'bulk-' . $this->_args['plural'];
            
            if (!wp_verify_nonce($nonce, $action)) {
                wp_die(__('Security check failed. Please try again.', 'cobra-ai'));
            }
            
            $action = $this->current_action();
            
            if ('bulk-delete' === $action) {
                $delete_ids = isset($_POST['bulk-delete']) ? array_map('absint', $_POST['bulk-delete']) : [];
                
                if (!empty($delete_ids)) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'cobra_contact_submissions';
                    
                    foreach ($delete_ids as $id) {
                        $wpdb->delete(
                            $table_name,
                            ['id' => $id],
                            ['%d']
                        );
                    }
                    
                    // Add admin notice
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . 
                            __('Submissions deleted successfully.', 'cobra-ai') . 
                            '</p></div>';
                    });
                }
            } elseif ('bulk-mark-read' === $action) {
                $mark_ids = isset($_POST['bulk-delete']) ? array_map('absint', $_POST['bulk-delete']) : [];
                
                if (!empty($mark_ids)) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'cobra_contact_submissions';
                    
                    foreach ($mark_ids as $id) {
                        $wpdb->update(
                            $table_name,
                            ['status' => 'read'],
                            ['id' => $id],
                            ['%s'],
                            ['%d']
                        );
                    }
                    
                    // Add admin notice
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . 
                            __('Submissions marked as read.', 'cobra-ai') . 
                            '</p></div>';
                    });
                }
            }
        }
    }
    
    /**
     * Get data from database
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cobra_contact_submissions';
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Set column headers
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Pagination settings
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Get search term
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        
        // Build query
        $query = "SELECT * FROM $table_name";
        $query_params = [];
        
        // Add search condition
        if (!empty($search)) {
            $query .= " WHERE (name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params = [$search_term, $search_term, $search_term, $search_term];
        }
        
        // Add status filter
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        if (!empty($status_filter)) {
            $query .= empty($search) ? " WHERE" : " AND";
            $query .= " status = %s";
            $query_params[] = $status_filter;
        }
        
        // Add order
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        if (!in_array($orderby, array_keys($this->get_sortable_columns()))) {
            $orderby = 'created_at';
        }
        
        if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
            $order = 'DESC';
        }
        
        $query .= " ORDER BY $orderby $order";
        
        // Add limit
        $query .= " LIMIT %d OFFSET %d";
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        // Prepare final query
        $prepared_query = $query_params ? $wpdb->prepare($query, $query_params) : $query;
        
        // Get total items count for pagination
        $count_query = "SELECT COUNT(*) FROM $table_name";
        $count_params = [];
        
        if (!empty($search)) {
            $count_query .= " WHERE (name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s)";
            $count_params = [$search_term, $search_term, $search_term, $search_term];
        }
        
        if (!empty($status_filter)) {
            $count_query .= empty($search) ? " WHERE" : " AND";
            $count_query .= " status = %s";
            $count_params[] = $status_filter;
        }
        
        $prepared_count_query = $count_params ? $wpdb->prepare($count_query, $count_params) : $count_query;
        $total_items = $wpdb->get_var($prepared_count_query);
        
        // Set pagination args
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        // Get items
        $this->items = $wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Extra table navigation
     */
    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php _e('All Statuses', 'cobra-ai'); ?></option>
                <option value="unread" <?php selected($status, 'unread'); ?>><?php _e('Unread', 'cobra-ai'); ?></option>
                <option value="read" <?php selected($status, 'read'); ?>><?php _e('Read', 'cobra-ai'); ?></option>
                <option value="replied" <?php selected($status, 'replied'); ?>><?php _e('Replied', 'cobra-ai'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'cobra-ai'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
    
    /**
     * No items found text
     */
    public function no_items() {
        _e('No submissions found.', 'cobra-ai');
    }
}