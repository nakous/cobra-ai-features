# Référence des Classes - Cobra AI Features

## 📚 Vue d'ensemble

Cette référence détaille toutes les classes principales du plugin Cobra AI Features avec leurs méthodes, propriétés et utilisation.

---

## 🏛️ Classes Principales (Core)

### CobraAI (Classe Principale)

**Namespace** : `CobraAI`  
**Type** : Singleton  
**Localisation** : `cobra-ai-features.php`

#### Propriétés

```php
class CobraAI
{
    const VERSION = '1.0.0';                    // Version du plugin
    private static ?CobraAI $instance = null;   // Instance singleton
    private array $container = [];              // Container DI
    private array $features = [];               // Fonctionnalités chargées
    public Database $db;                        // Instance DB
    public APIManager $api;                     // Gestionnaire API
    public Admin $admin;                        // Interface admin
}
```

#### Méthodes Principales

##### `instance(): self`
```php
/**
 * Obtenir l'instance singleton
 * @return CobraAI Instance unique du plugin
 */
public static function instance(): self
```

##### `get_feature(string $feature_id)`
```php
/**
 * Récupérer une fonctionnalité spécifique
 * @param string $feature_id ID de la fonctionnalité
 * @return FeatureBase|null Instance de la fonctionnalité
 */
public function get_feature(string $feature_id)
```

##### `get_features(bool $include_inactive = true): array`
```php
/**
 * Obtenir toutes les fonctionnalités
 * @param bool $include_inactive Inclure les inactives
 * @return array<string, FeatureBase> Tableau des fonctionnalités
 */
public function get_features(bool $include_inactive = true): array
```

##### `get(string $component)`
```php
/**
 * Récupérer un composant du container
 * @param string $component Nom du composant
 * @return mixed Instance du composant
 */
public function get(string $component)
```

#### Exemple d'Utilisation

```php
// Accès au plugin principal
$cobra_ai = cobra_ai();

// Récupérer une fonctionnalité
$ai_feature = $cobra_ai->get_feature('ai');

// Accès aux services
$db = $cobra_ai->get('db');
$api = $cobra_ai->get('api');

// Vérifier si une fonctionnalité existe
if ($cobra_ai->has('features')) {
    $features = $cobra_ai->get('features');
}
```

---

### FeatureBase (Classe Abstraite)

**Namespace** : `CobraAI`  
**Type** : Classe abstraite  
**Localisation** : `includes/FeatureBase.php`

#### Propriétés Protégées

```php
abstract class FeatureBase
{
    protected string $feature_id = '';           // ID unique
    protected string $name = '';                 // Nom affiché
    protected string $description = '';          // Description
    protected string $version = '1.0.0';        // Version
    protected string $author = '';               // Auteur
    protected array $requires = [];             // Dépendances
    protected string $min_wp_version = '5.8';   // WordPress min
    protected string $min_php_version = '7.4';  // PHP min
    protected bool $has_settings = false;       // A des paramètres
    protected bool $has_admin = false;          // Interface admin
    protected array $tables = [];              // Tables DB
    protected string $path;                     // Chemin fichiers
    protected string $url;                      // URL base
    protected string $assets_url;              // URL assets
    protected string $templates_path;          // Chemin templates
}
```

#### Méthodes Abstraites

##### `setup(): void`
```php
/**
 * Configuration de la fonctionnalité - OBLIGATOIRE
 * Définie dans chaque fonctionnalité enfant
 */
abstract protected function setup(): void;
```

#### Méthodes Principales

##### `init(): bool`
```php
/**
 * Initialiser la fonctionnalité
 * @return bool True si succès, false sinon
 */
public function init(): bool
```

##### `activate(): bool`
```php
/**
 * Activer la fonctionnalité
 * @return bool True si succès, false sinon
 */
public function activate(): bool
```

##### `get_settings(?string $key = null, $default = null)`
```php
/**
 * Récupérer les paramètres de la fonctionnalité
 * @param string|null $key Clé spécifique (optionnel)
 * @param mixed $default Valeur par défaut
 * @return mixed Paramètres ou valeur spécifique
 */
public function get_settings(?string $key = null, $default = null)
```

##### `update_settings(array $settings): bool`
```php
/**
 * Mettre à jour les paramètres
 * @param array $settings Nouveaux paramètres
 * @return bool True si succès
 */
public function update_settings(array $settings): bool
```

##### `get_table_name(string $table_name): ?string`
```php
/**
 * Obtenir le nom complet d'une table
 * @param string $table_name Nom court de la table
 * @return string|null Nom complet avec préfixe
 */
public function get_table_name(string $table_name): ?string
```

##### `get_health_status(): array`
```php
/**
 * Vérifier l'état de santé de la fonctionnalité
 * @return array Statut détaillé
 */
public function get_health_status(): array
```

#### Exemple d'Utilisation

