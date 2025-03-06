<?php

namespace CobraAI\Features\Credits;
use function CobraAI\{
    cobra_ai_db,
    cobra_ai
};
class CreditType {
    /**
     * Core credit types
     */
    private static $core_types = [
        'subscription' => [
            'name' => 'Subscription',
            'priority' => 10,
            'expirable' => true,
            'transferable' => false,
            'stackable' => false,
            'auto_consume' => true,
            'icon' => 'dashicons-calendar-alt'
        ],
        'paid' => [
            'name' => 'Paid',
            'priority' => 20,
            'expirable' => true,
            'transferable' => true,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-money-alt'
        ],
        'free' => [
            'name' => 'Free',
            'priority' => 30,
            'expirable' => true,
            'transferable' => false,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-gift'
        ],
        'coupon' => [
            'name' => 'Coupon',
            'priority' => 40,
            'expirable' => true,
            'transferable' => false,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-tickets-alt'
        ],
        'gift' => [
            'name' => 'Gift',
            'priority' => 50,
            'expirable' => true,
            'transferable' => true,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-heart'
        ],
        'reward' => [
            'name' => 'Reward',
            'priority' => 60,
            'expirable' => true,
            'transferable' => false,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-star-filled'
        ],
        'discount' => [
            'name' => 'Discount',
            'priority' => 70,
            'expirable' => true,
            'transferable' => false,
            'stackable' => false,
            'auto_consume' => true,
            'icon' => 'dashicons-tag'
        ],
        'bonus' => [
            'name' => 'Bonus',
            'priority' => 80,
            'expirable' => true,
            'transferable' => true,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-plus'
        ]
    ];

    /**
     * Registered credit types
     */
    private static $registered_types = [];

