<?php

namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * Database management class
 */
class Database
{
    /**
     * Singleton instance
     */
    private static ?Database $instance = null;

    /**
     * Database version
     */
    private string $version = '1.0.0';

    /**
     * Core plugin tables schema
     */
    private array $core_tables = [];

    /**
     * Feature tables registry
     */
    private array $feature_tables = [];
    
    /**
     * Installation status cache
     */
    private bool $tables_installed = false;
    
    /**
     * Database connection cache
     */
    private $wpdb = null;
    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize database components
     */
    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Ne vérifier et installer les tables que si WordPress est complètement chargé
        if (function_exists('did_action') && (did_action('init') || did_action('wp_loaded'))) {
            $this->define_core_tables();
            $this->check_version();
        } else {
            // Attendre que WordPress soit chargé si les fonctions sont disponibles
            if (function_exists('add_action')) {
                add_action('init', [$this, 'delayed_init'], 1);
            } else {
                // Si WordPress n'est pas disponible, initialiser quand même pour éviter l'erreur
                $this->define_core_tables();
                // Mais ne pas vérifier la version
            }
        }
    }
    
    /**
     * Initialisation différée après que WordPress soit chargé
     */
    public function delayed_init(): void
    {
        $this->define_core_tables();
        $this->check_version();
    }

    /**
     * Define core plugin tables
     */
    private function define_core_tables(): void
    {
        global $wpdb;

        $this->core_tables = [
            // System logs table
            'system_logs' => [
                'name' => $wpdb->prefix . 'cobra_system_logs',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'level' => "enum('debug','info','warning','error') NOT NULL DEFAULT 'info'",
                    'source' => 'varchar(100) NOT NULL',
                    'message' => 'text NOT NULL',
                    'context' => 'longtext',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'level_created_at' => '(level,created_at)',
                        'source' => '(source)'
                    ]
                ]
            ],

            // Features registry table
            'features' => [
                'name' => $wpdb->prefix . 'cobra_features',
                'schema' => [
                    'id' => 'varchar(50) NOT NULL',
                    'name' => 'varchar(100) NOT NULL',
                    'version' => 'varchar(20) NOT NULL',
                    'status' => "enum('active','inactive','error') NOT NULL DEFAULT 'inactive'",
                    'settings' => 'longtext',
                    'installed_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'status' => '(status)',
                        'installed_at' => '(installed_at)'
                    ]
                ]
            ],

            // Feature dependencies table
            'dependencies' => [
                'name' => $wpdb->prefix . 'cobra_dependencies',
                'schema' => [
                    'feature_id' => 'varchar(50) NOT NULL',
                    'dependency_id' => 'varchar(50) NOT NULL',
                    'required_version' => 'varchar(20)',
                    'PRIMARY KEY' => '(feature_id,dependency_id)',
                    'KEY' => [
                        'feature_id' => '(feature_id)',
                        'dependency_id' => '(dependency_id)'
                    ]
                ]
            ],

            // Analytics table
            'analytics' => [
                'name' => $wpdb->prefix . 'cobra_analytics',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'feature_id' => 'varchar(50) NOT NULL',
                    'event_type' => 'varchar(50) NOT NULL',
                    'event_data' => 'longtext',
                    'user_id' => 'bigint(20)',
                    'timestamp' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'feature_event' => '(feature_id,event_type)',
                        'user_id' => '(user_id)',
                        'timestamp' => '(timestamp)'
                    ]
                ]
            ]
        ];
    }

    /**
     * Check database version and update if necessary
     */
    private function check_version(): void
    {
        // Ne vérifier la version que si WordPress est disponible
        if (!function_exists('get_option')) {
            return;
        }
        
        $stored_version = get_option('cobra_ai_db_version');

        if ($stored_version !== $this->version) {
            $this->install_or_upgrade();
        }
    }

    /**
     * Install or upgrade database tables
     */
    public function install_or_upgrade(): void
    {
        if ($this->tables_installed) {
            return;
        }
        
        // Vérifier que WordPress et les outils nécessaires sont disponibles
        if (!defined('ABSPATH') || !function_exists('dbDelta')) {
            if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            } else {
                // Créer les tables manuellement si dbDelta n'est pas disponible
                $this->create_tables_manually();
                return;
            }
        }
        
        global $wpdb;
        if (!$wpdb) {
            return; // $wpdb n'est pas disponible
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Create core tables
        foreach ($this->core_tables as $table_id => $table_info) {
            $sql = $this->generate_table_sql($table_info['name'], $table_info['schema'], $charset_collate);
            dbDelta($sql);
        }
        $this->tables_installed = true;
        // Update version only if WordPress function is available
        if (function_exists('update_option')) {
            update_option('cobra_ai_db_version', $this->version);
        }
    }
    
    /**
     * Create tables manually when dbDelta is not available
     */
    private function create_tables_manually(): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        foreach ($this->core_tables as $table_id => $table_info) {
            $sql = $this->generate_table_sql($table_info['name'], $table_info['schema'], $charset_collate);
            $wpdb->query($sql);
        }
        
        $this->tables_installed = true;
        
        if (function_exists('update_option')) {
            update_option('cobra_ai_db_version', $this->version);
        }
    }

    /**
     * Generate SQL for table creation
     */
    private function generate_table_sql(string $table_name, array $schema, string $charset_collate): string
    {
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (\n";

        // Add fields
        foreach ($schema as $field => $definition) {
            if ($field !== 'KEY' && $field !== 'PRIMARY KEY') {
                $sql .= "  $field $definition,\n";
            }
        }

        // Add primary key
        if (isset($schema['PRIMARY KEY'])) {
            $sql .= "  PRIMARY KEY " . $schema['PRIMARY KEY'] . ",\n";
        }

        // Add keys
        if (isset($schema['KEY'])) {
            foreach ($schema['KEY'] as $key_name => $definition) {
                $sql .= "  KEY $key_name $definition,\n";
            }
        }

        // Remove trailing comma and add charset
        $sql = rtrim($sql, ",\n") . "\n) $charset_collate;";

        return $sql;
    }

    /**
     * Register feature tables
     */
    public function register_feature_tables(string $feature_id, array $tables): void
    {
        $this->feature_tables[$feature_id] = $tables;
    }

    /**
     * Install feature tables
     */
    public function install_feature_tables(string $feature_id): bool
    {
        if (!isset($this->feature_tables[$feature_id])) {
            $this->log('error', "No tables registered for feature: $feature_id");
            return false;
        }

        try {
            global $wpdb;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $charset_collate = $wpdb->get_charset_collate();

            foreach ($this->feature_tables[$feature_id] as $table_info) {
                $sql = $this->generate_table_sql($table_info['name'], $table_info['schema'], $charset_collate);
                dbDelta($sql);
            }

            $this->log('info', "Tables installed for feature: $feature_id");
            return true;
        } catch (\Exception $e) {
            $this->log('error', "Failed to install tables for feature: $feature_id", [
                'error' => $e->getMessage(),
                'tables' => $this->feature_tables[$feature_id]
            ]);
            return false;
        }
    }

    /**
     * Uninstall feature tables
     */
    public function uninstall_feature_tables(string $feature_id, bool $preserve_data = false): bool
    {
        if (!isset($this->feature_tables[$feature_id])) {
            return false;
        }

        try {
            global $wpdb;

            if (!$preserve_data) {
                foreach ($this->feature_tables[$feature_id] as $table_info) {
                    $wpdb->query("DROP TABLE IF EXISTS {$table_info['name']}");
                }
            }

            $this->log('info', "Tables uninstalled for feature: $feature_id");
            return true;
        } catch (\Exception $e) {
            $this->log('error', "Failed to uninstall tables for feature: $feature_id", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unified logging system
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        try {
            // Validate log level
            $valid_levels = ['debug', 'info', 'warning', 'error', 'critical'];
            if (!in_array($level, $valid_levels)) {
                $level = 'info';
            }
            
            // Use database logging if tables are installed
            if ($this->tables_installed) {
                // Utiliser current_time si disponible, sinon date()
                $current_time = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
                $context_json = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
                
                return $this->wpdb->insert(
                    $this->core_tables['system_logs']['name'],
                    [
                        'level' => $level,
                        'source' => sanitize_text_field($_SERVER['REQUEST_URI'] ?? 'unknown'),
                        'message' => $message,
                        'context' => !empty($context) ? $context_json : null,
                        'created_at' => $current_time
                    ],
                    ['%s', '%s', '%s', '%s', '%s']
                ) !== false;
            }
            
            // Fallback to error_log
            $log_time = function_exists('current_time') ? current_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $formatted_message = sprintf(
                '[Cobra AI] %s - [%s] %s',
                $log_time,
                strtoupper($level),
                $message
            );
            
            if (!empty($context)) {
                $context_str = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
                $formatted_message .= ' ' . $context_str;
            }
            
            error_log($formatted_message);
            return true;
            
        } catch (\Exception $e) {
            // Emergency fallback
            error_log('Cobra AI Logging Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip(): string
    {
        // Check for various headers that may contain the real IP
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Emergency logging when normal logging fails
     */
    private function emergency_log(string $message, array $context = []): void
    {
        $formatted = sprintf(
            '[Cobra AI Emergency] %s - %s',
            date('Y-m-d H:i:s'),
            $message
        );
        
        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        error_log($formatted);
    }

    /**
    }

   

    /**
     * Record analytics event
     */
    public function record_analytics(string $feature_id, string $event_type, $event_data = null, $user_id = null): bool
    {
        try {
            global $wpdb;

            $user_id = $user_id ?? (function_exists('get_current_user_id') ? get_current_user_id() : null);
            
            return $wpdb->insert(
                $this->core_tables['analytics']['name'],
                [
                    'feature_id' => $feature_id,
                    'event_type' => $event_type,
                    'event_data' => $event_data ? json_encode($event_data) : null,
                    'user_id' => $user_id
                ],
                ['%s', '%s', '%s', '%d']
            );
        } catch (\Exception $e) {
            $this->log('error', "Failed to record analytics", [
                'error' => $e->getMessage(),
                'feature_id' => $feature_id,
                'event_type' => $event_type
            ]);
            return false;
        }
    }

    /**
     * Get analytics for feature
     */
    public function get_feature_analytics(string $feature_id, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'start_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'end_date' => current_time('mysql'),
            'event_type' => null,
            'limit' => 100
        ];

        $args = wp_parse_args($args, $defaults);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->core_tables['analytics']['name']}
            WHERE feature_id = %s
            AND timestamp BETWEEN %s AND %s",
            $feature_id,
            $args['start_date'],
            $args['end_date']
        );

        if ($args['event_type']) {
            $query .= $wpdb->prepare(" AND event_type = %s", $args['event_type']);
        }

        $query .= " ORDER BY timestamp DESC LIMIT " . intval($args['limit']);

        return $wpdb->get_results($query, ARRAY_A);
    }
    /**
     * Get recent logs
     *
     * @param int $limit Number of logs to retrieve
     * @param string|null $level Filter by log level
     * @return array Array of log entries
     */
    public function get_recent_logs(int $limit = 10, ?string $level = null): array {
        try {
            global $wpdb;

            // Base query
            $query = "SELECT * FROM {$this->core_tables['system_logs']['name']} ";
            $params = [];

            // Add level filter if specified
            if ($level !== null) {
                $query .= "WHERE level = %s ";
                $params[] = $level;
            }

            // Add order and limit
            $query .= "ORDER BY created_at DESC LIMIT %d";
            $params[] = $limit;

            // Prepare and execute query
            if (!empty($params)) {
                $query = $wpdb->prepare($query, ...$params);
            }

            $results = $wpdb->get_results($query);

            // Format the results
            return array_map(function($log) {
                return (object)[
                    'id' => $log->id,
                    'level' => $log->level,
                    'message' => $log->message,
                    'context' => $log->context ? json_decode($log->context, true) : null,
                    'created_at' => $log->created_at
                ];
            }, $results ?: []);

        } catch (\Exception $e) {
            $this->emergency_log('Error getting logs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clean old logs
     */
    public function cleanup_logs(int $days = 30): bool {
        try {
            global $wpdb;

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->core_tables['system_logs']['name']} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));

            return true;
        } catch (\Exception $e) {
            $this->emergency_log('Error cleaning logs', ['error' => $e->getMessage(), 'days' => $days]);
            return false;
        }
    }

    /**
     * Get log count by level
     */
    public function get_log_count(?string $level = null): int {
        try {
            global $wpdb;

            $query = "SELECT COUNT(*) FROM {$this->core_tables['system_logs']['name']}";
            $params = [];

            if ($level !== null) {
                $query .= " WHERE level = %s";
                $params[] = $level;
            }

            if (!empty($params)) {
                $query = $wpdb->prepare($query, ...$params);
            }

            return (int)$wpdb->get_var($query) ?: 0;

        } catch (\Exception $e) {
            error_log('Cobra AI Error getting log count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all logs
     */
    public function clear_logs(): bool {
        try {
            global $wpdb;
            
            $wpdb->query("TRUNCATE TABLE {$this->core_tables['system_logs']['name']}");
            
            return true;
        } catch (\Exception $e) {
            error_log('Cobra AI Error clearing logs: ' . $e->getMessage());
            return false;
        }
    }
}

// Initialize database
function cobra_ai_db(): Database
{
    return Database::get_instance();
}