```php
// Créer une nouvelle fonctionnalité
class MyFeature extends FeatureBase
{
    protected $feature_id = 'my-feature';
    protected $name = 'Ma Fonctionnalité';
    protected $has_settings = true;
    
    protected function setup(): void
    {
        // Configuration spécifique
        $this->load_components();
        $this->setup_database();
    }
    
    protected function init_hooks(): void
    {
        parent::init_hooks();
        
        // Hooks spécifiques
        add_action('wp_loaded', [$this, 'handle_actions']);
    }
}
```

---

### Database

**Namespace** : `CobraAI`  
**Type** : Singleton  
**Localisation** : `includes/Database.php`

#### Propriétés

```php
class Database
{
    private static $instance = null;         // Singleton
    private string $version = '1.0.0';      // Version DB
    private array $core_tables = [];        // Tables core
    private array $feature_tables = [];     // Tables features
    private bool $tables_installed = false; // État installation
}
```

#### Méthodes Principales

##### `get_instance(): self`
```php
/**
 * Obtenir l'instance singleton
 * @return Database Instance unique
 */
public static function get_instance(): self
```

##### `install_or_upgrade(): void`
```php
/**
 * Installer ou mettre à jour les tables
 */
public function install_or_upgrade(): void
```

##### `register_feature_tables(string $feature_id, array $tables): void`
```php
/**
 * Enregistrer les tables d'une fonctionnalité
 * @param string $feature_id ID de la fonctionnalité
 * @param array $tables Configuration des tables
 */
public function register_feature_tables(string $feature_id, array $tables): void
```

##### `install_feature_tables(string $feature_id): bool`
```php
/**
 * Installer les tables d'une fonctionnalité
 * @param string $feature_id ID de la fonctionnalité
 * @return bool True si succès
 */
public function install_feature_tables(string $feature_id): bool
```

##### `log(string $level, string $message, array $context = []): bool`
```php
/**
 * Enregistrer un log système
 * @param string $level Niveau (debug, info, warning, error)
 * @param string $message Message du log
 * @param array $context Contexte supplémentaire
 * @return bool True si succès
 */
public function log(string $level, string $message, array $context = []): bool
```

#### Exemple d'Utilisation

```php
// Accès à la base de données
$db = cobra_ai_db(); // Fonction helper

// Enregistrer un log
$db->log('info', 'Action effectuée', [
    'user_id' => get_current_user_id(),
    'action' => 'test'
]);

// Installation manuelle de tables
$db->install_feature_tables('my-feature');
```

---

### Admin

**Namespace** : `CobraAI`  
**Type** : Singleton  
**Localisation** : `includes/Admin.php`

#### Propriétés

```php
class Admin
{
    private static $instance = null;         // Singleton
    private string $menu_slug = 'cobra-ai-dashboard'; // Menu principal
    private array $features_cache = [];      // Cache des fonctionnalités
}
```

#### Méthodes Principales

##### `get_instance(): self`
```php
/**
 * Obtenir l'instance singleton
 * @return Admin Instance unique
 */
public static function get_instance(): self
```

##### `add_menu_pages(): void`
```php
/**
 * Ajouter les pages de menu d'administration
 */
public function add_menu_pages(): void
```

##### `handle_feature_toggle(): void`
```php
/**
 * Gérer l'activation/désactivation des fonctionnalités
 */
public function handle_feature_toggle(): void
```

##### `render_dashboard(): void`
```php
/**
 * Afficher le tableau de bord principal
 */
public function render_dashboard(): void
```

##### `render_features_page(): void`
```php
/**
 * Afficher la page de gestion des fonctionnalités
 */
public function render_features_page(): void
```

#### Exemple d'Utilisation

```php
// Accès à l'admin
$admin = cobra_ai()->get('admin');

// Ajouter un menu personnalisé
add_action('admin_menu', function() use ($admin) {
    add_submenu_page(
        'cobra-ai-dashboard',
        'Ma Page',
        'Ma Page',
        'manage_options',
        'ma-page',
        'render_my_page'
    );
});
```

---

### APIManager

**Namespace** : `CobraAI`  
**Type** : Singleton  
**Localisation** : `includes/APIManager.php`

#### Propriétés

```php
class APIManager
{
    private static $instance = null;         // Singleton
    private array $registered_apis = [];    // APIs enregistrées
    private array $rate_limits = [];        // Limites de taux
}
```

#### Méthodes Principales

##### `get_instance(): self`
```php
/**
 * Obtenir l'instance singleton
 * @return APIManager Instance unique
 */
public static function get_instance(): self
```

##### `register_api(string $id, array $config): void`
```php
/**
 * Enregistrer une nouvelle API
 * @param string $id Identifiant unique de l'API
 * @param array $config Configuration de l'API
 */
public function register_api(string $id, array $config): void
```

##### `make_request(string $api_id, array $params): array`
```php
/**
 * Effectuer une requête API
 * @param string $api_id ID de l'API
 * @param array $params Paramètres de la requête
 * @return array Réponse de l'API
 */
public function make_request(string $api_id, array $params): array
```

