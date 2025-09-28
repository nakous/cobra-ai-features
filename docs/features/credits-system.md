# Système de Crédits - Cobra AI Features

## 💰 Vue d'ensemble

Le système de crédits permet de gérer l'utilisation des fonctionnalités payantes du plugin. Il offre une gestion flexible avec différents types de crédits, expiration automatique, et intégration avec les autres fonctionnalités.

## 🏗️ Architecture du Système de Crédits

### Structure des Fichiers
```
features/credits/
├── Feature.php                    # Classe principale
├── includes/
│   ├── CreditAdmin.php           # Interface d'administration
│   ├── CreditManager.php         # Gestionnaire de crédits
│   ├── CreditType.php            # Gestion des types
│   ├── CreditCron.php            # Tâches automatisées
│   └── Class_Credits_List_Table.php  # Table admin
├── assets/
│   ├── css/
│   ├── js/
│   └── help.html
└── views/
    ├── settings.php              # Configuration
    ├── add-credit.php            # Ajout manuel
    ├── credit-types.php          # Gestion des types
    ├── user-credits.php          # Crédits utilisateur
    └── reports.php               # Rapports
```

## 📊 Base de Données

### Table Credits Principale

```sql
CREATE TABLE cobra_credits (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    credit_type varchar(50) NOT NULL,
    amount decimal(10,2) NOT NULL,
    remaining decimal(10,2) NOT NULL,
    expires_at datetime,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status enum('active','expired','used') NOT NULL DEFAULT 'active',
    source varchar(100) NOT NULL DEFAULT 'manual',
    reference varchar(255),
    PRIMARY KEY (id),
    KEY user_type (user_id,credit_type),
    KEY status (status),
    KEY expires_at (expires_at),
    KEY source (source)
);
```

### Table Types de Crédits

```sql
CREATE TABLE cobra_credit_types (
    id varchar(50) NOT NULL,
    name varchar(100) NOT NULL,
    description text,
    unit_value decimal(10,4) NOT NULL DEFAULT 1.0000,
    default_expiry_days int,
    is_active boolean NOT NULL DEFAULT true,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY is_active (is_active)
);
```

### Explication des Champs

#### Table Credits
- **user_id** : ID de l'utilisateur WordPress
- **credit_type** : Type de crédit (ai_tokens, premium_features, etc.)
- **amount** : Montant initial de crédits
- **remaining** : Crédits restants
- **expires_at** : Date d'expiration (null = jamais)
- **status** : Statut (active, expired, used)
- **source** : Source (manual, purchase, bonus, etc.)
- **reference** : Référence externe (transaction ID, etc.)

#### Table Credit Types
- **id** : Identifiant unique du type
- **name** : Nom affiché du type
- **unit_value** : Valeur unitaire (pour conversions)
- **default_expiry_days** : Durée de vie par défaut

## ⚙️ Configuration

### Options par Défaut

```php
protected function get_feature_default_options(): array
{
    return [
        'general' => [
            'enable_expiration' => true,
            'default_expiry_days' => 365,
            'auto_cleanup_expired' => true,
            'cleanup_after_days' => 30,
            'enable_negative_balance' => false,
            'negative_limit' => 0
        ],
        'types' => [
            'ai_tokens' => [
                'name' => 'AI Tokens',
                'description' => 'Crédits pour les requêtes IA',
                'unit_value' => 1.0000,
                'default_expiry_days' => 365,
                'is_active' => true
            ],
            'premium_features' => [
                'name' => 'Premium Features',
                'description' => 'Accès aux fonctionnalités premium',
                'unit_value' => 1.0000,
                'default_expiry_days' => 30,
                'is_active' => true
            ]
        ],
        'limits' => [
            'max_credits_per_user' => 10000,
            'max_credits_per_transaction' => 1000,
            'min_credits_for_transfer' => 10
        ],
        'notifications' => [
            'low_balance_threshold' => 10,
            'expiration_warning_days' => 7,
            'enable_email_notifications' => true
        ]
    ];
}
```

## 🚀 Classes Principales

### CreditManager
Gestionnaire principal du système de crédits :

