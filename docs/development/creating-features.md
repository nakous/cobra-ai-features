# Guide de Développement - Créer une Nouvelle Fonctionnalité

## 🎯 Vue d'ensemble

Ce guide vous explique comment créer une nouvelle fonctionnalité pour le plugin Cobra AI Features en suivant l'architecture modulaire établie.

## 🏗️ Structure d'une Fonctionnalité

### Arborescence Standard

```
features/ma-nouvelle-fonctionnalite/
├── Feature.php                    # Classe principale (obligatoire)
├── includes/                      # Classes helper
│   ├── Manager.php               # Gestionnaire principal
│   ├── Admin.php                 # Interface d'administration
│   ├── Handler.php               # Gestionnaire des actions
│   └── ListTable.php             # Table d'administration
├── assets/                       # Ressources
│   ├── css/
│   │   ├── public.css            # Styles frontend
│   │   └── admin.css             # Styles admin
│   ├── js/
│   │   ├── public.js             # Scripts frontend
│   │   └── admin.js              # Scripts admin
│   └── help.html                 # Documentation (obligatoire)
├── views/                        # Templates admin
│   └── settings.php              # Page de configuration
├── templates/                    # Templates frontend
│   └── shortcode.php             # Templates de shortcodes
└── languages/                    # Traductions (optionnel)
    ├── ma-fonctionnalite-fr_FR.po
    └── ma-fonctionnalite-fr_FR.mo
```

## 🚀 Étape 1 : Créer la Classe Principale

### Template de Base

Créez le fichier `features/ma-fonctionnalite/Feature.php` :

