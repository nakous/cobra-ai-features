# 🚀 Cobra AI - Stripe Subscriptions Feature

Une fonctionnalité complète de gestion des abonnements Stripe pour le plugin Cobra AI, avec interface utilisateur moderne et gestion avancée des abonnements.

## 📋 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Installation](#-installation)
- [Structure des fichiers](#-structure-des-fichiers)
- [Utilisation](#-utilisation)
- [Shortcodes](#-shortcodes)
- [Hooks et Filtres](#-hooks-et-filtres)
- [Base de données](#-base-de-données)
- [API et Webhooks](#-api-et-webhooks)
- [Développement](#-développement)

## ✨ Fonctionnalités

### 🎯 Gestion des abonnements
- ✅ Création et gestion des plans d'abonnement
- ✅ Checkout intégré avec Stripe
- ✅ Gestion complète du cycle de vie des abonnements
- ✅ Interface utilisateur pour l'annulation/reprise d'abonnements
- ✅ Mise à jour des moyens de paiement
- ✅ Portail de facturation Stripe intégré

### 📧 Système de notifications
- ✅ Emails automatiques (création, annulation, échec de paiement)
- ✅ Templates HTML responsives
- ✅ Notifications en temps réel

### 🎨 Interface utilisateur
- ✅ Design moderne et responsive
- ✅ Modals interactifs pour la gestion des abonnements
- ✅ Système de messages et feedback utilisateur
- ✅ Loading states et animations

### 🔗 Intégrations
- ✅ Stripe API complète
- ✅ Webhooks Stripe pour la synchronisation
- ✅ WordPress hooks système
- ✅ Compatibilité multilingue

## 🛠 Installation

1. **Prérequis**
   - Plugin Cobra AI installé
   - Feature Stripe de base activée
   - Compte Stripe configuré

2. **Activation**
   ```php
   // La feature est automatiquement chargée si les dépendances sont satisfaites
   $feature = cobra_ai()->get_feature('stripesubscriptions');
   ```

## 📁 Structure des fichiers

```
features/stripesubscriptions/
├── 📄 Feature.php                    # Classe principale
├── 📂 assets/
│   ├── 🎨 css/
│   │   ├── public.css              # Styles publics optimisés
│   │   ├── admin.css               # Styles administration
│   │   └── subscription-styles.css # Styles spécifiques gestion abonnements
│   ├── 📜 js/
│   │   ├── public.js               # Scripts publics
│   │   ├── admin.js                # Scripts administration
│   │   └── subscription-manager.js # Gestionnaire abonnements
│   └── 📋 help.html                # Documentation d'aide
├── 📂 includes/
│   ├── 🏗️ Admin.php               # Interface administration
│   ├── 🔌 API.php                  # Gestion API Stripe
│   ├── 👥 Customers.php            # Gestion clients
│   ├── 💰 Payments.php             # Gestion paiements
│   ├── 📋 Plans.php                # Gestion plans
│   ├── 📊 Subscriptions.php        # Gestion abonnements
│   ├── 🔔 Webhooks.php             # Gestion webhooks
│   └── 🛠️ Utilities.php           # Fonctions utilitaires
├── 📂 templates/
│   ├── 📂 email/                   # Templates d'emails
│   │   ├── subscription-created.php
│   │   ├── subscription-cancelled.php
│   │   └── payment-failed.php
│   ├── 📂 shortcodes/              # Templates shortcodes
│   │   ├── checkout.php
│   │   ├── plan-list.php
│   │   └── subscription-details.php
│   ├── account-info.php            # Infos compte
│   ├── checkout.php                # Page checkout
│   └── payment-history.php         # Historique paiements
├── 📂 views/
│   ├── 📂 admin/                   # Vues administration
│   │   ├── payments.php
│   │   ├── plans.php
│   │   ├── subscriptions.php
│   │   └── partials/
│   ├── 📂 public/                  # Vues publiques
│   │   ├── account.php
│   │   ├── checkout.php
│   │   ├── success.php
│   │   ├── cancel.php
│   │   └── partials/
│   └── settings.php                # Paramètres
└── 📚 SUBSCRIPTION_MANAGEMENT_GUIDE.md # Guide utilisateur
```

## 🚀 Utilisation

### Interface d'administration

1. **Accéder aux paramètres**
   ```
   WordPress Admin → Cobra AI → Stripe Subscriptions
   ```

2. **Créer un plan d'abonnement**
   ```php
   $plan_data = [
       'name' => 'Plan Premium',
       'amount' => 2999, // 29.99€ en centimes
       'currency' => 'EUR',
       'interval' => 'month',
       'description' => 'Accès premium à toutes les fonctionnalités'
   ];
   
   $plan = $plans->create_plan($plan_data);
   ```

### Interface utilisateur

1. **Afficher les plans**
   ```php
   [stripe_plans columns="3" show_trial="true"]
   ```

2. **Checkout d'abonnement**
   ```php
   [stripe_checkout plan_id="123" button_text="S'abonner maintenant"]
   ```

3. **Détails d'abonnement utilisateur**
   ```php
   [stripe_subscription_details]
   ```

## 📝 Shortcodes

### `[stripe_plans]`
Affiche la liste des plans d'abonnement disponibles.

**Paramètres :**
- `columns` (int) : Nombre de colonnes (1-4, défaut: 3)
- `show_trial` (bool) : Afficher les périodes d'essai (défaut: true)
- `show_features` (bool) : Afficher les fonctionnalités (défaut: true)
- `highlight` (string) : ID du plan à mettre en évidence
- `currency` (string) : Devise à filtrer (défaut: USD)

**Exemple :**
```php
[stripe_plans columns="2" highlight="plan_123" currency="EUR"]
```

### `[stripe_checkout]`
Affiche un formulaire de checkout pour un plan spécifique.

**Paramètres :**
- `plan_id` (string) : ID du plan (requis)
- `success_url` (string) : URL de redirection en cas de succès
- `cancel_url` (string) : URL de redirection en cas d'annulation
- `button_text` (string) : Texte du bouton (défaut: "Subscribe Now")

### `[stripe_subscription_details]`
Affiche les détails et options de gestion des abonnements utilisateur.

## 🎣 Hooks et Filtres

### Actions disponibles

```php
// Abonnement créé
do_action('cobra_ai_subscription_created', $subscription_id, $subscription_data);

// Abonnement mis à jour
do_action('cobra_ai_subscription_updated', $subscription_id, $subscription_data);

// Abonnement annulé
do_action('cobra_ai_subscription_cancelled', $subscription_id, $cancellation_data);

// Paiement réussi
do_action('cobra_ai_payment_successful', $payment_id, $payment_data);

// Paiement échoué
do_action('cobra_ai_payment_failed', $payment_id, $error_data);
```

### Filtres disponibles

```php
// Modifier les actions disponibles pour un abonnement
add_filter('cobra_subscription_actions', function($actions, $subscription) {
    // Ajouter une action personnalisée
    $actions['custom'] = [
        'label' => 'Action personnalisée',
        'class' => 'cobra-btn-custom',
        'icon' => 'custom-icon'
    ];
    return $actions;
}, 10, 2);

// Modifier les raisons d'annulation
add_filter('cobra_cancellation_reasons', function($reasons) {
    $reasons['custom_reason'] = 'Raison personnalisée';
    return $reasons;
});
```

## 🗄️ Base de données

### Tables créées automatiquement

#### `wp_cobra_stripe_subscriptions`
```sql
CREATE TABLE wp_cobra_stripe_subscriptions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    subscription_id varchar(100) NOT NULL,
    customer_id varchar(100) NOT NULL,
    plan_id bigint(20) NOT NULL,
    user_id bigint(20) NOT NULL,
    status enum('active','past_due','canceled','incomplete','incomplete_expired','trialing','unpaid','paused') NOT NULL,
    current_period_start datetime NOT NULL,
    current_period_end datetime NOT NULL,
    cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
    cancel_reason text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY subscription_id (subscription_id)
);
```

#### `wp_cobra_stripe_payments`
```sql
CREATE TABLE wp_cobra_stripe_payments (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    payment_id varchar(100) NOT NULL,
    subscription_id bigint(20) NOT NULL,
    invoice_id varchar(100),
    amount decimal(10,2) NOT NULL,
    currency varchar(3) NOT NULL,
    status enum('pending','succeeded','failed','refunded') NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY payment_id (payment_id)
);
```

## 🔌 API et Webhooks

### Endpoints AJAX

- `cobra_cancel_subscription` : Annulation d'abonnement
- `cobra_resume_subscription` : Reprise d'abonnement  
- `cobra_update_payment_method` : Mise à jour du moyen de paiement
- `cobra_create_checkout_session` : Création session de checkout

### Webhooks Stripe supportés

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `customer.subscription.trial_will_end`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

## 👨‍💻 Développement

### Logging et débogage

```php
// Activer les logs de débogage
$feature->log('info', 'Message de débogage', $context_data);

// Types de logs disponibles
$feature->log('error', 'Erreur critique', $error_data);
$feature->log('warning', 'Avertissement', $warning_data);
$feature->log('info', 'Information', $info_data);
```

### Extensibilité

```php
// Exemple d'extension personnalisée
class CustomSubscriptionHandler {
    public function __construct() {
        add_action('cobra_ai_subscription_created', [$this, 'handle_new_subscription']);
        add_filter('cobra_subscription_actions', [$this, 'add_custom_actions'], 10, 2);
    }
    
    public function handle_new_subscription($subscription_id, $data) {
        // Logique personnalisée pour nouveaux abonnements
        $this->send_welcome_gift($data['user_id']);
        $this->update_user_role($data['user_id'], 'premium_subscriber');
    }
    
    public function add_custom_actions($actions, $subscription) {
        if ($subscription->status === 'active') {
            $actions['upgrade'] = [
                'label' => 'Mettre à niveau',
                'class' => 'cobra-btn-primary',
                'icon' => 'arrow-up'
            ];
        }
        return $actions;
    }
}

new CustomSubscriptionHandler();
```

### Tests

```php
// Tester les fonctionnalités en local
if (WP_DEBUG) {
    // Mode de test Stripe
    define('STRIPE_TEST_MODE', true);
    
    // Webhooks de test
    add_action('wp_ajax_test_webhook', function() {
        $feature = cobra_ai()->get_feature('stripesubscriptions');
        $feature->webhook->handle_subscription_created($test_data);
    });
}
```

## 📞 Support

Pour toute question ou problème :

1. **Documentation** : Consultez ce README et les guides inclus
2. **Logs** : Vérifiez les logs WordPress et les logs de la feature
3. **Debug** : Activez `WP_DEBUG` pour plus d'informations
4. **Stripe Dashboard** : Vérifiez les événements et webhooks côté Stripe

---

**Version :** 1.1.0  
**Compatibilité :** WordPress 5.8+, PHP 7.4+  
**Dépendances :** Cobra AI Core, Stripe Feature