```php
class CreditManager
{
    private $feature;
    private $table;
    
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->table = $this->feature->get_table_name('credits');
        $this->init_hooks();
    }
    
    /**
     * Ajouter des crédits à un utilisateur
     */
    public function add_credits(int $user_id, string $credit_type, float $amount, array $options = []): int
    {
        global $wpdb;
        
        $credit_type_info = CreditType::get($credit_type);
        if (!$credit_type_info) {
            throw new \Exception("Invalid credit type: $credit_type");
        }
        
        $expires_at = null;
        if ($credit_type_info['default_expiry_days']) {
            $expires_at = date('Y-m-d H:i:s', 
                strtotime("+{$credit_type_info['default_expiry_days']} days")
            );
        }
        
        $data = [
            'user_id' => $user_id,
            'credit_type' => $credit_type,
            'amount' => $amount,
            'remaining' => $amount,
            'expires_at' => $expires_at,
            'status' => 'active',
            'source' => $options['source'] ?? 'manual',
            'reference' => $options['reference'] ?? null
        ];
        
        $wpdb->insert($this->table, $data);
        $credit_id = $wpdb->insert_id;
        
        // Déclencher action
        do_action('cobra_ai_credit_added', $user_id, $amount, $credit_type, $credit_id);
        
        $this->feature->log('info', "Credits added", [
            'user_id' => $user_id,
            'amount' => $amount,
            'type' => $credit_type,
            'credit_id' => $credit_id
        ]);
        
        return $credit_id;
    }
    
    /**
     * Consommer des crédits
     */
    public function consume_credits(int $user_id, string $credit_type, float $amount): bool
    {
        global $wpdb;
        
        // Vérifier la disponibilité
        if (!$this->has_sufficient_credits($user_id, $credit_type, $amount)) {
            return false;
        }
        
        // Hook avant consommation
        do_action('cobra_ai_before_consume_credit', $user_id, $credit_type, $amount);
        
        // Récupérer les crédits actifs par ordre de priorité (expiration)
        $credits = $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = %d 
                  AND credit_type = %s 
                  AND status = 'active' 
                  AND remaining > 0
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY expires_at IS NULL, expires_at ASC
            ", $user_id, $credit_type)
        );
        
        $remaining_to_consume = $amount;
        
        foreach ($credits as $credit) {
            if ($remaining_to_consume <= 0) break;
            
            $to_consume = min($remaining_to_consume, $credit->remaining);
            $new_remaining = $credit->remaining - $to_consume;
            $new_status = $new_remaining <= 0 ? 'used' : 'active';
            
            // Mettre à jour le crédit
            $wpdb->update(
                $this->table,
                [
                    'remaining' => $new_remaining,
                    'status' => $new_status,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $credit->id]
            );
            
            $remaining_to_consume -= $to_consume;
        }
        
        // Hook après consommation
        do_action('cobra_ai_credit_consumed', $user_id, $credit_type, $amount);
        
        $this->feature->log('info', "Credits consumed", [
            'user_id' => $user_id,
            'amount' => $amount,
            'type' => $credit_type
        ]);
        
        return $remaining_to_consume <= 0;
    }
    
    /**
     * Obtenir le solde d'un utilisateur
     */
    public function get_balance(int $user_id, string $credit_type = null): array
    {
        global $wpdb;
        
        $where_type = $credit_type ? $wpdb->prepare("AND credit_type = %s", $credit_type) : "";
        
        $balances = $wpdb->get_results(
            $wpdb->prepare("
                SELECT 
                    credit_type,
                    SUM(remaining) as total_remaining,
                    COUNT(*) as active_credits,
                    MIN(expires_at) as next_expiration
                FROM {$this->table}
                WHERE user_id = %d 
                  AND status = 'active'
                  AND remaining > 0
                  AND (expires_at IS NULL OR expires_at > NOW())
                  {$where_type}
                GROUP BY credit_type
            ", $user_id),
            ARRAY_A
        );
        
        if ($credit_type) {
            return $balances[0] ?? [
                'credit_type' => $credit_type,
                'total_remaining' => 0,
                'active_credits' => 0,
                'next_expiration' => null
            ];
        }
        
        return $balances;
    }
}
```

### CreditType
Gestion des types de crédits :

```php
class CreditType
{
    /**
     * Créer un type de crédit
     */
    public static function create(string $id, array $data): bool
    {
        global $wpdb;
        
        $feature = cobra_ai()->get_feature('credits');
        $table = $feature->get_table_name('credit_types');
        
        return $wpdb->insert($table, array_merge([
            'id' => $id,
            'unit_value' => 1.0000,
            'is_active' => true
        ], $data));
    }
    
    /**
     * Obtenir un type de crédit
     */
    public static function get(string $id): ?array
    {
        global $wpdb;
        
        $feature = cobra_ai()->get_feature('credits');
        $table = $feature->get_table_name('credit_types');
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $id),
            ARRAY_A
        );
    }
    
    /**
     * Lister tous les types actifs
     */
    public static function get_active(): array
    {
        global $wpdb;
        
        $feature = cobra_ai()->get_feature('credits');
        $table = $feature->get_table_name('credit_types');
        
        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name",
            ARRAY_A
        );
    }
}
```

