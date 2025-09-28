# Vue d'ensemble du Plugin Cobra AI Features

## 🎯 Description Générale

**Cobra AI Features** est un plugin WordPress modulaire conçu pour fournir une suite complète de fonctionnalités alimentées par l'IA et des outils d'automatisation pour les sites web. Le plugin utilise une architecture modulaire qui permet d'activer/désactiver facilement les différentes fonctionnalités selon les besoins.

## 🚀 Objectifs Principaux

- **Modularité** : Chaque fonctionnalité peut être activée/désactivée indépendamment
- **Intelligence Artificielle** : Intégration avec plusieurs fournisseurs d'IA
- **Flexibilité** : Architecture extensible pour ajouter de nouvelles fonctionnalités
- **Performance** : Optimisé pour de bonnes performances avec mise en cache
- **Sécurité** : Implémentation de bonnes pratiques de sécurité

## 📦 Fonctionnalités Principales

### 🤖 Système d'IA (AI Feature)
- Intégration avec OpenAI, Claude, et autres fournisseurs
- Suivi des requêtes et réponses
- Gestion des quotas et limites
- Interface d'administration pour le monitoring

### 💰 Système de Crédits (Credits Feature)
- Gestion de différents types de crédits
- Expiration automatique des crédits
- Historique des transactions
- Intégration avec les autres fonctionnalités

### 💳 Intégration Stripe (Stripe Features)
- Paiements uniques avec Stripe
- Gestion des abonnements (Stripe Subscriptions)
- Webhooks et événements
- Logs détaillés des transactions

### 📧 Formulaire de Contact (Contact Feature)
- Formulaires personnalisables
- Protection anti-spam
- Interface d'administration pour les soumissions
- Templates personnalisables

### ❓ Système FAQ (FAQ Feature)
- Gestion des questions fréquentes
- Système de votes (utile/pas utile)
- Compteur de vues
- Shortcodes pour l'affichage

### 👥 Inscription Utilisateur (Register Feature)
- Inscription avancée avec vérification email
- Approbation administrateur
- Templates personnalisés
- Intégration avec les autres systèmes

### 🔐 Autres Fonctionnalités
- **reCAPTCHA** : Protection anti-spam
- **SMTP** : Configuration email avancée
- **Auth Google** : Connexion via Google
- **Extensions** : Système d'extensions

## 🏗️ Architecture Technique

### Namespace Principal
```php
namespace CobraAI;
```

### Classes Principales
- `CobraAI` : Classe principale du plugin (Singleton)
- `FeatureBase` : Classe abstraite pour toutes les fonctionnalités
- `Database` : Gestion centralisée de la base de données
- `Admin` : Interface d'administration
- `APIManager` : Gestion des API externes

### Structure des Fonctionnalités
Chaque fonctionnalité suit cette structure :
```
features/{nom-fonctionnalité}/
├── Feature.php           # Classe principale
├── includes/            # Classes helper
├── assets/             # CSS, JS, images
├── views/              # Templates admin
├── templates/          # Templates frontend
└── assets/help.html    # Documentation
```

## 🔧 Technologies Utilisées

- **PHP 7.4+** : Langage principal
- **WordPress 5.8+** : Plateforme
- **Composer** : Gestion des dépendances
- **Stripe PHP SDK** : Intégration paiements
- **PSR-4** : Autoloading standard
- **Namespaces** : Organisation du code

## 📊 Base de Données

### Tables Principales
- `cobra_system_logs` : Logs du système
- `cobra_features` : Registre des fonctionnalités
- `cobra_events` : Événements système

### Tables par Fonctionnalité
Chaque fonctionnalité peut définir ses propres tables :
- `cobra_ai_trackings` : Suivi IA
- `cobra_credits` : Système de crédits
- `cobra_contact_submissions` : Soumissions contact
- `cobra_faq_views` : Statistiques FAQ

## ⚙️ Configuration

### Options WordPress
- `cobra_ai_enabled_features` : Liste des fonctionnalités actives
- `cobra_ai_settings` : Configuration globale
- `cobra_ai_{feature}_options` : Configuration par fonctionnalité

### Constantes PHP
```php
COBRA_AI_VERSION      // Version du plugin
COBRA_AI_PATH         // Chemin du plugin
COBRA_AI_URL          // URL du plugin
COBRA_AI_FEATURES_DIR // Répertoire des fonctionnalités
```

## 🎣 Système de Hooks

### Actions WordPress
- `cobra_ai_loaded` : Plugin chargé
- `cobra_ai_features_loaded` : Fonctionnalités chargées
- `cobra_ai_feature_activated_{feature}` : Fonctionnalité activée
- `cobra_ai_feature_deactivated_{feature}` : Fonctionnalité désactivée

### Filtres WordPress
- `cobra_ai_feature_default_options_{feature}` : Options par défaut
- `cobra_ai_feature_sanitize_settings_{feature}` : Sanitisation

## 🔒 Sécurité

### Mesures Implémentées
- Validation et sanitisation des données
- Nonces pour les formulaires AJAX
- Vérification des capacités utilisateur
- Protection contre l'accès direct aux fichiers
- Rate limiting pour les API

### Bonnes Pratiques
- Échappement des données en sortie
- Préparation des requêtes SQL
- Validation des entrées utilisateur
- Logs de sécurité

## 📈 Performance

### Optimisations
- Chargement conditionnel des fonctionnalités
- Mise en cache des requêtes
- Autoloader Composer optimisé
- Minification des assets (optionnel)

### Monitoring
- Logs des performances
- Suivi des requêtes API
- Monitoring des erreurs
- Health checks des fonctionnalités

## 🌐 Compatibilité

### Versions WordPress
- Minimum : WordPress 5.8
- Testé jusqu'à : WordPress 6.4
- Compatible multisite

### Versions PHP
- Minimum : PHP 7.4
- Recommandé : PHP 8.0+
- Compatible avec PHP 8.1, 8.2

### Navigateurs
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

Cette vue d'ensemble vous donne une compréhension globale du plugin. Pour des informations plus détaillées, consultez les autres sections de la documentation.