    /**
     * Initialize credit types
     */
    public static function init(): void {
        self::$registered_types = apply_filters('cobra_ai_credit_types', self::$core_types);
        
        // Sort types by priority
        uasort(self::$registered_types, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Register a new credit type
     */
    public static function register(string $type_id, array $args): bool {
        if (self::exists($type_id)) {
            cobra_ai_db()->log('error', sprintf(
                'Credit type "%s" already exists',
                $type_id
            ));
            return false;
        }

        $defaults = [
            'name' => '',
            'priority' => 100,
            'expirable' => true,
            'transferable' => false,
            'stackable' => true,
            'auto_consume' => false,
            'icon' => 'dashicons-marker',
            'capabilities' => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($args['name'])) {
            cobra_ai_db()->log('error', sprintf(
                'Credit type "%s" registration failed: name is required',
                $type_id
            ));
            return false;
        }

        self::$registered_types[$type_id] = $args;
        return true;
    }

    /**
     * Unregister a credit type
     */
    public static function unregister(string $type_id): bool {
        if (!self::exists($type_id)) {
            return false;
        }

        // Don't allow unregistering core types
        if (isset(self::$core_types[$type_id])) {
            cobra_ai_db()->log('error', sprintf(
                'Cannot unregister core credit type "%s"',
                $type_id
            ));
            return false;
        }

        unset(self::$registered_types[$type_id]);
        return true;
    }

    /**
     * Check if a credit type exists
     */
    public static function exists(string $type_id): bool {
        return isset(self::$registered_types[$type_id]);
    }

    /**
     * Get credit type information
     */
    public static function get(string $type_id): ?array {
        return self::$registered_types[$type_id] ?? null;
    }

    /**
     * Get all registered credit types
     */
    public static function get_all(): array {
        return self::$registered_types;
    }

    /**
     * Get active credit types
     */
    public static function get_active(): array {
        $settings = cobra_ai()->get_feature('credits')->get_settings();
        $enabled_types = $settings['general']['credit_types'] ?? [];

        return array_intersect_key(self::$registered_types, array_flip($enabled_types));
    }

    /**
     * Get credit type name
     */
    public static function get_name(string $type_id): string {
        return self::$registered_types[$type_id]['name'] ?? $type_id;
    }

    /**
     * Check if credit type is expirable
     */
    public static function is_expirable(string $type_id): bool {
        return self::$registered_types[$type_id]['expirable'] ?? true;
    }

    /**
     * Check if credit type is transferable
     */
    public static function is_transferable(string $type_id): bool {
        return self::$registered_types[$type_id]['transferable'] ?? false;
    }

    /**
     * Check if credit type is stackable
     */
    public static function is_stackable(string $type_id): bool {
        return self::$registered_types[$type_id]['stackable'] ?? true;
    }

    /**
     * Check if credit type auto consumes
     */
    public static function auto_consumes(string $type_id): bool {
        return self::$registered_types[$type_id]['auto_consume'] ?? false;
    }

    /**
     * Get credit type icon
     */
    public static function get_icon(string $type_id): string {
        return self::$registered_types[$type_id]['icon'] ?? 'dashicons-marker';
    }

    /**
     * Get credit types ordered by priority
     */
    public static function get_ordered_types(): array {
        $types = self::$registered_types;
        uasort($types, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        return $types;
    }

    /**
     * Check if user can manage credit type
     */
    public static function user_can_manage(string $type_id, int $user_id = null): bool {
        if (!self::exists($type_id)) {
            return false;
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $type = self::get($type_id);
        
        // If no specific capabilities are defined, require manage_options
        if (empty($type['capabilities'])) {
            return user_can($user_id, 'manage_options');
        }

        // Check if user has any of the required capabilities
        foreach ($type['capabilities'] as $cap) {
            if (user_can($user_id, $cap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get consumption rules for credit type
     */
    public static function get_consumption_rules(string $type_id): array {
        $defaults = [
            'order' => 'asc', // asc = oldest first, desc = newest first
            'minimum_amount' => 0,
            'maximum_amount' => null,
            'increment' => 1,
            'allow_partial' => true,
            'require_approval' => false
        ];

        $type = self::get($type_id);
        return wp_parse_args($type['consumption_rules'] ?? [], $defaults);
    }

    /**
     * Validate credit amount for type
     */
    public static function validate_amount(string $type_id, float $amount): bool {
        if (!self::exists($type_id)) {
            return false;
        }

        $rules = self::get_consumption_rules($type_id);

        // Check minimum amount
        if ($amount < $rules['minimum_amount']) {
            return false;
        }

        // Check maximum amount
        if ($rules['maximum_amount'] !== null && $amount > $rules['maximum_amount']) {
            return false;
        }

        // Check increment
        if ($rules['increment'] > 0) {
            $remainder = fmod($amount, $rules['increment']);
            if ($remainder !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get expiration settings for credit type
     */
    public static function get_expiration_settings(string $type_id): array {
        $defaults = [
            'duration' => 0, // 0 = never expires
            'duration_unit' => 'days',
            'grace_period' => 0,
            'grace_period_unit' => 'days',
            'notify_before' => 7,
            'notify_before_unit' => 'days'
        ];

        $type = self::get($type_id);
        return wp_parse_args($type['expiration_settings'] ?? [], $defaults);
    }

    /**
     * Calculate expiration date for credit type
     */
    public static function calculate_expiration_date(string $type_id, string $start_date = ''): ?string {
        if (!self::is_expirable($type_id)) {
            return null;
        }

        $settings = self::get_expiration_settings($type_id);
        
        if ($settings['duration'] <= 0) {
            return null;
        }

        if (empty($start_date)) {
            $start_date = current_time('mysql');
        }

        $expiration = strtotime($start_date);
        
        switch ($settings['duration_unit']) {
            case 'hours':
                $expiration = strtotime("+{$settings['duration']} hours", $expiration);
                break;
            case 'days':
                $expiration = strtotime("+{$settings['duration']} days", $expiration);
                break;
            case 'weeks':
                $expiration = strtotime("+{$settings['duration']} weeks", $expiration);
                break;
            case 'months':
                $expiration = strtotime("+{$settings['duration']} months", $expiration);
                break;
            case 'years':
                $expiration = strtotime("+{$settings['duration']} years", $expiration);
                break;
        }

        return date('Y-m-d H:i:s', $expiration);
    }
}

// Initialize credit types
add_action('init', ['CobraAI\Features\Credits\CreditType', 'init']);