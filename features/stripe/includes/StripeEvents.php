<?php

namespace CobraAI\Features\Stripe;

use Stripe\Event;

class StripeEvents
{
    private $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Dispatch Stripe event to WordPress hooks
     */
    public function dispatch(Event $event): void
    {
        // Generic event hook
        do_action('cobra_ai_stripe_event', $event);
        // Specific event hook
        do_action('cobra_ai_stripe_' . str_replace('.', '_', $event->type), $event->data->object);
    }
}
