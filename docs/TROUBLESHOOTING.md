# Guide de dépannage pour l'onglet contact

## 1. Vérifier que les features sont activées

Allez dans votre admin WordPress → Cobra AI → Features
Assurez-vous que les features "Contact" et "Register" sont activées.

## 2. Vérifier les logs

Activez les logs WordPress en ajoutant dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Puis vérifiez le fichier `wp-content/debug.log` pour les messages "COBRA DEBUG".

## 3. Vérifier que le shortcode account est utilisé

L'onglet contact n'apparaît que sur la page qui utilise le shortcode `[cobra_account_form]`.

## 4. Test direct des hooks

Ajoutez ce code temporaire dans functions.php pour tester :

```php
add_action('init', function() {
    if (function_exists('cobra_ai')) {
        $contact = cobra_ai()->get_feature('contact');
        if ($contact) {
            error_log('Contact feature loaded: ' . get_class($contact));
            
            // Tester directement le hook
            if (has_action('cobra_register_profile_tab')) {
                error_log('Hook cobra_register_profile_tab has actions');
            }
        }
    }
});
```

## 5. Vérifier la page account

Assurez-vous que vous visitez la bonne page qui contient le formulaire de compte.

## 6. Vérifier l'utilisateur connecté

L'onglet ne s'affiche que pour les utilisateurs connectés.

## 7. Forcer l'affichage pour test

Vous pouvez temporairement modifier la méthode `contact_account_custom_tab()` pour toujours afficher l'onglet :

```php
public function contact_account_custom_tab()
{
    ?>
    <li>
        <a href="#contact-messages" data-tab="contact-messages">
            Mes Messages (TEST)
        </a>
    </li>
    <?php
}
```

## 8. Inspection du HTML

Utilisez les outils développeur de votre navigateur pour voir si l'élément `<li>` de l'onglet contact est présent dans le HTML mais caché par du CSS.

## 9. Cache

Videz tous les caches (plugin de cache, cache navigateur, etc.).

## 10. Si rien ne fonctionne

Contactez-moi avec :
- Le contenu du fichier debug.log
- Une capture d'écran de la page de gestion des features
- L'URL de la page où vous testez
- Le code source HTML de la section des onglets