```php
<?php

namespace CobraAI\Features\MaFonctionnalite;

use CobraAI\FeatureBase;

/**
 * Ma Nouvelle Fonctionnalité
 * 
 * Description de ce que fait la fonctionnalité
 */
class Feature extends FeatureBase
{
    /**
     * Configuration de la fonctionnalité
     */
    protected $feature_id = 'ma-fonctionnalite';
    protected $name = 'Ma Fonctionnalité';
    protected $description = 'Description de ma fonctionnalité';
    protected $version = '1.0.0';
    protected $author = 'Votre Nom';
    protected $has_settings = true;     // A des paramètres
    protected $has_admin = true;        // Interface admin
    protected $requires = [];           // Dépendances (optionnel)
    protected $min_wp_version = '5.8';
    protected $min_php_version = '7.4';

    /**
     * Composants de la fonctionnalité
     */
    private $manager;
    private $admin;

    /**
     * Constructeur
     */
    public function __construct()
    {
        parent::__construct();
        
        // Définir les tables de base de données si nécessaire
        $this->setup_database_tables();
    }

    /**
     * Configuration de la fonctionnalité
     */
    protected function setup(): void
    {
        // Charger les fichiers nécessaires
        $this->load_dependencies();
        
        // Initialiser les composants
        $this->init_components();
    }

    /**
     * Initialiser les hooks spécifiques
     */
    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Hooks spécifiques à votre fonctionnalité
        add_action('wp_loaded', [$this, 'handle_form_submission']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Shortcodes
        add_shortcode('ma_fonctionnalite', [$this, 'shortcode_handler']);
        
        // API REST
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Configuration des tables de base de données
     */
    private function setup_database_tables(): void
    {
        global $wpdb;

        $this->tables = [
            'ma_table' => [
                'name' => $wpdb->prefix . 'cobra_ma_table',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'user_id' => 'bigint(20) NOT NULL',
                    'title' => 'varchar(255) NOT NULL',
                    'content' => 'longtext',
                    'status' => "enum('active','inactive') NOT NULL DEFAULT 'active'",
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'user_id' => '(user_id)',
                        'status' => '(status)',
                        'created_at' => '(created_at)'
                    ]
                ]
            ]
        ];
    }

    /**
     * Charger les dépendances
     */
    private function load_dependencies(): void
    {
        $files = [
            'Manager.php',
            'Admin.php'
        ];

        foreach ($files as $file) {
            $file_path = $this->path . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialiser les composants
     */
    private function init_components(): void
    {
        // Gestionnaire principal
        if (class_exists(__NAMESPACE__ . '\\Manager')) {
            $this->manager = new Manager($this);
        }

        // Interface d'administration
        if (is_admin() && class_exists(__NAMESPACE__ . '\\Admin')) {
            $this->admin = new Admin($this);
        }
    }

    /**
     * Options par défaut de la fonctionnalité
     */
    protected function get_feature_default_options(): array
    {
        return [
            'general' => [
                'enabled' => true,
                'debug_mode' => false
            ],
            'display' => [
                'items_per_page' => 10,
                'show_author' => true,
                'date_format' => 'Y-m-d H:i:s'
            ],
            'security' => [
                'require_login' => false,
                'allowed_roles' => ['subscriber']
            ]
        ];
    }

    /**
     * Gestionnaire de shortcode
     */
    public function shortcode_handler($atts, $content = ''): string
    {
        $atts = shortcode_atts([
            'limit' => 10,
            'order' => 'DESC',
            'show_title' => true
        ], $atts, 'ma_fonctionnalite');

        // Générer le contenu du shortcode
        return $this->render_shortcode($atts, $content);
    }

    /**
     * Routes REST API
     */
    public function register_rest_routes(): void
    {
        register_rest_route('cobra-ai/v1', '/ma-fonctionnalite', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_items'],
            'permission_callback' => [$this, 'api_permissions_check'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ]
            ]
        ]);

        register_rest_route('cobra-ai/v1', '/ma-fonctionnalite', [
            'methods' => 'POST',
            'callback' => [$this, 'api_create_item'],
            'permission_callback' => [$this, 'api_permissions_check'],
            'args' => [
                'title' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($value) {
                        return !empty(trim($value));
                    }
                ]
            ]
        ]);
    }

    /**
     * Vérification des permissions API
     */
    public function api_permissions_check(): bool
    {
        return current_user_can('read');
    }

    /**
     * API: Récupérer les éléments
     */
    public function api_get_items(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            
            $items = $this->manager->get_items([
                'page' => $page,
                'per_page' => $per_page
            ]);

            return new WP_REST_Response([
                'success' => true,
                'data' => $items,
                'pagination' => $this->manager->get_pagination_info()
            ]);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rendu du shortcode
     */
    private function render_shortcode($atts, $content): string
    {
        $template_path = $this->path . 'templates/shortcode.php';
        
        if (!file_exists($template_path)) {
            return '<p>Template non trouvé</p>';
        }

        // Données pour le template
        $data = [
            'items' => $this->manager->get_items($atts),
            'settings' => $this->get_settings(),
            'atts' => $atts
        ];

        // Capture de sortie
        ob_start();
        extract($data);
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Validation personnalisée des paramètres
     */
    protected function validate_settings(array $settings): array
    {
        // Validation des paramètres généraux
        if (isset($settings['display']['items_per_page'])) {
            $settings['display']['items_per_page'] = max(1, min(100, intval($settings['display']['items_per_page'])));
        }

        // Validation des rôles autorisés
        if (isset($settings['security']['allowed_roles'])) {
            $valid_roles = array_keys(wp_roles()->roles);
            $settings['security']['allowed_roles'] = array_intersect(
                $settings['security']['allowed_roles'],
                $valid_roles
            );
        }

        return $settings;
    }

    /**
     * Getters pour accéder aux composants
     */
    public function get_manager()
    {
        return $this->manager;
    }

    public function get_admin()
    {
        return $this->admin;
    }
}
```

## 🔧 Étape 2 : Créer le Gestionnaire

Créez `features/ma-fonctionnalite/includes/Manager.php` :

