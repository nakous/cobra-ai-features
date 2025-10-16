# Système d'IA - Cobra AI Features

## 🤖 Vue d'ensemble

La fonctionnalité AI du plugin Cobra AI Features offre une intégration complète avec plusieurs fournisseurs d'intelligence artificielle, un système de suivi détaillé et une gestion flexible des réponses.

## 🏗️ Architecture de la Fonctionnalité AI

### Structure des Fichiers
```
features/ai/
├── Feature.php                    # Classe principale
├── includes/
│   ├── AIAdmin.php               # Interface d'administration
│   ├── AIManager.php             # Gestionnaire principal IA
│   ├── AIProvider.php            # Classe abstraite pour providers
│   ├── AITracking.php            # Système de suivi
│   ├── Class_Tracking_List_Table.php  # Table admin des trackings
│   └── Providers/
│       ├── OpenAIProvider.php    # Provider OpenAI
│       ├── ClaudeProvider.php    # Provider Claude
│       └── CustomProvider.php    # Provider personnalisé
├── assets/
│   ├── css/
│   ├── js/
│   └── help.html
└── views/
    ├── settings.php              # Configuration
    ├── admin/                    # Pages admin
    └── profile/                  # Interface utilisateur
```

## 📊 Base de Données

### Table AI Trackings
La table `cobra_ai_trackings` stocke toutes les interactions avec les APIs d'IA :

```sql
CREATE TABLE cobra_ai_trackings (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    prompt text NOT NULL,
    ai_provider varchar(50) NOT NULL,
    response longtext,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed int NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'completed',
    ip varchar(45),
    meta_data longtext,
    response_type varchar(20) NOT NULL DEFAULT 'text',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY ai_provider (ai_provider),
    KEY created_at (created_at),
    KEY status (status),
    KEY response_type (response_type)
);
```

### Champs de la Table

- **id** : Identifiant unique
- **user_id** : ID de l'utilisateur WordPress
- **prompt** : La requête envoyée à l'IA
- **ai_provider** : Fournisseur utilisé (openai, claude, etc.)
- **response** : Réponse de l'IA
- **created_at** : Date de création
- **consumed** : Crédits consommés
- **status** : Statut (completed, error, pending)
- **ip** : Adresse IP de l'utilisateur
- **meta_data** : Données supplémentaires (JSON)
- **response_type** : Type de réponse (text, json, html)

## ⚙️ Configuration

### Options par Défaut

```php
protected function get_feature_default_options(): array
{
    return [
        'providers' => [
            'openai' => [
                'active' => true,
                'name' => 'OpenAI',
                'api_key' => '',
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'rate_limit' => 60,
                'timeout' => 30
            ],
            'claude' => [
                'active' => false,
                'name' => 'Claude',
                'api_key' => '',
                'model' => 'claude-3-sonnet',
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'rate_limit' => 30,
                'timeout' => 30
            ]
        ],
        'tracking' => [
            'enable_logging' => true,
            'log_responses' => true,
            'retention_days' => 90,
            'enable_user_tracking' => true
        ],
        'limits' => [
            'daily_requests' => 100,
            'monthly_requests' => 1000,
            'max_prompt_length' => 4000,
            'max_response_length' => 8000
        ],
        'features' => [
            'enable_caching' => true,
            'cache_duration' => 3600,
            'enable_rate_limiting' => true,
            'require_credits' => false
        ]
    ];
}
```

## 🚀 Utilisation de l'API

### Classes Principales

#### AIManager
Le gestionnaire principal qui coordonne toutes les opérations :

```php
class AIManager
{
    private $feature;
    private $providers = [];
    private $tracking;
    
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->tracking = new AITracking($feature);
        $this->load_providers();
    }
    
    public function send_request(string $prompt, array $options = []): array
    {
        // Logique d'envoi de requête
    }
}
```

#### AIProvider (Classe Abstraite)
Base pour tous les fournisseurs d'IA :

