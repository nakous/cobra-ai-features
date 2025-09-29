# Guide d'utilisation - Gestion des abonnements Stripe

## Fonctionnalités ajoutées

### 1. Annulation d'abonnement
- **Action AJAX** : `cobra_cancel_subscription`
- **Méthode** : `handle_cancel_subscription()`
- **Options** :
  - Annulation immédiate
  - Annulation à la fin de la période de facturation
  - Collecte de la raison d'annulation

### 2. Reprise d'abonnement
- **Action AJAX** : `cobra_resume_subscription`
- **Méthode** : `handle_resume_subscription()`
- **Fonction** : Réactive un abonnement annulé

### 3. Mise à jour du moyen de paiement
- **Action AJAX** : `cobra_update_payment_method`
- **Méthode** : `handle_update_payment_method()`
- **Fonction** : Redirige vers le portail de facturation Stripe

## Utilisation dans l'interface utilisateur

### Dans l'onglet compte utilisateur

1. **Section Abonnements** :
   - Affiche tous les abonnements actifs de l'utilisateur
   - Boutons d'action contextuels selon le statut

2. **Modal d'annulation** :
   - Choix entre annulation immédiate ou fin de période
   - Sélection de la raison (optionnelle)
   - Confirmation avant traitement

3. **Gestion des statuts** :
   - `active` → Bouton "Annuler l'abonnement"
   - `canceled` → Bouton "Reprendre l'abonnement"
   - Tous statuts → Bouton "Mettre à jour le paiement"

## JavaScript/AJAX

### Événements gérés
```javascript
// Annulation
jQuery('#cobra-cancel-subscription').on('click', function() {
    // Ouvre le modal d'annulation
});

// Confirmation d'annulation
jQuery('#confirm-cancellation').on('click', function() {
    // Envoie la requête AJAX
});

// Reprise
jQuery('#cobra-resume-subscription').on('click', function() {
    // Envoie la requête AJAX directement
});
```

### Réponses AJAX
- **Succès** : Message de confirmation + rechargement de la page
- **Erreur** : Affichage du message d'erreur

## Base de données

### Mise à jour des abonnements
Les changements de statut sont automatiquement synchronisés :
- Table locale `wp_cobra_stripe_subscriptions`
- API Stripe via webhooks

### Logs
Toutes les actions sont enregistrées dans les logs du plugin pour le débogage.

## Sécurité

- Vérification des nonces WordPress
- Vérification des permissions utilisateur
- Validation des données côté serveur
- Communication sécurisée avec l'API Stripe