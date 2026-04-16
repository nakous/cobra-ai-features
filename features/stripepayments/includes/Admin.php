<?php

namespace CobraAI\Features\StripePayments;

class Admin
{
    private Feature $feature;
    private string $menu_slug = 'cobra-stripe-payments';
    private string $capability = 'manage_options';

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_menu_items']);
            add_action('add_meta_boxes_stripe_product', [$this, 'add_meta_boxes']);
            add_action('save_post_stripe_product', [$this, 'save_meta']);
            add_filter('manage_stripe_product_posts_columns', [$this, 'filter_columns']);
            add_action('manage_stripe_product_posts_custom_column', [$this, 'render_column'], 10, 2);
        }
    }

    public function register_post_type(): void
    {
        register_post_type('stripe_product', [
            'labels' => [
                'name'          => __('Stripe Products', 'cobra-ai'),
                'singular_name' => __('Stripe Product', 'cobra-ai'),
                'add_new'       => __('Add New', 'cobra-ai'),
                'add_new_item'  => __('Add New Product', 'cobra-ai'),
                'edit_item'     => __('Edit Product', 'cobra-ai'),
                'new_item'      => __('New Product', 'cobra-ai'),
                'view_item'     => __('View Product', 'cobra-ai'),
                'search_items'  => __('Search', 'cobra-ai'),
                'menu_name'     => __('Stripe Products', 'cobra-ai'),
            ],
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive'         => false,
            'rewrite'             => ['slug' => 'stripe-product'],
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-cart',
            'show_in_rest'        => true,
        ]);
    }

    public function add_menu_items(): void
    {
        add_menu_page(
            __('Stripe Payments', 'cobra-ai'),
            __('Stripe Payments', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_orders_page'],
            'dashicons-cart',
            32
        );

        add_submenu_page(
            $this->menu_slug,
            __('Orders', 'cobra-ai'),
            __('Orders', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_orders_page']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Products', 'cobra-ai'),
            __('Products', 'cobra-ai'),
            $this->capability,
            'edit.php?post_type=stripe_product'
        );
    }

    public function render_orders_page(): void
    {
        if (!current_user_can($this->capability)) {
            return;
        }

        $status = sanitize_key($_GET['status'] ?? '');
        $page   = max(1, (int) ($_GET['paged'] ?? 1));

        $result = $this->feature->get_orders()->list([
            'status' => $status,
            'page'   => $page,
            'per_page' => 20,
        ]);

        $feature = $this->feature;
        include $this->feature->get_path() . 'views/admin/orders.php';
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'stripe_product_details',
            __('Stripe Product Details', 'cobra-ai'),
            [$this, 'render_meta_box'],
            'stripe_product',
            'normal',
            'high'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('stripe_product_meta', 'stripe_product_meta_nonce');

        $amount        = get_post_meta($post->ID, '_price_amount', true);
        $currency      = get_post_meta($post->ID, '_price_currency', true) ?: ($this->feature->get_settings('currency_default') ?: 'USD');
        $product_type  = get_post_meta($post->ID, '_product_type', true) ?: 'standard';
        $stripe_price  = get_post_meta($post->ID, '_stripe_price_id', true);
        $product_types = $this->feature->get_product_types();

        $feature = $this->feature;
        include $this->feature->get_path() . 'views/admin/meta-box.php';
    }

    public function save_meta(int $post_id): void
    {
        if (!isset($_POST['stripe_product_meta_nonce']) || !wp_verify_nonce($_POST['stripe_product_meta_nonce'], 'stripe_product_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $amount = isset($_POST['_price_amount']) ? (float) $_POST['_price_amount'] : 0;
        $currency = strtoupper(substr(sanitize_text_field($_POST['_price_currency'] ?? 'USD'), 0, 3));
        $product_type = sanitize_key($_POST['_product_type'] ?? 'standard');
        $stripe_price = sanitize_text_field($_POST['_stripe_price_id'] ?? '');

        update_post_meta($post_id, '_price_amount', $amount);
        update_post_meta($post_id, '_price_currency', $currency);
        update_post_meta($post_id, '_product_type', $product_type);
        update_post_meta($post_id, '_stripe_price_id', $stripe_price);

        // Allow extensions (credits, etc.) to persist their extra fields.
        do_action('cobra_stripepayments_save_product_meta', $post_id, $_POST);
    }

    public function filter_columns(array $cols): array
    {
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['price'] = __('Price', 'cobra-ai');
                $new['type']  = __('Type', 'cobra-ai');
            }
        }
        return $new;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ($column === 'price') {
            $amount = get_post_meta($post_id, '_price_amount', true);
            $currency = get_post_meta($post_id, '_price_currency', true) ?: 'USD';
            echo esc_html(number_format((float) $amount, 2) . ' ' . $currency);
        } elseif ($column === 'type') {
            $type = get_post_meta($post_id, '_product_type', true) ?: 'standard';
            $types = $this->feature->get_product_types();
            echo esc_html($types[$type] ?? $type);
        }
    }
}
