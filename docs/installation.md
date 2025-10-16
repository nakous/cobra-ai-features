# Installation et Configuration - Cobra AI Features

## 🚀 Installation

### Prérequis Système

#### Serveur Web
- **WordPress** : 5.8 ou supérieur
- **PHP** : 7.4 ou supérieur (recommandé : 8.0+)
- **MySQL** : 5.7 ou supérieur / MariaDB 10.2+
- **Mémoire PHP** : 128MB minimum (256MB recommandé)
- **Extensions PHP requises** :
  - `curl` (pour les API externes)
  - `json` (pour le traitement des données)
  - `mbstring` (pour la gestion des caractères)
  - `openssl` (pour les connexions sécurisées)

#### Permissions
- Écriture dans le répertoire `/wp-content/plugins/`
- Écriture dans `/wp-content/uploads/` (pour les logs et cache)
- Capacité de créer des tables MySQL

### Méthodes d'Installation

#### 1. Installation via l'Interface WordPress

1. **Téléchargement**
   - Téléchargez le fichier `cobra-ai-features.zip`
   - Connectez-vous à votre administration WordPress

2. **Installation**
   ```
   Tableau de bord → Extensions → Ajouter → Téléverser une extension
   ```
   - Sélectionnez le fichier ZIP
   - Cliquez sur "Installer maintenant"
   - Activez l'extension

#### 2. Installation Manuelle (FTP/SFTP)

1. **Extraction**
   ```bash
   unzip cobra-ai-features.zip
   ```

2. **Upload**
   - Téléversez le dossier `cobra-ai-features/` dans `/wp-content/plugins/`
   - Assurez-vous que la structure soit : `/wp-content/plugins/cobra-ai-features/cobra-ai-features.php`

3. **Activation**
   - Allez dans `Extensions → Extensions installées`
   - Activez "Cobra AI Features"

#### 3. Installation via WP-CLI

```bash
# Téléchargement et installation
wp plugin install cobra-ai-features.zip --activate

# Vérification
wp plugin status cobra-ai-features
```

#### 4. Installation via Composer (pour développeurs)

```bash
# Dans votre projet WordPress
composer require nakous/cobra-ai-features

# Activation
wp plugin activate cobra-ai-features
```

## ⚙️ Configuration Initiale

### 1. Première Activation

Lors de la première activation, le plugin :

1. **Crée les tables de base de données**
   - `wp_cobra_system_logs` : Logs système
   - `wp_cobra_features` : Registre des fonctionnalités
   - `wp_cobra_events` : Événements système

2. **Définit les options par défaut**
   ```php
   // Options globales créées
   cobra_ai_settings
   cobra_ai_enabled_features
   cobra_ai_version
   cobra_ai_activated
   ```

3. **Crée les répertoires nécessaires**
   - Dossiers de cache
   - Répertoires de logs

### 2. Accès à l'Interface d'Administration

Après activation, vous trouverez un nouveau menu dans l'admin WordPress :

```
Cobra AI
├── Tableau de bord        # Vue d'ensemble
├── Fonctionnalités       # Gestion des features
└── Paramètres           # Configuration globale
```

### 3. Configuration des Fonctionnalités

#### Accès aux Fonctionnalités
```
Cobra AI → Fonctionnalités
```

#### Fonctionnalités Disponibles par Défaut

| Fonctionnalité | Description | Statut par défaut |
|---------------|-------------|-------------------|
| **AI System** | Intégration IA (OpenAI, Claude) | Désactivé |
| **Credits System** | Gestion des crédits utilisateur | Désactivé |
| **Contact Form** | Formulaires de contact | Désactivé |
| **FAQ System** | Questions fréquentes | Désactivé |
| **Stripe Integration** | Paiements Stripe | Désactivé |
| **User Registration** | Inscription avancée | Désactivé |

## 🔧 Configuration des Fonctionnalités

### Configuration du Système d'IA

1. **Activation**
   ```
   Cobra AI → Fonctionnalités → AI System → Activer
   ```

2. **Configuration**
   ```
   Cobra AI → AI System → Paramètres
   ```

3. **Paramètres OpenAI**
   ```php
   // Dans l'interface admin
   API Key: sk-xxxxxxxxxx
   Model: gpt-3.5-turbo
   Max Tokens: 2000
   Temperature: 0.7
   ```

4. **Test de Configuration**
   ```
   Cobra AI → AI System → Test de Connexion
   ```

### Configuration du Système de Crédits

1. **Activation**
   ```
   Cobra AI → Fonctionnalités → Credits System → Activer
   ```

2. **Types de Crédits par Défaut**
   - **AI Tokens** : Pour les requêtes IA
   - **Premium Features** : Fonctionnalités premium

3. **Attribution Manuelle**
   ```
   Cobra AI → Credits → Ajouter des Crédits
   ```

### Configuration Stripe

1. **Prérequis**
   - Compte Stripe actif
   - Clés API (Test et Live)

2. **Configuration**
   ```php
   // Clés de test
   Publishable Key: pk_test_xxxxxxxxxx
   Secret Key: sk_test_xxxxxxxxxx
   
   // Webhook Endpoint
   https://monsite.com/wp-json/cobra-ai/v1/stripe/webhook
   ```

3. **Webhooks Stripe**
   ```
   Types d'événements à configurer :
   - payment_intent.succeeded
   - payment_intent.payment_failed
   - customer.subscription.created
   - customer.subscription.deleted
   ```

## 🛠️ Configuration Avancée

