<?php

namespace CobraAI\Features\AI;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Class_Tracking_List_Table extends \WP_List_Table
{
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Query arguments
     */
    private $query_args = [];

    /**
     * Constructor
     */
    public function __construct($args = [])
    {
        global $cobra_ai;
        $this->feature = $cobra_ai->get_feature('ai');
        // $this->feature = $feature;
        $this->query_args = $args;

        parent::__construct([
            'singular' => 'tracking',
            'plural'   => 'trackings',
            'ajax'     => false
        ]);
    }

    /**
     * Get table columns
     */
    public function get_columns(): array
    {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => __('ID', 'cobra-ai'),
            'user'         => __('User', 'cobra-ai'),
            'prompt'       => __('Prompt', 'cobra-ai'),
            'ai_provider'  => __('Provider', 'cobra-ai'),
            'consumed'     => __('Consumed', 'cobra-ai'),
            'status'       => __('Status', 'cobra-ai'),
            'response_type' => __('Type', 'cobra-ai'),
            'created_at'   => __('Date', 'cobra-ai'),
            'actions'      => __('Actions', 'cobra-ai')
        ];
    }

    /**
     * Get sortable columns
     */
    protected function get_sortable_columns(): array
    {
        return [
            'id'          => ['id', true],
            'user'        => ['user_id', false],
            'ai_provider' => ['ai_provider', false],
            'consumed'    => ['consumed', false],
            'status'      => ['status', false],
            'created_at'  => ['created_at', true]
        ];
    }

    /**
     * Get bulk actions
     */
    protected function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'cobra-ai')
        ];
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action(): void
    {
        if ('delete' === $this->current_action()) {
            $tracking_ids = isset($_POST['tracking']) ? array_map('intval', $_POST['tracking']) : [];

            if (!empty($tracking_ids)) {
                foreach ($tracking_ids as $tracking_id) {
                    $this->feature->tracking->delete_tracking($tracking_id);
                }
            }
        }
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void
    {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        // Get filters
        $ai_provider = isset($_REQUEST['ai_provider']) ? sanitize_text_field($_REQUEST['ai_provider']) : '';
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $response_type = isset($_REQUEST['response_type']) ? sanitize_text_field($_REQUEST['response_type']) : '';

        // Prepare query args
        $args = [
            'provider' => $ai_provider,
            'status' => $status,
            'response_type' => $response_type,
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at',
            'order' => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC'
        ];

        // Merge with constructor args
        $args = wp_parse_args($args, $this->query_args);

        // Get total items count
        $total_items = $this->get_total_items_count($args);

        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        // Get items
        $this->items = $this->get_trackings($args);

        // Set columns
        $this->_column_headers = [
            $this->get_columns(),
            [], // Hidden columns
            $this->get_sortable_columns(),
            'prompt' // Primary column
        ];
    }

    /**
     * Get total items count
     */
    public function get_total_items_count(array $args): int
    {
        $user_id = isset($args['user_id']) ? $args['user_id'] : 0;
        return $this->feature->tracking->get_user_tracking_count($user_id, $args);
    }

    /**
     * Get trackings from database
     */
    private function get_trackings(array $args): array
    {
        $user_id = isset($args['user_id']) ? $args['user_id'] : 0;
        return $this->feature->tracking->get_user_trackings($user_id, $args);
    }

    /**
     * Column default
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
                return '#' . $item->id;

            case 'prompt':
                return $this->get_prompt_column($item);

            case 'ai_provider':
                return $this->get_provider_column($item);

            case 'consumed':
                return $item->consumed;

            case 'status':
                return $this->get_status_column($item);

            case 'response_type':
                return ucfirst($item->response_type);

            case 'created_at':
                return get_date_from_gmt($item->created_at, get_option('date_format') . ' ' . get_option('time_format'));

            default:
                return print_r($item, true);
        }
    }

    /**
     * Column user
     */
    public function column_user($item): string
    {
        $user = get_userdata($item->user_id);
        if (!$user) {
            return __('Unknown User', 'cobra-ai');
        }

        $user_text = sprintf(
            '<strong>%s</strong><br><em>%s</em>',
            esc_html($user->display_name),
            esc_html($user->user_email)
        );

        // Add user actions
        $actions = [
            'view' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-trackings',
                    'action' => 'user',
                    'user_id' => $item->user_id
                ], admin_url('admin.php'))),
                __('View All', 'cobra-ai')
            )
        ];

        return $user_text . $this->row_actions($actions);
    }

    /**
     * Column actions
     */
    public function column_actions($item): string
    {
        $actions = [
            'view' => sprintf(
                '<a href="%s" class="button button-small">%s</a>',
                esc_url(add_query_arg([
                    'page' => 'cobra-ai-trackings',
                    'action' => 'view',
                    'id' => $item->id
                ], admin_url('admin.php'))),
                __('View', 'cobra-ai')
            ),
            'delete' => sprintf(
                '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url(wp_nonce_url(
                    add_query_arg([
                        'action' => 'delete',
                        'tracking' => $item->id
                    ]),
                    'bulk-' . $this->_args['plural']
                )),
                __('Are you sure you want to delete this tracking?', 'cobra-ai'),
                __('Delete', 'cobra-ai')
            )
        ];

        return implode(' ', $actions);
    }
    private function is_json($string): bool
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    /**
     * Get prompt column content
     */
    private function get_prompt_column($item): string
    {
        if ($this->is_json($item->prompt)) {
            $decoded_prompt = json_decode($item->prompt, true);
            $fields = ['image', 'user', 'system'];
            $prompt_parts = [];

            foreach ($fields as $field) {
                if (isset($decoded_prompt[$field])) {
                    if ($field === 'image') {
                        $prompt_parts[] = sprintf('<strong>%s:</strong> <img src="%s" alt="Image" style="max-width: 100px; max-height: 100px;" />', ucfirst($field), esc_url($decoded_prompt[$field]));
                    } else if ($field === 'user') {
                        // For other fields, just display the text
                        $prompt_parts[] = sprintf('<strong>%s:</strong> %s', ucfirst($field), esc_html($decoded_prompt[$field]));
                    }
                }  
            }

            $prompt = implode('<br>', $prompt_parts);
        } else {
            $prompt = wp_trim_words($item->prompt, 10);
        }

        // Add view action
        $actions = [
            'view' => sprintf(
                '<a href="#" class="view-tracking" data-tracking="%d">%s</a>',
                $item->id,
                __('View Full', 'cobra-ai')
            )
        ];

        return $prompt . $this->row_actions($actions);
    }

    /**
     * Get provider column content
     */
    private function get_provider_column($item): string
    {
        $providers = [
            'openai' => 'OpenAI',
            'claude' => 'Claude',
            'gemini' => 'Gemini',
            'perplexity' => 'Perplexity'
        ];

        return $providers[$item->ai_provider] ?? $item->ai_provider;
    }

    /**
     * Get status column content
     */
    private function get_status_column($item): string
    {
        $statuses = [
            'pending' => [
                'label' => __('Pending', 'cobra-ai'),
                'class' => 'status-pending'
            ],
            'completed' => [
                'label' => __('Completed', 'cobra-ai'),
                'class' => 'status-completed'
            ],
            'failed' => [
                'label' => __('Failed', 'cobra-ai'),
                'class' => 'status-failed'
            ]
        ];

        $status = $statuses[$item->status] ?? [
            'label' => ucfirst($item->status),
            'class' => 'status-' . $item->status
        ];

        return sprintf(
            '<span class="tracking-status %s">%s</span>',
            esc_attr($status['class']),
            esc_html($status['label'])
        );
    }

    /**
     * Column checkbox
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="tracking[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Get views
     */
    protected function get_views(): array
    {
        global $wpdb;
        $table = $this->feature->get_table_name('trackings');
        $user_id = isset($this->query_args['user_id']) ? $this->query_args['user_id'] : 0;

        $views = [];
        $current = isset($_GET['status']) ? $_GET['status'] : 'all';

        // Get counts
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
            FROM $table 
            WHERE user_id = %d
            GROUP BY status",
            $user_id
        ), ARRAY_A);

        $total = 0;
        $status_counts = [];
        foreach ($counts as $row) {
            $total += $row['count'];
            $status_counts[$row['status']] = $row['count'];
        }

        // All link
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url(remove_query_arg('status')),
            $current === 'all' ? 'current' : '',
            __('All', 'cobra-ai'),
            $total
        );

        // Status links
        foreach (['completed', 'pending', 'failed'] as $status) {
            $count = $status_counts[$status] ?? 0;
            $views[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', $status)),
                $current === $status ? 'current' : '',
                ucfirst($status),
                $count
            );
        }

        return $views;
    }

    /**
     * Extra tablenav
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        // Get providers
        $providers = [
            'openai' => 'OpenAI',
            'claude' => 'Claude',
            'gemini' => 'Gemini',
            'perplexity' => 'Perplexity'
        ];

        // Get response types
        $response_types = [
            'text' => __('Text', 'cobra-ai'),
            'image' => __('Image', 'cobra-ai'),
            'json' => __('JSON', 'cobra-ai')
        ];

        // Get current filters
        $current_provider = isset($_GET['ai_provider']) ? sanitize_text_field($_GET['ai_provider']) : '';
        $current_type = isset($_GET['response_type']) ? sanitize_text_field($_GET['response_type']) : '';
?>
        <div class="alignleft actions">
            <select name="ai_provider">
                <option value=""><?php _e('All providers', 'cobra-ai'); ?></option>
                <?php foreach ($providers as $id => $name): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($current_provider, $id); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="response_type">
                <option value=""><?php _e('All types', 'cobra-ai'); ?></option>
                <?php foreach ($response_types as $id => $name): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($current_type, $id); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(__('Filter', 'cobra-ai'), '', 'filter_action', false); ?>
        </div>
<?php
    }

    /**
     * Display no items message
     */
    public function no_items(): void
    {
        _e('No trackings found.', 'cobra-ai');
    }
}
