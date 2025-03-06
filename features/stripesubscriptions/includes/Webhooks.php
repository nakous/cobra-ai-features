<?php

namespace CobraAI\Features\StripeSubscriptions;

use Stripe\Event;
use Stripe\Webhook;
use Stripe\Subscription;
use Stripe\Invoice;
use Stripe\Exception\SignatureVerificationException;

class Webhooks
{
    private $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Handle webhook request
     */
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $settings = $this->feature->get_settings();
            $webhook_secret = $settings['webhook_secret'];

            if (empty($webhook_secret)) {
                throw new \Exception('Webhook secret not configured');
            }

            // Get payload and signature
            $payload = $request->get_body();
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

            // Verify signature
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $webhook_secret
            );

            // Log event
            $this->log_event($event);

            // Process event
            $this->process_event($event);

            return new \WP_REST_Response(['success' => true], 200);
        } catch (SignatureVerificationException $e) {
            return new \WP_REST_Response([
                'error' => 'Invalid signature',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            $this->log_error('Webhook processing failed', [
                'error' => $e->getMessage()
            ]);

            return new \WP_REST_Response([
                'error' => 'Processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process webhook event
     */
    private function process_event(Event $event): void
    {
        switch ($event->type) {
                // Subscription events
            case 'customer.subscription.created':
                $this->handle_subscription_created($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event->data->object);
                break;

            case 'customer.subscription.trial_will_end':
                $this->handle_trial_ending($event->data->object);
                break;

                // Payment events
            case 'invoice.payment_succeeded':
                $this->handle_invoice_paid($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handle_invoice_failed($event->data->object);
                break;

            case 'invoice.upcoming':
                $this->handle_upcoming_invoice($event->data->object);
                break;

                // Refund and dispute events
            case 'charge.refunded':
                $this->feature->get_payments()->handle_payment_event('charge.refunded', $event->data->object);
                break;

            case 'charge.dispute.created':
                $this->feature->get_payments()->handle_payment_event('charge.dispute.created', $event->data->object);
                break;

            case 'charge.dispute.updated':
                $this->handle_dispute_updated($event->data->object);
                break;

            case 'charge.dispute.closed':
                $this->handle_dispute_closed($event->data->object);
                break;
        }

        // Allow additional processing
        do_action('cobra_ai_stripe_webhook_' . $event->type, $event);
    }

    /**
     * Handle subscription created
     */
    public function handle_subscription_created(Subscription $subscription): void
    {
        try {
            // Get user ID from metadata
            $user_id = $this->get_user_id_from_customer($subscription->customer);
            if (!$user_id) {
                throw new \Exception('User not found for customer: ' . $subscription->customer);
            }

            // Store subscription
            $subscription_id = $this->store_subscription([
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer,
                'plan_id' => $subscription->plan->id,
                'user_id' => $user_id,
                'status' => $subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end
            ]);

            // Trigger action
            do_action('cobra_ai_subscription_created', $subscription_id, $subscription);

            // Send confirmation email
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_subscription_notification('created', $subscription_id);
            }
        } catch (\Exception $e) {
            $this->log_error('Failed to handle subscription creation', [
                'error' => $e->getMessage(),
                'subscription' => $subscription->id
            ]);
        }
    }

    /**
     * Handle subscription updated
     */
    public function handle_subscription_updated(Subscription $subscription): void
    {
        try {
            // Get local subscription
            $local_subscription = $this->get_subscription_by_id($subscription->id);
            if (!$local_subscription) {
                throw new \Exception('Local subscription not found: ' . $subscription->id);
            }

            // Update subscription
            $this->update_subscription([
                'id' => $local_subscription->id,
                'status' => $subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end
            ]);

            // Check for significant changes
            $this->check_subscription_changes(
                $local_subscription,
                $subscription
            );

            // Trigger action
            do_action('cobra_ai_subscription_updated', $local_subscription->id, $subscription);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle subscription update', [
                'error' => $e->getMessage(),
                'subscription' => $subscription->id
            ]);
        }
    }

    /**
     * Handle subscription deleted
     */
    public function handle_subscription_deleted(Subscription $subscription): void
    {
        try {
            // Get local subscription
            $local_subscription = $this->get_subscription_by_id($subscription->id);
            if (!$local_subscription) {
                throw new \Exception('Local subscription not found: ' . $subscription->id);
            }

            // Update subscription status
            $this->update_subscription([
                'id' => $local_subscription->id,
                'status' => 'canceled',
                'cancel_at_period_end' => false
            ]);

            // Trigger action
            do_action('cobra_ai_subscription_deleted', $local_subscription->id, $subscription);

            // Send notification
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_subscription_notification('canceled', $local_subscription->id);
            }
        } catch (\Exception $e) {
            $this->log_error('Failed to handle subscription deletion', [
                'error' => $e->getMessage(),
                'subscription' => $subscription->id
            ]);
        }
    }

    /**
     * Handle trial ending
     */
    public function handle_trial_ending(Subscription $subscription): void
    {
        try {
            // Get local subscription
            $local_subscription = $this->get_subscription_by_id($subscription->id);
            if (!$local_subscription) {
                throw new \Exception('Local subscription not found: ' . $subscription->id);
            }

            // Send notification
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_subscription_notification('trial_ending', $local_subscription->id);
            }

            // Trigger action
            do_action('cobra_ai_subscription_trial_ending', $local_subscription->id, $subscription);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle trial ending', [
                'error' => $e->getMessage(),
                'subscription' => $subscription->id
            ]);
        }
    }

    /**
     * Handle invoice paid
     */
    public function handle_invoice_paid(Invoice $invoice): void
    {
        try {
            if (!$invoice->subscription) {
                return; // Not a subscription invoice
            }

            // Process payment
            $this->feature->get_payments()->process_payment($invoice->payment_intent);

            // Update subscription if needed
            if ($invoice->billing_reason === 'subscription_create') {
                $this->activate_subscription($invoice->subscription);
            }

            // Send receipt
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_invoice_notification('paid', $invoice->id);
            }
        } catch (\Exception $e) {
            $this->log_error('Failed to handle invoice payment', [
                'error' => $e->getMessage(),
                'invoice' => $invoice->id
            ]);
        }
    }

    /**
     * Handle invoice failed
     */
    public function handle_invoice_failed(Invoice $invoice): void
    {
        try {
            if (!$invoice->subscription) {
                return; // Not a subscription invoice
            }

            // Update subscription status
            $this->update_subscription_status(
                $invoice->subscription,
                'past_due'
            );

            // Send notification
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_invoice_notification('failed', $invoice->id);
            }

            // Trigger action
            do_action('cobra_ai_invoice_failed', $invoice);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle invoice failure', [
                'error' => $e->getMessage(),
                'invoice' => $invoice->id
            ]);
        }
    }

    /**
     * Handle upcoming invoice
     */
    private function handle_upcoming_invoice(Invoice $invoice): void
    {
        try {
            if (!$invoice->subscription) {
                return; // Not a subscription invoice
            }

            // Send notification
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_invoice_notification('upcoming', $invoice->id);
            }

            // Trigger action
            do_action('cobra_ai_invoice_upcoming', $invoice);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle upcoming invoice', [
                'error' => $e->getMessage(),
                'invoice' => $invoice->id
            ]);
        }
    }

    /**
     * Handle dispute updated
     */
    private function handle_dispute_updated($dispute): void
    {
        try {
            // Update dispute status
            $this->update_dispute_status(
                $dispute->id,
                $dispute->status
            );

            // Trigger action
            do_action('cobra_ai_dispute_updated', $dispute);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle dispute update', [
                'error' => $e->getMessage(),
                'dispute' => $dispute->id
            ]);
        }
    }

    /**
     * Handle dispute closed
     */
    private function handle_dispute_closed($dispute): void
    {
        try {
            // Update dispute status
            $this->update_dispute_status(
                $dispute->id,
                $dispute->status
            );

            // If dispute was lost, mark subscription as disputed
            if ($dispute->status === 'lost') {
                $payment = $this->get_payment_by_charge($dispute->charge);
                if ($payment && $payment->subscription_id) {
                    $this->update_subscription_status(
                        $payment->subscription_id,
                        'disputed'
                    );
                }
            }

            // Trigger action
            do_action('cobra_ai_dispute_closed', $dispute);
        } catch (\Exception $e) {
            $this->log_error('Failed to handle dispute closure', [
                'error' => $e->getMessage(),
                'dispute' => $dispute->id
            ]);
        }
    }

    /**
     * Store webhook event
     */
    private function log_event(Event $event): void
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_logs');

        $wpdb->insert(
            $table,
            [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'data' => json_encode($event->data),
                'created_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Get user ID from customer
     */
    private function get_user_id_from_customer(string $customer_id): ?int
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} 
             WHERE customer_id = %s 
             ORDER BY created_at DESC 
             LIMIT 1",
            $customer_id
        ));
    }

    /**
     * Store subscription
     */
    private function store_subscription(array $data): int
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Update subscription
     */
    private function update_subscription(array $data): bool
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        return $wpdb->update(
            $table,
            $data,
            ['id' => $data['id']]
        ) !== false;
    }

    /**
     * Get subscription by Stripe ID
     */
    private function get_subscription_by_id(string $subscription_id)
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE subscription_id = %s",
            $subscription_id
        ));
    }

    /**
     * Update subscription status
     */
    private function update_subscription_status(string $subscription_id, string $status): bool
    {
        $subscription = $this->get_subscription_by_id($subscription_id);
        if (!$subscription) {
            return false;
        }

        return $this->update_subscription([
            'id' => $subscription->id,
            'status' => $status
        ]);
    }

    /**
     * Send notification
     */
    private function send_subscription_notification(string $type, int $subscription_id): void
    {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return;
        }

        $user = get_user_by('id', $subscription->user_id);
        if (!$user) {
            return;
        }

        $template = $this->feature->get_path() . 'templates/email/subscription-' . $type . '.php';
        if (!file_exists($template)) {
            return;
        }

        $to = $user->user_email;
        $subject = $this->get_notification_subject($type);

        ob_start();
        include $template;
        $message = ob_get_clean();

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send invoice notification
     */
    private function send_invoice_notification(string $type, string $invoice_id): void
    {
        $invoice = Invoice::retrieve($invoice_id);
        if (!$invoice->customer) {
            return;
        }

        $user_id = $this->get_user_id_from_customer($invoice->customer);
        if (!$user_id) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $template = $this->feature->get_path() . 'templates/email/invoice-' . $type . '.php';
        if (!file_exists($template)) {
            return;
        }

        $to = $user->user_email;
        $subject = $this->get_notification_subject($type);

        ob_start();
        include $template;
        $message = ob_get_clean();

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get notification subject
     */
    private function get_notification_subject(string $type): string
    {
        $subjects = [
            'created' => __('Your subscription has been created', 'cobra-ai'),
            'updated' => __('Your subscription has been updated', 'cobra-ai'),
            'canceled' => __('Your subscription has been canceled', 'cobra-ai'),
            'trial_ending' => __('Your trial period is ending soon', 'cobra-ai'),
            'paid' => __('Payment receipt for your subscription', 'cobra-ai'),
            'failed' => __('Payment failed for your subscription', 'cobra-ai'),
            'upcoming' => __('Upcoming charge for your subscription', 'cobra-ai')
        ];

        return $subjects[$type] ?? __('Subscription notification', 'cobra-ai');
    }

    /**
     * Get subscription
     */
    private function get_subscription(int $subscription_id)
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_subscriptions');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $subscription_id
        ));
    }

    /**
     * Get payment by charge ID
     */
    private function get_payment_by_charge(string $charge_id)
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE charge_id = %s",
            $charge_id
        ));
    }

    /**
     * Update dispute status
     */
    private function update_dispute_status(string $dispute_id, string $status): bool
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_disputes');

        return $wpdb->update(
            $table,
            ['status' => $status],
            ['dispute_id' => $dispute_id]
        ) !== false;
    }

    /**
     * Activate subscription
     */
    private function activate_subscription(string $subscription_id): void
    {
        $subscription = $this->get_subscription_by_id($subscription_id);
        if (!$subscription) {
            return;
        }

        // Only activate if current status is 'incomplete'
        if ($subscription->status !== 'incomplete') {
            return;
        }

        $this->update_subscription([
            'id' => $subscription->id,
            'status' => 'active'
        ]);

        // Trigger activation action
        do_action('cobra_ai_subscription_activated', $subscription->id);
    }

    /**
     * Check subscription changes
     */
    private function check_subscription_changes($local, $stripe): void
    {
        // Check status change
        if ($local->status !== $stripe->status) {
            do_action(
                'cobra_ai_subscription_status_changed',
                $local->id,
                $local->status,
                $stripe->status
            );

            // Send notification if needed
            if ($this->feature->get_settings('email_notifications')) {
                if ($stripe->status === 'past_due') {
                    $this->send_subscription_notification('past_due', $local->id);
                } elseif ($stripe->status === 'canceled') {
                    $this->send_subscription_notification('canceled', $local->id);
                }
            }
        }

        // Check plan change
        if ($local->plan_id !== $stripe->plan->id) {
            do_action(
                'cobra_ai_subscription_plan_changed',
                $local->id,
                $local->plan_id,
                $stripe->plan->id
            );

            // Send notification
            if ($this->feature->get_settings('email_notifications')) {
                $this->send_subscription_notification('plan_changed', $local->id);
            }
        }

        // Check cancellation status
        if ($local->cancel_at_period_end !== $stripe->cancel_at_period_end) {
            do_action(
                'cobra_ai_subscription_cancel_status_changed',
                $local->id,
                $stripe->cancel_at_period_end
            );

            if ($stripe->cancel_at_period_end) {
                $this->send_subscription_notification('canceling', $local->id);
            }
        }
    }

    /**
     * Log error
     */
    private function log_error(string $message, array $context = []): void
    {
        $this->feature->log('error', $message, $context);
    }
}
