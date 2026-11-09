# Plan d'implémentation — Feature AI
**Date:** 2026-04-24  
**Statut global:** 🔴 En attente

---

## Décisions validées
| # | Sujet | Décision |
|---|---|---|
| 1 | Providers | OpenAI en priorité — Claude/Gemini/Perplexity modèles → plus tard |
| 2 | Image stockage | Télécharger + sauvegarder dans la médiathèque WordPress |
| 3 | Audio STT source | Supporter les 3 : upload frontend (multipart) + chemin fichier local + URL distante |
| 4 | TTS output | Sauvegarder dans `/wp-content/uploads/` et retourner l'URL |
| 5 | Modèle défaut OpenAI | `gpt-5.4-mini` |
| 6 | Migration BDD | Oui — migration automatique des anciens IDs (gpt-5-2025-08-07, etc.) |

---

## Phase 1 — Architecture de base
**Statut:** 🔴 À faire

### 1.1 `includes/AIProvider.php`
- [ ] Ajouter capabilities : `image_generation`, `audio`, `tts`
- [ ] Changer signature : `process_request(string|array $prompt, array $options = [], string $request_type = 'text')`
- [ ] Ajouter méthodes optionnelles : `generate_image()`, `transcribe_audio()`, `synthesize_speech()`
- [ ] Ajouter `make_multipart_request()` pour uploads audio (Whisper)
- [ ] Ajouter `get_request_types()` → liste des types supportés

### 1.2 `includes/AIManager.php`
- [ ] Ajouter paramètre `$request_type` dans `process_request()`
- [ ] Router selon type : `text` → `process_request`, `image` → `generate_image`, `audio` → `transcribe_audio`, `tts` → `synthesize_speech`
- [ ] Mettre à jour tracking : sauvegarder `response_type` correct (`text`, `image`, `audio`, `tts`)

### 1.3 `Feature.php`
- [ ] Étendre `get_feature_default_options()` avec configs image/audio/tts par provider
- [ ] Mettre à jour `validate_settings()` pour les nouveaux champs

---

## Phase 2 — Mise à jour des Providers

### 2.1 `includes/Providers/OpenAI.php`
**Statut:** 🔴 À faire

#### Modèles Chat/Text (via `/v1/chat/completions`)
| Model ID | Nom | Max Output | Context | Capabilities |
|---|---|---|---|---|
| `gpt-5.4` | GPT-5.4 | 128K | 1M | text, vision, reasoning |
| `gpt-5.4-mini` | GPT-5.4 Mini | 128K | 400K | text, vision, reasoning |
| `gpt-5.4-nano` | GPT-5.4 Nano | 128K | 400K | text, vision |
| `gpt-4o` | GPT-4o | 16K | 128K | text, vision |
| `gpt-4o-mini` | GPT-4o Mini | 16K | 128K | text, vision |
| `gpt-4-turbo` | GPT-4 Turbo | 4K | 128K | text, vision |
| `gpt-4` | GPT-4 | 8K | 8K | text |
| `gpt-3.5-turbo` | GPT-3.5 Turbo | 4K | 16K | text |

#### Modèles Image (via `/v1/images/generations`)
| Model ID | Nom | Tailles disponibles |
|---|---|---|
| `gpt-image-2` | GPT Image 2 | 1024×1024, 1536×1024, 1024×1536 |
| `dall-e-3` | DALL-E 3 | 1024×1024, 1792×1024, 1024×1792 |
| `dall-e-2` | DALL-E 2 | 256×256, 512×512, 1024×1024 |

#### Modèles Transcription STT (via `/v1/audio/transcriptions`)
| Model ID | Nom |
|---|---|
| `gpt-4o-transcribe` | GPT-4o Transcribe |
| `gpt-4o-mini-transcribe` | GPT-4o Mini Transcribe |
| `whisper-1` | Whisper 1 (legacy) |

#### Modèles TTS (via `/v1/audio/speech`)
| Model ID | Nom | Voix disponibles |
|---|---|---|
| `gpt-4o-mini-tts` | GPT-4o Mini TTS | alloy, echo, fable, onyx, nova, shimmer |
| `tts-1` | TTS-1 | alloy, echo, fable, onyx, nova, shimmer |
| `tts-1-hd` | TTS-1 HD | alloy, echo, fable, onyx, nova, shimmer |

#### Tâches OpenAI.php
- [ ] Remplacer `get_supported_models()` avec les modèles chat ci-dessus (supprimer gpt-5-2025-08-07, gpt-5-mini-2025-08-07, gpt-4-vision-preview)
- [ ] Changer modèle défaut → `gpt-5.4-mini`
- [ ] Ajouter migration automatique des anciens IDs invalides en BDD → `gpt-5.4-mini`
- [ ] Ajouter `get_image_models()` → liste des modèles image
- [ ] Ajouter `get_audio_models()` → liste des modèles STT
- [ ] Ajouter `get_tts_models()` → liste des modèles TTS
- [ ] Implémenter `generate_image(string $prompt, array $options)` → POST `/v1/images/generations` + **sauvegarder dans médiathèque WP** (via `media_sideload_image()`)
- [ ] Implémenter `transcribe_audio($audio_input, array $options)` → POST `/v1/audio/transcriptions` (multipart) — supporte 3 sources : upload (`$_FILES`), chemin local (`file_exists()`), URL distante (`filter_var(FILTER_VALIDATE_URL)`)
- [ ] Implémenter `synthesize_speech(string $text, array $options)` → POST `/v1/audio/speech` + **sauvegarder dans `/wp-content/uploads/`** + retourner URL
- [ ] Mettre à jour `get_default_config()` : `model` → `gpt-5.4-mini`, ajouter `image_model`, `tts_model`, `tts_voice`, `audio_model`

