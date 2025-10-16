# Système de Base de Données - Cobra AI Features

## 🗃️ Vue d'ensemble

Le plugin utilise un système de gestion de base de données centralisé et modulaire qui permet à chaque fonctionnalité de définir ses propres tables tout en maintenant une cohérence globale.

## 🏗️ Architecture de la Base de Données

### Classe Database Principale

La classe `CobraAI\Database` est le point central de gestion :

```php
namespace CobraAI;

class Database
{
    private static $instance = null;
    private $version = '1.0.0';
    private $core_tables = [];
    private $feature_tables = [];
    
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Tables du Core

#### 1. System Logs (`cobra_system_logs`)
```sql
CREATE TABLE cobra_system_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    level enum('debug','info','warning','error') NOT NULL DEFAULT 'info',
    source varchar(100) NOT NULL,
    message text NOT NULL,
    context longtext,
    PRIMARY KEY (id),
    KEY level_created_at (level,created_at),
    KEY source (source)
);
```

**Utilisation :** Centralise tous les logs du système et des fonctionnalités.

#### 2. Features Registry (`cobra_features`)
```sql
CREATE TABLE cobra_features (
    id varchar(50) NOT NULL,
    name varchar(100) NOT NULL,
    version varchar(20) NOT NULL,
    status enum('active','inactive','error') NOT NULL DEFAULT 'inactive',
    installed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    settings longtext,
    PRIMARY KEY (id),
    KEY status (status)
);
```

**Utilisation :** Registre de toutes les fonctionnalités installées.

#### 3. System Events (`cobra_events`)
```sql
CREATE TABLE cobra_events (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    feature_id varchar(50) NOT NULL,
    event_type varchar(50) NOT NULL,
    event_data longtext,
    user_id bigint(20),
    timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY feature_event (feature_id,event_type),
    KEY user_id (user_id),
    KEY timestamp (timestamp)
);
```

**Utilisation :** Suivi des événements système pour analytics et debugging.

## 🎯 Tables par Fonctionnalité

### AI Feature Tables

#### AI Trackings (`cobra_ai_trackings`)
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

**Utilisation :** Suivi complet des interactions avec les APIs d'IA.

### Credits Feature Tables

#### Credits (`cobra_credits`)
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

#### Credit Types (`cobra_credit_types`)
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

**Utilisation :** Gestion flexible des différents types de crédits.

### Contact Feature Tables

#### Contact Submissions (`cobra_contact_submissions`)
```sql
CREATE TABLE cobra_contact_submissions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    email varchar(100) NOT NULL,
    subject varchar(255) NOT NULL,
    message text NOT NULL,
    status enum('new','read','replied','spam') NOT NULL DEFAULT 'new',
    ip_address varchar(45),
    user_agent text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status),
    KEY email (email),
    KEY created_at (created_at)
);
```

**Utilisation :** Stockage des soumissions de formulaires de contact.

### Stripe Feature Tables

#### Stripe Logs (`cobra_stripe_logs`)
```sql
CREATE TABLE cobra_stripe_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    event_type varchar(100) NOT NULL,
    stripe_id varchar(255),
    user_id bigint(20),
    amount decimal(10,2),
    currency varchar(3),
    status varchar(50),
    data longtext,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY stripe_id (stripe_id),
    KEY user_id (user_id),
    KEY status (status),
    KEY created_at (created_at)
);
```

#### Stripe Webhooks (`cobra_stripe_webhooks`)
```sql
CREATE TABLE cobra_stripe_webhooks (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    stripe_event_id varchar(255) NOT NULL,
    event_type varchar(100) NOT NULL,
    processed_at datetime,
    status enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
    attempts int NOT NULL DEFAULT 0,
    data longtext NOT NULL,
    error_message text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY stripe_event_id (stripe_event_id),
    KEY event_type (event_type),
    KEY status (status),
    KEY processed_at (processed_at)
);
```

**Utilisation :** Gestion des événements et webhooks Stripe.

### FAQ Feature Tables

#### FAQ Views (`cobra_faq_views`)
```sql
CREATE TABLE cobra_faq_views (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    faq_id bigint(20) NOT NULL,
    views bigint(20) NOT NULL DEFAULT 0,
    helpful_yes bigint(20) NOT NULL DEFAULT 0,
    helpful_no bigint(20) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY faq_id (faq_id)
);
```

**Utilisation :** Statistiques des FAQ (vues, votes).

### User Registration Tables

#### Verification Tokens (`cobra_verification_tokens`)
```sql
CREATE TABLE cobra_verification_tokens (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    token varchar(255) NOT NULL,
    token_type enum('email_verification','password_reset') NOT NULL,
    expires_at datetime NOT NULL,
    used_at datetime,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY token (token),
    KEY user_id (user_id),
    KEY expires_at (expires_at),
    KEY token_type (token_type)
);
```

**Utilisation :** Gestion des tokens de vérification email et reset password.

## 🔧 Gestion des Tables

### Installation des Tables

```php
public function install_feature_tables(string $feature_id): bool
{
    if (!isset($this->feature_tables[$feature_id])) {
        return false;
    }

    try {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        foreach ($this->feature_tables[$feature_id] as $table_info) {
            $sql = $this->generate_table_sql(
                $table_info['name'], 
                $table_info['schema'], 
                $charset_collate
            );
            dbDelta($sql);
        }

        $this->log('info', "Tables installed for feature: $feature_id");
        return true;
    } catch (\Exception $e) {
        $this->log('error', "Failed to install tables", ['error' => $e->getMessage()]);
        return false;
    }
}
```

### Génération SQL Dynamique

```php
private function generate_table_sql(string $table_name, array $schema, string $charset_collate): string
{
    $sql = "CREATE TABLE $table_name (\n";

    foreach ($schema as $column => $definition) {
        if ($column === 'PRIMARY KEY' || $column === 'KEY') {
            continue;
        }
        $sql .= "  $column $definition,\n";
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

    $sql = rtrim($sql, ",\n") . "\n) $charset_collate;";
    return $sql;
}
```

### Désinstallation

```php
public function uninstall_feature_tables(string $feature_id, bool $preserve_data = false): bool
{
    if (!$preserve_data) {
        foreach ($this->feature_tables[$feature_id] as $table_info) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_info['name']}");
        }
    }
    return true;
}
```

## 📊 Utilisation dans les Fonctionnalités

### Définition des Tables

```php
class Feature extends FeatureBase
{
    protected function setup(): void
    {
        global $wpdb;
        
        $this->tables = [
            'my_table' => [
                'name' => $wpdb->prefix . 'cobra_my_table',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'name' => 'varchar(255) NOT NULL',
                    'data' => 'longtext',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'name' => '(name)',
                        'created_at' => '(created_at)'
                    ]
                ]
            ]
        ];
    }
}
```

### Accès aux Tables

```php
// Récupérer le nom d'une table
$table_name = $this->get_table_name('my_table');