```php
<?php

namespace CobraAI\Features\MaFonctionnalite;

/**
 * Gestionnaire principal de la fonctionnalité
 */
class Manager
{
    /**
     * Instance de la fonctionnalité
     */
    private $feature;

    /**
     * Cache des données
     */
    private $cache = [];

    /**
     * Constructeur
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->init_hooks();
    }

    /**
     * Initialiser les hooks
     */
    private function init_hooks(): void
    {
        // Hooks personnalisés
        add_action('wp_loaded', [$this, 'process_frontend_actions']);
        add_action('wp_ajax_ma_fonctionnalite_action', [$this, 'handle_ajax_request']);
        add_action('wp_ajax_nopriv_ma_fonctionnalite_action', [$this, 'handle_ajax_request']);
    }

    /**
     * Créer un nouvel élément
     */
    public function create_item(array $data): int
    {
        global $wpdb;

        // Validation des données
        $data = $this->validate_item_data($data);

        // Préparation pour l'insertion
        $insert_data = [
            'user_id' => get_current_user_id(),
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'status' => $data['status'] ?? 'active',
            'created_at' => current_time('mysql')
        ];

        // Insertion en base
        $table_name = $this->feature->get_table_name('ma_table');
        $result = $wpdb->insert($table_name, $insert_data);

        if ($result === false) {
            throw new \Exception('Failed to create item');
        }

        $item_id = $wpdb->insert_id;

        // Action après création
        do_action('cobra_ai_ma_fonctionnalite_item_created', $item_id, $data);

        // Log
        $this->feature->log('info', 'Item created', [
            'item_id' => $item_id,
            'user_id' => $insert_data['user_id']
        ]);

        // Invalider le cache
        $this->clear_cache();

        return $item_id;
    }

    /**
     * Récupérer des éléments
     */
    public function get_items(array $args = []): array
    {
        global $wpdb;

        // Arguments par défaut
        $args = wp_parse_args($args, [
            'page' => 1,
            'per_page' => 10,
            'status' => 'active',
            'user_id' => null,
            'order' => 'DESC',
            'orderby' => 'created_at'
        ]);

        // Cache key
        $cache_key = 'items_' . md5(serialize($args));

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // Construction de la requête
        $table_name = $this->feature->get_table_name('ma_table');
        $where_clauses = ['1=1'];
        $where_values = [];

        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        $where_sql = implode(' AND ', $where_clauses);
        $order_sql = sprintf('ORDER BY %s %s', 
            esc_sql($args['orderby']), 
            esc_sql($args['order'])
        );

        // Pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['per_page'], $offset);

        // Requête finale
        $query = "SELECT * FROM {$table_name} WHERE {$where_sql} {$order_sql} {$limit_sql}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, ...$where_values);
        }

        $items = $wpdb->get_results($query, ARRAY_A);

        // Mise en cache
        $this->cache[$cache_key] = $items;

        return $items;
    }

    /**
     * Valider les données d'un élément
     */
    private function validate_item_data(array $data): array
    {
        $errors = [];

        // Titre requis
        if (empty($data['title'])) {
            $errors[] = __('Title is required', 'cobra-ai');
        }

        // Longueur du titre
        if (strlen($data['title']) > 255) {
            $errors[] = __('Title is too long (max 255 characters)', 'cobra-ai');
        }

        // Statut valide
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            $errors[] = __('Invalid status', 'cobra-ai');
        }

        if (!empty($errors)) {
            throw new \Exception(implode('. ', $errors));
        }

        // Sanitisation
        $data['title'] = sanitize_text_field($data['title']);
        if (isset($data['content'])) {
            $data['content'] = wp_kses_post($data['content']);
        }

        return $data;
    }

    /**
     * Traitement des actions frontend
     */
    public function process_frontend_actions(): void
    {
        if (!isset($_POST['ma_fonctionnalite_action'])) {
            return;
        }

        // Vérification du nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ma_fonctionnalite_action')) {
            wp_die(__('Security check failed', 'cobra-ai'));
        }

        $action = sanitize_text_field($_POST['ma_fonctionnalite_action']);

        try {
            switch ($action) {
                case 'create_item':
                    $this->handle_create_item();
                    break;
                
                case 'update_item':
                    $this->handle_update_item();
                    break;

                default:
                    throw new \Exception('Unknown action');
            }
        } catch (Exception $e) {
            // Gestion des erreurs
            $this->add_error($e->getMessage());
        }
    }

    /**
     * Gestionnaire AJAX
     */
    public function handle_ajax_request(): void
    {
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ma_fonctionnalite_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $action = sanitize_text_field($_POST['sub_action'] ?? '');

        try {
            switch ($action) {
                case 'get_items':
                    $items = $this->get_items($_POST);
                    wp_send_json_success($items);
                    break;

                case 'delete_item':
                    $item_id = intval($_POST['item_id']);
                    $result = $this->delete_item($item_id);
                    wp_send_json_success(['deleted' => $result]);
                    break;

                default:
                    wp_send_json_error('Unknown action');
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Vider le cache
     */
    public function clear_cache(): void
    {
        $this->cache = [];
        wp_cache_flush_group('cobra_ai_ma_fonctionnalite');
    }

    /**
     * Ajouter une erreur
     */
    private function add_error(string $message): void
    {
        $errors = get_transient('ma_fonctionnalite_errors_' . get_current_user_id()) ?: [];
        $errors[] = $message;
        set_transient('ma_fonctionnalite_errors_' . get_current_user_id(), $errors, 60);
    }

    /**
     * Récupérer les erreurs
     */
    public function get_errors(): array
    {
        $errors = get_transient('ma_fonctionnalite_errors_' . get_current_user_id()) ?: [];
        delete_transient('ma_fonctionnalite_errors_' . get_current_user_id());
        return $errors;
    }
}
```