### 1. Fichier wp-config.php

Ajoutez ces constantes pour une configuration avancée :

```php
// Configuration Cobra AI
define('COBRA_AI_DEBUG', true);                    // Mode debug
define('COBRA_AI_LOG_LEVEL', 'info');             // Niveau de logs
define('COBRA_AI_CACHE_DURATION', 3600);          // Durée du cache
define('COBRA_AI_MAX_FEATURES', 20);              // Limite de fonctionnalités
define('COBRA_AI_API_TIMEOUT', 30);               // Timeout API

// Configuration base de données
define('COBRA_AI_DB_PREFIX', 'cobra_');           // Préfixe tables
define('COBRA_AI_LOG_RETENTION', 30);             // Rétention logs (jours)

// Sécurité
define('COBRA_AI_RATE_LIMIT', 60);                // Limite de requêtes/heure
define('COBRA_AI_REQUIRE_HTTPS', true);           // HTTPS obligatoire
```

### 2. Configuration Serveur Web

#### Apache (.htaccess)
```apache
# Protection des fichiers de configuration
<Files "*.php">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</Files>

# Optimisation des assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

#### Nginx
```nginx
# Limitation du rate limiting
location /wp-json/cobra-ai/ {
    limit_req zone=api burst=10 nodelay;
}

# Cache des assets
location ~* \.(css|js|png|jpg|jpeg|gif|svg)$ {
    expires 1M;
    add_header Cache-Control "public, immutable";
}
```

### 3. Configuration PHP

#### php.ini Recommandé
```ini
; Mémoire
memory_limit = 256M
max_execution_time = 60

; Uploads
upload_max_filesize = 64M
post_max_size = 64M

; Sessions
session.gc_maxlifetime = 3600

; Logs
log_errors = On
error_log = /path/to/php-errors.log
```

## 🔒 Configuration de Sécurité

### 1. Permissions Utilisateur

Le plugin utilise les capacités WordPress standard :

```php
// Capacités requises
'manage_options'     // Configuration globale
'edit_users'        // Gestion des crédits utilisateur  
'moderate_comments' // Modération des soumissions
'publish_posts'     // Utilisation des fonctionnalités
```

### 2. Protection des API Keys

#### Stockage Sécurisé
```php
// Méthode recommandée dans wp-config.php
define('OPENAI_API_KEY', 'sk-xxxxxxxxxx');
define('STRIPE_SECRET_KEY', 'sk_test_xxxxxxxxxx');

// Utilisation dans les paramètres
$api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : $settings['openai']['api_key'];
```

#### Variables d'Environnement
```bash
# .env (avec plugin de gestion d'environnement)
OPENAI_API_KEY=sk-xxxxxxxxxx
STRIPE_SECRET_KEY=sk_test_xxxxxxxxxx
```

### 3. Configuration HTTPS

#### Force HTTPS
```php
// wp-config.php
define('FORCE_SSL_ADMIN', true);
define('COBRA_AI_REQUIRE_HTTPS', true);
```

#### Certificat SSL
Assurez-vous d'avoir un certificat SSL valide pour :
- Les webhooks externes
- Les API sécurisées
- La protection des données utilisateur

## 📊 Monitoring et Logs

### 1. Configuration des Logs

#### Niveaux de Logs
```php
// Configuration dans Cobra AI → Paramètres
Log Level Options:
- debug   : Tous les messages (développement)
- info    : Informations générales
- warning : Avertissements
- error   : Erreurs uniquement (production)
```

#### Rotation des Logs
```php
// Configuration automatique
Max Log Age: 30 days (configurable)
Auto Cleanup: Enabled
Log File Size: 10MB max per file
```

### 2. Monitoring de Performance

#### Métriques Disponibles
- Temps de réponse des API
- Utilisation mémoire
- Nombre de requêtes par heure
- Taux d'erreur par fonctionnalité

#### Dashboard Admin
```
Cobra AI → Tableau de bord
├── Statistiques générales
├── Santé des fonctionnalités  
├── Logs récents
└── Métriques de performance
```

## 🔧 Dépannage Installation

### Problèmes Courants

#### 1. Erreur "Plugin could not be activated"
```php
// Vérifications :
- Version PHP ≥ 7.4
- Version WordPress ≥ 5.8
- Extensions PHP requises installées
- Permissions d'écriture suffisantes
```

#### 2. Tables non créées
```sql
-- Vérification manuelle
SHOW TABLES LIKE 'wp_cobra_%';

-- Création forcée (si nécessaire)
-- Les tables seront recréées lors de la réactivation
```

#### 3. Fonctionnalités non visibles
```php
// Vérification des droits utilisateur
if (current_user_can('manage_options')) {
    // L'utilisateur doit avoir les droits d'administration
}
```

### Logs de Debug

#### Activation du Mode Debug
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('COBRA_AI_DEBUG', true);
```

#### Emplacement des Logs
```
/wp-content/debug.log                    # Logs WordPress
/wp-content/uploads/cobra-ai/logs/       # Logs spécifiques
```

### Support et Assistance

#### Informations Système
```
Cobra AI → Paramètres → Informations Système
```
Cette page affiche :
- Versions (WordPress, PHP, Plugin)
- Configuration serveur
- Statut des fonctionnalités
- Logs récents

#### Contact Support
- **Documentation** : Consultez les fichiers `/docs/`
- **Issues** : GitHub repository issues
- **Email** : Support technique disponible

Votre installation de Cobra AI Features est maintenant complète et configurée !