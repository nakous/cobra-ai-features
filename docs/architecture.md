# Architecture du Plugin Cobra AI Features

## 🏛️ Vue d'ensemble de l'architecture

Le plugin Cobra AI Features utilise une architecture modulaire basée sur les principes SOLID et les design patterns modernes. Cette architecture permet une extensibilité maximale et une maintenance aisée.

## 🔧 Design Patterns Utilisés

### 1. Singleton Pattern
La classe principale `CobraAI` utilise le pattern Singleton pour s'assurer qu'une seule instance existe :

```php
final class CobraAI
{
    private static ?CobraAI $instance = null;
    
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### 2. Template Method Pattern
La classe `FeatureBase` utilise ce pattern pour définir le squelette des fonctionnalités :

```php
abstract class FeatureBase
{
    public function init(): bool
    {
        if (!$this->check_dependencies()) return false;
        if (!$this->check_requirements()) return false;
        
        $this->init_hooks();
        $this->register_shortcodes();
        
        if ($this->has_admin) {
            $this->init_admin();
        }
        
        return true;
    }
    
    abstract protected function setup(): void;
}
```

### 3. Registry Pattern
Le container de dépendances utilise ce pattern :

```php
private $container = [];

public function get(string $component)
{
    return $this->container[$component] ?? null;
}

public function set(string $component, $instance): void
{
    $this->container[$component] = $instance;
}
```

## 🏗️ Structure du Code

### Namespace Principal
```
CobraAI\
├── Database
├── Admin
├── APIManager
├── FeatureBase
└── Features\
    ├── AI\
    ├── Credits\
    ├── Contact\
    └── ...
```

### Organisation des Fichiers
```
cobra-ai-features/
├── cobra-ai-features.php    # Point d'entrée principal
├── includes/                # Classes core
│   ├── FeatureBase.php
│   ├── Database.php
│   ├── Admin.php
│   └── ...
├── features/               # Fonctionnalités modulaires
│   └── {feature}/
│       ├── Feature.php
│       ├── includes/
│       └── ...
└── admin/                 # Interface d'admin globale
```

## 🔄 Cycle de Vie du Plugin

### 1. Initialisation
```php
// 1. Définition des constantes
$this->define_constants();

// 2. Vérification des prérequis
if (!$this->check_requirements()) return;

// 3. Inclusion des fichiers
$this->include_files();

// 4. Initialisation DB
$this->init_database();

// 5. Container de dépendances
$this->init_container();

// 6. Hooks WordPress
$this->init_hooks();
```

### 2. Chargement des Fonctionnalités
```php
public function init_features(): void
{
    $active_features = get_option('cobra_ai_enabled_features', []);
    $feature_dirs = glob(COBRA_AI_FEATURES_DIR . '*', GLOB_ONLYDIR);
    
    foreach ($feature_dirs as $dir) {
        $feature_id = basename($dir);
        if (in_array($feature_id, $active_features)) {
            $this->load_feature($feature_id);
        }
    }
}
```

### 3. Activation/Désactivation
```php
register_activation_hook(__FILE__, [$this, 'activate']);
register_deactivation_hook(__FILE__, [$this, 'deactivate']);
```

## 🗃️ Gestion des Dépendances

### Container de Services
Le plugin utilise un container simple pour la gestion des dépendances :

```php
private function init_container(): void
{
    $this->container['db'] = $this->db;
    $this->container['api'] = $this->api = APIManager::get_instance();
    $this->container['admin'] = $this->admin = Admin::get_instance();
    $this->container['features'] = $this->features;
}
```

### Injection de Dépendances
Les fonctionnalités reçoivent les dépendances nécessaires :

```php
$feature = new $class_name();
// La fonctionnalité peut accéder aux services via cobra_ai()
$db = cobra_ai()->get('db');
$api = cobra_ai()->get('api');
```

## 🏛️ Architecture des Fonctionnalités

### Structure Standard
Chaque fonctionnalité hérite de `FeatureBase` :

```php
class Feature extends FeatureBase
{
    protected $feature_id = 'my-feature';
    protected $name = 'My Feature';
    protected $has_settings = true;
    protected $has_admin = true;
    