### CreditCron
Tâches automatisées :

```php
class CreditCron
{
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->init_cron();
    }
    
    private function init_cron(): void
    {
        // Tâche quotidienne pour les crédits expirés
        add_action('cobra_ai_daily_credit_check', [$this, 'process_expired_credits']);
        
        if (!wp_next_scheduled('cobra_ai_daily_credit_check')) {
            wp_schedule_event(time(), 'daily', 'cobra_ai_daily_credit_check');
        }
        
        // Tâche hebdomadaire de nettoyage
        add_action('cobra_ai_weekly_credit_cleanup', [$this, 'cleanup_old_credits']);
        
        if (!wp_next_scheduled('cobra_ai_weekly_credit_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'cobra_ai_weekly_credit_cleanup');
        }
    }
    
    /**
     * Traiter les crédits expirés
     */
    public function process_expired_credits(): void
    {
        global $wpdb;
        
        $table = $this->feature->get_table_name('credits');
        
        // Marquer comme expirés
        $expired_count = $wpdb->query("
            UPDATE {$table} 
            SET status = 'expired', updated_at = NOW()
            WHERE status = 'active' 
              AND expires_at IS NOT NULL 
              AND expires_at <= NOW()
        ");
        
        if ($expired_count > 0) {
            $this->feature->log('info', "Expired {$expired_count} credit entries");
            do_action('cobra_ai_credits_expired', $expired_count);
        }
    }
    
    /**
     * Nettoyer les anciens crédits
     */
    public function cleanup_old_credits(): void
    {
        $settings = $this->feature->get_settings();
        
        if (!$settings['general']['auto_cleanup_expired']) {
            return;
        }
        
        $cleanup_days = $settings['general']['cleanup_after_days'] ?? 30;
        
        global $wpdb;
        $table = $this->feature->get_table_name('credits');
        
        $deleted_count = $wpdb->query(
            $wpdb->prepare("
                DELETE FROM {$table}
                WHERE status IN ('expired', 'used')
                  AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $cleanup_days)
        );
        
        if ($deleted_count > 0) {
            $this->feature->log('info', "Cleaned up {$deleted_count} old credit entries");
        }
    }
}
```

## 🎯 Intégration avec Autres Fonctionnalités

### Consommation IA

```php
// Dans AIManager
public function send_request(string $prompt, array $options = []): array
{
    $user_id = $options['user_id'] ?? get_current_user_id();
    $credit_cost = $this->calculate_credit_cost($prompt, $options);
    
    // Vérifier et consommer les crédits
    $credit_manager = cobra_ai()->get_feature('credits')->manager;
    
    if (!$credit_manager->consume_credits($user_id, 'ai_tokens', $credit_cost)) {
        throw new \Exception('Insufficient credits');
    }
    
    // Procéder avec la requête IA
    $response = $this->make_api_request($prompt, $options);
    
    // Logger la consommation
    $this->tracking->log_request([
        'user_id' => $user_id,
        'prompt' => $prompt,
        'consumed' => $credit_cost,
        'response' => $response
    ]);
    
    return $response;
}
```

### Intégration Stripe

```php
// Après paiement Stripe réussi
public function handle_successful_payment($payment_intent): void
{
    $user_id = $this->get_user_from_payment($payment_intent);
    $package = $this->get_package_from_metadata($payment_intent);
    
    // Ajouter les crédits
    $credit_manager = cobra_ai()->get_feature('credits')->manager;
    $credit_manager->add_credits(
        $user_id,
        $package['credit_type'],
        $package['amount'],
        [
            'source' => 'stripe_payment',
            'reference' => $payment_intent->id
        ]
    );
}
```

## 🔧 Interface d'Administration

### CreditAdmin
Interface complète d'administration :

