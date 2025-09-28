# Vérification des Shortcodes du Plugin Cobra AI

## Shortcodes Définis dans le System

Basé sur `features/register/Feature.php` lignes 153-159 :

### Shortcodes Enregistrés ✅
- `[cobra_login]` - Formulaire de connexion
- `[cobra_register]` - Formulaire d'inscription  
- `[cobra_forgot_password]` - Formulaire mot de passe oublié
- `[cobra_reset_password]` - Formulaire reset mot de passe
- `[cobra_account]` - Page compte utilisateur
- `[cobra_logout]` - Lien de déconnexion
- `[cobra_confirm_registration]` - Confirmation d'inscription

## Configuration des Pages Corrigée

Dans `features/register/views/settings.php` :

### Avant (❌ Incorrect)
```php
'login' => [
    'description' => __('Page containing the [user_login] shortcode.', 'cobra-ai'),
    'shortcode' => '[user_login]'
],
'register' => [
    'description' => __('Page containing the [user_register] shortcode.', 'cobra-ai'),
    'shortcode' => '[user_register]'
],
// etc...
```

### Après (✅ Correct)
```php
'login' => [
    'description' => __('Page containing the [cobra_login] shortcode.', 'cobra-ai'),
    'shortcode' => '[cobra_login]'
],
'register' => [
    'description' => __('Page containing the [cobra_register] shortcode.', 'cobra-ai'),
    'shortcode' => '[cobra_register]'
],
// etc...
```

## Points de Vérification

### 1. Cohérence des Noms
- ✅ Tous les shortcodes utilisent le préfixe `cobra_`
- ✅ Les noms correspondent entre l'enregistrement et la configuration
- ✅ Les descriptions sont mises à jour

### 2. JavaScript Integration
- ✅ `data-shortcode` attributes utilisent les bons shortcodes
- ✅ Code HTML `<code>` affiche les bons shortcodes  
- ✅ JavaScript dynamique récupère les bons shortcodes

### 3. Création de Pages
Le processus de création de page fonctionne comme suit :
1. L'utilisateur clique sur "Create Page"
2. JavaScript récupère `data-shortcode` du bouton
3. AJAX envoie le shortcode comme `page_content`
4. PHP crée la page avec le shortcode correct

## Résultat

Maintenant, quand l'administrateur crée une nouvelle page depuis l'onglet "Pages" :
- ✅ Le shortcode inséré sera `[cobra_login]` au lieu de `[user_login]`
- ✅ Le shortcode inséré sera `[cobra_register]` au lieu de `[user_register]`
- ✅ Etc. pour tous les autres formulaires

## Test Recommandé

Pour vérifier que la correction fonctionne :
1. Aller dans WordPress Admin → Cobra AI → Register → Pages
2. Cliquer sur "Create Page" pour n'importe quel type de page
3. Vérifier que la nouvelle page contient le bon shortcode `[cobra_*]`
4. Tester que le shortcode s'affiche correctement en frontend

## Shortcodes Additionnels (Autres Fonctionnalités)

### AuthGoogle
- `[cobra_google_login]` - Bouton de connexion Google

### Autres Fonctionnalités
Vérifiez les autres Features pour d'éventuels shortcodes additionnels qui pourraient nécessiter des corrections similaires.