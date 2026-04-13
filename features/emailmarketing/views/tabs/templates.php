<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\Emailmarketing\Feature $this */

$template_types = [
    'onboarding_j0'     => __('Onboarding J+0 — Bienvenue', 'cobra-ai'),
    'onboarding_j2'     => __('Onboarding J+2 — Premier examen', 'cobra-ai'),
    'onboarding_j7'     => __('Onboarding J+7 — Bilan', 'cobra-ai'),
    're_engagement_7j'  => __('Re-engagement 7 jours', 'cobra-ai'),
    're_engagement_30j' => __('Re-engagement 30 jours', 'cobra-ai'),
    'weekly_report'     => __('Rapport hebdomadaire', 'cobra-ai'),
    'tips'              => __('Conseils personnalisés', 'cobra-ai'),
    'milestone'         => __('Félicitations (milestones)', 'cobra-ai'),
];

$selected_type = sanitize_key($_GET['tpl'] ?? 'onboarding_j0');
if (!array_key_exists($selected_type, $template_types)) {
    $selected_type = 'onboarding_j0';
}

$current_content = $settings['templates'][$selected_type] ?? '';
$layout_content  = $settings['templates']['layout']        ?? '';
$footer_content  = $settings['templates']['footer']        ?? '';

$global_vars = ['{{prenom}}', '{{email}}', '{{site_name}}', '{{site_url}}', '{{unsubscribe_url}}', '{{account_url}}'];
$type_vars   = [
    'weekly_report' => ['{{sessions_count}}', '{{sessions_delta}}', '{{score_moyen}}', '{{score_delta}}', '{{permit_type}}', '{{meilleure_serie}}', '{{meilleure_score}}', '{{serie_faible}}', '{{serie_faible_score}}'],
    'tips'          => ['{{permit_type}}', '{{tips_content}}'],
    'milestone'     => ['{{milestone_label}}', '{{milestone_score}}', '{{quiz_name}}'],
];
$extra_vars = $type_vars[$selected_type] ?? [];

$preview_nonce = wp_create_nonce('cobra-ai-admin-emailmarketing');
$ajax_url      = admin_url('admin-ajax.php');
$feature_id    = $this->get_feature_id();
$page_slug     = 'cobra-ai-' . $feature_id;
?>

<?php /* ================================================================
   SECTION 1 — Modèle global + Pied de page (toujours visibles)
================================================================ */ ?>
<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:24px;">

    <h2 style="margin:0 0 4px;font-size:15px;color:#1d2327;">
        <?php _e('Structure globale des emails', 'cobra-ai'); ?>
    </h2>
    <p style="margin:0 0 20px;color:#777;font-size:12px;">
        <?php _e('Ces deux blocs s\'appliquent à tous les emails. Le modèle global entoure chaque email via <code>{{content}}</code>. Le pied de page est injecté via <code>{{footer}}</code>.', 'cobra-ai'); ?>
    </p>

    <div style="display:flex;gap:20px;">

        <!-- Modèle global (layout) -->
        <div style="flex:1;min-width:0;">
            <label style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#1d2327;">
                <?php _e('Modèle global (layout)', 'cobra-ai'); ?>
                <span style="font-weight:400;color:#777;margin-left:6px;">
                    — <?php _e('variables :', 'cobra-ai'); ?>
                    <code onclick="cobraEmInsertToLayout('{{content}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{content}}</code>
                    <code onclick="cobraEmInsertToLayout('{{footer}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{footer}}</code>
                    <code onclick="cobraEmInsertToLayout('{{site_name}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{site_name}}</code>
                </span>
            </label>
            <textarea
                name="settings[templates][layout]"
                id="cobra-em-layout-editor"
                style="width:100%;min-height:220px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;
                       border:1px solid #ddd;border-radius:4px;padding:10px;resize:vertical;box-sizing:border-box;"
            ><?php echo esc_textarea($layout_content); ?></textarea>
            <p style="color:#777;font-size:11px;margin:4px 0 0;">
                <?php _e('HTML complet de l\'email. Placez {{content}} là où le corps de chaque email sera injecté.', 'cobra-ai'); ?>
            </p>
        </div>

        <!-- Pied de page (footer) -->
        <div style="flex:1;min-width:0;">
            <label style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#1d2327;">
                <?php _e('Pied de page', 'cobra-ai'); ?>
                <span style="font-weight:400;color:#777;margin-left:6px;">
                    — <?php _e('variables :', 'cobra-ai'); ?>
                    <code onclick="cobraEmInsertToFooter('{{site_name}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{site_name}}</code>
                    <code onclick="cobraEmInsertToFooter('{{site_url}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{site_url}}</code>
                    <code onclick="cobraEmInsertToFooter('{{unsubscribe_url}}')" style="cursor:pointer;background:#f0f0f0;padding:1px 5px;border-radius:3px;">{{unsubscribe_url}}</code>
                </span>
            </label>
            <textarea
                name="settings[templates][footer]"
                id="cobra-em-footer-editor"
                style="width:100%;min-height:220px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;
                       border:1px solid #ddd;border-radius:4px;padding:10px;resize:vertical;box-sizing:border-box;"
            ><?php echo esc_textarea($footer_content); ?></textarea>
            <p style="color:#777;font-size:11px;margin:4px 0 0;">
                <?php _e('Contenu injecté dans {{footer}} du modèle global (liens légaux, désinscription, copyright…).', 'cobra-ai'); ?>
            </p>
        </div>

    </div>
