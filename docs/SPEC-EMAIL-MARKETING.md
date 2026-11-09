# SPEC — Feature `emailmarketing`

> **Plugin :** `cobra-ai-features`
> **Feature ID :** `emailmarketing`
> **Namespace :** `CobraAI\Features\EmailMarketing`
> **Dossier :** `features/emailmarketing/`
> **Statut :** Spécification — à implémenter
> **Date :** 2026-04-10
> **Auteur :** Nakous Mustapha

---

## 1. Objectif

Workflow email automatique, générique et réutilisable sur tout site WordPress utilisant cobra-ai-features.
Conçu pour accompagner l'utilisateur depuis l'activation de son compte jusqu'au suivi hebdomadaire de sa progression.

**Ce que ce feature NE fait PAS :**
- Envoyer les emails de vérification et confirmation (géré par le feature `register`)
- Gérer le transport SMTP (géré par le feature `smtp`)

**Ce que ce feature FAIT :**
- Orchestrer le workflow email post-activation
- Gérer les templates éditables depuis l'admin
- Planifier et envoyer les emails via WP Cron
- Logger tous les envois
- Gérer les préférences utilisateur (désinscription par type)
- Gérer les bounces et spam (via webhooks Brevo si API key disponible)
- Offrir des hooks WordPress pour que d'autres plugins déclenchent des emails

---

## 2. Dépendances

| Dépendance | Type | Raison |
|---|---|---|
| `smtp` | Feature cobra-ai (requise) | Transport email via `wp_mail()` |
| `register` | Feature cobra-ai (optionnelle) | Hook sur `cobra_register_user_confirmed` |
| `canvas_quiz` | Plugin externe (optionnel) | Hook sur `canvas_quiz_session_completed` |

---

## 3. Structure des fichiers

```
features/emailmarketing/
├── Feature.php                        # Classe principale, settings, cron
├── includes/
│   ├── EmailSender.php                # Envoi wp_mail() + logging + anti-doublon
│   ├── EmailQueue.php                 # File d'attente, planification, déduplification
│   ├── TemplateEngine.php             # Rendu des templates avec variables
│   ├── CronManager.php                # Enregistrement et callbacks WP Cron
│   ├── BounceHandler.php              # Webhook Brevo → blacklist (optionnel)
│   └── UserEmailPrefs.php             # Préférences user + unsubscribe token
├── views/
│   ├── settings.php                   # Page admin principale (tabs)
│   ├── tabs/
│   │   ├── general.php                # Toggles par type d'email + fréquences
│   │   ├── templates.php              # Éditeur de templates
│   │   ├── log.php                    # Historique des envois
│   │   └── bounce.php                 # Config webhook Brevo
│   └── unsubscribe.php                # Page de désinscription publique
├── templates/
│   ├── layout.html                    # Layout HTML global (header + footer)
│   ├── onboarding-j0.html             # Bienvenue + 1er quiz
│   ├── onboarding-j2.html             # Conseils mode examen
│   ├── onboarding-j7.html             # Bilan J+7
│   ├── re-engagement-7j.html          # "Tu nous manques" (7j inactif)
│   ├── re-engagement-30j.html         # Dernière relance (30j inactif)
│   ├── weekly-report.html             # Rapport hebdomadaire
│   ├── milestone.html                 # Email félicitations (générique)
│   └── tips-generic.html              # Conseils personnalisés
└── assets/
    ├── css/admin.css
    └── js/admin.js                    # Éditeur templates (TinyMCE ou textarea)
```

---

## 4. Base de données

### 4.1 Table `wp_cobra_email_log`

Historique complet de tous les emails envoyés.

```sql
CREATE TABLE wp_cobra_email_log (
    id          BIGINT(20)      NOT NULL AUTO_INCREMENT,
    user_id     BIGINT(20)      NOT NULL,
    email_type  VARCHAR(50)     NOT NULL,          -- 'onboarding_j0', 'weekly_report', etc.
    email_to    VARCHAR(255)    NOT NULL,
    subject     VARCHAR(500)    NOT NULL,
    status      ENUM(
                    'sent',
                    'failed',
                    'bounced',
                    'spam',
                    'skipped'
                )               NOT NULL DEFAULT 'sent',
    sent_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    metadata    LONGTEXT,                          -- JSON : score, permit_type, etc.
    PRIMARY KEY (id),
    KEY user_id     (user_id),
    KEY email_type  (email_type),
    KEY status      (status),
    KEY sent_at     (sent_at)
);
```

### 4.2 Table `wp_cobra_email_queue`

File d'attente pour les emails planifiés (onboarding J+2, J+7, etc.).

