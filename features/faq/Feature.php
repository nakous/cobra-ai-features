<?php

namespace CobraAI\Features\FAQ;

use CobraAI\FeatureBase;

use function CobraAI\{
    cobra_ai_db
};


class Feature extends FeatureBase
{
    protected string $feature_id = 'faq';
    protected string $name = 'FAQ Manager';
    protected string $description = 'Advanced FAQ management system with categories, search, and customizable display options';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;

    protected $post_type = 'cobra_faq';
    protected $taxonomy = 'cobra_faq_category';

    protected function setup(): void
    {
        global $wpdb;
        // Handle post type registration based on feature status

        // if (in_array($this->feature_id, get_option('cobra_ai_enabled_features', []))) {
        //     add_action('init', [$this, 'register_post_type']);
        //     add_action('init', [$this, 'register_taxonomy']);
        //     add_action('init', [$this, 'add_rewrite_rules']);
        //     // add_filter('post_type_link', [$this, 'post_type_link'], 10, 2);
        // } else {
        //     add_action('init', [$this, 'unregister_post_type']);
        // }
        // Add cleanup hook for plugin deactivation
        // add_action('cobra_ai_feature_deactivated_' . $this->feature_id, [$this, 'cleanup_post_types']);
        // Register post types and taxonomies

        // Initialize REST API
        // add_action('rest_api_init', [$this, 'register_rest_fields']);

        // Add shortcode
       

        // AJAX handlers
        // add_action('wp_ajax_cobra_faq_search', [$this, 'handle_faq_search']);
        // add_action('wp_ajax_nopriv_cobra_faq_search', [$this, 'handle_faq_search']);
        // add_action('wp_ajax_cobra_faq_load_more', [$this, 'handle_load_more']);
        // add_action('wp_ajax_nopriv_cobra_faq_load_more', [$this, 'handle_load_more']);
        // add_action('wp_ajax_cobra_faq_helpful', [$this, 'handle_helpful']);
        // add_action('wp_ajax_nopriv_cobra_faq_helpful', [$this, 'handle_helpful']);
        // add_action('wp_ajax_cobra_faq_increment_views', [$this, 'handle_increment_views']);
        // add_action('wp_ajax_nopriv_cobra_faq_increment_views', [$this, 'handle_increment_views']);
        // register_deactivation_hook($this->path . 'Feature.php', [$this, 'cleanup_post_types']);

        // Define database tables
        $this->tables = [
            'faq_views' => [
                'name' => $wpdb->prefix . 'cobra_faq_views',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'faq_id' => 'bigint(20) NOT NULL',
                    'views' => 'bigint(20) NOT NULL DEFAULT 0',
                    'helpful_yes' => 'bigint(20) NOT NULL DEFAULT 0',
                    'helpful_no' => 'bigint(20) NOT NULL DEFAULT 0',
                    'last_viewed' => 'datetime DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'faq_id' => '(faq_id)',
                        'views' => '(views)'
                    ]
                ]
            ]
        ];

        // Add view count column to admin
        // add_filter('manage_' . $this->post_type . '_posts_columns', [$this, 'add_custom_columns']);
        // add_action('manage_' . $this->post_type . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        // add_filter('manage_edit-' . $this->post_type . '_sortable_columns', [$this, 'sortable_columns']);

        // load enqueue_assets
        // add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // add_filter('rest_route_for_post', [$this, 'cobra_faq_rest_route_for_post'], 10, 2);
    }
    protected function init_hooks(): void {
        // log this 
        // $this->log('info', 'Initializing Stripe Subscription feature');
        
        parent::init_hooks();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets_param']);
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'add_rewrite_rules']);
        add_action('cobra_ai_feature_deactivated_' . $this->feature_id, [$this, 'cleanup_post_types']);
        add_action('rest_api_init', [$this, 'register_rest_fields']);
        add_action('wp_ajax_cobra_faq_search', [$this, 'handle_faq_search']);
        add_action('wp_ajax_nopriv_cobra_faq_search', [$this, 'handle_faq_search']);
        add_action('wp_ajax_cobra_faq_load_more', [$this, 'handle_load_more']);
        add_action('wp_ajax_nopriv_cobra_faq_load_more', [$this, 'handle_load_more']);
        add_action('wp_ajax_cobra_faq_helpful', [$this, 'handle_helpful']);
        add_action('wp_ajax_nopriv_cobra_faq_helpful', [$this, 'handle_helpful']);
        add_action('wp_ajax_cobra_faq_increment_views', [$this, 'handle_increment_views']);
        add_action('wp_ajax_nopriv_cobra_faq_increment_views', [$this, 'handle_increment_views']);

        add_filter('manage_' . $this->post_type . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . $this->post_type . '_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-' . $this->post_type . '_sortable_columns', [$this, 'sortable_columns']);


    }
    protected function register_shortcodes(): void {
        add_shortcode('cobra_faq', [$this, 'render_faq_shortcode']);
    }
    public function register_post_type(): void
    {
        $args = [
            'labels' => [
                'name' => __('FAQs', 'cobra-ai'),
                'singular_name' => __('FAQ', 'cobra-ai'),
                'add_new' => __('Add New FAQ', 'cobra-ai'),
                'add_new_item' => __('Add New FAQ', 'cobra-ai'),
                'edit_item' => __('Edit FAQ', 'cobra-ai'),
                'new_item' => __('New FAQ', 'cobra-ai'),
                'view_item' => __('View FAQ', 'cobra-ai'),
                'search_items' => __('Search FAQs', 'cobra-ai'),
                'not_found' => __('No FAQs found', 'cobra-ai'),
                'not_found_in_trash' => __('No FAQs found in Trash', 'cobra-ai'),
                'menu_name' => __('FAQs', 'cobra-ai'),
            ],
            'public' => true,
            'description' => __('Frequently Asked Questions', 'cobra-ai'),
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => 'cobra_faq',
            'rewrite' => [
                'slug' => 'faqs',
                // 'with_front' => true,
                // 'pages' => true,
                // 'feeds' => true,
            ],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => [
                'title',
                'editor',
                'excerpt',
                // 'revisions',
                'custom-fields'
            ],
            'taxonomies' => [$this->taxonomy],
            // 'show_in_rest' => true,
            // // 'rest_base' => 'faqs',
            // 'rest_namespace' => 'wp/v2',      // Important pour l'API REST
            // 'rest_controller_class' => 'WP_REST_Posts_Controller',
            // 'template' => [],
            // 'template_lock' => false,
            // 'map_meta_cap' => true,           // Important pour les permissions
            // 'show_in_graphql' => true,        // Optionnel, pour le support GraphQL
            // 'delete_with_user' => false,      // Les FAQs ne sont pas supprimés avec l'utilisateur
        ];

        register_post_type($this->post_type, $args);

        // Register the capabilities explicitly
        // $this->register_post_type_capabilities();
    }

    /**
     * Register post type capabilities
     */
    private function register_post_type_capabilities(): void
    {
        // Get the admin role
        $admin = get_role('administrator');

        // Register capabilities for the post type
        $capabilities = [
            'edit_' . $this->post_type,
            'read_' . $this->post_type,
            'delete_' . $this->post_type,
            'edit_' . $this->post_type . 's',
            'edit_others_' . $this->post_type . 's',
            'publish_' . $this->post_type . 's',
            'read_private_' . $this->post_type . 's',
            'delete_' . $this->post_type . 's',
            'delete_private_' . $this->post_type . 's',
            'delete_published_' . $this->post_type . 's',
            'delete_others_' . $this->post_type . 's',
            'edit_private_' . $this->post_type . 's',
            'edit_published_' . $this->post_type . 's',
        ];

        // Add the capabilities to the administrator role
        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }
    }
    public function register_taxonomy(): void
    {
        register_taxonomy($this->taxonomy, $this->post_type, [
            'labels' => [
                'name' => __('FAQ Categories', 'cobra-ai'),
                'singular_name' => __('FAQ Category', 'cobra-ai'),
                'search_items' => __('Search FAQ Categories', 'cobra-ai'),
                'all_items' => __('All FAQ Categories', 'cobra-ai'),
                'parent_item' => __('Parent FAQ Category', 'cobra-ai'),
                'parent_item_colon' => __('Parent FAQ Category:', 'cobra-ai'),
                'edit_item' => __('Edit FAQ Category', 'cobra-ai'),
                'update_item' => __('Update FAQ Category', 'cobra-ai'),
                'add_new_item' => __('Add New FAQ Category', 'cobra-ai'),
                'new_item_name' => __('New FAQ Category Name', 'cobra-ai'),
                'menu_name' => __('Categories', 'cobra-ai'),
            ],
            'hierarchical' => true,
            'public' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'cobra-faq-categories',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'rewrite' => ['slug' => 'faq-category'],
            'show_in_nav_menus' => true,
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ],
            'show_in_quick_edit' => true,
        ]);
    }
    public function add_rewrite_rules(): void
    {
        add_rewrite_rule(
            '^faq/([^/]+)/?$',
            'index.php?cobra_faq=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^faq/category/([^/]+)/?$',
            'index.php?faq_category=$matches[1]',
            'top'
        );

        // Make sure WordPress knows about our query vars
        add_filter('query_vars', function ($query_vars) {
            $query_vars[] = 'cobra_faq';
            $query_vars[] = 'faq_category';
            return $query_vars;
        });
    }
    /**
     * Register REST API fields
     */
    public function register_rest_fields(): void
    {
        // Register FAQ endpoint
        register_rest_route('cobra-ai/v1', '/faq/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_faq'],
            'permission_callback' => function () {
                return true;
            },
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);

        // Register search endpoint
        register_rest_route('cobra-ai/v1', '/faq/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_faqs'],
            'permission_callback' => function () {
                return true;
            },
            'args' => [
                'query' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'category' => [
                    'type' => 'string',
                ],
            ],
        ]);

        
        // Register meta fields
        register_post_meta($this->post_type, 'cobra_faq_views', [
            'type' => 'integer',
            'description' => 'Number of views for this FAQ',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_post_meta($this->post_type, 'cobra_faq_helpful', [
            'type' => 'object',
            'description' => 'Helpful votes for this FAQ',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'yes' => ['type' => 'integer'],
                        'no' => ['type' => 'integer'],
                    ],
                ],
            ],
        ]);
    }
    
    protected function get_feature_default_options(): array
    {
        return [
            'display' => [
                'title' => __('Frequently Asked Questions', 'cobra-ai'),
                'items_per_page' => 10,
                'show_categories' => true,
                'order_by' => 'date', // date, title, views
                'order' => 'DESC',
                'footer_text' => __('Have more questions? Contact us', 'cobra-ai'),
                'footer_link' => home_url('/contact'),
                'load_more_type' => 'pagination', // pagination, button
                'show_views' => true,
                'show_helpful' => true,
                'show_share' => true,
                'show_last_updated' => true,
                'show_meta' => true,
                
              
              
            ],
            'search' => [
                'enable_search' => true,
                'enable_autocomplete' => true,
                'min_chars' => 3,
                'show_category_filter' => true,
            ],
            'styling' => [
                'theme' => 'default', // default, minimal, boxed
                'animation' => true,
                'highlight_search' => true,
            ]
        ];
    }

    public function render_faq_shortcode($atts): string
    {
        // Merge attributes with defaults
        $atts = shortcode_atts([
            // 'category' => '',
            'limit' => $this->get_settings('display.items_per_page', 10),
            'orderby' => $this->get_settings('display.order_by', 'date'),
            'order' => $this->get_settings('display.order', 'DESC'),
        ], $atts);

        // Get FAQs
        $faqs = $this->get_faqs($atts);

        // Start output buffering
        ob_start();

        // Include template
        include $this->path . 'templates/faq-display.php';

        return ob_get_clean();
    }

    private function get_faqs(array $args = []): array
    {
        $query_args = [
            'post_type' => $this->post_type,
            'posts_per_page' => $args['limit'] ?? 10,
            'orderby' => $args['orderby'] ?? 'date',
            'order' => $args['order'] ?? 'DESC',
            'post_status' => 'publish',
        ];

        if (!empty($args['category'])) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => $this->taxonomy,
                    'field' => 'slug',
                    'terms' => $args['category'],
                ]
            ];
        }

        // // If ordering by views
        // if ($args['orderby'] === 'views') {
        //     $query_args['meta_key'] = 'cobra_faq_views';
        //     $query_args['orderby'] = 'meta_value_num';
        // }
