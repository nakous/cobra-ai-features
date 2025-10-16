# Améliorations de la Gestion des Pages

## Problème Résolu

L'utilisateur a signalé un problème où après avoir supprimé le contenu d'une page (par exemple, le shortcode `[cobra_account]`), l'interface d'administration continuait d'afficher le bouton "Modifier la page" au lieu de détecter que la page n'existait plus ou ne contenait plus le shortcode requis.

## Solution Implémentée

### 1. Détection Intelligente du Statut des Pages

**Fichier modifié** : `features/register/views/settings.php`

Une nouvelle fonction `check_page_status` a été ajoutée qui vérifie :
- Si la page existe réellement dans WordPress
- Si la page est publiée
- Si la page contient le shortcode requis

```php
$check_page_status = function($page_id, $shortcode) {
    if (empty($page_id)) {
        return ['exists' => false, 'has_shortcode' => false];
    }
    
    $page = get_post($page_id);
    if (!$page || $page->post_status !== 'publish') {
        return ['exists' => false, 'has_shortcode' => false];
    }
    
    $has_shortcode = strpos($page->post_content, $shortcode) !== false;
    return ['exists' => true, 'has_shortcode' => $has_shortcode, 'page' => $page];
};
```

### 2. Interface Adaptative

L'interface affiche maintenant différents états selon le statut de la page :

#### Page inexistante ou supprimée
- Bouton "Créer la page"
- Message d'avertissement si une page était précédemment sélectionnée

#### Page existante sans le bon shortcode
- Boutons "Modifier la page" et "Voir la page"
- Avertissement indiquant que le shortcode requis est manquant
- Bouton "Réinitialiser" pour effacer la sélection

#### Page existante avec le bon shortcode
- Boutons "Modifier la page" et "Voir la page"
- Bouton "Réinitialiser" pour permettre la resélection

### 3. Réinitialisation Automatique des Paramètres

Quand une page n'existe plus, le paramètre est automatiquement réinitialisé dans l'interface (la base de données sera mise à jour lors de la sauvegarde du formulaire).

### 4. Action AJAX pour la Réinitialisation

**Fichier modifié** : `features/register/Feature.php`

Ajout de deux nouvelles méthodes :

#### `update_setting(string $key, $value): bool`
Permet de mettre à jour un paramètre spécifique en utilisant la notation par points :
```php
$this->update_setting('pages.login', 123);
```

#### `handle_reset_page_setting(): void`
Gère les requêtes AJAX pour réinitialiser un paramètre de page :
```php
add_action('wp_ajax_cobra_reset_page_setting', [$this, 'handle_reset_page_setting']);
```

### 5. JavaScript Amélioré

Le JavaScript a été amélioré pour :
- Gérer le bouton de réinitialisation avec confirmation
- Mettre à jour l'interface dynamiquement lors des changements de sélection
- Nettoyer les messages d'avertissement lors des changements

## Fonctionnalités

### États des Pages Gérés

1. **Page non sélectionnée** : Affiche le bouton "Créer la page"
2. **Page sélectionnée mais supprimée** : Réinitialise automatiquement + bouton "Créer la page" + avertissement
3. **Page existante sans shortcode** : Boutons d'édition + avertissement + bouton de réinitialisation
4. **Page existante avec shortcode** : Boutons d'édition + bouton de réinitialisation

### Messages d'Avertissement

- **Page supprimée** : "Attention : La page sélectionnée n'existe plus et a été réinitialisée."
- **Shortcode manquant** : "Attention : La page ne contient pas le shortcode requis [cobra_xxx]"

### Sécurité

- Vérification des nonces pour les actions AJAX
- Vérification des permissions (manage_options pour la réinitialisation)
- Validation et sanitisation des données d'entrée

## Test du Système

Pour tester le système :

1. Créer une page via l'interface
2. Supprimer le contenu de la page ou la page complètement
3. Retourner dans les paramètres de la fonctionnalité
4. Vérifier que l'interface détecte automatiquement le problème
5. Utiliser le bouton "Réinitialiser" ou "Créer la page" selon le cas

## Bénéfices

- **Détection automatique** des problèmes de pages
- **Interface adaptative** selon l'état réel des pages
- **Messages informatifs** pour guider l'utilisateur
- **Réinitialisation facile** en cas de problème
- **Maintien de la cohérence** entre interface et réalité