</div>

<?php /* ================================================================
   SECTION 2 — Templates individuels
================================================================ */ ?>
<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;">

    <h2 style="margin:0 0 4px;font-size:15px;color:#1d2327;">
        <?php _e('Templates individuels', 'cobra-ai'); ?>
    </h2>
    <p style="margin:0 0 16px;color:#777;font-size:12px;">
        <?php _e('Contenu spécifique à chaque email, injecté dans {{content}} du modèle global.', 'cobra-ai'); ?>
    </p>

    <div style="display:flex;gap:0;min-height:560px;">

        <!-- Sidebar -->
        <div style="width:190px;flex-shrink:0;border-right:1px solid #ddd;padding-right:0;">
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ($template_types as $type => $label): ?>
                <li>
                    <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'templates', 'tpl' => $type], admin_url('admin.php'))); ?>"
                       style="display:block;padding:9px 14px;text-decoration:none;border-bottom:1px solid #f0f0f0;font-size:13px;
                              background:<?php echo $selected_type === $type ? '#1a73e8' : '#fff'; ?>;
                              color:<?php echo $selected_type === $type ? '#fff' : '#333'; ?>;
                              font-weight:<?php echo $selected_type === $type ? '600' : 'normal'; ?>;">
                        <?php echo esc_html($label); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Zone éditeur + preview -->
        <div style="flex:1;display:flex;flex-direction:column;padding-left:20px;min-width:0;">

            <!-- Toolbar -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                <h3 style="margin:0;font-size:14px;"><?php echo esc_html($template_types[$selected_type]); ?></h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div style="display:flex;border:1px solid #ddd;border-radius:4px;overflow:hidden;">
                        <button type="button" id="cobra-em-mode-edit"
                            style="padding:5px 14px;border:none;background:#1a73e8;color:#fff;cursor:pointer;font-size:13px;">
                            ✏️ Code
                        </button>
                        <button type="button" id="cobra-em-mode-split"
                            style="padding:5px 14px;border:none;background:#f0f0f0;color:#333;cursor:pointer;font-size:13px;">
                            ⬜ Split
                        </button>
                        <button type="button" id="cobra-em-mode-preview"
                            style="padding:5px 14px;border:none;background:#f0f0f0;color:#333;cursor:pointer;font-size:13px;">
                            👁 Preview
                        </button>
                    </div>
                    <div id="cobra-em-device-toggle" style="display:flex;border:1px solid #ddd;border-radius:4px;overflow:hidden;">
                        <button type="button" class="cobra-em-device" data-width="100%"
                            style="padding:5px 10px;border:none;background:#1a73e8;color:#fff;cursor:pointer;font-size:13px;" title="Desktop">🖥</button>
                        <button type="button" class="cobra-em-device" data-width="480px"
                            style="padding:5px 10px;border:none;background:#f0f0f0;color:#333;cursor:pointer;font-size:13px;" title="Mobile">📱</button>
                    </div>
                    <button type="button" class="button" id="cobra-em-reset-tpl"
                        data-type="<?php echo esc_attr($selected_type); ?>">
                        <?php _e('Restaurer le défaut', 'cobra-ai'); ?>
                    </button>
                </div>
            </div>

            <!-- Variables -->
            <?php if (!empty($global_vars) || !empty($extra_vars)): ?>
            <div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;padding:8px 12px;margin-bottom:10px;font-size:12px;">
                <strong><?php _e('Variables :', 'cobra-ai'); ?></strong>
                <span style="margin-left:8px;">
                    <?php foreach (array_merge($global_vars, $extra_vars) as $var): ?>
                    <code onclick="cobraEmInsertVar('<?php echo esc_js($var); ?>')"
                          style="background:#fff;border:1px solid #ddd;padding:1px 6px;border-radius:3px;cursor:pointer;margin:2px;display:inline-block;font-size:11px;">
                        <?php echo esc_html($var); ?>
                    </code>
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Workspace -->
            <div id="cobra-em-workspace" style="flex:1;display:flex;gap:12px;min-height:460px;">

                <!-- Code editor -->
                <div id="cobra-em-editor-pane" style="flex:1;display:flex;flex-direction:column;min-width:0;">
                    <input type="hidden" name="settings[templates][_active_type]" value="<?php echo esc_attr($selected_type); ?>">
                    <textarea
                        name="settings[templates][<?php echo esc_attr($selected_type); ?>]"
                        id="cobra-em-tpl-editor"
                        style="flex:1;width:100%;min-height:450px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;
                               border:1px solid #ddd;border-radius:4px;padding:12px;resize:none;box-sizing:border-box;"
                    ><?php echo esc_textarea($current_content); ?></textarea>
                    <p style="color:#777;font-size:11px;margin:4px 0 0;">
                        <?php _e('Ce contenu sera injecté dans {{content}} du modèle global.', 'cobra-ai'); ?>
                    </p>
                </div>

                <!-- Preview pane -->
                <div id="cobra-em-preview-pane" style="flex:1;display:none;flex-direction:column;min-width:0;">
                    <div style="background:#f5f5f5;border:1px solid #ddd;border-bottom:none;border-radius:4px 4px 0 0;padding:10px 14px;font-size:12px;color:#444;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <strong><?php _e('De :', 'cobra-ai'); ?></strong>
                                <?php echo esc_html(get_bloginfo('name')); ?>
                                &lt;<?php echo esc_html(get_option('admin_email')); ?>&gt;
                                &nbsp;&nbsp;
                                <strong><?php _e('À :', 'cobra-ai'); ?></strong>
                                <?php echo esc_html(wp_get_current_user()->user_email); ?>
                            </div>
                            <div id="cobra-em-preview-status" style="font-size:11px;color:#999;"></div>
                        </div>
                        <div style="margin-top:4px;">
                            <strong><?php _e('Objet :', 'cobra-ai'); ?></strong>
                            <span id="cobra-em-preview-subject" style="color:#1a73e8;"></span>
                        </div>
                    </div>
                    <div id="cobra-em-iframe-wrapper" style="border:1px solid #ddd;border-radius:0 0 4px 4px;overflow:hidden;flex:1;display:flex;align-items:flex-start;justify-content:center;background:#e8e8e8;padding:16px;">
                        <iframe id="cobra-em-preview-frame"
                            style="width:100%;max-width:100%;border:none;background:#fff;border-radius:4px;
                                   box-shadow:0 2px 12px rgba(0,0,0,.15);transition:width .3s ease;min-height:420px;"
                            sandbox="allow-same-origin"
                            title="Email Preview">
                        </iframe>
                    </div>
                    <p style="color:#777;font-size:11px;margin:4px 0 0;text-align:center;">
                        <?php _e('Les variables sont remplacées par des données de démonstration.', 'cobra-ai'); ?>
                    </p>
                </div>

            </div><!-- /workspace -->
        </div><!-- /main -->
    </div><!-- /flex -->
