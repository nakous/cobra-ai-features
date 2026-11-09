<?php
// views/settings.php
defined('ABSPATH') || exit;

// Display settings errors/notifications
$this->display_settings_errors();
?>

<div class="wrap">
    <h1><?php _e('AI Feature Settings', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cobra-ai-settings-form">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- Settings navigation tabs -->
        <nav class="nav-tab-wrapper">
            <a href="#providers" class="nav-tab nav-tab-active"><?php _e('AI Providers', 'cobra-ai'); ?></a>
            <a href="#limits" class="nav-tab"><?php _e('Usage Limits', 'cobra-ai'); ?></a>
            <a href="#maintenance" class="nav-tab"><?php _e('Maintenance', 'cobra-ai'); ?></a>
            <a href="#display" class="nav-tab"><?php _e('Display', 'cobra-ai'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- AI Providers Settings -->
            <div id="providers" class="tab-pane active">
                <?php
                $providers = [
                    'openai' => [
                        'name' => 'OpenAI',
                        'icon' => 'dashicons-share-alt',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P',
                            'frequency_penalty' => 'Frequency Penalty',
                            'presence_penalty' => 'Presence Penalty'
                        ]
                    ],
                    'claude' => [
                        'name' => 'Claude',
                        'icon' => 'dashicons-superhero',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ],
                    'gemini' => [
                        'name' => 'Gemini',
                        'icon' => 'dashicons-google',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ],
                    'perplexity' => [
                        'name' => 'Perplexity',
                        'icon' => 'dashicons-admin-network',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ]
                ];

                foreach ($providers as $provider_id => $provider):
                    $provider_settings = $settings['providers'][$provider_id] ?? [];
                    $is_active = !empty($provider_settings['active']);
                ?>
                    <div class="provider-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="provider-header">
                            <span class="dashicons <?php echo esc_attr($provider['icon']); ?>"></span>
                            <h3><?php echo esc_html($provider['name']); ?></h3>
                            <label class="toggle-switch">
                                <input type="hidden" name="settings[providers][<?php echo esc_attr($provider_id); ?>][active]" value="0">
                                <input type="checkbox"
                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][active]"
                                    value="1"
                                    <?php checked($is_active); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="provider-content" <?php echo !$is_active ? 'style="display: none;"' : ''; ?>>
                            <table class="form-table">
                                <?php foreach ($provider['fields'] as $field_id => $field_label):
                                    $field_value = $provider_settings['config'][$field_id] ?? '';
                                    $is_sensitive = $field_id === 'api_key';
                                ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>">
                                                <?php echo esc_html($field_label); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <?php if ($is_sensitive): ?>
                                                <input type="password"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text"
                                                    autocomplete="new-password">
                                                <button type="button" class="button toggle-password"
                                                    data-target="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                            <?php elseif ($field_id === 'model'): ?>
                                                <select id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]">
                                                    <?php
                                                    $models = [];
                                                    switch ($provider_id) {
                                                        case 'openai':
                                                            $_oi_for_models = $this->manager->get_provider('openai');
                                                            if ($_oi_for_models) {
                                                                $models = array_map(
                                                                    fn($info) => $info['name'],
                                                                    $_oi_for_models->get_supported_models()
                                                                );
                                                            } else {
                                                                $models = [
                                                                    'gpt-5.4'       => 'GPT-5.4',
                                                                    'gpt-5.4-mini'  => 'GPT-5.4 Mini',
                                                                    'gpt-5.4-nano'  => 'GPT-5.4 Nano',
                                                                    'gpt-4o'        => 'GPT-4o',
                                                                    'gpt-4o-mini'   => 'GPT-4o Mini',
                                                                    'gpt-4-turbo'   => 'GPT-4 Turbo',
                                                                    'gpt-4'         => 'GPT-4',
                                                                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                                                ];
                                                            }
                                                            break;
                                                        case 'claude':
                                                            $models = [
                                                                'claude-3-opus-20240229' => 'Claude 3 Opus',
                                                                'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                                                                'claude-2.1' => 'Claude 2.1'
                                                            ];
                                                            break;
                                                        case 'gemini':
                                                            $models = [
                                                                'gemini-pro' => 'Gemini Pro',
                                                                'gemini-pro-vision' => 'Gemini Pro Vision'
                                                            ];
                                                            break;
                                                        case 'perplexity':
                                                            $models = [
                                                                'pplx-70b-online' => 'PPLX 70B Online',
                                                                'pplx-7b-online' => 'PPLX 7B Online',
                                                                'pplx-70b-chat' => 'PPLX 70B Chat'
                                                            ];
                                                            break;
                                                    }
                                                    foreach ($models as $model_id => $model_name):
                                                    ?>
                                                        <option value="<?php echo esc_attr($model_id); ?>"
                                                            <?php selected($field_value, $model_id); ?>>
                                                            <?php echo esc_html($model_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif (in_array($field_id, ['temperature', 'top_p'])): ?>
                                                <input type="number"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="small-text"
                                                    step="0.1"
                                                    min="0"
                                                    max="1">
                                            <?php elseif (in_array($field_id, ['frequency_penalty', 'presence_penalty'])): ?>
                                                <input type="number"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="small-text"
                                                    step="0.1"
                                                    min="-2"
                                                    max="2">
                                            <?php else: ?>
                                                <input type="text"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <?php if ($is_active): ?>
                                <button type="button" class="button test-api-connection"
                                    data-provider="<?php echo esc_attr($provider_id); ?>">
                                    <?php _e('Test Connection', 'cobra-ai'); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($provider_id === 'openai'):
                                $oi_inst       = $this->manager->get_provider('openai');
                                $oi_cfg        = $provider_settings['config'] ?? [];
                                $img_models    = $oi_inst ? $oi_inst->get_image_models() : [];
                                $aud_models    = $oi_inst ? $oi_inst->get_audio_models() : [];
                                $tts_models    = $oi_inst ? $oi_inst->get_tts_models()   : [];
                                $cur_img_model  = $oi_cfg['image_model'] ?? 'dall-e-3';
                                $cur_tts_model  = $oi_cfg['tts_model']   ?? 'gpt-4o-mini-tts';
                                $cur_img_sizes  = $img_models[$cur_img_model]['sizes']   ?? ['1024x1024'];
                                $cur_tts_voices = $tts_models[$cur_tts_model]['voices']  ?? ['alloy','echo','fable','onyx','nova','shimmer'];
                            ?>

                            <!-- OpenAI: Image | Audio/STT | TTS sub-tabs -->
                            <nav class="provider-sub-tabs">
                                <a href="#openai-image" class="provider-sub-tab"><?php _e('Image Generation', 'cobra-ai'); ?></a>
                                <a href="#openai-audio" class="provider-sub-tab"><?php _e('Audio / STT', 'cobra-ai'); ?></a>
                                <a href="#openai-tts"   class="provider-sub-tab"><?php _e('Text-to-Speech', 'cobra-ai'); ?></a>
                            </nav>

                            <!-- Image Generation -->
                            <div id="openai-image" class="provider-sub-pane" style="display:none;">
                                <h4><?php _e('Image Generation Settings', 'cobra-ai'); ?></h4>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Image Model', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][image_model]"
                                                    id="openai_image_model" class="openai-image-model-select">
                                                <?php foreach ($img_models as $mid => $minfo): ?>
                                                    <option value="<?php echo esc_attr($mid); ?>"
                                                        <?php selected($oi_cfg['image_model'] ?? 'dall-e-3', $mid); ?>>
                                                        <?php echo esc_html($minfo['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Image Size', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][image_size]" id="openai_image_size">
                                                <?php foreach ($cur_img_sizes as $s): ?>
                                                    <option value="<?php echo esc_attr($s); ?>"
                                                        <?php selected($oi_cfg['image_size'] ?? '1024x1024', $s); ?>>
                                                        <?php echo esc_html($s); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description"><?php _e('Available sizes depend on the selected model.', 'cobra-ai'); ?></p>
                                        </td>
                                    </tr>
                                    <tr class="dall-e-3-only"<?php echo ($cur_img_model !== 'dall-e-3') ? ' style="display:none;"' : ''; ?>>
                                        <th scope="row"><?php _e('Quality', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][image_quality]">
                                                <option value="standard" <?php selected($oi_cfg['image_quality'] ?? 'standard', 'standard'); ?>><?php _e('Standard', 'cobra-ai'); ?></option>
                                                <option value="hd"       <?php selected($oi_cfg['image_quality'] ?? 'standard', 'hd'); ?>><?php _e('HD', 'cobra-ai'); ?></option>
                                            </select>
                                            <p class="description"><?php _e('DALL-E 3 only.', 'cobra-ai'); ?></p>
                                        </td>
                                    </tr>
                                    <tr class="dall-e-3-only"<?php echo ($cur_img_model !== 'dall-e-3') ? ' style="display:none;"' : ''; ?>>
                                        <th scope="row"><?php _e('Style', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][image_style]">
                                                <option value="vivid"   <?php selected($oi_cfg['image_style'] ?? 'vivid', 'vivid'); ?>><?php _e('Vivid', 'cobra-ai'); ?></option>
                                                <option value="natural" <?php selected($oi_cfg['image_style'] ?? 'vivid', 'natural'); ?>><?php _e('Natural', 'cobra-ai'); ?></option>
                                            </select>
                                            <p class="description"><?php _e('DALL-E 3 only.', 'cobra-ai'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Response Format', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][image_response_format]">
                                                <option value="url"      <?php selected($oi_cfg['image_response_format'] ?? 'url', 'url'); ?>><?php _e('URL (recommended)', 'cobra-ai'); ?></option>
                                                <option value="b64_json" <?php selected($oi_cfg['image_response_format'] ?? 'url', 'b64_json'); ?>><?php _e('Base64 JSON', 'cobra-ai'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Audio Transcription (STT) -->
                            <div id="openai-audio" class="provider-sub-pane" style="display:none;">
                                <h4><?php _e('Audio Transcription Settings', 'cobra-ai'); ?></h4>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Transcription Model', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][audio_model]">
                                                <?php foreach ($aud_models as $mid => $minfo): ?>
                                                    <option value="<?php echo esc_attr($mid); ?>"
                                                        <?php selected($oi_cfg['audio_model'] ?? 'gpt-4o-transcribe', $mid); ?>>
                                                        <?php echo esc_html($minfo['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Language', 'cobra-ai'); ?></th>
                                        <td>
                                            <input type="text"
                                                name="settings[providers][openai][config][audio_language]"
                                                value="<?php echo esc_attr($oi_cfg['audio_language'] ?? ''); ?>"
                                                class="small-text"
                                                placeholder="fr, en, es...">
                                            <p class="description"><?php _e('ISO 639-1 code. Leave blank for auto-detection.', 'cobra-ai'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Response Format', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][audio_response_format]">
                                                <?php foreach (['json' => 'JSON', 'text' => 'Text', 'srt' => 'SRT', 'verbose_json' => 'Verbose JSON', 'vtt' => 'VTT'] as $fv => $fl): ?>
                                                    <option value="<?php echo esc_attr($fv); ?>"
                                                        <?php selected($oi_cfg['audio_response_format'] ?? 'json', $fv); ?>>
                                                        <?php echo esc_html($fl); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Text-to-Speech (TTS) -->
                            <div id="openai-tts" class="provider-sub-pane" style="display:none;">
                                <h4><?php _e('Text-to-Speech Settings', 'cobra-ai'); ?></h4>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('TTS Model', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][tts_model]"
                                                    id="openai_tts_model" class="openai-tts-model-select">
                                                <?php foreach ($tts_models as $mid => $minfo): ?>
                                                    <option value="<?php echo esc_attr($mid); ?>"
                                                        <?php selected($oi_cfg['tts_model'] ?? 'gpt-4o-mini-tts', $mid); ?>>
                                                        <?php echo esc_html($minfo['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Voice', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][tts_voice]" id="openai_tts_voice">
                                                <?php foreach ($cur_tts_voices as $v): ?>
                                                    <option value="<?php echo esc_attr($v); ?>"
                                                        <?php selected($oi_cfg['tts_voice'] ?? 'alloy', $v); ?>>
                                                        <?php echo esc_html(ucfirst($v)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Speed', 'cobra-ai'); ?></th>
                                        <td>
                                            <input type="number"
                                                name="settings[providers][openai][config][tts_speed]"
                                                value="<?php echo esc_attr($oi_cfg['tts_speed'] ?? '1.0'); ?>"
                                                class="small-text" step="0.25" min="0.25" max="4.0">
                                            <p class="description"><?php _e('Range: 0.25 – 4.0', 'cobra-ai'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Audio Format', 'cobra-ai'); ?></th>
                                        <td>
                                            <select name="settings[providers][openai][config][tts_format]">
                                                <?php foreach (['mp3' => 'MP3', 'opus' => 'Opus', 'aac' => 'AAC', 'flac' => 'FLAC', 'wav' => 'WAV', 'pcm' => 'PCM'] as $fv => $fl): ?>
                                                    <option value="<?php echo esc_attr($fv); ?>"
                                                        <?php selected($oi_cfg['tts_format'] ?? 'mp3', $fv); ?>>
                                                        <?php echo esc_html($fl); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <?php endif; // openai only ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Usage Limits Settings -->
            <div id="limits" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Daily Request Limit', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="number"
                                name="settings[limits][requests_per_day]"
                                value="<?php echo esc_attr($settings['limits']['requests_per_day']); ?>"
                                min="0"
                                step="1"
                                class="small-text">
                            <p class="description">
                                <?php _e('Maximum number of requests per user per day (0 for unlimited)', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Limit Message', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <textarea name="settings[limits][limit_message]"
                                rows="3"
                                class="large-text"><?php
                                                    echo esc_textarea($settings['limits']['limit_message']);
                                                    ?></textarea>
                            <p class="description">
                                <?php _e('Message to display when user reaches the daily limit', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Maintenance Settings -->
            <div id="maintenance" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Maintenance Mode', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="hidden" name="settings[maintenance][active]" value="0">
                                <input type="checkbox"
                                    name="settings[maintenance][active]"
                                    value="1"
                                    <?php checked(!empty($settings['maintenance']['active'])); ?>>
                                <?php _e('Enable maintenance mode', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Maintenance Message', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <textarea name="settings[maintenance][message]"
                                rows="3"
                                class="large-text"><?php
                                                    echo esc_textarea($settings['maintenance']['message']);
                                                    ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Start Date', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="datetime-local"
                                name="settings[maintenance][start_date]"
                                value="<?php echo isset($settings['maintenance']['start_date']) ? esc_attr($settings['maintenance']['start_date']) : ''; ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('End Date', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="datetime-local"
                                name="settings[maintenance][end_date]"
                                value="<?php echo isset($settings['maintenance']['end_date']) ? esc_attr($settings['maintenance']['end_date']) : ''; ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Excluded Roles', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <?php
                            $excluded_roles = $settings['maintenance']['excluded_roles'] ?? [];
                            foreach (wp_roles()->roles as $role_id => $role):
                            ?>
                                <label class="role-checkbox">
                                    <input type="checkbox"
                                        name="settings[maintenance][excluded_roles][]"
                                        value="<?php echo esc_attr($role_id); ?>"
                                        <?php checked(in_array($role_id, $excluded_roles)); ?>>
                                    <?php echo esc_html($role['name']); ?>>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Selected roles will still have access during maintenance', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Display Settings -->
            <div id="display" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Profile Tracking', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="hidden" name="settings[display][show_in_profile]" value="0">
                                <input type="checkbox"
                                    name="settings[display][show_in_profile]"
                                    value="1"
                                    <?php checked(!empty($settings['display']['show_in_profile'])); ?>>
                                <?php _e('Show tracking history in user profile', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('REST API', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="hidden" name="settings[display][enable_rest_api]" value="0">
                                <input type="checkbox"
                                    name="settings[display][enable_rest_api]"
                                    value="1"
                                    <?php checked(!empty($settings['display']['enable_rest_api'])); ?>>
                                <?php _e('Enable REST API endpoints', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
    .provider-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin-bottom: 20px;
        padding: 20px;
    }

    .provider-card.active {
        border-color: #2271b1;
    }

    .provider-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }

    .provider-header .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        margin-right: 10px;
    }

    .provider-header h3 {
        margin: 0;
        flex-grow: 1;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: #2271b1;
    }

    input:checked+.slider:before {
        transform: translateX(26px);
    }

    .role-checkbox {
        display: block;
        margin-bottom: 5px;
    }

    .tab-content>.tab-pane {
        display: none;
    }

    .tab-content>.active {
        display: block;
    }

    .test-api-connection {
        margin-top: 10px !important;
    }

    /* Icon display on hover/focus */
    .form-table input[type="text"],
    .form-table input[type="password"],
    .form-table textarea {
        position: relative;
    }

    .toggle-password {
        margin-left: 5px;
    }

    .toggle-password .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
    }

    /* Provider sub-tabs */
    .provider-sub-tabs {
        margin-top: 16px;
        border-bottom: 1px solid #ccd0d4;
        margin-bottom: 4px;
    }
    .provider-sub-tab {
        display: inline-block;
        padding: 6px 14px;
        margin-right: 4px;
        border: 1px solid transparent;
        border-bottom: none;
        border-radius: 3px 3px 0 0;
        text-decoration: none;
        color: #646970;
        font-size: 13px;
        cursor: pointer;
        position: relative;
        bottom: -1px;
    }
    .provider-sub-tab:hover { color: #2271b1; }
    .provider-sub-tab.active {
        color: #1d2327;
        background: #fff;
        border-color: #ccd0d4;
        border-bottom-color: #fff;
    }
    .provider-sub-pane h4 {
        margin: 12px 0 8px;
        font-size: 14px;
        font-weight: 600;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();

            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show content
            const target = $(this).attr('href').substring(1);
            $('.tab-pane').removeClass('active');
            $('#' + target).addClass('active');
        });

        // Provider toggle
        $('.toggle-switch input').on('change', function() {
            const card = $(this).closest('.provider-card');
            const content = card.find('.provider-content');

            if ($(this).is(':checked')) {
                card.addClass('active');
                content.slideDown();
            } else {
                card.removeClass('active');
                content.slideUp();
            }
        });

        // Password toggle
        $('.toggle-password').on('click', function() {
            const target = $('#' + $(this).data('target'));
            const icon = $(this).find('.dashicons');

            if (target.attr('type') === 'password') {
                target.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                target.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Test API connection
        $('.test-api-connection').on('click', function() {
            const provider = $(this).data('provider');
            const button = $(this);
            const originalText = button.text();

            button.prop('disabled', true).text('Testing...');

            // Get API key
            const apiKey = $('#' + provider + '_api_key').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_test_connection',
                    provider: provider, // 32
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce("cobra_ai_test_connection"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Connection successful!');
                    } else {
                        alert('Connection failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Connection test failed');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Icon visibility on hover/focus
        function toggleIcon(element, show) {
            const icon = element.next('.dashicons');
            if (icon.length) {
                icon.css('opacity', show ? 1 : 0);
            }
        }

        $('.form-table input[type="text"], .form-table textarea').each(function() {
            // Show icon on hover
            $(this).hover(
                function() {
                    toggleIcon($(this), true);
                },
                function() {
                    if (!$(this).is(':focus')) toggleIcon($(this), false);
                }
            );

            // Show icon on focus
            $(this).focus(function() {
                toggleIcon($(this), true);
            }).blur(function() {
                toggleIcon($(this), false);
            });
        });

        // Provider sub-tab navigation
        $(document).on('click', '.provider-sub-tab', function(e) {
            e.preventDefault();
            var card   = $(this).closest('.provider-card');
            var target = $(this).attr('href').replace('#', '');
            card.find('.provider-sub-tab').removeClass('active');
            $(this).addClass('active');
            card.find('.provider-sub-pane').hide();
            $('#' + target).show();
        });

        // OpenAI: image model → update size options + toggle dall-e-3-only rows
        var cobraAiImageSizes = <?php
            $_oi_js = $this->manager->get_provider('openai');
            $_img_sizes_js = [];
            if ($_oi_js) {
                foreach ($_oi_js->get_image_models() as $_m => $_md) {
                    $_img_sizes_js[$_m] = $_md['sizes'] ?? [];
                }
            }
            echo json_encode($_img_sizes_js);
        ?>;
        $(document).on('change', '.openai-image-model-select', function() {
            var model   = $(this).val();
            var card    = $(this).closest('.provider-card');
            var sizeSel = card.find('select[name*="[image_size]"]');
            var sizes   = cobraAiImageSizes[model] || ['1024x1024'];
            sizeSel.empty();
            $.each(sizes, function(i, s) { sizeSel.append($('<option>').val(s).text(s)); });
            card.find('.dall-e-3-only').toggle(model === 'dall-e-3');
        });

        // OpenAI: TTS model → update voice options
        var cobraAiTtsVoices = <?php
            $_tts_voices_js = [];
            if ($_oi_js) {
                foreach ($_oi_js->get_tts_models() as $_m => $_md) {
                    $_tts_voices_js[$_m] = $_md['voices'] ?? [];
                }
            }
            echo json_encode($_tts_voices_js);
        ?>;
        $(document).on('change', '.openai-tts-model-select', function() {
            var model    = $(this).val();
            var card     = $(this).closest('.provider-card');
            var voiceSel = card.find('select[name*="[tts_voice]"]');
            var voices   = cobraAiTtsVoices[model] || ['alloy','echo','fable','onyx','nova','shimmer'];
            voiceSel.empty();
            $.each(voices, function(i, v) {
                voiceSel.append($('<option>').val(v).text(v.charAt(0).toUpperCase() + v.slice(1)));
            });
        });
    });
</script>