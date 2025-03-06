<?php

namespace CobraAI\Features\Credits;

use function CobraAI\{
    cobra_ai_db
};
class CreditManager {
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Database table name
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct($feature) {
        $this->feature = $feature;
        $this->table = $this->feature->get_table_name('credits');
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // User deletion
        add_action('delete_user', [$this, 'handle_user_deletion']);

        // Credit expiration
        add_action('cobra_ai_daily_credit_check', [$this, 'process_expired_credits']);
        
        // Consumption hooks
        add_action('cobra_ai_before_consume_credit', [$this, 'validate_consumption'], 10, 3);
        add_action('cobra_ai_credit_consumed', [$this, 'update_user_balance'], 10, 3);
    }

    /**
     * Add credits to user
     *
     * @param int    $user_id       User ID
     * @param float  $amount        Credit amount
     * @param string $credit_type   Credit type
     * @param array  $args          Additional arguments
     * @return int|false           Credit ID or false on failure
     */
    public function add_credit(int $user_id, float $amount, string $credit_type, array $args = []) {
        global $wpdb;

        try {
            // Validate credit type
            if (!CreditType::exists($credit_type)) {
                throw new \Exception('Invalid credit type');
            }

            // Validate amount
            if (!CreditType::validate_amount($credit_type, $amount)) {
                throw new \Exception('Invalid credit amount');
            }

            // Default arguments
            $defaults = [
                'comment' => '',
                'start_date' => current_time('mysql'),
                'expiration_date' => null,
                'type_id' => uniqid($credit_type . '_'),
                'status' => 'active',
                'meta' => []
            ];

            $args = wp_parse_args($args, $defaults);

            // Calculate expiration if not provided
            if (empty($args['expiration_date']) && CreditType::is_expirable($credit_type)) {
                $args['expiration_date'] = CreditType::calculate_expiration_date(
                    $credit_type, 
                    $args['start_date']
                );
            }

            // Prepare data
            $data = [
                'user_id' => $user_id,
                'credit_type' => $credit_type,
                'type_id' => $args['type_id'],
                'credit' => $amount,
                'consumed' => 0,
                'comment' => $args['comment'],
                'status' => $args['status'],
                'start_date' => $args['start_date'],
                'expiration_date' => $args['expiration_date'],
                'created_at' => current_time('mysql'),
                'meta' => !empty($args['meta']) ? json_encode($args['meta']) : null
            ];

            // Allow modification of credit data
            $data = apply_filters('cobra_ai_before_add_credit', $data, $user_id, $credit_type, $args);

            // Insert credit
            $result = $wpdb->insert($this->table, $data);

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            $credit_id = $wpdb->insert_id;

            // Update user meta
            $this->update_user_balance($user_id);

            // Fire action
            do_action('cobra_ai_credit_added', $credit_id, $data, $user_id);

            return $credit_id;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to add credit: ' . $e->getMessage(), [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $credit_type,
                'args' => $args
            ]);
            return false;
        }
    }

    /**
     * Consume credits
     *
     * @param int    $user_id   User ID
     * @param float  $amount    Amount to consume
     * @param array  $args      Additional arguments
     * @return bool            Success status
     */
    public function consume_credits(int $user_id, float $amount, array $args = []): bool {
        global $wpdb;

        try {
            // Validate amount
            if ($amount <= 0) {
                throw new \Exception('Invalid consumption amount');
            }

            // Default arguments
            $defaults = [
                'credit_type' => null,
                'priority' => 'asc', // asc = oldest first, desc = newest first
                'comment' => '',
                'meta' => []
            ];

            $args = wp_parse_args($args, $defaults);

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Get available credits
            $credits = $this->get_available_credits($user_id, $args['credit_type']);
            
            if (empty($credits)) {
                throw new \Exception('No credits available');
            }

            $remaining_amount = $amount;
            $consumed_credits = [];

            // Sort credits by priority
            if ($args['priority'] === 'desc') {
                $credits = array_reverse($credits);
            }

            // Consume credits
            foreach ($credits as $credit) {
                if ($remaining_amount <= 0) {
                    break;
                }

                $available = $credit->credit - $credit->consumed;
                $to_consume = min($remaining_amount, $available);

                // Validate consumption
                $validated = apply_filters('cobra_ai_validate_credit_consumption', 
                    true, 
                    $credit, 
                    $to_consume, 
                    $args
                );

                if (!$validated) {
                    continue;
                }

                // Update consumed amount
                $result = $wpdb->update(
                    $this->table,
                    ['consumed' => $credit->consumed + $to_consume],
                    ['id' => $credit->id],
                    ['%f'],
                    ['%d']
                );

                if ($result === false) {
                    throw new \Exception('Failed to update credit consumption');
                }

                $consumed_credits[] = [
                    'credit_id' => $credit->id,
                    'amount' => $to_consume
                ];

                $remaining_amount -= $to_consume;
            }

            if ($remaining_amount > 0) {
                throw new \Exception('Insufficient credits');
            }

            // Update user balance
            $this->update_user_balance($user_id);

            // Commit transaction
            $wpdb->query('COMMIT');

            // Fire action for each consumed credit
            foreach ($consumed_credits as $consumed) {
                do_action('cobra_ai_credit_consumed', 
                    $consumed['credit_id'], 
                    $consumed['amount'], 
                    $args
                );
            }

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            
            cobra_ai_db()->log('error', 'Failed to consume credits: ' . $e->getMessage(), [
                'user_id' => $user_id,
                'amount' => $amount,
                'args' => $args
            ]);
            
            return false;
        }
    }

    /**
     * Get available credits
     *
     * @param int         $user_id     User ID
     * @param string|null $credit_type Specific credit type or null for all
     * @return array                  Array of credit objects
     */
    public function get_available_credits(int $user_id, ?string $credit_type = null): array {
        global $wpdb;

        $where = [
            'user_id = %d',
            'status = %s',
            '(credit - consumed) > 0',
            '(expiration_date IS NULL OR expiration_date > %s)'
        ];

        $params = [
            $user_id,
            'active',
            current_time('mysql')
        ];

        if ($credit_type !== null) {
            $where[] = 'credit_type = %s';
            $params[] = $credit_type;
        }

        $query = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at ASC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Update credit status
     *
     * @param int    $credit_id Credit ID
     * @param string $status    New status
     * @param array  $args      Additional arguments
     * @return bool            Success status
     */
    public function update_credit_status(int $credit_id, string $status, array $args = []): bool {
        global $wpdb;

        try {
            $valid_statuses = ['active', 'pending', 'expired', 'deleted'];
            
            if (!in_array($status, $valid_statuses)) {
                throw new \Exception('Invalid status');
            }

            $credit = $this->get_credit($credit_id);
            if (!$credit) {
                throw new \Exception('Credit not found');
            }

            // Allow status update validation
            $validated = apply_filters('cobra_ai_validate_status_update', 
                true, 
                $credit, 
                $status, 
                $args
            );

            if (!$validated) {
                throw new \Exception('Status update validation failed');
            }

            $result = $wpdb->update(
                $this->table,
                ['status' => $status],
                ['id' => $credit_id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Update user balance if status change affects availability
            if (in_array($status, ['expired', 'deleted']) || $credit->status === 'active') {
                $this->update_user_balance($credit->user_id);
            }

            do_action('cobra_ai_credit_status_updated', $credit_id, $status, $credit->status);

            return true;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to update credit status: ' . $e->getMessage(), [
                'credit_id' => $credit_id,
                'status' => $status
            ]);
            return false;
        }
    }

    /**
     * Get credit by ID
     *
     * @param int $credit_id Credit ID
     * @return object|null  Credit object or null
     */
    public function get_credit(int $credit_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $credit_id
        ));
    }

    /**
     * Update user balance
     *
     * @param int $user_id User ID
     */
    public function update_user_balance(int $user_id): void {
        global $wpdb;

        // Calculate total available credits
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credit - consumed) 
            FROM {$this->table} 
            WHERE user_id = %d 
            AND status = 'active'
            AND (expiration_date IS NULL OR expiration_date > %s)",
            $user_id,
            current_time('mysql')
        ));

        update_user_meta($user_id, '_cobra_ai_credit_balance', (float)$total);

        do_action('cobra_ai_user_balance_updated', $user_id, $total);
    }

    /**
     * Process expired credits
     */
    public function process_expired_credits(): void {
        global $wpdb;

        $expired = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
            WHERE status = 'active' 
            AND expiration_date IS NOT NULL 
            AND expiration_date <= %s",
            current_time('mysql')
        ));

        foreach ($expired as $credit) {
            $this->update_credit_status($credit->id, 'expired');
        }
    }

    /**
     * Handle user deletion
     *
     * @param int $user_id User ID
     */
    public function handle_user_deletion(int $user_id): void {
        global $wpdb;

        // Mark all user's credits as deleted
        $wpdb->update(
            $this->table,
            ['status' => 'deleted'],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        );

        // Remove user meta
        delete_user_meta($user_id, '_cobra_ai_credit_balance');
    }

    /**
     * Get user credit summary
     *
     * @param int   $user_id User ID
     * @param array $args    Query arguments
     * @return array        Credit summary
     */
    public function get_user_credit_summary(int $user_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'credit_type' => null,
            'status' => 'active'
        ];

        $args = wp_parse_args($args, $defaults);
        $where = ['user_id = %d'];
        $params = [$user_id];

        if ($args['credit_type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['credit_type'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        $query = "SELECT 
            SUM(credit) as total_credits,
            SUM(consumed) as total_consumed,
            SUM(credit - consumed) as available_credits,
            COUNT(*) as credit_count
            FROM {$this->table}
            WHERE " . implode(' AND ', $where);

        $summary = $wpdb->get_row($wpdb->prepare($query, $params), ARRAY_A);

        // Add additional info
        $summary['next_expiration'] = $this->get_next_expiration($user_id, $args['credit_type']);
        $summary['credit_types'] = $this->get_user_credit_types($user_id);

        return $summary;
    }

    /**
     * Get next credit expiration
     *
     * @param int         $user_id     User ID
     * @param string|null $credit_type Credit type
     * @return string|null            Next expiration date or null
     */
    public function get_next_expiration(int $user_id, ?string $credit_type = null): ?string {
        global $wpdb;

        $where = [
            'user_id = %d',
            'status = "active"',
            'expiration_date IS NOT NULL',
            'expiration_date > NOW()'
        ];

        $params = [$user_id];

        if ($credit_type) {
            $where[] = 'credit_type = %s';
            $params[] = $credit_type;
        }

        $query = "SELECT MIN(expiration_date) 
                 FROM {$this->table} 
                 WHERE " . implode(' AND ', $where);

        return $wpdb->get_var($wpdb->prepare($query, $params));
    }

    /**
     * Get user credit types
     *
     * @param int $user_id User ID
     * @return array      Array of credit types with amounts
     */
    public function get_user_credit_types(int $user_id): array {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT credit_type, 
                    SUM(credit) as total_credit,
                    SUM(consumed) as total_consumed,
                    SUM(credit - consumed) as available
             FROM {$this->table}
             WHERE user_id = %d 
             AND status = 'active'
             AND (expiration_date IS NULL OR expiration_date > NOW())
             GROUP BY credit_type",
            $user_id
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        $types = [];

        foreach ($results as $row) {
            $types[$row['credit_type']] = [
                'total' => (float)$row['total_credit'],
                'consumed' => (float)$row['total_consumed'],
                'available' => (float)$row['available']
            ];
        }

        return $types;
    }

    /**
     * Transfer credits between users
     *
     * @param int   $from_user_id Source user ID
     * @param int   $to_user_id   Target user ID
     * @param float $amount       Amount to transfer
     * @param array $args         Additional arguments
     * @return bool              Success status
     */
    public function transfer_credits(int $from_user_id, int $to_user_id, float $amount, array $args = []): bool {
        global $wpdb;

        try {
            $defaults = [
                'credit_type' => null,
                'comment' => '',
                'meta' => []
            ];

            $args = wp_parse_args($args, $defaults);

            // Validate amount
            if ($amount <= 0) {
                throw new \Exception('Invalid transfer amount');
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Get available credits
            $credits = $this->get_available_credits($from_user_id, $args['credit_type']);

            // Calculate total available
            $total_available = array_reduce($credits, function($carry, $credit) {
                return $carry + ($credit->credit - $credit->consumed);
            }, 0);

            if ($total_available < $amount) {
                throw new \Exception('Insufficient credits for transfer');
            }

            // Consume credits from source user
            $consumed = $this->consume_credits($from_user_id, $amount, [
                'credit_type' => $args['credit_type'],
                'comment' => 'Transfer to user #' . $to_user_id . ' - ' . $args['comment'],
                'meta' => array_merge($args['meta'], ['transfer_to' => $to_user_id])
            ]);

            if (!$consumed) {
                throw new \Exception('Failed to consume credits from source user');
            }

            // Add credits to target user
            $added = $this->add_credit($to_user_id, $amount, $args['credit_type'] ?? 'transfer', [
                'comment' => 'Transfer from user #' . $from_user_id . ' - ' . $args['comment'],
                'meta' => array_merge($args['meta'], ['transfer_from' => $from_user_id])
            ]);

            if (!$added) {
                throw new \Exception('Failed to add credits to target user');
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            // Fire action
            do_action('cobra_ai_credits_transferred', $from_user_id, $to_user_id, $amount, $args);

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            cobra_ai_db()->log('error', 'Failed to transfer credits: ' . $e->getMessage(), [
                'from_user' => $from_user_id,
                'to_user' => $to_user_id,
                'amount' => $amount,
                'args' => $args
            ]);

            return false;
        }
    }

    /**
     * Get credit transactions history
     *
     * @param array $args Query arguments
     * @return array     Array of transactions
     */
    public function get_transactions(array $args = []): array {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'credit_type' => null,
            'status' => null,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'limit' => 20,
            'offset' => 0,
            'start_date' => null,
            'end_date' => null
        ];

        $args = wp_parse_args($args, $defaults);
        $where = [];
        $params = [];

        // Build where clause
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $params[] = $args['user_id'];
        }

        if ($args['credit_type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['credit_type'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['start_date']) {
            $where[] = 'created_at >= %s';
            $params[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where[] = 'created_at <= %s';
            $params[] = $args['end_date'];
        }

        // Build query
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        // Add ordering
        $query .= sprintf(' ORDER BY %s %s', 
            esc_sql($args['orderby']), 
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        // Add limit
        $query .= $wpdb->prepare(' LIMIT %d OFFSET %d', 
            $args['limit'], 
            $args['offset']
        );

        // Execute query
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get transaction count
     *
     * @param array $args Query arguments
     * @return int       Number of transactions
     */
    public function get_transaction_count(array $args = []): int {
        global $wpdb;

        $defaults = [
            'user_id' => null,
            'credit_type' => null,
            'status' => null,
            'start_date' => null,
            'end_date' => null
        ];

        $args = wp_parse_args($args, $defaults);
        $where = [];
        $params = [];

        // Build where clause
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $params[] = $args['user_id'];
        }

        if ($args['credit_type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['credit_type'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['start_date']) {
            $where[] = 'created_at >= %s';
            $params[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where[] = 'created_at <= %s';
            $params[] = $args['end_date'];
        }

        // Build query
        $query = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        // Execute query
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return (int)$wpdb->get_var($query);
    }

    /**
     * Get usage statistics
     *
     * @param array $args Query arguments
     * @return array     Usage statistics
     */
    public function get_usage_stats(array $args = []): array {
        global $wpdb;

        $defaults = [
            'period' => 'daily', // daily, weekly, monthly
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d'),
            'credit_type' => null
        ];

        $args = wp_parse_args($args, $defaults);

        // Define date format based on period
        switch ($args['period']) {
            case 'weekly':
                $date_format = 'DATE_FORMAT(created_at, "%x-%v")'; // Year-Week
                break;
            case 'monthly':
                $date_format = 'DATE_FORMAT(created_at, "%Y-%m")'; // Year-Month
                break;
            default:
                $date_format = 'DATE(created_at)'; // Daily
        }

        $where = ['created_at BETWEEN %s AND %s'];
        $params = [$args['start_date'], $args['end_date']];

        if ($args['credit_type']) {
            $where[] = 'credit_type = %s';
            $params[] = $args['credit_type'];
        }

        $query = $wpdb->prepare(
            "SELECT 
                {$date_format} as period,
                COUNT(*) as transactions,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(credit) as total_credits,
                SUM(consumed) as total_consumed
            FROM {$this->table}
            WHERE " . implode(' AND ', $where) . "
            GROUP BY period
            ORDER BY period ASC",
            $params
        );

        return $wpdb->get_results($query, ARRAY_A);
    }
}