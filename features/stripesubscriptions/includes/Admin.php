<?php

namespace CobraAI\Features\StripeSubscriptions;

class Admin
{
    private $feature;
    private $menu_slug = 'cobra-stripe-subscriptions';
    private $capability = 'manage_options';


    private static $instance = null;

    public static function get_instance(Feature $feature): self
    {
        if (null === self::$instance) {
            self::$instance = new self($feature);
        }
        return self::$instance;
    }

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_cobra_subscription_sync_plans', [$this, 'handle_sync_plans']);
        add_action('wp_ajax_cobra_subscription_get_plan', [$this, 'handle_get_plan']);
        add_action('wp_ajax_cobra_subscription_cancel', [$this, 'handle_cancel_subscription']);
        add_action('wp_ajax_cobra_subscription_refund', [$this, 'handle_refund_payment']);
        add_action('wp_ajax_cobra_subscription_sync', [$this, 'handle_sync_subscriptions']);

        add_action('init', [$this, 'stripe_plan_custom_post_type'], 0);
        add_action('add_meta_boxes_stripe_plan', [$this, 'add_plan_meta_boxes']);
        add_action('save_post_stripe_plan', [$this, 'save_plan_meta']);
        add_filter('manage_stripe_plan_posts_columns', [$this, 'add_plan_columns']);
        add_action('manage_stripe_plan_posts_custom_column', [$this, 'render_plan_column'], 10, 2);
    }

    public function enqueue_assets($hook): void
    {
        if (strpos($hook, $this->menu_slug) === false) return;

        wp_enqueue_style('cobra-stripe-admin', $this->feature->get_url() . 'assets/css/admin.css', [], $this->feature->getVesrion());
        wp_enqueue_script('cobra-stripe-admin', $this->feature->get_url() . 'assets/js/admin.js', ['jquery'], $this->feature->getVesrion(), true);
    }

    public function add_menu_items(): void
    {
        add_menu_page(
            __('Dashboard Subscriptions', 'cobra-ai'),
            __('Subscriptions', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard'],
            'dashicons-money'
        );

        add_submenu_page(
            $this->menu_slug,
            __('Plans Subscriptions', 'cobra-ai'),
            __('Plans', 'cobra-ai'),
            $this->capability,
            $this->menu_slug . '&view=plans',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Payments Subscriptions', 'cobra-ai'),
            __('Payments', 'cobra-ai'),
            $this->capability,
            $this->menu_slug . '&view=payments',
            [$this, 'render_dashboard']
        );
    }

    public function render_dashboard(): void
    {
        $view = $_GET['view'] ?? 'dashboard';

        switch ($view) {
            case 'plans':
                $this->render_plans_page();
                break;
            case 'subscription':
                $this->render_subscription_details();
                break;
            case 'customer':
                // $this->render_customer_details();
                break;
            case 'payment':
                $this->render_payment_details();
                break;
            case 'payments':
                $this->render_payments_page();
                break;
            default:
                include $this->feature->get_path() . 'views/admin/subscriptions.php';
                break;
        }
    }
    /**
     * Render payments management page
     */
    private function render_payments_page(): void
    {
        // Get analytics data
        $period = $_GET['period'] ?? '30_days';
        $start_date = '';
        $end_date = date('Y-m-d');

        switch ($period) {
            case '7_days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30_days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90_days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'custom':
                $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $end_date = $_GET['end_date'] ?? date('Y-m-d');
                break;
        }

        // Get analytics using existing function
        $analytics = $this->feature->get_payments()->get_payment_analytics([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'subscription_id' => $_GET['subscription_id'] ?? null
        ]);

        // Get paginated payments
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;

        // Get payments directly from the table
        global $wpdb;
        $table = $this->feature->get_table('stripe_payments');

        // Count total items
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table['name']}");
        $total_pages = ceil($total_items / $per_page);

        // Get paginated items
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table['name']} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        // Include template
        include $this->feature->get_path() . 'views/admin/payments.php';
    }
    private function render_plans_page(): void
    {
        // Get plans with status counts
        $plans = $this->feature->get_plans()->get_plans();
        $status_counts = $this->feature->get_plans()->get_plan_status_counts();

        // Get current filters
        $current_status = sanitize_text_field($_GET['status'] ?? '');
        $search_query = sanitize_text_field($_GET['s'] ?? '');
        include $this->feature->get_path() . 'views/admin/plans.php';
    }
    /**
     * Render subscription details
     */
    private function render_subscription_details(): void
    {
        $subscription_id = sanitize_text_field($_GET['id'] ?? '');
        if (empty($subscription_id)) {
            wp_die(__('No subscription specified', 'cobra-ai'));
        }

        // Get subscription details
        $subscription = $this->feature->get_subscriptions()->get_subscription_by_id($subscription_id);
        if (!$subscription) {
            wp_die(__('Subscription not found', 'cobra-ai'));
        }

        // Get related data
        $customer = $this->feature->get_customers()->get_customer($subscription->customer_id);
        // $payments = $this->get_subscription_payments($subscription_id);
        // Prevent direct access


        $user = get_user_by('id', $subscription->user_id);
        $plan =  $this->feature->get_plans()->get_plan($subscription->plan_id);

        // Get payment history
        $payments = $this->feature->get_payments()->get_subscription_payments($subscription->id);

        // Get subscription analytics
        $analytics = $this->feature->get_payments()->get_payment_analytics([
            'subscription_id' => $subscription->id
        ]);
        // Include view
        include $this->feature->get_path() . 'views/admin/subscription-details.php';
    }
    private function render_payment_details(): void
    {
        $payment_id = sanitize_text_field($_GET['id'] ?? '');
        if (empty($payment_id)) {
            wp_die(__('No payment specified', 'cobra-ai'));
        }

        // Get payment details
        // $payment = $this->get_payment($payment_id);
        // if (!$payment) {
        //     wp_die(__('Payment not found', 'cobra-ai'));
        // }

        // // Get related data
        // $customer = $this->get_customer($payment->customer_id);
        // $subscription = $this->get_subscription($payment->subscription_id);

        // Include view
        include $this->feature->get_path() . 'views/admin/payment-details.php';
    }
    public function stripe_plan_custom_post_type(): void
    {
        register_post_type('stripe_plan', [
            'labels' => [
                'name' => __('Subscription Plans', 'cobra-ai'),
                'singular_name' => __('Subscription Plan', 'cobra-ai'),
                'menu_name' => __('Plans', 'cobra-ai'),
                'add_new' => __('Add New', 'cobra-ai'),
                'add_new_item' => __('Add New Plan', 'cobra-ai'),
                'edit_item' => __('Edit Plan', 'cobra-ai'),
                'view_item' => __('View Plan', 'cobra-ai'),
                'all_items' => __('All Plans', 'cobra-ai'),
                'search_items' => __('Search Plans', 'cobra-ai'),
                'not_found' => __('No plans found.', 'cobra-ai')
            ],
            'public' => true,
            'has_archive' => 'plans',
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => ['slug' => 'plans'],
            'show_in_menu' => true
        ]);
    }
    /**
     * Get customer details
     */
    private function get_customer(string $customer_id)
    {
        try {
            return \Stripe\Customer::retrieve([
                'id' => $customer_id,
                'expand' => ['default_source']
            ]);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to fetch customer', [
                'customer_id' => $customer_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    // ... Keep other necessary plan-related methods ...
    /**
     * Add plan meta boxes
     */
    public function add_plan_meta_boxes(): void
    {
        add_meta_box(
            'stripe_plan_details',
            __('Plan Details', 'cobra-ai'),
            [$this, 'render_plan_meta_box'],
            'stripe_plan',
            'normal',
            'high'
        );
    }


    public function render_plan_meta_box(\WP_Post $post): void
    {
        $defaults = [
            'price' => '',
            'currency' => 'USD',
            'billing_interval' => 'month',
            'interval_count' => 1,
            'trial_days' => 0,
            'features' => [],
            'status' => 'active',
            'public' => true
        ];
        // Get individual meta values
        $plan_data = [
            'price' => get_post_meta($post->ID, '_price_amount', true),
            'currency' => get_post_meta($post->ID, '_price_currency', true) ?: 'USD',
            'billing_interval' => get_post_meta($post->ID, '_billing_interval', true) ?: 'month',
            'interval_count' => get_post_meta($post->ID, '_interval_count', true) ?: 1,
            'trial_enabled' => get_post_meta($post->ID, '_trial_enabled', true),
            'trial_days' => get_post_meta($post->ID, '_trial_days', true) ?: 14,
            'public' => get_post_meta($post->ID, '_public', true) !== false,
            'features' => get_post_meta($post->ID, '_features', true),
            'stripe_price_id' => get_post_meta($post->ID, '_stripe_price_id', true),
            'stripe_product_id' => get_post_meta($post->ID, '_stripe_product_id', true),
            'status' => get_post_meta($post->ID, '_status', true) ?: 'active'
        ];

        // wp_nonce_field('stripe_plan_save', 'stripe_plan_nonce');
        include $this->feature->get_path() . 'views/admin/plan-meta-box.php';
    }
    /**
     * Save plan meta
     */
    public function save_plan_meta(int $post_id): void
    {
        if (!$this->verify_save_permissions($post_id) || !isset($_POST['stripe_plan'])) {
            return;
        }

        $data = $_POST['stripe_plan'];
        try {
            $stripe_product_id = get_post_meta($post_id, '_stripe_product_id', true);
            $stripe_price_id = get_post_meta($post_id, '_stripe_price_id', true);

            // Update or create Stripe product
            if ($stripe_product_id) {
                $product = \Stripe\Product::update($stripe_product_id, [
                    'name' => get_the_title($post_id),
                    'description' => get_the_content($post_id)
                ]);
            } else {
                $product = \Stripe\Product::create([
                    'name' => get_the_title($post_id),
                    'description' => get_the_content($post_id)
                ]);
                update_post_meta($post_id, '_stripe_product_id', $product->id);
            }

            // Create new price if needed
            $old_amount = get_post_meta($post_id, '_price_amount', true);
            $old_currency = get_post_meta($post_id, '_price_currency', true);
            $old_interval = get_post_meta($post_id, '_billing_interval', true);
            $old_interval_count = get_post_meta($post_id, '_interval_count', true);

            if (
                $old_amount != $data['price'] ||
                $old_currency != $data['currency'] ||
                $old_interval != $data['billing_interval'] ||
                $old_interval_count != $data['interval_count']
            ) {

                // Archive old price
                if ($stripe_price_id) {
                    \Stripe\Price::update($stripe_price_id, ['active' => false]);
                }

                // Create new price
                $price = \Stripe\Price::create([
                    'product' => $product->id,
                    'unit_amount' => $data['price'] * 100,
                    'currency' => $data['currency'],
                    'recurring' => [
                        'interval' => $data['billing_interval'],
                        'interval_count' => $data['interval_count']
                    ]
                ]);
                update_post_meta($post_id, '_stripe_price_id', $price->id);
            }

            // Update all meta fields
            update_post_meta($post_id, '_price_amount', $data['price']);
            update_post_meta($post_id, '_price_currency', $data['currency']);
            update_post_meta($post_id, '_billing_interval', $data['billing_interval']);
            update_post_meta($post_id, '_interval_count', $data['interval_count']);
            update_post_meta($post_id, '_trial_enabled', !empty($data['trial_enabled']));
            update_post_meta($post_id, '_trial_days', $data['trial_days'] ?? 14);
            update_post_meta($post_id, '_public', !empty($data['public']));
            update_post_meta($post_id, '_features', $data['features'] ?? []);


            // Handle featured image
            if (has_post_thumbnail($post_id)) {
                $image_url = wp_get_attachment_url(get_post_thumbnail_id($post_id));
                \Stripe\Product::update($product->id, ['images' => [$image_url]]);
            }
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to sync plan with Stripe', [
                'error' => $e->getMessage(),
                'plan_id' => $post_id
            ]);
        }
    }

    public function add_plan_columns($columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['price'] = __('Price', 'cobra-ai');
                $new_columns['interval'] = __('Billing Interval', 'cobra-ai');
                $new_columns['subscribers'] = __('Active Subscribers', 'cobra-ai');
            } else {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function render_plan_column(string $column, int $post_id): void
    {
        $plan_data = get_post_meta($post_id, '_stripe_plan_data', true) ?: [];

        switch ($column) {
            case 'price':
                $price = get_post_meta($post_id, '_price_amount', true) ?: 0;
                $currency = get_post_meta($post_id, '_price_currency', true) ?: 'USD';
                if (!empty($price)) {
                    echo esc_html(
                        $currency . ' ' .
                            number_format($price, 2)
                    );
                }
                break;

            case 'interval':
                $interval = get_post_meta($post_id, '_billing_interval', true) ?: '';
                $interval_count = get_post_meta($post_id, '_interval_count', true) ?: 1;
                if (!empty($interval)) {
                    printf(
                        __('Every %d %s', 'cobra-ai'),
                        $interval_count,
                        $interval
                    );
                }
                break;

            case 'subscribers':
                echo esc_html($this->get_plan_subscriber_count($post_id));
                break;
        }
    }

    public function handle_sync_plans(): void
    {
        try {
            check_ajax_referer('cobra_sync_plans');

            if (!current_user_can($this->capability)) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            $synced = $this->feature->get_plans()->sync_with_stripe();

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully synced %d plans', 'cobra-ai'),
                    count($synced)
                ),
                'plans' => $synced
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * handle get plan by id
     */
    public function handle_get_plan(): void
    {
        try {
            check_ajax_referer('cobra_get_plan');

            if (!current_user_can($this->capability)) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            $plan_id = sanitize_text_field($_POST['plan_id']);
            $plan =    $this->feature->get_plans()->get_plan($plan_id);


            wp_send_json_success(
                $plan ?? []
            );
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle subscription cancellation
     */
    public function handle_cancel_subscription(): void
    {
        try {
            check_ajax_referer('cobra_subscription_admin');

            if (!current_user_can($this->capability)) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            $stripe_subscription_id = sanitize_text_field($_POST['subscription_id']);
            $immediately = !empty($_POST['immediately']);

            // Get subscription by Stripe ID to get the DB ID
            $subscription = $this->feature->get_subscriptions()->get_subscription_by_stripe_id($stripe_subscription_id);
            
            if (!$subscription) {
                throw new \Exception(__('Subscription not found', 'cobra-ai'));
            }

            // Cancel using the DB ID
            $result = $this->feature->get_subscriptions()->cancel_subscription(
                $subscription->id,
                $immediately
            );

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle payment refund
     */
    public function handle_refund_payment(): void
    {
        try {
            check_ajax_referer('cobra_subscription_admin');

            if (!current_user_can($this->capability)) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            $payment_id = sanitize_text_field($_POST['payment_id']);
            $amount = filter_input(
                INPUT_POST,
                'amount',
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION
            );

            $result = $this->feature->get_payments()->process_refund(
                $payment_id,
                $amount
            );
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    private function get_plan_subscriber_count(int $post_id): int
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table['name']}
             WHERE plan_id = %d AND status = 'active'",
            $post_id
        ));
    }
    /**
     * Get subscription status counts
     */
    private function get_subscription_status_counts(): array
    {
        global $wpdb;
        $table = $this->feature->get_table('stripe_subscriptions');

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
         FROM {$table['name']}
         GROUP BY status"
        );

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->status] = (int) $row->count;
        }

        return $counts;
    }

    private function verify_save_permissions(int $post_id): bool
    {
        // Check if our nonce is set
        if (!isset($_POST['stripe_plan_nonce'])) {
            return false;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['stripe_plan_nonce'], 'stripe_plan_save')) {
            return false;
        }

        // If this is an autosave, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        return true;
    }


    /**
     * Get single subscription
     */
    // public function get_subscription(string $subscription_id)
    // {
    //     global $wpdb;

    //     $table = $this->feature->get_table('stripe_subscriptions');

    //     return $wpdb->get_row($wpdb->prepare(
    //         "SELECT * FROM {$table['name']} WHERE subscription_id = %s",
    //         $subscription_id
    //     ));
    // }


    /**
     * Handle sync subscriptions AJAX request
     */
    public function handle_sync_subscriptions(): void
    {
        try {
            check_ajax_referer('cobra-stripe-nonce', 'nonce');

            if (!current_user_can($this->capability)) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            // Get all Stripe subscriptions
            $stripe = $this->feature->get_stripe_feature()->get_api();
            $subscriptions = \Stripe\Subscription::all([
                'limit' => 100,
                'status' => 'all',
                'expand' => ['data.customer', 'data.latest_invoice']
            ]);

            $synced = 0;
            $errors = [];

            foreach ($subscriptions->data as $subscription) {
                try {
                    $this->sync_subscription($subscription);
                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log results
            $this->feature->log('info', 'Subscription sync completed', [
                'synced' => $synced,
                'errors' => count($errors),
                'details' => $errors
            ]);

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully synced %d subscriptions. %d errors occurred.', 'cobra-ai'),
                    $synced,
                    count($errors)
                ),
                'synced' => $synced,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Subscription sync failed', [
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    private function sync_subscription(\Stripe\Subscription $stripe_subscription): void
    {
        try {
            // Initialize required components
            $subscriptions = $this->feature->get_subscriptions();
            $payments =  $this->feature->get_payments();
            $api = $this->feature->get_api();

            // Get user ID from customer metadata or lookup
            $user_id = $this->get_user_id_for_customer($stripe_subscription->customer);
            if (!$user_id) {
                throw new \Exception("No WordPress user found for customer {$stripe_subscription->customer}");
            }

            // Format subscription data
            $subscription_data = [
                'subscription_id' => $stripe_subscription->id,
                'customer_id' => $stripe_subscription->customer,
                'plan_id' => $this->get_plan_id_from_price($stripe_subscription->items->data[0]->price->id),
                'user_id' => $user_id,
                'status' => $stripe_subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $stripe_subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $stripe_subscription->current_period_end),
                'cancel_at_period_end' => $stripe_subscription->cancel_at_period_end ? 1 : 0,
                'updated_at' => current_time('mysql')
            ];

            // Check if subscription exists
            $existing_subscription = $subscriptions->get_subscription_by_stripe_id($stripe_subscription->id);
            $existing_subscription_id = 0;
            if ($existing_subscription) {
                $subscriptions->update_subscription($existing_subscription->id, $subscription_data);
                $existing_subscription_id = $existing_subscription->id;
            } else {
                $subscription_data['created_at'] = current_time('mysql');
                $existing_subscription_id = $subscriptions->store_subscription($subscription_data);
            }

            // Sync payments
            // Get all invoices for stripe subscription
            $invoices =  $api->get_invoices($stripe_subscription->id);


            foreach ($invoices->data as $invoice) {
                if ($invoice->payment_intent) {
                    // Format payment data
                    $payment_data = [
                        'payment_id' => $invoice->payment_intent,
                        'subscription_id' =>  $existing_subscription_id,
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->amount_paid / 100,
                        'currency' => $invoice->currency,
                        'status' => $invoice->status === 'paid' ? 'succeeded' : $invoice->status
                    ];

                    // Check if payment exists
                    $existing_payment = $payments->get_payment_by_intent($invoice->payment_intent);

                    if ($existing_payment) {
                        // Update payment
                        $payments->update_payment(
                            $existing_payment->id,
                            $payment_data
                        );

                        // Trigger payment update event

                    } else {
                        // Store new payment
                        $payment_id = $payments->store_payment($payment_data);


                        // Handle refunds if any
                        if ($payment_id && $invoice->status === 'paid') {
                            $payment_intent =  $api->get_payment_intent($invoice->payment_intent);
                            if ($payment_intent->charges->data[0]->refunded) {
                                $payments->process_refund(
                                    $payment_id,
                                    $payment_intent->charges->data[0]->amount_refunded / 100
                                );
                            }
                        }
                    }
                }
            }

            // Handle any disputes
            foreach ($invoices->data as $invoice) {
                if ($invoice->payment_intent) {
                    $payment_intent =  $api->get_payment_intent($invoice->payment_intent);
                    if (!empty($payment_intent->charges->data[0]->dispute)) {
                        $dispute = $payment_intent->charges->data[0]->dispute;
                        $payments->handle_dispute_created([
                            'id' => $dispute->id,
                            'payment_intent' => $invoice->payment_intent,
                            'amount' => $dispute->amount,
                            'currency' => $dispute->currency,
                            'status' => $dispute->status,
                            'reason' => $dispute->reason
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to sync subscription', [
                'subscription_id' => $stripe_subscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get WordPress user ID from Stripe customer
     */
    private function get_user_id_for_customer($customer): ?int
    {
        // First try metadata
        if (!empty($customer->metadata['user_id'])) {
            return (int) $customer->metadata['user_id'];
        }

        // Then try email lookup
        if (!empty($customer->email)) {
            $user = get_user_by('email', $customer->email);
            if ($user) {
                return $user->ID;
            }
        }

        return null;
    }

    /**
     * Get WordPress plan ID from Stripe price ID
     */
    private function get_plan_id_from_price(string $price_id): ?int
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
         WHERE meta_key = '_stripe_price_id' 
         AND meta_value = %s 
         LIMIT 1",
            $price_id
        ));
    }
}