</div><!-- /section 2 -->

<script>
(function($) {
    const AJAX     = <?php echo json_encode($ajax_url); ?>;
    const NONCE    = <?php echo json_encode($preview_nonce); ?>;
    const TPL_TYPE = <?php echo json_encode($selected_type); ?>;

    let previewTimer = null;
    let currentMode  = 'edit';

    // -----------------------------------------------------------------------
    // Insert variable into layout / footer editors
    // -----------------------------------------------------------------------
    window.cobraEmInsertToLayout = function(variable) {
        _insertInto(document.getElementById('cobra-em-layout-editor'), variable);
    };
    window.cobraEmInsertToFooter = function(variable) {
        _insertInto(document.getElementById('cobra-em-footer-editor'), variable);
    };
    window.cobraEmInsertVar = function(variable) {
        _insertInto(document.getElementById('cobra-em-tpl-editor'), variable);
        $(document.getElementById('cobra-em-tpl-editor')).trigger('input');
    };
    function _insertInto(ta, variable) {
        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + variable + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + variable.length;
        ta.focus();
    }

    // -----------------------------------------------------------------------
    // Mode switching
    // -----------------------------------------------------------------------
    function setMode(mode) {
        currentMode = mode;
        $('#cobra-em-mode-edit, #cobra-em-mode-split, #cobra-em-mode-preview').css({ background: '#f0f0f0', color: '#333' });
        $('#cobra-em-mode-' + mode).css({ background: '#1a73e8', color: '#fff' });

        if (mode === 'edit') {
            $('#cobra-em-editor-pane').css('display', 'flex');
            $('#cobra-em-preview-pane').css('display', 'none');
        } else if (mode === 'split') {
            $('#cobra-em-editor-pane').css('display', 'flex');
            $('#cobra-em-preview-pane').css('display', 'flex');
            renderPreview();
        } else {
            $('#cobra-em-editor-pane').css('display', 'none');
            $('#cobra-em-preview-pane').css('display', 'flex');
            renderPreview();
        }
    }

    $('#cobra-em-mode-edit').on('click',    function() { setMode('edit'); });
    $('#cobra-em-mode-split').on('click',   function() { setMode('split'); });
    $('#cobra-em-mode-preview').on('click', function() { setMode('preview'); });

    // -----------------------------------------------------------------------
    // Device toggle
    // -----------------------------------------------------------------------
    $(document).on('click', '.cobra-em-device', function() {
        $('.cobra-em-device').css({ background: '#f0f0f0', color: '#333' });
        $(this).css({ background: '#1a73e8', color: '#fff' });
        $('#cobra-em-preview-frame').css('width', $(this).data('width'));
    });

    // -----------------------------------------------------------------------
    // Live preview — debounced 600ms
    // -----------------------------------------------------------------------
    $('#cobra-em-tpl-editor').on('input', function() {
        if (currentMode === 'edit') return;
        clearTimeout(previewTimer);
        $('#cobra-em-preview-status').text('⏳');
        previewTimer = setTimeout(renderPreview, 600);
    });

    function renderPreview() {
        $('#cobra-em-preview-status').text('⏳ Chargement...');
        $.post(AJAX, {
            action: 'cobra_emailmarketing_preview',
            nonce:  NONCE,
            type:   TPL_TYPE,
            html:   $('#cobra-em-tpl-editor').val(),
        })
        .done(function(res) {
            if (!res.success) { $('#cobra-em-preview-status').text('⚠ Erreur').css('color', '#d93025'); return; }
            $('#cobra-em-preview-subject').text(res.data.subject || '');
            const frame = document.getElementById('cobra-em-preview-frame');
            frame.srcdoc = res.data.body;
            frame.onload = function() {
                try { frame.style.minHeight = Math.max(420, frame.contentDocument.documentElement.scrollHeight) + 'px'; } catch(e) {}
            };
            $('#cobra-em-preview-status').text('✅ ' + new Date().toLocaleTimeString()).css('color', '#34a853');
        })
        .fail(function() { $('#cobra-em-preview-status').text('⚠ Connexion échouée').css('color', '#d93025'); });
    }

    // -----------------------------------------------------------------------
    // Reset template
    // -----------------------------------------------------------------------
    $('#cobra-em-reset-tpl').on('click', function() {
        if (!confirm('Restaurer le template par défaut ? Vos modifications seront perdues.')) return;
        const btn  = $(this);
        btn.prop('disabled', true);
        $.post(AJAX, { action: 'cobra_emailmarketing_reset_template', nonce: <?php echo json_encode($preview_nonce); ?>, template_type: TPL_TYPE })
        .done(function(res) {
            if (res.success && res.data && res.data.content !== undefined) {
                $('#cobra-em-tpl-editor').val(res.data.content);
            }
        })
        .always(function() { btn.prop('disabled', false); });
    });

}(jQuery));
</script>
