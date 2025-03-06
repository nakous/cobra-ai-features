<?php

namespace CobraAI\Features\StripeSubscriptions;

use Stripe\Product;
use Stripe\Price;

class Plans
{
    private $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    public function get_plans(array $args = []): array
    {
        $defaults = [
            'post_type' => 'stripe_plan',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => []
        ];

        if (!empty($args['status'])) {
            $defaults['meta_query'][] = [
                'key' => '_status',
                'value' => $args['status']
            ];
        }
        if (!empty($args['public'])) {
            $defaults['meta_query'][] = [
                'key' => '_public',
                'value' => true
            ];
        }

        return get_posts(wp_parse_args($args, $defaults));
    }

    public function get_plan(int $plan_id)
    {
        $post = get_post($plan_id);
        if (!$post) return null;

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'price_id' => get_post_meta($post->ID, '_stripe_price_id', true),
            'product_id' => get_post_meta($post->ID, '_stripe_product_id', true),
            'amount' => get_post_meta($post->ID, '_price_amount', true),
            'currency' => get_post_meta($post->ID, '_price_currency', true),
            'billing_interval' => get_post_meta($post->ID, '_billing_interval', true),
            'interval_count' => get_post_meta($post->ID, '_interval_count', true),
            'trial_enabled' => get_post_meta($post->ID, '_trial_enabled', true),
            'trial_days' => get_post_meta($post->ID, '_trial_days', true),
            'status' => get_post_meta($post->ID, '_status', true),
            'public' => get_post_meta($post->ID, '_public', true)
        ];
    }

    public function create_plan(array $data): array
    {
        try {
            $this->validate_plan_data($data);

            // Create product & price in Stripe
            $stripe_data = $this->feature->get_api()->create_price($data);

            // Store in WordPress
            $post_id = wp_insert_post([
                'post_title' => $data['name'],
                'post_content' => $data['description'] ?? '',
                'post_type' => 'stripe_plan',
                'post_status' => 'publish',
                'meta_input' => [
                    '_stripe_price_id' => $stripe_data['price']->id,
                    '_stripe_product_id' => $stripe_data['product']->id,
                    '_price_amount' => $data['amount'],
                    '_price_currency' => $data['currency'],
                    '_billing_interval' => $data['interval'],
                    '_interval_count' => $data['interval_count'] ?? 1,
                    '_trial_enabled' => !empty($data['trial_enabled']),
                    '_trial_days' => $data['trial_days'] ?? 0,
                    '_status' => 'active',
                    '_public' => true
                ]
            ]);

            if (is_wp_error($post_id)) {
                throw new \Exception($post_id->get_error_message());
            }

            // Handle image
            if (!empty($data['image_id'])) {
                set_post_thumbnail($post_id, $data['image_id']);
                $image_url = wp_get_attachment_url($data['image_id']);
                Product::update($stripe_data['product']->id, [
                    'images' => [$image_url]
                ]);
            }

            return ['success' => true, 'plan_id' => $post_id];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function update_plan(int $plan_id, array $data): array
    {
        try {
            $existing_data = $this->get_plan($plan_id);
            if (!$existing_data) throw new \Exception('Plan not found');

            // Update product in Stripe
            Product::update($existing_data['product_id'], [
                'name' => $data['name'],
                'description' => $data['description'] ?? ''
            ]);

            // Create new price if pricing changed
            if ($this->price_details_changed($existing_data, $data)) {
                $price = Price::create([
                    'product' => $existing_data['product_id'],
                    'unit_amount' => $data['amount'] * 100,
                    'currency' => $data['currency'],
                    'recurring' => [
                        'interval' => $data['interval'],
                        'interval_count' => $data['interval_count'] ?? 1
                    ]
                ]);

                // Archive old price
                Price::update($existing_data['price_id'], [
                    'active' => false
                ]);

                // Update price ID
                update_post_meta($plan_id, '_stripe_price_id', $price->id);
            }

            // Update WordPress post
            wp_update_post([
                'ID' => $plan_id,
                'post_title' => $data['name'],
                'post_content' => $data['description'] ?? '',
                'meta_input' => [
                    '_price_amount' => $data['amount'],
                    '_price_currency' => $data['currency'],
                    '_billing_interval' => $data['interval'],
                    '_interval_count' => $data['interval_count'] ?? 1,
                    '_trial_enabled' => !empty($data['trial_enabled']),
                    '_trial_days' => $data['trial_days'] ?? 0,
                    '_public' => !empty($data['public'])
                ]
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function archive_plan(int $plan_id): bool
    {
        try {
            $plan = $this->get_plan($plan_id);
            if (!$plan) throw new \Exception('Plan not found');

            // Archive price in Stripe
            Price::update($plan['price_id'], [
                'active' => false
            ]);

            // Update status
            update_post_meta($plan_id, '_status', 'archived');

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function sync_with_stripe(): array
    {
        try {
            $prices = Price::all([
                'active' => true,
                'type' => 'recurring',
                'expand' => ['data.product']
            ]);

            $synced = [];
            foreach ($prices->data as $price) {
                if ($price->product->deleted) continue;

                $existing_plan = $this->get_plan_by_stripe_id($price->id);

                if ($existing_plan) {
                    $this->update_plan($existing_plan->ID, [
                        'name' => $price->product->name,
                        'description' => $price->product->description,
                        'amount' => $price->unit_amount / 100,
                        'currency' => $price->currency,
                        'interval' => $price->recurring->interval,
                        'interval_count' => $price->recurring->interval_count
                    ]);
                } else {
                    wp_insert_post([
                        'post_title' => $price->product->name,
                        'post_content' => $price->product->description,
                        'post_type' => 'stripe_plan',
                        'post_status' => 'publish',
                        'meta_input' => [
                            '_stripe_price_id' => $price->id,
                            '_stripe_product_id' => $price->product->id,
                            '_price_amount' => $price->unit_amount / 100,
                            '_price_currency' => $price->currency,
                            '_billing_interval' => $price->recurring->interval,
                            '_interval_count' => $price->recurring->interval_count,
                            '_trial_enabled' => false,
                            '_trial_days' => 0,
                            '_status' => 'active',
                            '_public' => true
                        ]
                    ]);
                }

                $synced[] = $price->id;
            }

            return $synced;
        } catch (\Exception $e) {
            $this->feature->log('error', 'Plan sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function get_plan_by_stripe_id(string $stripe_id)
    {
        $posts = get_posts([
            'post_type' => 'stripe_plan',
            'meta_key' => '_stripe_price_id',
            'meta_value' => $stripe_id,
            'posts_per_page' => 1
        ]);
        return !empty($posts) ? $posts[0] : null;
    }

    private function price_details_changed($old, $new): bool
    {
        return $old['amount'] != $new['amount'] ||
            $old['currency'] != $new['currency'] ||
            $old['billing_interval'] != $new['interval'] ||
            $old['interval_count'] != ($new['interval_count'] ?? 1);
    }

    private function validate_plan_data(array $data): void
    {
        $required = ['name', 'amount', 'currency', 'interval'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Invalid amount');
        }

        if (!in_array($data['interval'], ['day', 'week', 'month', 'year'])) {
            throw new \Exception('Invalid interval');
        }
    }

    
    /**
     * Get plan status counts
     */
    public function get_plan_status_counts(): array
    {
        global $wpdb;

        // Count both post status and plan's custom status field
        $results = $wpdb->get_results("
        SELECT pm.meta_value as status, COUNT(*) as count 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type = 'stripe_plan' 
        AND pm.meta_key = '_status'
        GROUP BY pm.meta_value
    ");

        // Initialize counts array with defaults
        $counts = [
            'active' => 0,
            'inactive' => 0,
            'archived' => 0
        ];

        // Update counts from results
        foreach ($results as $row) {
            if ($row->status) {
                $counts[$row->status] = (int)$row->count;
            }
        }

        // Also count draft and trash posts
        $post_status_counts = wp_count_posts('stripe_plan');

        // Add draft and trash counts if you want to track them
        $counts['draft'] = (int)$post_status_counts->draft;
        $counts['trash'] = (int)$post_status_counts->trash;

        return $counts;
    }
}
