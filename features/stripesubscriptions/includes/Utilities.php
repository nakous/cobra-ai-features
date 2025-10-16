<?php

namespace CobraAI\Features\StripeSubscriptions;

/**
 * Utility functions for StripeSubscriptions feature
 */
class Utilities
{
    /**
     * Format price for display
     */
    public static function format_price($amount, $currency = 'USD')
    {
        $formatted_amount = number_format($amount / 100, 2);
        return strtoupper($currency) . ' ' . $formatted_amount;
    }

    /**
     * Get subscription status label
     */
    public static function get_status_label($status)
    {
        $labels = [
            'active' => __('Active', 'cobra-ai'),
            'trialing' => __('Trial', 'cobra-ai'),
            'past_due' => __('Past Due', 'cobra-ai'),
            'canceled' => __('Cancelled', 'cobra-ai'),
            'incomplete' => __('Incomplete', 'cobra-ai'),
            'incomplete_expired' => __('Incomplete Expired', 'cobra-ai'),
            'unpaid' => __('Unpaid', 'cobra-ai'),
            'paused' => __('Paused', 'cobra-ai'),
        ];

        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    /**
     * Get subscription status color
     */
    public static function get_status_color($status)
    {
        $colors = [
            'active' => '#28a745',
            'trialing' => '#17a2b8',
            'past_due' => '#ffc107',
            'canceled' => '#dc3545',
            'incomplete' => '#6c757d',
            'incomplete_expired' => '#dc3545',
            'unpaid' => '#dc3545',
            'paused' => '#6c757d',
        ];

        return isset($colors[$status]) ? $colors[$status] : '#6c757d';
    }

    /**
     * Validate subscription data
     */
    public static function validate_subscription_data($data)
    {
        $required_fields = ['subscription_id', 'customer_id', 'plan_id', 'user_id', 'status'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new \WP_Error('missing_field', "Missing required field: {$field}");
            }
        }

        return true;
    }

    /**
     * Sanitize subscription data
     */
    public static function sanitize_subscription_data($data)
    {
        return [
            'subscription_id' => sanitize_text_field($data['subscription_id'] ?? ''),
            'customer_id' => sanitize_text_field($data['customer_id'] ?? ''),
            'plan_id' => absint($data['plan_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? ''),
            'current_period_start' => $data['current_period_start'] ?? null,
            'current_period_end' => $data['current_period_end'] ?? null,
            'cancel_at_period_end' => (bool)($data['cancel_at_period_end'] ?? false),
            'cancel_reason' => sanitize_textarea_field($data['cancel_reason'] ?? ''),
        ];
    }

    /**
     * Generate subscription actions based on status
     */
    public static function get_subscription_actions($subscription)
    {
        $actions = [];

        switch ($subscription->status) {
            case 'active':
            case 'trialing':
                $actions['cancel'] = [
                    'label' => __('Cancel Subscription', 'cobra-ai'),
                    'class' => 'cobra-btn-danger',
                    'icon' => 'times',
                ];
                $actions['update_payment'] = [
                    'label' => __('Update Payment Method', 'cobra-ai'),
                    'class' => 'cobra-btn-primary',
                    'icon' => 'credit-card',
                ];
                break;

            case 'canceled':
                if ($subscription->cancel_at_period_end) {
                    $actions['resume'] = [
                        'label' => __('Resume Subscription', 'cobra-ai'),
                        'class' => 'cobra-btn-success',
                        'icon' => 'play',
                    ];
                }
                $actions['update_payment'] = [
                    'label' => __('Update Payment Method', 'cobra-ai'),
                    'class' => 'cobra-btn-primary',
                    'icon' => 'credit-card',
                ];
                break;

            case 'past_due':
            case 'unpaid':
                $actions['update_payment'] = [
                    'label' => __('Update Payment Method', 'cobra-ai'),
                    'class' => 'cobra-btn-warning',
                    'icon' => 'exclamation-triangle',
                ];
                $actions['cancel'] = [
                    'label' => __('Cancel Subscription', 'cobra-ai'),
                    'class' => 'cobra-btn-danger',
                    'icon' => 'times',
                ];
                break;
        }

        return apply_filters('cobra_subscription_actions', $actions, $subscription);
    }

    /**
     * Get cancellation reason options
     */
    public static function get_cancellation_reasons()
    {
        return apply_filters('cobra_cancellation_reasons', [
            'too_expensive' => __('Too expensive', 'cobra-ai'),
            'not_using' => __('Not using the service', 'cobra-ai'),
            'found_alternative' => __('Found a better alternative', 'cobra-ai'),
            'technical_issues' => __('Technical issues', 'cobra-ai'),
            'poor_support' => __('Poor customer support', 'cobra-ai'),
            'missing_features' => __('Missing features', 'cobra-ai'),
            'billing_issues' => __('Billing issues', 'cobra-ai'),
            'temporary_pause' => __('Temporary pause', 'cobra-ai'),
            'other' => __('Other', 'cobra-ai'),
        ]);
    }

    /**
     * Check if user can manage subscription
     */
    public static function user_can_manage_subscription($user_id, $subscription)
    {
        // Admin can manage all subscriptions
        if (current_user_can('manage_options')) {
            return true;
        }

        // User can only manage their own subscriptions
        return $subscription->user_id === $user_id;
    }

    /**
     * Get trial end date
     */
    public static function get_trial_end_date($subscription)
    {
        if (empty($subscription->trial_end)) {
            return null;
        }

        return date_i18n(get_option('date_format'), strtotime($subscription->trial_end));
    }

    /**
     * Check if subscription is in trial
     */
    public static function is_trial_active($subscription)
    {
        return !empty($subscription->trial_end) && 
               strtotime($subscription->trial_end) > current_time('timestamp');
    }

    /**
     * Get subscription remaining days
     */
    public static function get_remaining_days($subscription)
    {
        $end_date = self::is_trial_active($subscription) 
            ? $subscription->trial_end 
            : $subscription->current_period_end;

        if (empty($end_date)) {
            return 0;
        }

        $remaining = strtotime($end_date) - current_time('timestamp');
        return max(0, ceil($remaining / DAY_IN_SECONDS));
    }

    /**
     * Send email notification
     */
    public static function send_email($template, $data)
    {
        $feature = cobra_ai()->get_feature('stripesubscriptions');
        if (!$feature) {
            return false;
        }

        $template_path = $feature->get_template_path("email/{$template}.php");
        if (!file_exists($template_path)) {
            return false;
        }

        // Extract data for template
        extract($data);

        // Capture template output
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        // Get email subject
        $subjects = [
            'subscription-created' => __('Welcome! Your subscription is active', 'cobra-ai'),
            'subscription-cancelled' => __('Subscription cancelled', 'cobra-ai'),
            'payment-failed' => __('Payment failed - Action required', 'cobra-ai'),
        ];

        $subject = isset($subjects[$template]) ? $subjects[$template] : __('Subscription notification', 'cobra-ai');

        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('admin_email'),
        ];

        return wp_mail($data['user']->user_email, $subject, $message, $headers);
    }

    /**
     * Log subscription activity
     */
    public static function log_activity($subscription_id, $activity, $details = [])
    {
        $feature = cobra_ai()->get_feature('stripesubscriptions');
        if (!$feature) {
            return;
        }

        $feature->log('info', "Subscription {$subscription_id}: {$activity}", $details);
    }
}