```php
class CreditAdmin
{
    public function add_menu_items(): void
    {
        // Menu principal des crédits
        add_submenu_page(
            'cobra-ai-dashboard',
            __('Credits Management', 'cobra-ai'),
            __('Credits', 'cobra-ai'),
            'manage_options',
            'cobra-ai-credits',
            [$this, 'render_credits_page']
        );
        
        // Sous-menus
        add_submenu_page(
            'cobra-ai-credits',
            __('Add Credits', 'cobra-ai'),
            __('Add Credits', 'cobra-ai'),
            'manage_options',
            'cobra-ai-add-credits',
            [$this, 'render_add_credits_page']
        );
    }
    
    public function render_credits_page(): void
    {
        $list_table = new Class_Credits_List_Table();
        $list_table->prepare_items();
        
        include $this->feature->get_path() . 'views/credits-list.php';
    }
}
```

### Formulaire d'Ajout Manuel

```php
public function handle_add_credits_form(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('add_credits', 'add_credits_nonce');
    
    $user_id = intval($_POST['user_id']);
    $credit_type = sanitize_text_field($_POST['credit_type']);
    $amount = floatval($_POST['amount']);
    $expires_days = intval($_POST['expires_days']);
    $note = sanitize_textarea_field($_POST['note']);
    
    try {
        $options = [
            'source' => 'manual_admin',
            'reference' => $note
        ];
        
        if ($expires_days > 0) {
            $options['expires_at'] = date('Y-m-d H:i:s', 
                strtotime("+{$expires_days} days")
            );
        }
        
        $credit_id = $this->feature->manager->add_credits(
            $user_id, 
            $credit_type, 
            $amount, 
            $options
        );
        
        add_settings_error(
            'cobra_ai_credits',
            'credits_added',
            sprintf(__('Successfully added %s %s credits to user.', 'cobra-ai'), 
                number_format($amount, 2), $credit_type),
            'success'
        );
        
    } catch (Exception $e) {
        add_settings_error(
            'cobra_ai_credits',
            'credits_error',
            $e->getMessage(),
            'error'
        );
    }
}
```

## 📊 Rapports et Analytics

### Rapports de Crédits

```php
public function generate_credits_report(array $filters = []): array
{
    global $wpdb;
    
    $table = $this->feature->get_table_name('credits');
    
    // Résumé général
    $summary = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_entries,
            COUNT(DISTINCT user_id) as unique_users,
            SUM(amount) as total_issued,
            SUM(remaining) as total_remaining,
            SUM(amount - remaining) as total_consumed
        FROM {$table}
        WHERE status = 'active'
    ", ARRAY_A);
    
    // Par type de crédit
    $by_type = $wpdb->get_results("
        SELECT 
            credit_type,
            COUNT(*) as entries,
            COUNT(DISTINCT user_id) as users,
            SUM(amount) as issued,
            SUM(remaining) as remaining,
            AVG(amount) as avg_amount
        FROM {$table}
        WHERE status = 'active'
        GROUP BY credit_type
        ORDER BY issued DESC
    ", ARRAY_A);
    
    // Expiration prochaine
    $expiring_soon = $wpdb->get_results("
        SELECT 
            user_id,
            credit_type,
            SUM(remaining) as expiring_amount,
            MIN(expires_at) as expires_at
        FROM {$table}
        WHERE status = 'active'
          AND expires_at IS NOT NULL
          AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        GROUP BY user_id, credit_type
        ORDER BY expires_at ASC
    ", ARRAY_A);
    
    return [
        'summary' => $summary,
        'by_type' => $by_type,
        'expiring_soon' => $expiring_soon,
        'generated_at' => current_time('mysql')
    ];
}
```

## 🎨 Shortcodes et Frontend

### Shortcodes Disponibles

```php
// Afficher le solde utilisateur
add_shortcode('cobra_credits_balance', [$this, 'shortcode_balance']);

// Historique des crédits
add_shortcode('cobra_credits_history', [$this, 'shortcode_history']);

// Acheter des crédits
add_shortcode('cobra_credits_purchase', [$this, 'shortcode_purchase']);
```

### Exemple d'Utilisation

```php
public function shortcode_balance($atts): string
{
    $atts = shortcode_atts([
        'user_id' => get_current_user_id(),
        'type' => null,
        'format' => 'table'
    ], $atts);
    
    if (!$atts['user_id']) {
        return '<p>' . __('Please log in to view your credits.', 'cobra-ai') . '</p>';
    }
    
    $balances = $this->feature->manager->get_balance(
        intval($atts['user_id']), 
        $atts['type']
    );
    
    if ($atts['format'] === 'simple') {
        return $this->render_simple_balance($balances);
    }
    
    return $this->render_balance_table($balances);
}
```

Le système de crédits offre une solution complète et flexible pour la monétisation et le contrôle d'utilisation des fonctionnalités du plugin.