    protected function setup(): void
    {
        // Configuration spécifique
    }
    
    protected function init_hooks(): void
    {
        parent::init_hooks();
        // Hooks spécifiques à la fonctionnalité
    }
}
```

### Système de Tables
Les fonctionnalités définissent leurs tables :

```php
protected function setup(): void
{
    global $wpdb;
    
    $this->tables = [
        'my_table' => [
            'name' => $wpdb->prefix . 'cobra_my_table',
            'schema' => [
                'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                'name' => 'varchar(255) NOT NULL',
                'PRIMARY KEY' => '(id)'
            ]
        ]
    ];
}
```

## 📊 Gestion de la Base de Données

### Architecture Centralisée
La classe `Database` gère toutes les opérations de base de données :

```php
class Database
{
    private $core_tables = [];      // Tables du plugin
    private $feature_tables = [];   // Tables des fonctionnalités
    
    public function register_feature_tables(string $feature_id, array $tables)
    {
        $this->feature_tables[$feature_id] = $tables;
    }
    
    public function install_feature_tables(string $feature_id): bool
    {
        // Installation des tables d'une fonctionnalité
    }
}
```

### Schema Dynamique
Les tables sont créées dynamiquement :

```php
foreach ($this->feature_tables[$feature_id] as $table_info) {
    $sql = $this->generate_table_sql(
        $table_info['name'], 
        $table_info['schema'], 
        $charset_collate
    );
    dbDelta($sql);
}
```

## 🔌 Système de Hooks

### Hooks du Plugin
```php
// Chargement du plugin
do_action('cobra_ai_loaded');

// Fonctionnalités chargées
do_action('cobra_ai_features_loaded');

// Activation de fonctionnalité
do_action('cobra_ai_feature_activated_' . $feature_id, $feature);
```

### Hooks des Fonctionnalités
Chaque fonctionnalité peut définir ses propres hooks :

```php
// Dans la fonctionnalité AI
do_action('cobra_ai_before_api_request', $params);
do_action('cobra_ai_after_api_response', $response);

// Dans la fonctionnalité Credits  
do_action('cobra_ai_credit_added', $user_id, $amount, $type);
```

## 🛡️ Sécurité et Validation

### Validation Centralisée
```php
class Validator
{
    public static function validate_email(string $email): bool
    public static function validate_required(array $data, array $fields): array
    public static function sanitize_array(array $data): array
}
```

### Nonces et Permissions
```php
// Vérification des nonces
if (!wp_verify_nonce($_POST['nonce'], 'cobra-ai-action')) {
    wp_die('Security check failed');
}

// Vérification des capacités
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}
```

## 🚀 Performance et Optimisation

### Chargement Conditionnel
```php
// Ne charger que les fonctionnalités actives
if (in_array($feature_id, $active_features)) {
    $this->load_feature($feature_id);
}

// Chargement admin uniquement en admin
if (is_admin()) {
    $this->container['admin'] = Admin::get_instance();
}
```

### Mise en Cache
```php
// Cache des options
$settings = wp_cache_get('cobra_ai_settings');
if (false === $settings) {
    $settings = get_option('cobra_ai_settings');
    wp_cache_set('cobra_ai_settings', $settings);
}
```

## 📈 Monitoring et Logs

### Système de Logs Centralisé
```php
public function log(string $level, string $message, array $context = []): bool
{
    global $wpdb;
    
    return $wpdb->insert(
        $this->core_tables['system_logs']['name'],
        [
            'level' => $level,
            'source' => 'cobra-ai',
            'message' => $message,
            'context' => json_encode($context),
            'created_at' => current_time('mysql')
        ]
    );
}
```

### Health Checks
```php
public function get_health_status(): array
{
    return [
        'status' => 'healthy',
        'checks' => [
            'dependencies' => $this->check_dependencies(),
            'system' => $this->check_requirements(),
            'database' => $this->check_tables(),
            'files' => $this->check_files()
        ]
    ];
}
```

Cette architecture garantit un plugin flexible, maintenable et extensible, capable de gérer de multiples fonctionnalités de manière efficace et sécurisée.