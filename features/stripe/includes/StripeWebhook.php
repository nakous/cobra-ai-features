<?php

namespace CobraAI\Features\Stripe;

use Stripe\Webhook;
use Stripe\Event;

class StripeWebhook
{
    private $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Handle webhook request
     */
    public function handle_webhook(\WP_REST_Request $request)
    {
        try {
            $settings = $this->feature->get_settings();

            // Get webhook secret
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

            // Dispatch event
            $this->feature->get_events()->dispatch($event);

            return new \WP_REST_Response(['received' => true]);
        } catch (\Exception $e) {
            $this->feature->log_error('Webhook error: ' . $e->getMessage());
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Log webhook event
     */
    private function log_event(Event $event): void
    {
        global $wpdb;
        $table_name = $this->feature->get_table_name('stripe_logs');
        if (!$table_name) {
            throw new \Exception('Stripe logs table not found');
        }
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'data' => json_encode($event->data),
                'is_live' => $this->feature->get_api()->is_live_mode(),
            ]
        );
    }

    /**
     * Get webhook status
     */
    public function get_status(): array
    {
        global $wpdb;

        $logs_table = $this->feature->get_table_name('stripe_logs');
        if (!$logs_table) {
            return [
                'total_events' => 0,
                'recent_events' => []
            ];
        }

        return [
            'total_events' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$logs_table}"
            ),
            'recent_events' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT event_type, COUNT(*) as count 
                     FROM {$logs_table}
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                     GROUP BY event_type",
                    7
                )
            )
        ];
    }
    /**
     * Get webhooks configuration
     */
    public function get_webhooks(): array
    {
        global $wpdb;

        $webhooks_table = $this->feature->get_table_name('stripe_webhooks');
        if (!$webhooks_table) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM {$webhooks_table} WHERE status = 'active'",
            ARRAY_A
        );
    }

    /**
     * Register new webhook
     */
    public function register_webhook(string $webhook_id, string $secret, array $events, bool $is_live = false): bool
    {
        global $wpdb;

        $webhooks_table = $this->feature->get_table_name('stripe_webhooks');
        if (!$webhooks_table) {
            throw new \Exception('Stripe webhooks table not found');
        }

        return $wpdb->insert(
            $webhooks_table,
            [
                'webhook_id' => $webhook_id,
                'secret' => $secret,
                'is_live' => $is_live ? 1 : 0,
                'events' => json_encode($events),
                'status' => 'active'
            ],
            ['%s', '%s', '%d', '%s', '%s']
        ) !== false;
    }

    /**
     * Deactivate webhook
     */
    public function deactivate_webhook(string $webhook_id): bool
    {
        global $wpdb;

        $webhooks_table = $this->feature->get_table_name('stripe_webhooks');
        if (!$webhooks_table) {
            throw new \Exception('Stripe webhooks table not found');
        }

        return $wpdb->update(
            $webhooks_table,
            ['status' => 'inactive'],
            ['webhook_id' => $webhook_id],
            ['%s'],
            ['%s']
        ) !== false;
    }
}
