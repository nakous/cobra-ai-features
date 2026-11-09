<?php

namespace CobraAI\Features\StripePayments;

class Orders
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    private function table(): string
    {
        return $this->feature->get_table_name('stripe_orders');
    }

    /**
     * Create a pending order when a checkout session is created.
     */
    public function create_pending(array $data): ?int
    {
        global $wpdb;

        $defaults = [
            'user_id'         => 0,
            'product_id'      => 0,
            'product_type'    => 'standard',
            'session_id'      => null,
            'amount'          => 0,
            'currency'        => 'USD',
            'status'          => 'pending',
            'metadata'        => null,
        ];
        $row = array_intersect_key(array_merge($defaults, $data), $defaults);

        if (is_array($row['metadata'])) {
            $row['metadata'] = wp_json_encode($row['metadata']);
        }

        $ok = $wpdb->insert($this->table(), $row);
        return $ok ? (int) $wpdb->insert_id : null;
    }

    public function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id));
        return $row ?: null;
    }

    public function get_by_session(string $session_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE session_id = %s", $session_id));
        return $row ?: null;
    }

    public function get_by_payment_intent(string $intent_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE payment_intent_id = %s", $intent_id));
        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }
        return (bool) $wpdb->update($this->table(), $data, ['id' => $id]);
    }

    public function mark_paid(int $id, array $extra = []): bool
    {
        return $this->update($id, array_merge(['status' => 'paid'], $extra));
    }

    public function mark_failed(int $id, array $extra = []): bool
    {
        return $this->update($id, array_merge(['status' => 'failed'], $extra));
    }

    public function mark_refunded(int $id, array $extra = []): bool
    {
        return $this->update($id, array_merge(['status' => 'refunded'], $extra));
    }

    /**
     * Paginated listing for the admin orders page.
     */
    public function list(array $args = []): array
    {
        global $wpdb;
        $args = wp_parse_args($args, [
            'status' => '',
            'user_id' => 0,
            'product_type' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ]);

        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (!empty($args['product_type'])) {
            $where[] = 'product_type = %s';
            $params[] = $args['product_type'];
        }

        $where_sql = implode(' AND ', $where);
        $offset = max(0, ((int) $args['page'] - 1) * (int) $args['per_page']);
        $orderby = in_array($args['orderby'], ['id', 'created_at', 'amount', 'status'], true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = (int) $args['per_page'];
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $count_sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, array_slice($params, 0, -2)) : $count_sql);

        return [
            'rows' => $rows ?: [],
            'total' => $total,
            'per_page' => (int) $args['per_page'],
            'page' => (int) $args['page'],
        ];
    }
}