```sql
CREATE TABLE wp_cobra_email_queue (
    id              BIGINT(20)  NOT NULL AUTO_INCREMENT,
    user_id         BIGINT(20)  NOT NULL,
    email_type      VARCHAR(50) NOT NULL,
    scheduled_at    DATETIME    NOT NULL,          -- Quand envoyer
    status          ENUM(
                        'pending',
                        'sent',
                        'cancelled',
                        'failed'
                    )           NOT NULL DEFAULT 'pending',
    payload         LONGTEXT,                      -- JSON : données à injecter dans template
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id         (user_id),
    KEY scheduled_at    (scheduled_at),
    KEY status          (status),
    UNIQUE KEY user_email_type (user_id, email_type) -- Un seul email de chaque type par user
);
```

### 4.3 Table `wp_cobra_email_prefs`

Préférences de désinscription par utilisateur.

```sql
CREATE TABLE wp_cobra_email_prefs (
    user_id             BIGINT(20)  NOT NULL,
    unsubscribed_all    TINYINT(1)  NOT NULL DEFAULT 0,
    bounced             TINYINT(1)  NOT NULL DEFAULT 0,
    bounce_count        INT(11)     NOT NULL DEFAULT 0,
    prefs               LONGTEXT,                  -- JSON : {"weekly_report": true, "tips": false}
    unsubscribe_token   VARCHAR(64),               -- Token URL désinscription
    updated_at          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY unsubscribed_all    (unsubscribed_all),
    KEY bounced             (bounced),
    KEY unsubscribe_token   (unsubscribe_token)
);
```

---

## 5. Types d'emails

| ID | Déclencheur | Délai | Condition d'envoi |
|---|---|---|---|
| `onboarding_j0` | Activation compte | Immédiat | 1 seule fois |
| `onboarding_j2` | Activation compte | +2 jours | Si 0 session complétée |
| `onboarding_j7` | Activation compte | +7 jours | Toujours (bilan) |
| `re_engagement_7j` | Cron hebdo | — | 0 session depuis 7 jours |
| `re_engagement_30j` | Cron hebdo | — | 0 session depuis 30 jours |
| `weekly_report` | Cron lundi 8h | — | Au moins 1 session cette semaine |
| `tips` | Cron hebdo | — | Actif + type de permis détecté |
| `milestone` | Hook quiz complété | Immédiat | Conditions milestone atteintes |

---

## 6. Variables de templates

Variables disponibles dans tous les templates :

```
{{prenom}}              Prénom de l'utilisateur (user_meta 'firstname' ou display_name)
{{email}}               Email de l'utilisateur
{{site_name}}           Nom du site (get_bloginfo)
{{site_url}}            URL du site
{{unsubscribe_url}}     URL de désinscription personnalisée
{{account_url}}         URL du compte utilisateur
```

Variables spécifiques au rapport hebdomadaire :

```
{{sessions_count}}      Nombre de sessions cette semaine
{{sessions_delta}}      +X / -X vs semaine précédente
{{score_moyen}}         Score moyen en % cette semaine
{{score_delta}}         Évolution du score vs semaine précédente
{{permit_type}}         Type de permis principal (moto, voiture, etc.)
{{meilleure_serie}}     Nom de la meilleure série
{{meilleure_score}}     Score de la meilleure série
{{serie_faible}}        Nom de la série avec le score le plus bas
{{mode_repartition}}    Ex: "70% apprentissage / 30% examen"
```

Variables spécifiques aux milestones :

```
{{milestone_label}}     Ex: "Première série complétée !"
{{milestone_score}}     Score du quiz déclencheur
{{quiz_name}}           Nom de la série/quiz
```

---

## 7. Calcul du rapport hebdomadaire

Requête principale depuis `{prefix}canvas_quiz_statistics` :

```sql
-- Sessions de la semaine courante
SELECT
    COUNT(*)                                           AS sessions_count,
    ROUND(SUM(total_correct) / SUM(question_number) * 100, 1) AS score_moyen,
    SUM(total_correct)                                 AS total_correct,
    SUM(wrong)                                         AS total_wrong,
    SUM(ignored)                                       AS total_ignored,
    GROUP_CONCAT(DISTINCT mode)                        AS modes_used
FROM {prefix}canvas_quiz_statistics
WHERE user_id = %d
  AND status = 'completed'
  AND date_taken >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Type de permis principal (via JOIN sur canvas_quiz_series)
SELECT s.type, COUNT(*) AS nb
FROM {prefix}canvas_quiz_statistics st
JOIN {prefix}canvas_quiz_series s ON s.id = st.serie_id
WHERE st.user_id = %d
  AND st.date_taken >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY s.type
ORDER BY nb DESC
LIMIT 1;
```

**Règle d'envoi :** Le rapport n'est envoyé que si `sessions_count >= 1` pour la semaine.

---

## 8. Settings (structure `wp_options`)

Clé : `cobra_ai_emailmarketing_options`