##### `check_rate_limit(string $api_id, int $user_id): bool`
```php
/**
 * Vérifier les limites de taux
 * @param string $api_id ID de l'API
 * @param int $user_id ID utilisateur
 * @return bool True si autorisé
 */
public function check_rate_limit(string $api_id, int $user_id): bool
```

---

## 🎯 Classes de Fonctionnalités

### AI Feature Classes

#### `CobraAI\Features\AI\Feature`
```php
class Feature extends FeatureBase
{
    protected $feature_id = 'ai';
    protected $name = 'AI Integration';
    
    public $manager;      // AIManager
    public $tracking;     // AITracking
    private $admin;       // AIAdmin
    
    // Méthodes spécifiques IA
    public function get_providers(): array;
    public function send_request(string $prompt, array $options = []): array;
}
```

#### `CobraAI\Features\AI\AIManager`
```php
class AIManager
{
    public function send_request(string $prompt, array $options = []): array;
    public function get_provider(string $name): ?AIProvider;
    public function validate_response($response): bool;
}
```

#### `CobraAI\Features\AI\AITracking`
```php
class AITracking
{
    public function log_request(array $data): int;
    public function update_tracking(int $id, array $data): bool;
    public function get_user_trackings(int $user_id, array $args = []): array;
    public function get_usage_stats(array $filters = []): array;
}
```

### Credits Feature Classes

#### `CobraAI\Features\Credits\Feature`
```php
class Feature extends FeatureBase
{
    protected $feature_id = 'credits';
    protected $name = 'Credits System';
    
    public $manager;      // CreditManager
    private $admin;       // CreditAdmin
    private $cron;        // CreditCron
}
```

#### `CobraAI\Features\Credits\CreditManager`
```php
class CreditManager
{
    public function add_credits(int $user_id, string $type, float $amount, array $options = []): int;
    public function consume_credits(int $user_id, string $type, float $amount): bool;
    public function get_balance(int $user_id, string $type = null): array;
    public function has_sufficient_credits(int $user_id, string $type, float $amount): bool;
}
```

---

## 🛠️ Classes Utilitaires

### Validator

**Namespace** : `CobraAI`  
**Localisation** : `includes/utilities/Validator.php`

#### Méthodes Statiques

```php
class Validator
{
    public static function validate_email(string $email): bool;
    public static function validate_url(string $url): bool;
    public static function validate_phone(string $phone): bool;
    public static function validate_required(array $data, array $fields): array;
    public static function sanitize_array(array $data): array;
    public static function validate_json(string $json): bool;
}
```

#### Exemple d'Utilisation

```php
// Validation d'email
if (!Validator::validate_email($email)) {
    throw new \Exception('Email invalide');
}

// Validation de champs requis
$errors = Validator::validate_required($_POST, ['name', 'email', 'message']);
if (!empty($errors)) {
    // Gérer les erreurs
}

// Sanitisation de tableau
$clean_data = Validator::sanitize_array($_POST);
```

---

## 📋 Classes List Table

### Class_Tracking_List_Table

**Namespace** : `CobraAI\Features\AI`  
**Hérite de** : `WP_List_Table`

#### Méthodes Principales

```php
class Class_Tracking_List_Table extends \WP_List_Table
{
    public function get_columns(): array;
    public function prepare_items(): void;
    public function column_default($item, $column_name): string;
    public function column_cb($item): string;
    public function get_bulk_actions(): array;
    public function process_bulk_action(): void;
}
```

### Class_Credits_List_Table

**Namespace** : `CobraAI\Features\Credits`  
**Hérite de** : `WP_List_Table`

#### Utilisation Similaire
Structure identique aux autres List Tables WordPress avec méthodes spécifiques aux crédits.

---

## 🚀 Fonctions Helper Globales

### Fonctions Principales

```php
/**
 * Accès au plugin principal
 * @return CobraAI Instance du plugin
 */
function cobra_ai(): CobraAI;

/**
 * Accès à la base de données
 * @return Database Instance de la DB
 */
function cobra_ai_db(): Database;

/**
 * Logger un message
 * @param string $level Niveau de log
 * @param string $message Message
 * @param array $context Contexte
 */
function cobra_ai_log(string $level, string $message, array $context = []): void;

/**
 * Obtenir une fonctionnalité
 * @param string $feature_id ID de la fonctionnalité
 * @return FeatureBase|null Instance de la fonctionnalité
 */
function cobra_ai_get_feature(string $feature_id): ?FeatureBase;
```

### Exemple d'Utilisation des Helpers

```php
// Accès rapide au plugin
$plugin = cobra_ai();

// Log rapide
cobra_ai_log('info', 'Action effectuée');

// Accès direct à une fonctionnalité
$ai = cobra_ai_get_feature('ai');
if ($ai) {
    $response = $ai->send_request('Hello world');
}
```

---

Cette référence couvre les classes principales et leurs utilisations dans le plugin Cobra AI Features. Pour des détails spécifiques sur l'implémentation, consultez le code source de chaque classe.