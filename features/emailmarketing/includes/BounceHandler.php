<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

class BounceHandler
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // WEBHOOK ENTRY POINT (called on 'init')
    // -------------------------------------------------------------------------

    public function handle_webhook_request(): void
    {
        if (!isset($_GET['cobra_emailmarketing_webhook'])) {
            return;
        }

        $settings = $this->feature->get_settings();
        $brevo    = $settings['brevo'] ?? [];

        if (empty($brevo['enabled']) || empty($brevo['webhook_token'])) {
            http_response_code(403);
            exit('Brevo webhook not configured.');
        }

        // Validate token
        $token = sanitize_text_field($_GET['token'] ?? '');
        if (!hash_equals($brevo['webhook_token'], $token)) {
            http_response_code(403);
            exit('Invalid token.');
        }

        // Read JSON body
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (empty($data)) {
            http_response_code(400);
            exit('Empty payload.');
        }

        // Brevo can send a single event or array of events
        $events = isset($data[0]) ? $data : [$data];

        foreach ($events as $event) {
            $this->process_event($event);
        }

        http_response_code(200);
        exit('OK');
    }

    // -------------------------------------------------------------------------
    // EVENT PROCESSING
    // -------------------------------------------------------------------------

    private function process_event(array $event): void
    {
        $event_type = strtolower($event['event'] ?? '');
        $email      = sanitize_email($event['email'] ?? '');

        if (empty($email)) {
            return;
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $this->log_unknown($email, $event_type);
            return;
        }

        $user_id = (int) $user->ID;

        switch ($event_type) {
            case 'hard_bounce':
            case 'invalid_email':
                $this->feature->prefs->mark_bounced($user_id);
                $this->feature->queue->cancel_all_for_user($user_id);
                $this->feature->log('warning', "Email hard bounce for user #{$user_id} ({$email})", ['event' => $event]);
                break;

            case 'spam':
            case 'complaint':
                $this->feature->prefs->mark_spam($user_id);
                $this->feature->queue->cancel_all_for_user($user_id);
                $this->feature->log('warning', "Spam complaint for user #{$user_id} ({$email})", ['event' => $event]);
                break;

            case 'unsubscribe':
            case 'list_unsubscribe':
                $this->feature->prefs->set_unsubscribed($user_id, 'all');
                $this->feature->queue->cancel_all_for_user($user_id);
                $this->feature->log('info', "Unsubscribe via Brevo for user #{$user_id} ({$email})", ['event' => $event]);
                break;

            // soft_bounce, deferred, click, open — no action needed
            default:
                break;
        }
    }

    // -------------------------------------------------------------------------
    // API TEST (verify Brevo API key)
    // -------------------------------------------------------------------------

    public function test_api_connection(): array
    {
        $api_key = $this->feature->get_settings('brevo.api_key');
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'Clé API Brevo non configurée.'];
        }

        $response = wp_remote_get('https://api.brevo.com/v3/account', [
            'headers' => [
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200) {
            return [
                'success' => true,
                'message' => 'Connexion Brevo OK. Compte : ' . ($body['companyName'] ?? $body['email'] ?? ''),
            ];
        }

        return [
            'success' => false,
            'message' => 'Erreur Brevo ' . $code . ': ' . ($body['message'] ?? 'Erreur inconnue'),
        ];
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function log_unknown(string $email, string $event_type): void
    {
        $this->feature->log('info', "Brevo webhook: unknown email '{$email}' for event '{$event_type}'");
    }

    public function get_webhook_url(): string
    {
        $token = $this->feature->get_settings('brevo.webhook_token');

        return add_query_arg([
            'cobra_emailmarketing_webhook' => 1,
            'token'                        => $token,
        ], home_url('/'));
    }
}