```php
[
    'general' => [
        'enabled'           => true,
        'from_name'         => '',          // Hérite du feature smtp si vide
        'from_email'        => '',          // Hérite du feature smtp si vide
        'frequency_limit'   => 1,           // Max emails par user par jour
        'unsubscribe_page'  => 0,           // ID de la page WordPress désinscription
    ],

    'emails' => [
        'onboarding_j0'     => ['enabled' => true,  'subject' => 'Bienvenue sur {{site_name}} !'],
        'onboarding_j2'     => ['enabled' => true,  'subject' => 'Prêt pour ton premier examen ?'],
        'onboarding_j7'     => ['enabled' => true,  'subject' => 'Ton bilan de la semaine'],
        're_engagement_7j'  => ['enabled' => true,  'subject' => 'Tu nous manques !'],
        're_engagement_30j' => ['enabled' => true,  'subject' => 'Dernière chance de reprendre'],
        'weekly_report'     => ['enabled' => true,  'subject' => 'Ta progression cette semaine'],
        'tips'              => ['enabled' => true,  'subject' => 'Conseil du jour pour ton permis'],
        'milestone'         => ['enabled' => true,  'subject' => '{{milestone_label}}'],
    ],

    'templates' => [
        'layout'            => '',          // HTML du layout global (éditable admin)
        'onboarding_j0'     => '',          // Corps du template (éditable admin)
        'onboarding_j2'     => '',
        'onboarding_j7'     => '',
        're_engagement_7j'  => '',
        're_engagement_30j' => '',
        'weekly_report'     => '',
        'tips'              => '',
        'milestone'         => '',
    ],

    'brevo' => [
        'enabled'       => false,
        'api_key'       => '',
        'webhook_token' => '',              // Token secret pour valider les webhooks entrants
    ],

    'cron' => [
        'weekly_report_day'     => 'monday',    // Jour d'envoi du rapport
        'weekly_report_hour'    => 8,           // Heure (0-23)
        'queue_process_interval'=> 'hourly',    // Fréquence de traitement de la queue
    ],
]
```

---

## 9. Hooks WordPress exposés

### Hooks entrants (canvas_quiz → emailmarketing)

```php
// Déclenché par canvas_quiz quand un quiz est complété
do_action('canvas_quiz_session_completed', int $user_id, array $stats);
// $stats = ['serie_id', 'total_correct', 'question_number', 'mode', 'type', 'date_taken']

// Déclenché par le feature register quand un compte est confirmé
do_action('cobra_register_user_confirmed', int $user_id);
```

### Hooks sortants (emailmarketing → autres plugins)

```php
// Avant envoi d'un email (peut être annulé en retournant false)
apply_filters('cobra_emailmarketing_should_send', bool $send, string $email_type, int $user_id);

// Après envoi réussi
do_action('cobra_emailmarketing_email_sent', string $email_type, int $user_id, array $data);

// Quand un user se désinscrit
do_action('cobra_emailmarketing_unsubscribed', int $user_id, string $type);
// $type = 'all' | 'weekly_report' | 'tips' | etc.
```

---

## 10. Anti-spam et limites d'envoi

### Règles dans `EmailSender.php`

```
1. User blacklisté (bounced = 1 ou unsubscribed_all = 1) → skip, log 'skipped'
2. Préférence user désactivée pour ce type d'email → skip, log 'skipped'
3. Fréquence max : vérifier wp_cobra_email_log → si envoi < 24h → skip
4. Doublon : vérifier wp_cobra_email_queue UNIQUE KEY user_id+email_type → pas de doublon
5. Filtre apply_filters('cobra_emailmarketing_should_send') → permet overrides externes
```

### Gestion des bounces (si API Brevo configurée)

**Endpoint webhook :** `/?cobra_emailmarketing_webhook=1&token={webhook_token}`

```
Brevo POST → BounceHandler::receive()
    │
    ├─ Vérifier token secret
    ├─ Parser event : 'hard_bounce' | 'spam' | 'unsubscribe'
    └─ Mettre à jour wp_cobra_email_prefs :
         hard_bounce  → bounced = 1, bounce_count++
         spam         → unsubscribed_all = 1
         unsubscribe  → unsubscribed_all = 1
```

**Sans API Brevo :** gestion manuelle depuis l'admin (onglet Bounces → liste des emails en erreur).

---

## 11. WP Cron — planification

```
cobra_emailmarketing_process_queue    → 'hourly'
    → EmailQueue::process_due()
    → Récupère les entrées pending dont scheduled_at <= NOW()
    → Envoie via EmailSender::send()

cobra_emailmarketing_weekly_report    → 'weekly' (lundi 8h)
    → CronManager::send_weekly_reports()
    → Récupère tous les users actifs avec activité cette semaine
    → Enqueue le rapport pour chaque user éligible

cobra_emailmarketing_re_engagement    → 'daily'
    → CronManager::check_re_engagement()
    → Détecte users inactifs depuis 7j ou 30j
    → Enqueue l'email de relance approprié (si pas déjà envoyé)
```