## 📋 Étape 3 : Interface d'Administration

Créez `features/ma-fonctionnalite/includes/Admin.php` :

```php
<?php

namespace CobraAI\Features\MaFonctionnalite;

/**
 * Interface d'administration
 */
class Admin
{
    private $feature;
    private $menu_slug = 'cobra-ai-ma-fonctionnalite';

    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_save_ma_fonctionnalite_settings', [$this, 'save_settings']);
    }

    public function add_menu_items(): void
    {
        add_submenu_page(
            'cobra-ai-dashboard',
            __('Ma Fonctionnalité', 'cobra-ai'),
            __('Ma Fonctionnalité', 'cobra-ai'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_main_page']
        );
    }

    public function render_main_page(): void
    {
        $tab = $_GET['tab'] ?? 'overview';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->render_tabs($tab); ?>
            
            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'items':
                        $this->render_items_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_tabs($current_tab): void
    {
        $tabs = [
            'overview' => __('Vue d\'ensemble', 'cobra-ai'),
            'items' => __('Éléments', 'cobra-ai'),
            'settings' => __('Paramètres', 'cobra-ai')
        ];

        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $label) {
            $class = $tab === $current_tab ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = admin_url("admin.php?page={$this->menu_slug}&tab={$tab}");
            echo "<a href='{$url}' class='{$class}'>{$label}</a>";
        }
        echo '</nav>';
    }

    private function render_settings_tab(): void
    {
        $settings = $this->feature->get_settings();
        include $this->feature->get_path() . 'views/settings.php';
    }
}
```

## 📝 Étape 4 : Template de Configuration

Créez `features/ma-fonctionnalite/views/settings.php` :

```php
<?php
defined('ABSPATH') || exit;

// Récupération des paramètres
$settings = $this->get_settings();
?>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('save_ma_fonctionnalite_settings', '_wpnonce'); ?>
    <input type="hidden" name="action" value="save_ma_fonctionnalite_settings">
    
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="enabled"><?php _e('Activer la fonctionnalité', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <input type="checkbox" 
                           id="enabled" 
                           name="settings[general][enabled]" 
                           value="1" 
                           <?php checked($settings['general']['enabled'] ?? false); ?>>
                    <p class="description">
                        <?php _e('Activer ou désactiver cette fonctionnalité', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="items_per_page"><?php _e('Éléments par page', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="items_per_page" 
                           name="settings[display][items_per_page]" 
                           value="<?php echo esc_attr($settings['display']['items_per_page'] ?? 10); ?>"
                           min="1" 
                           max="100">
                    <p class="description">
                        <?php _e('Nombre d\'éléments à afficher par page', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php submit_button(__('Enregistrer les paramètres', 'cobra-ai')); ?>
</form>
```

