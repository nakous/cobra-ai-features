<?php

namespace CobraAI\Features\AI;
use function CobraAI\{
    cobra_ai_db
};
class AITracking {
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
        $this->table = $this->feature->get_table_name('trackings');
    }

    /**
     * Create tracking entry
     */
    public function create_tracking(array $data): int {
        global $wpdb;

        try {
            // Prepare data
            $tracking_data = [
                'user_id' => $data['user_id'],
                'prompt' => $data['prompt'],
                'ai_provider' => $data['ai_provider'],
                'status' => 'pending',
                'ip' => $data['ip'] ?? $this->get_client_ip(),
                'meta_data' => isset($data['meta_data']) ? json_encode($data['meta_data']) : null,
                'response_type' => $data['response_type'] ?? 'text',
                'created_at' => current_time('mysql')
            ];

            // Insert tracking
            $result = $wpdb->insert(
                $this->table,
                $tracking_data,
                [
                    '%d', // user_id
                    '%s', // prompt
                    '%s', // ai_provider
                    '%s', // status
                    '%s', // ip
                    '%s', // meta_data
                    '%s', // response_type
                    '%s'  // created_at
                ]
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            $tracking_id = $wpdb->insert_id;

            // Fire action
            do_action('cobra_ai_tracking_created', $tracking_id, $tracking_data);

            return $tracking_id;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to create tracking: ' . $e->getMessage(), [
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Update tracking entry
     */
    public function update_tracking(int $tracking_id, array $data): bool {
        global $wpdb;

        try {
            // Prepare data
            $update_data = [];
            $formats = [];

            if (isset($data['response'])) {
                $update_data['response'] = $data['response'];
                $formats[] = '%s';
            }

            if (isset($data['consumed'])) {
                $update_data['consumed'] = $data['consumed'];
                $formats[] = '%d';
            }

            if (isset($data['status'])) {
                $update_data['status'] = $data['status'];
                $formats[] = '%s';
            }

            if (isset($data['meta_data'])) {
                $update_data['meta_data'] = is_string($data['meta_data']) 
                    ? $data['meta_data'] 
                    : json_encode($data['meta_data']);
                $formats[] = '%s';
            }

            if (empty($update_data)) {
                return false;
            }

            // Update tracking
            $result = $wpdb->update(
                $this->table,
                $update_data,
                ['id' => $tracking_id],
                $formats,
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Fire action
            do_action('cobra_ai_tracking_updated', $tracking_id, $update_data);

            return true;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to update tracking: ' . $e->getMessage(), [
                'tracking_id' => $tracking_id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get tracking entry
     */
    public function get_tracking(int $tracking_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $tracking_id
        ));
    }

    /**
     * Delete tracking entry
     */
    public function delete_tracking(int $tracking_id): bool {
        global $wpdb;

        try {
            $result = $wpdb->delete(
                $this->table,
                ['id' => $tracking_id],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Fire action
            do_action('cobra_ai_tracking_deleted', $tracking_id);

            return true;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to delete tracking: ' . $e->getMessage(), [
                'tracking_id' => $tracking_id
            ]);
            throw $e;
        }
    }

    /**
     * Get user trackings
     */
    /**
     * Get user trackings
     * If user is admin and user_id is 0, returns all trackings
     */
    public function get_user_trackings(int $user_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'provider' => '',
            'status' => '',
            'response_type' => '',
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'period' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        // Build query
        $where = [];
        $params = [];

        // Only filter by user_id if:
        // 1. User is not an admin, or
        // 2. A specific user_id was requested
        if (!current_user_can('manage_options') || $user_id > 0) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }

        // Add other filters
        if (!empty($args['provider'])) {
            $where[] = 'ai_provider = %s';
            $params[] = $args['provider'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['response_type'])) {
            $where[] = 'response_type = %s';
            $params[] = $args['response_type'];
        }

        if (!empty($args['period'])) {
            switch ($args['period']) {
                case 'today':
                    $where[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
                    break;
                case 'month':
                    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                    break;
            }
        }

        // Build the query
        $query = "SELECT t.*, u.display_name, u.user_email 
                 FROM {$this->table} t
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID";

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        // Add ordering
        $query .= sprintf(' ORDER BY %s %s', 
            esc_sql($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        // Add pagination
        if ($args['limit'] > 0) {
            $query .= ' LIMIT %d OFFSET %d';
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        // Execute query
        $prepared_query = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        try {
            $results = $wpdb->get_results($prepared_query);
            
            // Log query for debugging if needed
            cobra_ai_db()->log('debug', 'Trackings query executed', [
                'query' => $prepared_query,
                'results_count' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to get trackings: ' . $e->getMessage(), [
                'query' => $prepared_query,
                'user_id' => $user_id,
                'args' => $args
            ]);
            return [];
        }
    }

     /**
     * Get user tracking count with same admin logic
     */
    public function get_user_tracking_count(int $user_id, array $args = []): int {
        global $wpdb;

        $defaults = [
            'provider' => '',
            'status' => '',
            'response_type' => '',
            'period' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        // Build query
        $where = [];
        $params = [];

        // Only filter by user_id if:
        // 1. User is not an admin, or
        // 2. A specific user_id was requested
        if (!current_user_can('manage_options') || $user_id > 0) {
            $where[] = 'user_id = %d';
            $params[] = $user_id;
        }

        if (!empty($args['provider'])) {
            $where[] = 'ai_provider = %s';
            $params[] = $args['provider'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['response_type'])) {
            $where[] = 'response_type = %s';
            $params[] = $args['response_type'];
        }

        if (!empty($args['period'])) {
            switch ($args['period']) {
                case 'today':
                    $where[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'week':
                    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
                    break;
                case 'month':
                    $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                    break;
            }
        }

        $query = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        return (int) $wpdb->get_var(
            !empty($params) ? $wpdb->prepare($query, $params) : $query
        );
    }
    /**
     * Get user tracking stats
     */
    public function get_user_tracking_stats(int $user_id): array {
        global $wpdb;

        return [
            'total' => $this->get_user_tracking_count($user_id),
            'today' => $this->get_user_tracking_count($user_id, ['period' => 'today']),
            'week' => $this->get_user_tracking_count($user_id, ['period' => 'week']),
            'month' => $this->get_user_tracking_count($user_id, ['period' => 'month']),
            'by_provider' => $this->get_user_tracking_by_provider($user_id),
            'by_type' => $this->get_user_tracking_by_type($user_id)
        ];
    }

    /**
     * Get user tracking by provider
     */
    private function get_user_tracking_by_provider(int $user_id): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ai_provider, COUNT(*) as count 
            FROM {$this->table} 
            WHERE user_id = %d 
            GROUP BY ai_provider",
            $user_id
        ));

        $stats = [];
        foreach ($results as $row) {
            $stats[$row->ai_provider] = (int)$row->count;
        }

        return $stats;
    }

    /**
     * Get user tracking by type
     */
    private function get_user_tracking_by_type(int $user_id): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT response_type, COUNT(*) as count 
            FROM {$this->table} 
            WHERE user_id = %d 
            GROUP BY response_type",
            $user_id
        ));

        $stats = [];
        foreach ($results as $row) {
            $stats[$row->response_type] = (int)$row->count;
        }

        return $stats;
    }

    /**
     * Get client IP
     */
    private function get_client_ip(): string {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Clean old trackings
     */
    public function clean_old_trackings(int $days = 30): int {
        global $wpdb;

        try {
            return $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));

        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to clean old trackings: ' . $e->getMessage());
            return 0;
        }
    }
}