```php
abstract class AIProvider
{
    abstract public function send_request(string $prompt, array $options = []): array;
    abstract public function get_name(): string;
    abstract public function is_configured(): bool;
    
    protected function validate_response($response): bool
    {
        // Validation commune
    }
}
```

#### OpenAIProvider
Implémentation spécifique pour OpenAI :

```php
class OpenAIProvider extends AIProvider
{
    public function send_request(string $prompt, array $options = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];
        
        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $options['max_tokens'] ?? $this->max_tokens,
            'temperature' => $options['temperature'] ?? $this->temperature
        ];
        
        return $this->make_request($headers, $body);
    }
}
```

### Système de Tracking

#### AITracking
Gère le suivi de toutes les interactions :

```php
class AITracking
{
    public function log_request(array $data): int
    {
        global $wpdb;
        
        $table_name = $this->feature->get_table_name('trackings');
        
        $wpdb->insert($table_name, [
            'user_id' => $data['user_id'],
            'prompt' => $data['prompt'],
            'ai_provider' => $data['provider'],
            'response' => $data['response'] ?? null,
            'consumed' => $data['consumed'] ?? 0,
            'status' => $data['status'] ?? 'pending',
            'ip' => $this->get_user_ip(),
            'meta_data' => json_encode($data['meta'] ?? []),
            'response_type' => $data['response_type'] ?? 'text'
        ]);
        
        return $wpdb->insert_id;
    }
    
    public function update_tracking(int $id, array $data): bool
    {
        global $wpdb;
        
        return $wpdb->update(
            $this->feature->get_table_name('trackings'),
            $data,
            ['id' => $id]
        );
    }
}
```

## 🔧 Interface d'Administration

### AIAdmin
Gère l'interface d'administration :

```php
class AIAdmin
{
    public function add_menu_items(): void
    {
        add_submenu_page(
            $this->parent_slug,
            __('AI Trackings', 'cobra-ai'),
            __('AI Trackings', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_trackings_page']
        );
    }
    
    public function render_trackings_page(): void
    {
        $list_table = new Class_Tracking_List_Table();
        $list_table->prepare_items();
        
        include $this->feature->get_path() . 'views/admin/trackings.php';
    }
}
```

### Table des Trackings
Liste personnalisée pour l'administration :

```php
class Class_Tracking_List_Table extends WP_List_Table
{
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'cobra-ai'),
            'user' => __('User', 'cobra-ai'),
            'provider' => __('Provider', 'cobra-ai'),
            'prompt' => __('Prompt', 'cobra-ai'),
            'status' => __('Status', 'cobra-ai'),
            'consumed' => __('Credits', 'cobra-ai'),
            'created_at' => __('Date', 'cobra-ai')
        ];
    }
    
    public function column_prompt($item): string
    {
        $prompt = wp_trim_words($item->prompt, 10);
        return esc_html($prompt);
    }
}
```

## 🎯 API REST

### Endpoints Disponibles

```php
public function register_rest_routes(): void
{
    // Envoyer une requête IA
    register_rest_route('cobra-ai/v1', '/ai/request', [
        'methods' => 'POST',
        'callback' => [$this, 'handle_ai_request'],
        'permission_callback' => [$this, 'check_permissions'],
        'args' => [
            'prompt' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => [$this, 'validate_prompt']
            ],
            'provider' => [
                'type' => 'string',
                'default' => 'openai'
            ]
        ]
    ]);
    
    // Récupérer les trackings d'un utilisateur
    register_rest_route('cobra-ai/v1', '/ai/trackings', [
        'methods' => 'GET',
        'callback' => [$this, 'get_user_trackings'],
        'permission_callback' => 'is_user_logged_in'
    ]);
}
```

### Handlers REST

