<?php

namespace CobraAI\Features\StripePayments;

class Webhooks
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * checkout.session.completed — the customer finished Stripe Checkout.
     * For 'payment' mode sessions we mark the order paid and fire cobra_ai_order_paid.
     */
    public function handle_session_completed($session): void
    {
        try {
            if (empty($session->id) || (isset($session->mode) && $session->mode !== 'payment')) {
                return;
            }

            $order = $this->feature->get_orders()->get_by_session($session->id);
            if (!$order) {
                // Session completed without a local pending order — might come from external flow.
                $this->feature->log('warning', 'Stripe session completed but no local order found', [
                    'session_id' => $session->id,
                ]);
                return;
            }

            // Skip already-paid (webhook can fire twice).
            if ($order->status === 'paid' || $order->status === 'refunded') {
                return;
            }

            $this->feature->get_orders()->mark_paid((int) $order->id, [
                'payment_intent_id' => $session->payment_intent ?? null,
                'customer_id' => $session->customer ?? null,
            ]);

            $refreshed = $this->feature->get_orders()->get((int) $order->id);
            $this->dispatch_paid_hook($refreshed);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to handle session completed: ' . $e->getMessage());
        }
    }

    public function handle_session_expired($session): void
    {
        try {
            if (empty($session->id)) {
                return;
            }
            $order = $this->feature->get_orders()->get_by_session($session->id);
            if ($order && $order->status === 'pending') {
                $this->feature->get_orders()->update((int) $order->id, ['status' => 'cancelled']);
            }
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to handle session expired: ' . $e->getMessage());
        }
    }

    /**
     * payment_intent.succeeded — redundant safety net when checkout.session.completed
     * fires out of order or the order was created from a PaymentIntent directly.
     */
    public function handle_payment_succeeded($intent): void
    {
        try {
            if (empty($intent->id)) {
                return;
            }
            $order = $this->feature->get_orders()->get_by_payment_intent($intent->id);
            if (!$order || $order->status === 'paid') {
                return;
            }
            $this->feature->get_orders()->mark_paid((int) $order->id);
            $this->dispatch_paid_hook($this->feature->get_orders()->get((int) $order->id));
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to handle payment succeeded: ' . $e->getMessage());
        }
    }

    public function handle_payment_failed($intent): void
    {
        try {
            if (empty($intent->id)) {
                return;
            }
            $order = $this->feature->get_orders()->get_by_payment_intent($intent->id);
            if (!$order) {
                return;
            }
            $this->feature->get_orders()->mark_failed((int) $order->id, [
                'metadata' => wp_json_encode([
                    'failure_message' => $intent->last_payment_error->message ?? null,
                ]),
            ]);
            do_action('cobra_ai_order_failed', (int) $order->id, (array) $order);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to handle payment failed: ' . $e->getMessage());
        }
    }

    public function handle_charge_refunded($charge): void
    {
        try {
            $intent_id = $charge->payment_intent ?? null;
            if (!$intent_id) {
                return;
            }
            $order = $this->feature->get_orders()->get_by_payment_intent($intent_id);
            if (!$order) {
                return;
            }
            $this->feature->get_orders()->mark_refunded((int) $order->id);
            do_action('cobra_ai_order_refunded', (int) $order->id, (array) $order);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Failed to handle charge refunded: ' . $e->getMessage());
        }
    }

    /**
     * Central place to fire the extensibility hook consumers listen to.
     * Passes the order row as an array so listeners don't need to know about our classes.
     */
    private function dispatch_paid_hook($order): void
    {
        if (!$order) {
            return;
        }
        do_action('cobra_ai_order_paid', (int) $order->id, [
            'id'            => (int) $order->id,
            'user_id'       => (int) $order->user_id,
            'product_id'    => (int) $order->product_id,
            'product_type'  => (string) $order->product_type,
            'amount'        => (float) $order->amount,
            'currency'      => (string) $order->currency,
            'session_id'    => $order->session_id,
            'payment_intent_id' => $order->payment_intent_id,
        ]);
    }
}