// Récupérer les infos complètes d'une table
$table_info = $this->get_table('my_table');

// Utilisation dans une requête
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$table_name} WHERE user_id = %d", $user_id)
);
```

## 🔍 Requêtes et Optimisation

### Indexes Recommandés

Toutes les tables incluent des indexes appropriés :
- **Primary Keys** : Pour l'unicité et les performances
- **Foreign Keys** : Pour les relations (user_id, feature_id, etc.)
- **Temporal Keys** : Pour les requêtes par date (created_at, expires_at)
- **Status Keys** : Pour les filtres par statut

### Patterns de Requêtes

```php
// Pattern de pagination
$offset = ($page - 1) * $per_page;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         ORDER BY created_at DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    )
);

// Pattern de recherche avec filtres
$where_clauses = [];
$where_values = [];

if (!empty($search_term)) {
    $where_clauses[] = "(name LIKE %s OR description LIKE %s)";
    $where_values[] = "%{$search_term}%";
    $where_values[] = "%{$search_term}%";
}

if (!empty($status)) {
    $where_clauses[] = "status = %s";
    $where_values[] = $status;
}

$where_sql = !empty($where_clauses) 
    ? 'WHERE ' . implode(' AND ', $where_clauses)
    : '';

$query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC";
$results = $wpdb->get_results($wpdb->prepare($query, ...$where_values));
```

## 🛡️ Sécurité des Données

### Préparation des Requêtes
Toujours utiliser `$wpdb->prepare()` pour éviter les injections SQL :

```php
$wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d AND status = %s",
        $user_id, $status
    )
);
```

### Validation des Données
```php
// Avant insertion
$data = [
    'name' => sanitize_text_field($input['name']),
    'email' => sanitize_email($input['email']),
    'amount' => floatval($input['amount']),
    'status' => in_array($input['status'], ['active', 'inactive']) 
        ? $input['status'] 
        : 'inactive'
];
```

Ce système de base de données garantit une gestion cohérente, sécurisée et performante de toutes les données du plugin.