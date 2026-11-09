<?php

namespace CobraAI\Features\Emailmarketing;

defined('ABSPATH') || exit;

/**
 * Evaluates condition groups against a user context.
 *
 * Condition JSON structure stored in triggers.conditions:
 * {
 *   "operator": "AND"|"OR",        // root operator between groups
 *   "groups": [
 *     {
 *       "operator": "AND"|"OR",    // operator between rules inside this group
 *       "rules": [
 *         {
 *           "source":   "user_meta"|"user_role"|"hook_arg"|"user_credits"|"days_since_register"|"post_count"|"order_count",
 *           "field":    "string",  // meta key for user_meta; arg index for hook_arg; unused for others
 *           "operator": "="|"!="|">"|"<"|">="|"<="|"in"|"not_in"|"exists"|"not_exists"|"contains"|"not_contains",
 *           "value":    "string"|number|array
 *         }
 *       ]
 *     }
 *   ]
 * }
 */
class ConditionEvaluator
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Check whether all condition groups pass for a given user.
     *
     * @param int        $user_id
     * @param array|null $conditions  Decoded conditions array (from trigger row). Null means always pass.
     * @param array      $hook_args   Arguments from the triggering WordPress hook (for hook_arg source).
     * @return bool
     */
    public function evaluate(int $user_id, ?array $conditions, array $hook_args = []): bool
    {
        if (empty($conditions) || empty($conditions['groups'])) {
            return true;
        }

        $root_op = strtoupper($conditions['operator'] ?? 'AND');
        $groups  = $conditions['groups'];

        foreach ($groups as $group) {
            $group_result = $this->evaluate_group($user_id, $group, $hook_args);

            if ($root_op === 'OR' && $group_result) {
                return true;
            }
            if ($root_op === 'AND' && !$group_result) {
                return false;
            }
        }

        // AND: all groups passed. OR: none passed.
        return $root_op === 'AND';
    }

    // -------------------------------------------------------------------------
    // GROUP + RULE
    // -------------------------------------------------------------------------

    private function evaluate_group(int $user_id, array $group, array $hook_args): bool
    {
        $group_op = strtoupper($group['operator'] ?? 'AND');
        $rules    = $group['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $rule_result = $this->evaluate_rule($user_id, $rule, $hook_args);

            if ($group_op === 'OR' && $rule_result) {
                return true;
            }
            if ($group_op === 'AND' && !$rule_result) {
                return false;
            }
        }

        return $group_op === 'AND';
    }

    private function evaluate_rule(int $user_id, array $rule, array $hook_args): bool
    {
        $source   = $rule['source']   ?? '';
        $field    = $rule['field']    ?? '';
        $operator = $rule['operator'] ?? '=';
        $value    = $rule['value']    ?? '';

        $actual = $this->resolve_source($user_id, $source, $field, $hook_args);

        return $this->compare($actual, $operator, $value);
    }

    // -------------------------------------------------------------------------
    // SOURCE RESOLUTION
    // -------------------------------------------------------------------------

    /**
     * @return mixed
     */
    private function resolve_source(int $user_id, string $source, string $field, array $hook_args)
    {
        switch ($source) {

            case 'user_meta':
                return get_user_meta($user_id, $field, true);

            case 'user_role':
                $user = get_userdata($user_id);
                return $user ? $user->roles : [];

            case 'hook_arg':
                $index = (int) $field;
                return $hook_args[$index] ?? null;

            case 'user_credits':
                // Compatible with cobra credits feature
                if (class_exists('\CobraAI\Features\Credits\Feature')) {
                    $credits_feature = cobra_ai()->get_feature('credits');
                    if ($credits_feature && method_exists($credits_feature, 'get_user_balance')) {
                        return (float) $credits_feature->get_user_balance($user_id);
                    }
                }
                return (float) get_user_meta($user_id, 'cobra_credits_balance', true);

            case 'days_since_register':
                $user = get_userdata($user_id);
                if (!$user) {
                    return 0;
                }
                $registered = strtotime($user->user_registered);
                return (int) floor((time() - $registered) / DAY_IN_SECONDS);

            case 'post_count':
                return (int) count_user_posts($user_id);

            case 'order_count':
                // WooCommerce-compatible (optional)
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'customer' => $user_id,
                        'status'   => ['wc-completed'],
                        'limit'    => -1,
                        'return'   => 'ids',
                    ]);
                    return is_array($orders) ? count($orders) : 0;
                }
                return 0;

            default:
                return null;
        }
    }

    // -------------------------------------------------------------------------
    // COMPARISON
    // -------------------------------------------------------------------------

    /**
     * @param mixed $actual
     * @param mixed $expected
     */
    private function compare($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case '=':
            case 'eq':
                if (is_array($actual)) {
                    return in_array($expected, $actual, false);
                }
                // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                return $actual == $expected;

            case '!=':
            case 'neq':
                if (is_array($actual)) {
                    return !in_array($expected, $actual, false);
                }
                // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                return $actual != $expected;

            case '>':
                return (float) $actual > (float) $expected;

            case '<':
                return (float) $actual < (float) $expected;

            case '>=':
                return (float) $actual >= (float) $expected;

            case '<=':
                return (float) $actual <= (float) $expected;

            case 'in':
                $list = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
                if (is_array($actual)) {
                    return !empty(array_intersect($actual, $list));
                }
                return in_array((string) $actual, $list, false);

            case 'not_in':
                $list = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
                if (is_array($actual)) {
                    return empty(array_intersect($actual, $list));
                }
                return !in_array((string) $actual, $list, false);

            case 'contains':
                return is_string($actual) && strpos($actual, (string) $expected) !== false;

            case 'not_contains':
                return is_string($actual) && strpos($actual, (string) $expected) === false;

            case 'exists':
                return $actual !== null && $actual !== '' && $actual !== false;

            case 'not_exists':
                return $actual === null || $actual === '' || $actual === false;

            default:
                return false;
        }
    }
}