## 🎨 Étape 5 : Template de Shortcode

Créez `features/ma-fonctionnalite/templates/shortcode.php` :

```php
<?php
defined('ABSPATH') || exit;

// Variables disponibles : $items, $settings, $atts
?>

<div class="cobra-ma-fonctionnalite-container">
    <?php if (!empty($items)): ?>
        <div class="items-grid">
            <?php foreach ($items as $item): ?>
                <div class="item-card" data-id="<?php echo esc_attr($item['id']); ?>">
                    <?php if ($atts['show_title']): ?>
                        <h3 class="item-title"><?php echo esc_html($item['title']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($item['content'])): ?>
                        <div class="item-content">
                            <?php echo wp_kses_post($item['content']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-meta">
                        <span class="item-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item['created_at']))); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-items"><?php _e('Aucun élément trouvé.', 'cobra-ai'); ?></p>
    <?php endif; ?>
</div>
```

## 📄 Étape 6 : Documentation

Créez `features/ma-fonctionnalite/assets/help.html` :

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ma Fonctionnalité - Documentation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #2271b1; }
        code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Ma Fonctionnalité</h1>
    
    <h2>Description</h2>
    <p>Description détaillée de votre fonctionnalité et de son utilité.</p>
    
    <h2>Configuration</h2>
    <p>Allez dans <strong>Cobra AI → Ma Fonctionnalité → Paramètres</strong> pour configurer la fonctionnalité.</p>
    
    <h2>Shortcodes</h2>
    <h3>[ma_fonctionnalite]</h3>
    <p>Affiche les éléments de votre fonctionnalité.</p>
    
    <h4>Paramètres disponibles :</h4>
    <ul>
        <li><code>limit</code> - Nombre d'éléments à afficher (défaut: 10)</li>
        <li><code>order</code> - Ordre de tri: ASC ou DESC (défaut: DESC)</li>
        <li><code>show_title</code> - Afficher le titre: true ou false (défaut: true)</li>
    </ul>
    
    <h4>Exemples :</h4>
    <pre>[ma_fonctionnalite limit="5" order="ASC"]</pre>
    <pre>[ma_fonctionnalite show_title="false"]</pre>
    
    <h2>API REST</h2>
    <p>Cette fonctionnalité expose les endpoints suivants :</p>
    <ul>
        <li><code>GET /wp-json/cobra-ai/v1/ma-fonctionnalite</code> - Récupérer les éléments</li>
        <li><code>POST /wp-json/cobra-ai/v1/ma-fonctionnalite</code> - Créer un élément</li>
    </ul>
</body>
</html>
```

## ✅ Étape 7 : Activation et Test

### 1. Activer la Fonctionnalité

1. Allez dans l'admin WordPress
2. Menu **Cobra AI → Fonctionnalités**  
3. Activez votre nouvelle fonctionnalité

### 2. Tester la Fonctionnalité

```php
// Test de base
$feature = cobra_ai()->get_feature('ma-fonctionnalite');
if ($feature) {
    echo "Fonctionnalité chargée avec succès !";
}

// Test du gestionnaire
$manager = $feature->get_manager();
$items = $manager->get_items(['limit' => 5]);

// Test du shortcode
echo do_shortcode('[ma_fonctionnalite limit="3"]');
```

## 🔧 Bonnes Pratiques

### 1. Sécurité
- Toujours valider et sanitiser les données
- Utiliser des nonces pour les formulaires
- Vérifier les permissions utilisateur
- Préparer les requêtes SQL

### 2. Performance
- Utiliser le cache WordPress
- Optimiser les requêtes de base de données
- Charger les assets seulement quand nécessaire

### 3. Maintenance
- Commentez votre code
- Utilisez les hooks appropriés
- Créez des logs pour le debugging
- Testez toutes les fonctionnalités

### 4. Internationalisation
- Utilisez les fonctions de traduction WordPress
- Créez les fichiers .po/.mo si nécessaire
- Testez dans différentes langues

Votre nouvelle fonctionnalité est maintenant prête à être utilisée dans l'écosystème Cobra AI Features !