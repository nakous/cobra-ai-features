<?php

namespace CobraAI\Features\StripeSubscriptions;

use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Dispute;
use Stripe\Invoice;
use Stripe\Exception\ApiErrorException;

class Payments
{
    private $feature;

    private $table_name = 'wp_stripe_payments';

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Process a payment
     */
    public function process_payment(string $payment_intent_id, $subscription_id=null ): array
    {
        try {
            $payment_intent = PaymentIntent::retrieve($payment_intent_id);

            // Store payment record
            $payment_id = $this->store_payment([
                'payment_id' => $payment_intent->id,
                'subscription_id' => $subscription_id ?? null,
                'invoice_id' => $payment_intent->invoice ?? null,
                'amount' => $payment_intent->amount / 100,
                'currency' => $payment_intent->currency,
                'status' => $payment_intent->status
            ]);

            // If payment successful, trigger success actions
            if ($payment_intent->status === 'succeeded') {
                $this->handle_successful_payment($payment_intent, $payment_id);
            }

            return [
                'success' => true,
                'payment_id' => $payment_id,
                'status' => $payment_intent->status
            ];
        } catch (\Exception $e) {
            $this->log_error('Payment processing failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $payment_intent_id
            ]);

            throw $e;
        }
    }

    /**
     * Process a refund
     */
    public function process_refund(string $payment_id, float $amount = null): array
    {
        try {
            // Get payment record
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            // Create refund in Stripe
            $refund_data = [
                'payment_intent' => $payment->payment_id,
                'metadata' => [
                    'subscription_id' => $payment->subscription_id
                ]
            ];

            if ($amount !== null) {
                $refund_data['amount'] = $amount * 100;
            }

            $refund = Refund::create($refund_data);

            // Update payment record
            $this->update_payment_status(
                $payment->id,
                'refunded',
                ['refund_id' => $refund->id]
            );

            // Trigger refund actions
            do_action('cobra_ai_subscription_refunded', $payment, $refund);

            return [
                'success' => true,
                'refund' => $refund
            ];
        } catch (\Exception $e) {
            $this->log_error('Refund processing failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment_id
            ]);

            throw $e;
        }
    }

    /**
     * Submit dispute evidence
     */
    public function submit_dispute_evidence(string $dispute_id, array $evidence): array
    {
        try {
            $dispute = Dispute::update($dispute_id, [
                'evidence' => $evidence
            ]);

            // Update dispute record
            $this->update_dispute_status($dispute_id, $dispute->status);

            return [
                'success' => true,
                'dispute' => $dispute
            ];
        } catch (\Exception $e) {
            $this->log_error('Failed to submit dispute evidence', [
                'error' => $e->getMessage(),
                'dispute_id' => $dispute_id
            ]);

            throw $e;
        }
    }

    /**
     * Handle webhook payment events
     */
    public function handle_payment_event(string $event_type, array $data): void
    {
        switch ($event_type) {
            case 'payment_intent.succeeded':
                $this->process_payment($data['id'], $data['metadata']['subscription_id'] ?? null);
                break;

            case 'payment_intent.payment_failed':
                $this->handle_failed_payment($data);
                break;

            case 'charge.refunded':
                $this->handle_refund_event($data);
                break;

            case 'charge.dispute.created':
                $this->handle_dispute_created($data);
                break;

            case 'invoice.payment_succeeded':
                $this->handle_invoice_paid($data);
                break;

            case 'invoice.payment_failed':
                $this->handle_invoice_failed($data);
                break;
        }
    }

    /**
     * Handle successful payment
     */
    private function handle_successful_payment(PaymentIntent $payment_intent, int $payment_id): void
    {
        // Get subscription if exists
        // if (!empty($payment_intent->metadata['subscription_id'])) {
        //     $subscription = $this->feature->get_subscription(
        //         $payment_intent->metadata['subscription_id']
        //     );

        //     if ($subscription) {
        //         // Update subscription status if needed
        //         if ($subscription->status === 'incomplete') {
        //             $this->feature->update_subscription_status(
        //                 $subscription->id,
        //                 'active'
        //             );
        //         }
        //     }
        // }

        // Trigger success actions
        do_action('cobra_ai_payment_successful', $payment_id, $payment_intent);
    }

    /**
     * Handle failed payment
     */
    private function handle_failed_payment(array $data): void
    {
        $payment = $this->get_payment_by_intent($data['id']);
        if (!$payment) {
            return;
        }

        // Update payment status
        $this->update_payment_status(
            $payment->id,
            'failed',
            ['error' => $data['failure_message'] ?? '']
        );

        // Get subscription if exists
        // if ($payment->subscription_id) {
        //     $subscription = $this->feature->get_subscriptions()->get_subscription($payment->subscription_id);
        //     if ($subscription) {
        //         // Update subscription status
        //         $this->feature->update_subscription_status(
        //             $subscription->id,
        //             'past_due'
        //         );
        //     }
        // }

        // Trigger failed actions
        do_action('cobra_ai_payment_failed', $payment, $data);
    }

    /**
     * Handle refund event
     */
    public function handle_refund_event(array $data): void
    {
        $payment = $this->get_payment_by_intent($data['payment_intent']);
        if (!$payment) {
            return;
        }

        // Update payment status
        $this->update_payment_status(
            $payment->id,
            'refunded',
            ['refund_id' => $data['id']]
        );

        // Trigger refund actions
        do_action('cobra_ai_payment_refunded', $payment, $data);
    }

    /**
     * Handle dispute created
     */
    public function handle_dispute_created(array $data): void
    {
        $payment = $this->get_payment_by_intent($data['payment_intent']);
        if (!$payment) {
            return;
        }

        // Store dispute record
        $dispute_id = $this->store_dispute([
            'dispute_id' => $data['id'],
            'payment_id' => $payment->id,
            'amount' => $data['amount'] / 100,
            'currency' => $data['currency'],
            'status' => $data['status'],
            'reason' => $data['reason']
        ]);

        // Trigger dispute actions
        do_action('cobra_ai_dispute_created', $dispute_id, $data);
    }

    /**
     * Handle invoice paid
     */
    private function handle_invoice_paid(array $data): void
    {
        $invoice = Invoice::retrieve($data['id']);

        // Store payment if not exists
        if ($invoice->payment_intent) {
            $payment = $this->get_payment_by_intent($invoice->payment_intent);
            if (!$payment) {
                $this->store_payment([
                    'payment_id' => $invoice->payment_intent,
                    'subscription_id' => $invoice->subscription,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => $invoice->currency,
                    'status' => 'succeeded'
                ]);
            }
        }

        // Trigger invoice actions
        do_action('cobra_ai_invoice_paid', $invoice);
    }

    /**
     * Handle invoice failed
     */
    private function handle_invoice_failed(array $data): void
    {
        $invoice = Invoice::retrieve($data['id']);

        // Update subscription status if exists
        // if ($invoice->subscription) {
        //     $subscription = $this->feature->get_subscription($invoice->subscription);
        //     if ($subscription) {
        //         $this->feature->update_subscription_status(
        //             $subscription->id,
        //             'past_due'
        //         );
        //     }
        // }

        // Trigger invoice actions
        do_action('cobra_ai_invoice_failed', $invoice);
    }

    /**
     * Store payment record
     */
    public function store_payment(array $data): int
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        $wpdb->insert(
            $table['name'],
            [
                'payment_id' => $data['payment_id'],
                'subscription_id' => $data['subscription_id'],
                'invoice_id' => $data['invoice_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => $data['status']
            ]
        );
        if ($wpdb->last_error) {
            $this->log_error('Failed to store payment record', [
                'error' => $wpdb->last_error,
                'data' => $data
            ]);
            return 0;
        } else {
            do_action('cobra_ai_payment_created', $wpdb->insert_id, $data);
        }
        return $wpdb->insert_id;
    }

    /**
     * Store dispute record
     */
    private function store_dispute(array $data): int
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_disputes');

        $wpdb->insert(
            $table,
            [
                'dispute_id' => $data['dispute_id'],
                'payment_id' => $data['payment_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => $data['status'],
                'reason' => $data['reason']
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Get payment record
     */
    public function get_payment(int $payment_id)
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $payment_id
        ));
    }

    /**
     * Get payment by intent ID
     */
    public function get_payment_by_intent(string $payment_intent_id)
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table['name']} WHERE payment_id = %s",
            $payment_intent_id
        ));
    }

    // update by stripe payment id
    public function update_payment(string $payment_id, array $data = []): bool
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        $resulta = $wpdb->update(
            $table['name'],
            $data,
            ['id' => $payment_id]
        );
        if ($resulta === false) {
            $this->log_error('Failed to update payment status', [
                'error' => $wpdb->last_error,
                'id' => $payment_id
            ]);
        } else {
            do_action('cobra_ai_payment_updated', $payment_id, $data);
        }

        return $resulta !== false;
    }
    /**
     * Update payment status
     */
    private function update_payment_status(int $payment_id, string $status, array $data = []): bool
    {
        global $wpdb;

        $table = $this->feature->get_table('stripe_payments');

        $update = ['status' => $status];
        if (!empty($data)) {
            $update = array_merge($update, $data);
        }

        $reulta = $wpdb->update(
            $table['name'],
            $update,
            ['id' => $payment_id]
        );

        if ($wpdb->last_error) {
            $this->log_error('Failed to update payment status', [
                'error' => $wpdb->last_error,
                'payment_id' => $payment_id,
                'status' => $status
            ]);
        } else {
            do_action('cobra_ai_payment_updated', $payment_id, $data);
        }

        return $reulta !== false;
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
     * Get payment analytics
     */
    public function get_payment_analytics(array $args = []): array
    {
        global $wpdb;

        $payments_table = $this->feature->get_table('stripe_payments');

        $defaults = [
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d'),
            'subscription_id' => null
        ];

        $args = wp_parse_args($args, $defaults);
        $where = [];
        $params = [];

        // Date range
        $where[] = "DATE(created_at) BETWEEN %s AND %s";
        $params[] = $args['start_date'];
        $params[] = $args['end_date'];

        // Subscription filter
        if ($args['subscription_id']) {
            $where[] = "subscription_id = %s";
            $params[] = $args['subscription_id'];
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        return [
            'total_revenue' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$payments_table['name']} 
                 {$where_clause} AND status = 'succeeded'",
                $params
            )),
            'successful_payments' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$payments_table['name']} 
                 {$where_clause} AND status = 'succeeded'",
                $params
            )),
            'failed_payments' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$payments_table['name']} 
                 {$where_clause} AND status = 'failed'",
                $params
            )),
            'refunded_amount' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$payments_table['name']} 
                 {$where_clause} AND status = 'refunded'",
                $params
            )),
            'daily_revenue' => $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(created_at) as date, SUM(amount) as revenue 
                 FROM {$payments_table['name']} 
                 {$where_clause} AND status = 'succeeded'
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                $params
            ))
        ];
    }

    /**
     * Get subscription payments
     */
    public function get_subscription_payments(string $subscription_id): array
    {
        global $wpdb;

        // $table = $this->feature->get_table('stripe_payments');
        $this->table_name = $this->feature->get_table('stripe_payments')['name'];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE subscription_id = %s ORDER BY created_at DESC",
            $subscription_id
        ));
    }
    /**
     * Log error
     */
    private function log_error(string $message, array $context = []): void
    {
        $this->feature->log('error', $message, $context);
    }
}