### 2.2 `includes/Providers/Claude.php`
**Statut:** ⏸️ Différé (modèles mis à jour plus tard)

> Les corrections d'API et modèles Claude seront faites dans une prochaine itération.

#### Modèles Chat
| Model ID | Nom | Max Tokens |
|---|---|---|
| `claude-opus-4-5` | Claude Opus 4.5 | 32K |
| `claude-sonnet-4-5` | Claude Sonnet 4.5 | 16K |
| `claude-3-5-sonnet-20241022` | Claude 3.5 Sonnet | 8K |
| `claude-3-5-haiku-20241022` | Claude 3.5 Haiku | 8K |
| `claude-3-opus-20240229` | Claude 3 Opus (legacy) | 4K |

#### Tâches Claude.php
- [ ] *(différé)*

### 2.3 `includes/Providers/Gemini.php`
**Statut:** ⏸️ Différé (modèles mis à jour plus tard)

> Les corrections d'endpoint et modèles Gemini seront faites dans une prochaine itération.

#### Tâches Gemini.php
- [ ] *(différé)*

### 2.4 `includes/Providers/Perplexity.php`
**Statut:** ⏸️ Différé (modèles mis à jour plus tard)

> Les modèles Perplexity seront mis à jour dans une prochaine itération.

#### Tâches Perplexity.php
- [ ] *(différé)*

---

## Phase 3 — Formulaire `views/settings.php`
**Statut:** 🔴 À faire

### 3.1 Structure Provider Cards
- [ ] Ajouter sous-onglets par provider : **Text/Chat** | **Image** *(si supporté)* | **Audio/TTS** *(si supporté)*
- [ ] Rendre la liste de modèles dynamique (depuis `get_supported_models()`)
- [ ] Afficher badges capabilities à côté de chaque modèle

### 3.2 Section Image (OpenAI uniquement)
- [ ] `image_model` : select (gpt-image-2 / dall-e-3 / dall-e-2)
- [ ] `image_size` : select selon modèle sélectionné
- [ ] `image_quality` : select (standard / hd) — dall-e-3 seulement
- [ ] `image_style` : select (vivid / natural) — dall-e-3 seulement
- [ ] `image_response_format` : select (url / b64_json)

### 3.3 Section Audio STT (OpenAI uniquement)
- [ ] `audio_model` : select (gpt-4o-transcribe / gpt-4o-mini-transcribe / whisper-1)
- [ ] `audio_language` : input text (optionnel, code ISO 639-1)
- [ ] `audio_response_format` : select (json / text / srt / verbose_json / vtt)

### 3.4 Section TTS (OpenAI uniquement)
- [ ] `tts_model` : select (gpt-4o-mini-tts / tts-1 / tts-1-hd)
- [ ] `tts_voice` : select (alloy / echo / fable / onyx / nova / shimmer)
- [ ] `tts_speed` : number (0.25 → 4.0, step 0.05)
- [ ] `tts_format` : select (mp3 / opus / aac / flac / wav)

---

## Phase 4 — Tracking & REST API
**Statut:** 🔴 À faire

### 4.1 `includes/AITracking.php`
- [ ] Étendre `response_type` : `text`, `image`, `audio`, `tts`, `json`
- [ ] Stocker URL/data image dans `response`
- [ ] Stocker métadonnées spécifiques (size, voice, duration) dans `meta_data`

### 4.2 REST API dans `Feature.php`
- [ ] Ajouter paramètre `type` (text/image/audio/tts) dans `POST /cobra-ai/v1/request`
- [ ] Nouveau endpoint : `POST /cobra-ai/v1/image`
- [ ] Nouveau endpoint : `POST /cobra-ai/v1/audio/transcribe`
- [ ] Nouveau endpoint : `POST /cobra-ai/v1/audio/speech`

---

## Phase 5 — Validation & Sanitization
**Statut:** 🔴 À faire

### 5.1 `Feature.php` → `validate_settings()`
- [ ] Valider `image_size` selon le modèle image choisi
- [ ] Valider `image_quality` et `image_style` uniquement si dall-e-3
- [ ] Valider `tts_voice` (whitelist : alloy, echo, fable, onyx, nova, shimmer)
- [ ] Valider `tts_speed` (0.25 ≤ speed ≤ 4.0)
- [ ] Valider `audio_language` (regex ISO 639-1 ou vide)

---

## Ordre d'exécution recommandé

```
Phase 1 (AIProvider + AIManager + Feature defaults)
    ↓
Phase 2.1 (OpenAI — modèles + image + audio + TTS)
    ↓
Phase 3 (Settings form — sections OpenAI uniquement)
    ↓
Phase 4 (Tracking + REST)
    ↓
Phase 5 (Validation)
    ↓
[Itération suivante]
Phase 2.2 (Claude — corrections + modèles)
Phase 2.3 (Gemini — endpoint + modèles)
Phase 2.4 (Perplexity — modèles)
```

---

## Légende
- 🔴 À faire
- 🟡 En cours
- 🟢 Terminé
- ⚠️ Correction critique requise
