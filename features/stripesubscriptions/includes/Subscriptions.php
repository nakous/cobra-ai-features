<?php

namespace CobraAI\Features\StripeSubscriptions;

class Subscriptions
{

    private $feature;

    private $table_name;

    private $total_items = 0;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->table_name = $feature->get_table('stripe_subscriptions')['name']  ?? '';
    }

    public function store_subscription($subscription_data)
    {
        global $wpdb;

        // Get table name
        $table = $this->feature->get_table('stripe_subscriptions');

        // Insert subscription data
        $result = $wpdb->insert(
            $table['name'],
            $subscription_data
        );

        if ($result === false) {
            $this->feature->log('error', 'Error storing subscription', [
                'subscription_data' => $subscription_data,
                'error' => $wpdb->last_error
            ]);
        }else{
            do_action('cobra_ai_subscription_created', $subscription_data['subscription_id'], $subscription_data);
        }

        return $wpdb->insert_id;
    }

    // update by stripe subscription id
    public function update_subscription($subscription_id, $data)
    {
        global $wpdb;

        // Get table name
        $table = $this->feature->get_table('stripe_subscriptions');

        // Update subscription data
        $result = $wpdb->update(
            $table['name'],
            $data,
            ['subscription_id' => $subscription_id]
        );

        if ($result === false) {
            $this->feature->log('error', 'Error updating subscription', [
                'subscription_id' => $subscription_id,
                'data' => $data,
                'error' => $wpdb->last_error
            ]);
        }else{
            do_action('cobra_ai_subscription_updated', $subscription_id, $data);
        }

        return $result !== false;
    }


    public function get_subscription_by_id(int $id): ?object
    {
        global $wpdb;

        try {
            $this->table_name = $this->feature->get_table('stripe_subscriptions')['name']  ;
            // Get subscription data
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ));

            if (!$subscription) {
                return null;
            }
            return $subscription;
        } catch (\Exception $e) {
            return null;
        }
    }
    public function get_subscription_by_stripe_id(string $subscription_id): ?object
    {
        global $wpdb;

        try {
            // Get table name for subscriptions
            $this->table_name = $this->feature->get_table('stripe_subscriptions')['name']  ;

            // Get subscription data
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE subscription_id = %s",
                $subscription_id
            ));

            if (!$subscription) {
                return null;
            }
            return $subscription;
        } catch (\Exception $e) {
            return null;
        }
    }

      /**
     * Get subscriber count
     */
    public function get_subscriber_count(int $plan_id, string $status = 'active'): int
    {
        global $wpdb;

        // $table = $this->feature->get_table('stripe_subscriptions');
        $this->table_name = $this->feature->get_table('stripe_subscriptions')['name']  ;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE plan_id = %d AND status = %s",
            $plan_id,
            $status
        ));
    }
    /**
     * Get all subscriptions for a user
     */
    public function get_user_subscriptions(int $user_id): array
    {
        global $wpdb;

        $this->table_name = $this->feature->get_table('stripe_subscriptions')['name']  ;
        // Get all subscriptions for user
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
         WHERE user_id = %d 
         ORDER BY created_at DESC",
            $user_id
        ));
    }

    /**
     * Get first active subscription for a user
     */
    public function get_user_subscription(int $user_id): ?object
    {
        global $wpdb;


        $this->table_name = $this->feature->get_table('stripe_subscriptions')['name']  ;
        // Get active subscription for user
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
         WHERE user_id = %d 
         AND status IN ('active', 'trialing') 
         ORDER BY created_at DESC 
         LIMIT 1",
            $user_id
        ));

        return $subscription ?: null;
    }
    /**
     * Check if user has active subscription
     */
    public function has_active_subscription(int $user_id): bool
    {
        global $wpdb;

        // Get table name
        $table = $this->table_name;

        // Check for active subscription
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
         FROM {$$this->table_name} 
         WHERE user_id = %d 
         AND status IN ('active', 'trialing')",
            $user_id
        ));

        return (int)$count > 0;
    }
    /**
     * Create subscription for a user
     * @param int $id    Subscription ID
     * @param  bool $immediately  bool
     */
    public function cancel_subscription($id, bool $immediately): bool
    {
        global $wpdb;


        // // Get active subscription for user
        $subscription = $this->get_subscription_by_id($id);

        // Check if subscription exists
        if (!$subscription) {
            return false;
        }
        $result = $this->feature->get_api()->cancel_subscription(
            $subscription->subscription_id,
            $immediately
        );


        // // Cancel subscription
        $result = $wpdb->update(
            $this->table_name,
            ['status' => 'canceled'],
            ['id' => $subscription->id]
        );

        return $result !== false;
    }


    /**
     * Get subscription by ID with plan details
     */
    public function get_full_subscription_by_id(string $subscription_id): ?object
    {
        global $wpdb;

        try {
          

            // Get subscription data
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE subscription_id = %s",
                $subscription_id
            ));

            if (!$subscription) {
                return null;
            }

            // Get plan details from post and post meta
            $plan_post = get_post($subscription->plan_id);
            if (!$plan_post) {
                return null;
            }

            // Enhance subscription object with plan details
            $subscription->plan_name = $plan_post->post_title;
            $subscription->plan_description = $plan_post->post_content;
            $subscription->amount = (float) get_post_meta($subscription->plan_id, '_price_amount', true);
            $subscription->currency = get_post_meta($subscription->plan_id, '_price_currency', true);
            $subscription->billing_interval = get_post_meta($subscription->plan_id, '_billing_interval', true);
            $subscription->interval_count = (int) get_post_meta($subscription->plan_id, '_interval_count', true);
            $subscription->trial_enabled = (bool) get_post_meta($subscription->plan_id, '_trial_enabled', true);
            $subscription->trial_days = (int) get_post_meta($subscription->plan_id, '_trial_days', true);
            $subscription->features = get_post_meta($subscription->plan_id, '_features', true) ?: [];
            $subscription->stripe_price_id = get_post_meta($subscription->plan_id, '_stripe_price_id', true);
            $subscription->stripe_product_id = get_post_meta($subscription->plan_id, '_stripe_product_id', true);

            // Add formatted price
            $subscription->formatted_amount = $this->feature->format_price($subscription->amount, $subscription->currency);

            // Add formatted interval
            $subscription->formatted_interval = $subscription->interval_count > 1
                ? sprintf(
                    __('every %d %ss', 'cobra-ai'),
                    $subscription->interval_count,
                    $subscription->billing_interval
                )
                : sprintf(
                    __('per %s', 'cobra-ai'),
                    $subscription->billing_interval
                );

            // Format dates
            if (!empty($subscription->current_period_start)) {
                $subscription->formatted_period_start = date_i18n(
                    get_option('date_format'),
                    strtotime($subscription->current_period_start)
                );
            }

            if (!empty($subscription->current_period_end)) {
                $subscription->formatted_period_end = date_i18n(
                    get_option('date_format'),
                    strtotime($subscription->current_period_end)
                );
            }

            // Add formatted status
            $subscription->formatted_status = ucfirst($subscription->status);
            $subscription->status_class = sanitize_html_class('status-' . $subscription->status);

            return $subscription;
        } catch (\Exception $e) {
            $this->feature->log('error', 'Error getting subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription_id
            ]);
            return null;
        }
    }

 
    /**
     * Get subscriptions with pagination
     */
    public function get_subscriptions(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        $table = $this->feature->get_table('stripe_subscriptions');

        // Build query
        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $params[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where[] = "(subscription_id LIKE %s OR customer_id LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        // Build WHERE clause
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get total items for pagination
        if (empty($params)) {
            $this->total_items = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table['name']}"
            );
        } else {
            $this->total_items = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table['name']} $where_clause",
                $params
            ));
        }

        // Get paginated results
        if (empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table['name']} 
             ORDER BY %s %s 
             LIMIT %d OFFSET %d",
                $args['orderby'],
                $args['order'],
                $args['per_page'],
                $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table['name']} $where_clause 
             ORDER BY %s %s 
             LIMIT %d OFFSET %d",
                array_merge(
                    $params,
                    [
                        $args['orderby'],
                        $args['order'],
                        $args['per_page'],
                        $offset
                    ]
                )
            );
        }

        return $wpdb->get_results($query);
    }
        /**
     * Get pagination arguments
     */
    public function get_pagination_args(): array
    {
        $per_page = $this->get_items_per_page('subscriptions_per_page', 20);
        $total_pages = ceil($this->total_items / $per_page);

        return [
            'total_items' => $this->total_items,
            'per_page' => $per_page,
            'total_pages' => $total_pages
        ];
    }
        /**
     * Get subscription by ID
     */
    
     public function get_items_per_page(string $option, int $default = 20): int
     {
         $per_page = (int) get_user_option($option);
         if (empty($per_page) || $per_page < 1) {
             $per_page = $default;
         }
         return $per_page;
     }

       /**
     * Get current page number
     */
    public function get_pagenum(): int
    {
        $pagenum = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 0;
        return max(1, $pagenum);
    }
}
