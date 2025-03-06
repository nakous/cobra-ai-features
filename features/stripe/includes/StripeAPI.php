<?php
namespace CobraAI\Features\Stripe;

class StripeAPI {
    private $feature;

    public function __construct(Feature $feature) {
        $this->feature = $feature;
    }

    /**
     * Get Stripe API key based on mode
     */
    public function get_secret_key(): string {
        $settings = $this->feature->get_settings();
        return $settings['mode'] === 'live' 
            ? $settings['live_secret_key'] 
            : $settings['test_secret_key'];
    }

    /**
     * Get publishable key based on mode
     */
    public function get_publishable_key(): string {
        $settings = $this->feature->get_settings();
        return $settings['mode'] === 'live' 
            ? $settings['live_publishable_key'] 
            : $settings['test_publishable_key'];
    }

    /**
     * Check if we're in live mode
     */
    public function is_live_mode(): bool {
        $settings = $this->feature->get_settings();
        return $settings['mode'] === 'live';
    }
}