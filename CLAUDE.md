# CLAUDE.md — Cobra AI Features Plugin

## Project Overview

**Cobra AI Features** is a modular WordPress plugin providing enterprise-grade features: AI integration (Claude, OpenAI, Gemini, Perplexity), Stripe payments, Google OAuth, contact forms, user registration, reCAPTCHA, FAQ management, and SMTP configuration.

- **Version:** 2.0.0
- **Author:** Nakous Mustapha (onlevelup.com)
- **Text Domain:** `cobra-ai`
- **Requires:** WordPress 5.8+, PHP 7.4+
- **Main file:** `cobra-ai-features.php`
- **Namespace root:** `CobraAI\`

---

## Architecture

### Core Framework (`/includes/`)

| File | Class | Role |
|---|---|---|
| `cobra-ai-features.php` | `CobraAI\CobraAI` | Singleton entry point, loads all features |
| `includes/Database.php` | `CobraAI\Database` | Schema management, logging, `dbDelta()` |
| `includes/FeatureBase.php` | `CobraAI\FeatureBase` | Abstract base class for all features |
| `includes/APIManager.php` | `CobraAI\APIManager` | Centralized HTTP/API calls, caching, rate limiting |
| `includes/Admin.php` | `CobraAI\Admin` | Admin UI, AJAX handlers, menus |
| `includes/Utilities/functions.php` | — | Global helper functions |
| `includes/Utilities/Validator.php` | `CobraAI\Validator` | Input validation utilities |

### PSR-4 Autoloading (composer.json)

```
CobraAI\          → includes/
CobraAI\Features\ → features/
```

### Plugin Lifecycle

```
plugins_loaded → load translations → cobra_ai_loaded
init           → load enabled features → cobra_ai_features_loaded
```

---

## Feature Modules (`/features/`)

Each feature extends `FeatureBase` and lives in its own directory with a `Feature.php` entry point.

| Feature ID | Class | Description |
|---|---|---|
| `ai` | `CobraAI\Features\AI\Feature` | AI chat integration (Claude, OpenAI, Gemini, Perplexity) |
| `authgoogle` | `CobraAI\Features\AuthGoogle\Feature` | Google OAuth 2.0 login |
| `contact` | `CobraAI\Features\Contact\Feature` | Contact form + submissions management |
| `credits` | `CobraAI\Features\Credits\Feature` | Credit/token system for AI usage |
| `faq` | `CobraAI\Features\FAQ\Feature` | FAQ management |
| `recaptcha` | `CobraAI\Features\Recaptcha\Feature` | Google reCAPTCHA v2/v3 |
| `register` | `CobraAI\Features\Register\Feature` | User registration with email verification |
| `smtp` | `CobraAI\Features\SMTP\Feature` | SMTP email configuration |
| `stripe` | `CobraAI\Features\Stripe\Feature` | Stripe one-time payments + webhooks |
| `stripesubscriptions` | `CobraAI\Features\StripeSubscriptions\Feature` | Stripe subscription management |

**Pending features** (not active): `chatai`, `extension`, `HelloWorld`, `popads`, `referral`, `submitpost` — in `/features/pending/`.

### Adding a New Feature

1. Create `/features/{feature-id}/Feature.php` extending `FeatureBase`
2. Implement required abstract methods: `init()`, `get_settings_fields()`, `sanitize_settings()`
3. Register tables via `register_tables()` if needed
4. Enable via the admin Features page

---

## Database

### Core Tables (always present)

| Table | Purpose |
|---|---|
| `{prefix}cobra_system_logs` | Plugin-wide logging (level, source, message, context) |
| `{prefix}cobra_features` | Feature registry (name, version, status, settings) |
| `{prefix}cobra_dependencies` | Feature dependency map |
| `{prefix}cobra_analytics` | Feature event tracking |

### Feature Tables

| Table | Feature |
|---|---|
| `{prefix}cobra_ai_trackings` | AI prompt/response tracking |
| `{prefix}cobra_contact_submissions` | Contact form entries |
| `{prefix}cobra_verification_tokens` | Email verification & password reset tokens |
| `{prefix}cobra_stripe_logs` | Stripe event log |
| `{prefix}cobra_stripe_webhooks` | Stripe webhook registry |

Tables created via `dbDelta()`. Always use `$wpdb->prefix` when referencing table names. Use `FeatureBase::get_table_name($table_id)` within feature context.

---

## WordPress Options

| Option | Content |
|---|---|
| `cobra_ai_settings` | Global plugin settings (core, security, performance, logging, backup) |
| `cobra_ai_enabled_features` | Array of enabled feature IDs |
| `cobra_ai_version` | Installed version (for upgrade detection) |

---

## Admin Interface

**Menu:** "Cobra AI" at position 30, icon `dashicons-randomize`

**Pages:**
- `cobra-ai-dashboard` — System status, logs, diagnostics
- `cobra-ai-features` — Enable/disable features
- `cobra-ai-settings` — Global settings
- Feature-specific settings pages auto-generated per active feature

### AJAX Actions (all require nonce `cobra_ai_nonce`)

- `cobra_ai_toggle_feature` — Enable/disable a feature
- `cobra_ai_save_settings` — Save feature settings
- `cobra_ai_verify_api_key` — Validate external API keys
- `cobra_ai_test_email` — Test SMTP configuration
- `cobra_ai_clear_cache` / `cobra_ai_clear_logs` — Maintenance
- `cobra_ai_run_diagnostics` — System health check
- `cobra_ai_create_backup` / `cobra_ai_cleanup_backups` — Backup management

### Form POST Actions

- `admin_post_cobra_ai_save_feature_settings`
- `admin_post_cobra_ai_save_settings`

---

## External Dependencies

### Composer (`vendor/`)

- `stripe/stripe-php` ^16.2 — Stripe PHP SDK

### External APIs

| API | Feature | Config Key |
|---|---|---|
| Anthropic (Claude) | `ai` | `api_key` in AI settings |
| OpenAI | `ai` | `openai_api_key` |
| Google Gemini | `ai` | `gemini_api_key` |
| Perplexity | `ai` | `perplexity_api_key` |
| Stripe | `stripe`, `stripesubscriptions` | `secret_key`, `publishable_key` |
| Google OAuth | `authgoogle` | `client_id`, `client_secret` |
| Google reCAPTCHA | `recaptcha` | `site_key`, `secret_key` |

---

## Translations

- **Text domain:** `cobra-ai`
- **Languages dir:** `/languages/`
- **Supported locale:** `fr_FR` (French)
- **Compile:** Run `compile-translations.ps1` (PowerShell) after editing `.po` files

---

## Development Conventions

- **PHP strict types:** All files use `declare(strict_types=1)`
- **Singletons:** Core classes (`CobraAI`, `Database`, `APIManager`) use `::instance()`
- **Settings sanitization:** Always implement `sanitize_settings()` in each feature
- **Nonces:** All AJAX and form submissions must verify nonce with `check_ajax_referer()` or `wp_verify_nonce()`
- **Capability checks:** Use `current_user_can('manage_options')` for admin actions
- **Database queries:** Always use `$wpdb->prepare()` for user-supplied values
- **Assets:** Enqueue via `wp_enqueue_scripts` / `admin_enqueue_scripts` — never inline
- **Logging:** Use `Database::instance()->log($level, $source, $message)` — do not use `error_log()` directly

---

## Git Branches

- `main` — stable/production branch
- `feat-discount` — current branch (Stripe discount system)

## Key Recent Work

- Stripe discount selection in admin + discounted prices on frontend
- Resend verification email + manual email confirmation flow
- French language support
- Registration form validation improvements