```php
public function handle_ai_request(WP_REST_Request $request): WP_REST_Response
{
    $prompt = $request->get_param('prompt');
    $provider = $request->get_param('provider');
    $user_id = get_current_user_id();
    
    try {
        // Vérifier les limites
        if (!$this->check_user_limits($user_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Daily limit exceeded', 'cobra-ai')
            ], 429);
        }
        
        // Envoyer la requête
        $response = $this->manager->send_request($prompt, [
            'provider' => $provider,
            'user_id' => $user_id
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $response
        ]);
        
    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
```

## 🎨 Interface Utilisateur

### Shortcodes

```php
// Formulaire de requête IA simple
add_shortcode('cobra_ai_form', [$this, 'render_ai_form']);

// Historique des requêtes utilisateur
add_shortcode('cobra_ai_history', [$this, 'render_user_history']);

// Statistiques utilisateur
add_shortcode('cobra_ai_stats', [$this, 'render_user_stats']);
```

### JavaScript Frontend

```javascript
class CobraAIClient {
    constructor(options) {
        this.apiUrl = options.apiUrl;
        this.nonce = options.nonce;
    }
    
    async sendRequest(prompt, provider = 'openai') {
        const response = await fetch(`${this.apiUrl}/ai/request`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            },
            body: JSON.stringify({
                prompt: prompt,
                provider: provider
            })
        });
        
        return await response.json();
    }
    
    async getTrackings(page = 1, perPage = 20) {
        const response = await fetch(
            `${this.apiUrl}/ai/trackings?page=${page}&per_page=${perPage}`,
            {
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            }
        );
        
        return await response.json();
    }
}
```

## 🔒 Sécurité et Limitations

### Rate Limiting

```php
private function check_rate_limit(int $user_id, string $provider): bool
{
    $cache_key = "cobra_ai_rate_limit_{$user_id}_{$provider}";
    $current_count = wp_cache_get($cache_key);
    
    if (false === $current_count) {
        $current_count = $this->get_hourly_requests($user_id, $provider);
        wp_cache_set($cache_key, $current_count, '', HOUR_IN_SECONDS);
    }
    
    $settings = $this->feature->get_settings();
    $limit = $settings['providers'][$provider]['rate_limit'] ?? 60;
    
    return $current_count < $limit;
}
```

### Validation des Données

```php
public function validate_prompt(string $prompt): bool
{
    $settings = $this->feature->get_settings();
    $max_length = $settings['limits']['max_prompt_length'] ?? 4000;
    
    if (strlen($prompt) > $max_length) {
        return false;
    }
    
    // Filtrer le contenu inapproprié
    if ($this->contains_inappropriate_content($prompt)) {
        return false;
    }
    
    return true;
}
```

## 📊 Analytics et Reporting

### Métriques Disponibles

- Nombre de requêtes par utilisateur
- Utilisation par fournisseur d'IA  
- Temps de réponse moyen
- Taux d'erreur
- Consommation de crédits
- Types de requêtes les plus populaires

### Génération de Rapports

```php
public function generate_usage_report(array $filters = []): array
{
    global $wpdb;
    
    $table_name = $this->feature->get_table_name('trackings');
    
    // Requêtes par jour
    $daily_stats = $wpdb->get_results("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as requests,
            AVG(consumed) as avg_credits,
            COUNT(DISTINCT user_id) as unique_users
        FROM {$table_name}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    
    // Top utilisateurs
    $top_users = $wpdb->get_results("
        SELECT 
            user_id,
            COUNT(*) as total_requests,
            SUM(consumed) as total_credits
        FROM {$table_name}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY user_id
        ORDER BY total_requests DESC
        LIMIT 10
    ");
    
    return [
        'daily_stats' => $daily_stats,
        'top_users' => $top_users,
        'period' => '30 days'
    ];
}
```

Cette fonctionnalité IA offre une solution complète pour intégrer l'intelligence artificielle dans WordPress avec un contrôle total sur l'utilisation, les coûts et les performances.