---

## 12. Page désinscription (frontend)

URL : `/desinscription/?token={unsubscribe_token}&type={email_type}`

- Token unique généré par user dans `wp_cobra_email_prefs.unsubscribe_token`
- `type=all` → désinscrit de tout
- `type=weekly_report` → désactive uniquement ce type dans `prefs` JSON
- Page affichée : confirmation + lien pour se réinscrire
- Shortcode : `[cobra_emailmarketing_unsubscribe]`

---

## 13. Interface admin

### Onglet Général
- Toggle global enable/disable
- From Name / From Email (override SMTP)
- Limite fréquence par jour
- Page désinscription (sélecteur de page WP)
- Toggle individuel par type d'email

### Onglet Templates
- Sélecteur du template à éditer
- Champ Subject (texte, avec variables disponibles listées)
- Éditeur body (textarea HTML ou TinyMCE)
- Bouton "Restaurer le template par défaut"
- Bouton "Envoyer un email de test"

### Onglet Historique (Log)
- Tableau paginé : user, type, statut, date
- Filtres : statut, type, date
- Export CSV

### Onglet Bounce / Brevo
- Champ API Key Brevo
- Champ Webhook Token secret
- URL du webhook à configurer dans Brevo
- Bouton "Tester la connexion API"
- Liste des users blacklistés (bounced/spam) avec bouton débloquer

---

## 14. Intégration canvas_quiz

### Dans `canvas_quiz` — 2 lignes à ajouter

**Après completion d'un quiz** (dans `api-canvas-quiz.php` ou `api-canvas-quiz-v2.php`) :

```php
// Après insertion dans canvas_quiz_statistics
do_action('canvas_quiz_session_completed', $user_id, [
    'serie_id'        => $serie_id,
    'total_correct'   => $total_correct,
    'question_number' => $question_number,
    'mode'            => $mode,
    'type'            => $type,
    'date_taken'      => current_time('mysql'),
]);
```

**Le feature emailmarketing écoute ce hook** et décide si un milestone est atteint sans que canvas_quiz n'ait à connaître la logique email.

### Milestones détectés automatiquement

| Milestone | Condition |
|---|---|
| `first_quiz` | 1ère session complétée (toutes séries confondues) |
| `first_success` | 1ère série avec score >= 80% |
| `streak_5` | 5 sessions en 5 jours consécutifs |
| `perfect_score` | 100% sur une série |

---

## 15. Plan d'implémentation — Phases

### Phase 1 — Skeleton + DB + Settings admin
- `Feature.php` (squelette complet)
- Tables DB (email_log, email_queue, email_prefs)
- Page settings avec tous les onglets (sans fonctionnalité)
- Activation du feature depuis l'admin Cobra AI

### Phase 2 — Onboarding + Queue
- `EmailQueue.php` — enqueue / process
- `EmailSender.php` — send + log + anti-spam
- `TemplateEngine.php` — rendu variables
- Emails onboarding J+0, J+2, J+7
- Hook sur `cobra_register_user_confirmed`
- Cron `process_queue` (hourly)

### Phase 3 — Rapport hebdomadaire + Relances
- `CronManager.php`
- Calcul stats depuis `canvas_quiz_statistics`
- Email `weekly_report` avec données réelles
- Emails `re_engagement` (7j, 30j)
- Cron weekly + daily

### Phase 4 — Milestones + Tips
- Détection milestones depuis hook `canvas_quiz_session_completed`
- Email `milestone` générique
- Email `tips` avec type de permis inféré
- Éditeur templates admin (TinyMCE)
- Page désinscription frontend

### Phase 5 — Brevo webhooks + Log admin
- `BounceHandler.php` + endpoint webhook
- Onglet Bounce / admin
- Tableau log avec filtres + export CSV
- Tests end-to-end

---

## 16. Conventions techniques

| Élément | Valeur |
|---|---|
| Feature ID | `emailmarketing` |
| Namespace | `CobraAI\Features\EmailMarketing` |
| Option settings | `cobra_ai_emailmarketing_options` |
| Nonce admin | `cobra-ai-admin-emailmarketing` |
| AJAX action | `cobra_ai_emailmarketing` |
| Cron hook queue | `cobra_emailmarketing_process_queue` |
| Cron hook weekly | `cobra_emailmarketing_weekly_report` |
| Cron hook relance | `cobra_emailmarketing_re_engagement` |
| Webhook URL | `/?cobra_emailmarketing_webhook=1` |
| Shortcode unsubscribe | `[cobra_emailmarketing_unsubscribe]` |
| Text domain | `cobra-ai` (hérité) |