// print_r($query_args);
        $query = new \WP_Query($query_args);
        return $query->posts;
    }

    public function handle_faq_search(): void
    {
        check_ajax_referer('cobra_faq_search', 'nonce');

        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        $args = [
            'post_type' => $this->post_type,
            's' => $search,
            'posts_per_page' => 10,
            'post_status' => 'publish',
        ];

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $this->taxonomy,
                    'field' => 'slug',
                    'terms' => $category,
                ]
            ];
        }

        $query = new \WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 20),
                'url' => get_permalink($post->ID),
            ];
        }

        wp_send_json_success($results);
    }

    public function handle_load_more(): void
    {
        check_ajax_referer('cobra_faq_load_more', 'nonce');

        $page = intval($_POST['page'] ?? 1);
        $args = [
            'limit' => $this->get_settings('display.items_per_page', 10),
            'orderby' => $this->get_settings('display.order_by', 'date'),
            'order' => $this->get_settings('display.order', 'DESC'),
            'paged' => $page,
        ];

        $faqs = $this->get_faqs($args);
        $html = '';

        foreach ($faqs as $faq) {
            ob_start();
            include $this->path . 'templates/faq-item.php';
            $html .= ob_get_clean();
        }

        wp_send_json_success([
            'html' => $html,
            'hasMore' => count($faqs) === $args['limit'],
        ]);
    }

    public function handle_helpful(): void
    {
        check_ajax_referer('cobra_faq_helpful', 'nonce');

        $faq_id = intval($_POST['faq_id'] ?? 0);
        $helpful = $_POST['helpful'] === 'true';

        if (!$faq_id) {
            wp_send_json_error('Invalid FAQ ID');
        }

        $meta_key = $helpful ? 'helpful_yes' : 'helpful_no';
        $current = get_post_meta($faq_id, $meta_key, true) ?: 0;
        update_post_meta($faq_id, $meta_key, $current + 1);

        wp_send_json_success();
    }

    public function handle_increment_views(): void
    {
    

        check_ajax_referer('cobra_faq_views', 'nonce');

        $faq_id = intval($_POST['faq_id'] ?? 0);

        if (!$faq_id) {
            wp_send_json_error('Invalid FAQ ID');
        }

        $views = intval(get_post_meta($faq_id, 'cobra_faq_views', true) ?: 0 ) +1;
        update_post_meta($faq_id, 'cobra_faq_views', $views);

        global $wpdb;
        $table_name = $this->tables['faq_views']['name'];

        $wpdb->replace(
            $table_name,
            [
                'faq_id' => $faq_id,
                'views' => $views,
                'last_viewed' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );

        wp_send_json_success();
    }

    public function get_faq($request)
    {
        $post_id = $request['id'];
        $post = get_post($post_id);

        if (!$post || $post->post_type !== $this->post_type) {
            return new \WP_Error(
                'faq_not_found',
                __('FAQ not found', 'cobra-ai'),
                ['status' => 404]
            );
        }

        $categories = wp_get_post_terms($post->ID, $this->taxonomy);
        $category_data = [];
        foreach ($categories as $category) {
            $category_data[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            ];
        }

        $views = get_post_meta($post->ID, 'cobra_faq_views', true) ?: 0;
        $helpful = [
            'yes' => get_post_meta($post->ID, 'helpful_yes', true) ?: 0,
            'no' => get_post_meta($post->ID, 'helpful_no', true) ?: 0
        ];

        $response = [
            'id' => $post->ID,
            'date' => mysql_to_rfc3339($post->post_date),
            'date_gmt' => mysql_to_rfc3339($post->post_date_gmt),
            'modified' => mysql_to_rfc3339($post->post_modified),
            'modified_gmt' => mysql_to_rfc3339($post->post_modified_gmt),
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'link' => get_permalink($post->ID),
            'title' => [
                'rendered' => get_the_title($post->ID)
            ],
            'content' => [
                'rendered' => apply_filters('the_content', $post->post_content),
                'protected' => false
            ],
            'excerpt' => [
                'rendered' => get_the_excerpt($post->ID),
                'protected' => false
            ],
            'categories' => $category_data,
            'meta' => [
                'views' => $views,
                'helpful' => $helpful
            ]
        ];

        return rest_ensure_response($response);
    }

    public function search_faqs($request)
    {
        $query = sanitize_text_field($request['query']);
        $category = sanitize_text_field($request['category'] ?? '');

        $args = [
            'post_type' => $this->post_type,
            's' => $query,
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ];

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $this->taxonomy,
                    'field' => 'slug',
                    'terms' => $category
                ]
            ];
        }

        $query = new \WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $results[] = [
                'id' => $post->ID,
                'title' => [
                    'rendered' => $post->post_title
                ],
                'excerpt' => [
                    'rendered' => wp_trim_words($post->post_content, 20)
                ],
                'link' => get_permalink($post->ID)
            ];
        }

        return rest_ensure_response($results);
    }

    public function add_custom_columns($columns)
    {
        $date_column = $columns['date'];
        unset($columns['date']);

        $columns['views'] = __('Views', 'cobra-ai');
        $columns['helpful'] = __('Helpful', 'cobra-ai');
        $columns['date'] = $date_column;

        return $columns;
    }

    public function render_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'views':       
                $views = get_post_meta($post_id, 'cobra_faq_views', true) ?: 0;
                echo esc_html(number_format($views));
                break;

            case 'helpful':
                $helpful_yes = get_post_meta($post_id, 'helpful_yes', true) ?: 0;
                $helpful_no = get_post_meta($post_id, 'helpful_no', true) ?: 0;
                $total = $helpful_yes + $helpful_no;

                if ($total > 0) {
                    $percentage = round(($helpful_yes / $total) * 100);
                    echo esc_html(sprintf(
                        __('%d%% (%d/%d)', 'cobra-ai'),
                        $percentage,
                        $helpful_yes,
                        $total
                    ));
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function sortable_columns($columns)
    {
        $columns['views'] = 'views';
        return $columns;
    }

    public function enqueue_assets_param(): void
    {
    
        // Localize script
        wp_localize_script('cobra-ai-' . $this->feature_id, 'cobraFAQ', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cobra_faq_search'),
            'views_nonce' => wp_create_nonce('cobra_faq_views'),
            'helpful_nonce' => wp_create_nonce('cobra_faq_helpful'),
            'load_more_nonce' => wp_create_nonce('cobra_faq_load_more'),
            'settings' => [
                'min_chars' => $this->get_settings('search.min_chars', 3),
                'highlight_search' => $this->get_settings('styling.highlight_search', true),
                'animation' => $this->get_settings('styling.animation', true),
            ],
            'i18n' => [
                'searching' => __('Searching...', 'cobra-ai'),
                'no_results' => __('No results found', 'cobra-ai'),
                'load_more' => __('Load More', 'cobra-ai'),
                'loading' => __('Loading...', 'cobra-ai'),
                'thank_you' => __('Thank you for your feedback!', 'cobra-ai'),
                'copied' => __('Copied to clipboard!', 'cobra-ai'),
            ]
        ]);
    }

    public function enqueue_admin_assets($hook): void
    {
        $screen = get_current_screen();

        if ($screen->post_type !== $this->post_type) {
            return;
        }

        wp_enqueue_style(
            'cobra-faq-admin',
            $this->assets_url . 'css/admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'cobra-faq-admin',
            $this->assets_url . 'js/admin.js',
            ['jquery'],
            $this->version,
            true
        );
    }

    public function deactivate(): bool
    {
        try {
            // Unregister post type and taxonomy
            $this->unregister_post_type();

            // Clean up options
            delete_option('cobra_faq_post_type');
            delete_option('cobra_faq_taxonomy');

            // Clear menu cache
            delete_transient('cobra_ai_' . $this->feature_id . '_admin_menu');

            // Remove any scheduled events
            wp_clear_scheduled_hook('cobra_ai_' . $this->feature_id . '_cleanup');

            // Flush rewrite rules
            flush_rewrite_rules();

            // Run parent deactivation
            parent::deactivate();

            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', sprintf(
                'Failed to deactivate FAQ feature: %s',
                $e->getMessage()
            ));
            return false;
        }
    }
    /**
     * Unregister post type and taxonomy
     */
    public function unregister_post_type(): void
    {
        global $wp_post_types, $wp_taxonomies;

        // Unregister post type if exists
        if (isset($wp_post_types[$this->post_type])) {
            unset($wp_post_types[$this->post_type]);
        }

        // Unregister taxonomy if exists
        if (isset($wp_taxonomies[$this->taxonomy])) {
            unset($wp_taxonomies[$this->taxonomy]);
        }

        // Remove from options
        delete_option($this->post_type . '_capabilities');
        delete_option($this->taxonomy . '_capabilities');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
    public function cleanup_post_types(): void
    {
        // Get stored post type and taxonomy names
        $post_type = get_option('cobra_faq_post_type');
        $taxonomy = get_option('cobra_faq_taxonomy');

        if ($post_type) {
            unregister_post_type($post_type);
            delete_option('cobra_faq_post_type');
        }

        if ($taxonomy) {
            unregister_taxonomy($taxonomy);
            delete_option('cobra_faq_taxonomy');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
    public function check_feature_state(): void
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        if (!in_array($this->feature_id, $active_features)) {
            $this->unregister_post_type();
            $this->cleanup_post_types();
        }
    }

}
