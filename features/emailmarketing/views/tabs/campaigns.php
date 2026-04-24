<?php
/**
 * Email Marketing — Tab Campaigns
 *
 * Included directly from settings.php (no enclosing <form>).
 * $this = Feature instance
 */
defined('ABSPATH') || exit;

$page_slug    = 'cobra-ai-' . $this->get_feature_id();
$nonce_action = 'cobra-ai-admin-emailmarketing';
$edit_id      = (int) ($_GET['edit_campaign'] ?? 0);
$view         = $edit_id || isset($_GET['new_campaign']) ? 'edit' : 'list';

// Load campaign for editing
$campaign = null;
if ($edit_id && $this->campaign_repo) {
    $campaign = $this->campaign_repo->get_by_id($edit_id);
    if (!$campaign) {
        $view = 'list';
        $edit_id = 0;
    }
}

// Load templates for dropdown
$templates = $this->tpl_repo ? $this->tpl_repo->get_all(true) : [];

// Available WP roles
$wp_roles       = wp_roles()->roles;
$available_roles = [];
foreach ($wp_roles as $role_key => $role_data) {
    $available_roles[$role_key] = translate_user_role($role_data['name']);
}

// Notices
if (isset($_GET['cpg_saved'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Campagne enregistrée.', 'cobra-ai'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['cpg_deleted'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Campagne supprimée.', 'cobra-ai'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['cpg_sent'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Campagne envoyée et emails mis en file d\'attente.', 'cobra-ai'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="notice notice-error is-dismissible"><p><?php echo esc_html(urldecode($_GET['error'])); ?></p></div>
<?php endif; ?>

<?php if ($view === 'list'): ?>
<!-- ============================================================
     LIST VIEW
============================================================ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h2 style="margin:0;"><?php _e('Campagnes email', 'cobra-ai'); ?></h2>
    <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'campaigns', 'new_campaign' => '1'], admin_url('admin.php'))); ?>"
       class="button button-primary">
        + <?php _e('Nouvelle campagne', 'cobra-ai'); ?>
    </a>
</div>

<?php
$campaigns = $this->campaign_repo ? $this->campaign_repo->get_all() : [];
$status_labels = [
    'draft'     => ['label' => __('Brouillon', 'cobra-ai'),   'color' => '#888'],
    'scheduled' => ['label' => __('Planifiée', 'cobra-ai'),   'color' => '#2271b1'],
    'sending'   => ['label' => __('En cours', 'cobra-ai'),    'color' => '#f0a500'],
    'sent'      => ['label' => __('Envoyée', 'cobra-ai'),     'color' => '#34a853'],
    'cancelled' => ['label' => __('Annulée', 'cobra-ai'),     'color' => '#cc1818'],
];
?>

<?php if (empty($campaigns)): ?>
    <p style="color:#666;margin:24px 0;"><?php _e('Aucune campagne créée. Cliquez sur "Nouvelle campagne" pour commencer.', 'cobra-ai'); ?></p>
<?php else: ?>
<table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
    <thead>
        <tr>
            <th style="width:28%;"><?php _e('Nom', 'cobra-ai'); ?></th>
            <th style="width:14%;"><?php _e('Statut', 'cobra-ai'); ?></th>
            <th style="width:14%;"><?php _e('Audience', 'cobra-ai'); ?></th>
            <th style="width:16%;"><?php _e('Planifiée le', 'cobra-ai'); ?></th>
            <th style="width:10%;text-align:center;"><?php _e('Envoyés', 'cobra-ai'); ?></th>
            <th style="width:18%;"><?php _e('Actions', 'cobra-ai'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $cpg): ?>
    <?php
        $status_info = $status_labels[$cpg['status']] ?? ['label' => $cpg['status'], 'color' => '#888'];
        $edit_url    = add_query_arg(['page' => $page_slug, 'tab' => 'campaigns', 'edit_campaign' => $cpg['id']], admin_url('admin.php'));
        $can_send    = in_array($cpg['status'], ['draft', 'scheduled'], true);
        $can_delete  = in_array($cpg['status'], ['draft', 'cancelled'], true);
        $audience_label = match($cpg['audience_type']) {
            'role' => __('Par rôle', 'cobra-ai'),
            'meta' => __('Par meta', 'cobra-ai'),
            default => __('Tous', 'cobra-ai'),
        };
    ?>
    <tr>
        <td><strong><?php echo esc_html($cpg['name']); ?></strong></td>
        <td>
            <span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;
                         background:<?php echo esc_attr($status_info['color']); ?>22;
                         color:<?php echo esc_attr($status_info['color']); ?>;
                         border:1px solid <?php echo esc_attr($status_info['color']); ?>44;">
                <?php echo esc_html($status_info['label']); ?>
            </span>
        </td>
        <td><?php echo esc_html($audience_label); ?></td>
        <td><?php echo $cpg['scheduled_at'] ? esc_html(date_i18n('d/m/Y H:i', strtotime($cpg['scheduled_at']))) : '—'; ?></td>
        <td style="text-align:center;">
            <?php echo esc_html($cpg['total_count'] > 0 ? $cpg['sent_count'] . ' / ' . $cpg['total_count'] : '—'); ?>
        </td>
        <td>
            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php _e('Modifier', 'cobra-ai'); ?></a>
            <?php if ($can_send): ?>
            <button type="button"
                    class="button button-small cobra-em-send-now"
                    data-id="<?php echo (int) $cpg['id']; ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce($nonce_action)); ?>"
                    style="color:#34a853;border-color:#34a853;">
                ▶ <?php _e('Envoyer', 'cobra-ai'); ?>
            </button>
            <?php endif; ?>
            <?php if ($can_delete): ?>
            <button type="button"
                    class="button button-small cobra-em-delete-campaign"
                    data-id="<?php echo (int) $cpg['id']; ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce($nonce_action)); ?>"
                    style="color:#cc1818;border-color:#cc1818;">
                <?php _e('Supprimer', 'cobra-ai'); ?>
            </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php else: ?>
<!-- ============================================================
     EDIT / CREATE VIEW
============================================================ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h2 style="margin:0;">
        <?php echo $edit_id ? __('Modifier la campagne', 'cobra-ai') : __('Nouvelle campagne', 'cobra-ai'); ?>
    </h2>
    <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'campaigns'], admin_url('admin.php'))); ?>"
       class="button">← <?php _e('Retour à la liste', 'cobra-ai'); ?></a>
</div>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cobra-campaign-form">
    <?php wp_nonce_field('cobra_ai_save_email_campaign'); ?>
    <input type="hidden" name="action"      value="cobra_ai_save_email_campaign">
    <input type="hidden" name="campaign_id" value="<?php echo (int) $edit_id; ?>">

    <!-- Section: Informations générales -->
    <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php _e('Informations', 'cobra-ai'); ?></h3>
    <table class="form-table" style="margin-bottom:0;">
        <tr>
            <th><label for="cpg_name"><?php _e('Nom de la campagne', 'cobra-ai'); ?> *</label></th>
            <td>
                <input type="text" id="cpg_name" name="cpg_name" class="regular-text"
                       value="<?php echo esc_attr($campaign['name'] ?? ''); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="cpg_subject"><?php _e('Objet de l\'email', 'cobra-ai'); ?> *</label></th>
            <td>
                <input type="text" id="cpg_subject" name="cpg_subject" class="large-text"
                       value="<?php echo esc_attr($campaign['subject'] ?? ''); ?>" required>
                <p class="description"><?php _e('Vous pouvez utiliser {{prenom}}, {{site_name}}, etc.', 'cobra-ai'); ?></p>
            </td>
        </tr>
    </table>

    <!-- Section: Contenu -->
    <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;margin-top:24px;"><?php _e('Contenu de l\'email', 'cobra-ai'); ?></h3>

    <!-- Template selector -->
    <table class="form-table" style="margin-bottom:0;">
        <tr>
            <th><label for="cpg_template_id"><?php _e('Utiliser un template existant', 'cobra-ai'); ?></label></th>
            <td>
                <select id="cpg_template_id" name="cpg_template_id">
                    <option value="0"><?php _e('— Contenu personnalisé ci-dessous —', 'cobra-ai'); ?></option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?php echo (int) $tpl['id']; ?>"
                        <?php selected((int)($campaign['template_id'] ?? 0), (int) $tpl['id']); ?>>
                        <?php echo esc_html($tpl['label']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Si sélectionné, le sujet et le corps du template seront utilisés.', 'cobra-ai'); ?></p>
            </td>
        </tr>
    </table>

    <!-- AI generation panel -->
    <?php
    // Check AI feature availability
    $ai_feature      = \CobraAI\CobraAI::instance()->get_feature('ai');
    $ai_available    = $ai_feature && !empty($ai_feature->manager) && !empty($ai_feature->manager->get_active_providers());
    $ai_panel_style  = 'background:#f6f8fc;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin:12px 0 16px;';
    if (!$ai_available) {
        $ai_panel_style .= 'opacity:.45;pointer-events:none;filter:grayscale(1);';
    }
    ?>
    <div id="cobra-ai-gen-panel" style="<?php echo esc_attr($ai_panel_style); ?>">
        <strong><?php _e('🤖 Générer le contenu avec l\'IA', 'cobra-ai'); ?></strong>
        <?php if (!$ai_available): ?>
        <span style="margin-left:10px;font-size:12px;color:#b32d2e;font-weight:600;">
            ⚠️ <?php _e('Fonctionnalité AI désactivée ou aucun fournisseur actif configuré.', 'cobra-ai'); ?>
        </span>
        <?php endif; ?>
        <p style="margin:8px 0 4px;font-size:13px;color:#555;">
            <?php _e('Décrivez l\'email à rédiger. Vous pouvez aussi sélectionner des pages/articles comme contexte.', 'cobra-ai'); ?>
        </p>

        <textarea id="cobra-ai-prompt" name="cpg_ai_prompt" rows="3"
                  style="width:100%;margin:6px 0;"
                  placeholder="<?php esc_attr_e('Ex : Écris un email de newsletter annonçant notre nouvelle fonctionnalité de quiz en ligne. Ton de confiance, tutoyant.', 'cobra-ai'); ?>"><?php echo esc_textarea($campaign['ai_prompt'] ?? ''); ?></textarea>

        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <label for="cobra-ai-posts" style="font-size:12px;font-weight:600;color:#444;"><?php _e('Pages / articles à inclure (optionnel)', 'cobra-ai'); ?></label>
                <?php
                $all_posts = get_posts([
                    'post_type'      => ['post', 'page'],
                    'post_status'    => 'publish',
                    'posts_per_page' => 200,
                    'orderby'        => 'post_type',
                    'order'          => 'ASC',
                ]);
                ?>
                <select id="cobra-ai-posts" name="cobra_ai_posts[]" multiple
                        style="width:100%;margin-top:4px;">
                    <?php foreach ($all_posts as $p): ?>
                    <option value="<?php echo (int) $p->ID; ?>">
                        [<?php echo esc_html(get_post_type_labels(get_post_type_object($p->post_type))->singular_name); ?>]
                        <?php echo esc_html($p->post_title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="padding-top:22px;">
                <button type="button" id="cobra-ai-generate-btn" class="button button-primary"
                        data-nonce="<?php echo esc_attr(wp_create_nonce($nonce_action)); ?>">
                    <?php _e('Générer', 'cobra-ai'); ?>
                </button>
                <span id="cobra-ai-gen-spinner" class="spinner" style="float:none;visibility:hidden;"></span>
            </div>
        </div>
        <p id="cobra-ai-gen-error" style="color:#cc1818;display:none;margin:6px 0 0;"></p>
    </div>

    <!-- Body editor -->
    <div id="cpg_body_wrap">
        <?php
        wp_editor(
            $campaign['body'] ?? '',
            'cpg_body',
            [
                'textarea_name' => 'cpg_body',
                'textarea_rows' => 18,
                'media_buttons' => false,
            ]
        );
        ?>
    </div>

    <!-- Section: Audience -->
    <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;margin-top:28px;"><?php _e('Audience', 'cobra-ai'); ?></h3>
    <table class="form-table" style="margin-bottom:0;">
        <tr>
            <th><?php _e('Type d\'audience', 'cobra-ai'); ?></th>
            <td>
                <fieldset>
                    <?php
                    $audience_type = $campaign['audience_type'] ?? 'all';
                    $aud_filters   = json_decode($campaign['audience_filters'] ?? '{}', true) ?: [];
                    $aud_options   = [
                        'all'  => __('Tous les utilisateurs', 'cobra-ai'),
                        'role' => __('Par rôle WordPress', 'cobra-ai'),
                        'meta' => __('Par meta utilisateur', 'cobra-ai'),
                    ];
                    foreach ($aud_options as $aud_key => $aud_label): ?>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="radio" name="cpg_audience_type" value="<?php echo esc_attr($aud_key); ?>"
                               <?php checked($audience_type, $aud_key); ?>
                               onchange="cobraCpgAudienceToggle()">
                        <?php echo esc_html($aud_label); ?>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
                <label style="display:block;margin-top:8px;">
                    <input type="checkbox" name="cpg_verified_only" value="1"
                           <?php checked(!empty($aud_filters['verified_only'])); ?>>
                    <?php _e('Uniquement les utilisateurs ayant validé leur email', 'cobra-ai'); ?>
                </label>
            </td>
        </tr>
        <!-- Role filter -->
        <tr id="aud-row-role" style="<?php echo $audience_type === 'role' ? '' : 'display:none;'; ?>">
            <th><label><?php _e('Rôles', 'cobra-ai'); ?></label></th>
            <td>
                <?php
                $selected_roles = (array) ($aud_filters['roles'] ?? []);
                foreach ($available_roles as $rkey => $rlabel): ?>
                <label style="display:inline-block;margin-right:12px;margin-bottom:4px;">
                    <input type="checkbox" name="cpg_roles[]" value="<?php echo esc_attr($rkey); ?>"
                           <?php echo in_array($rkey, $selected_roles, true) ? 'checked' : ''; ?>>
                    <?php echo esc_html($rlabel); ?>
                </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <!-- Meta filter -->
        <tr id="aud-row-meta" style="<?php echo $audience_type === 'meta' ? '' : 'display:none;'; ?>">
            <th><label><?php _e('Meta utilisateur', 'cobra-ai'); ?></label></th>
            <td>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="text" name="cpg_meta_key" class="regular-text"
                           placeholder="meta_key"
                           value="<?php echo esc_attr($aud_filters['meta_key'] ?? ''); ?>">
                    <select name="cpg_meta_compare">
                        <?php
                        $meta_compares = ['=' => '=', '!=' => '≠', '>' => '>', '<' => '<', '>=' => '≥', '<=' => '≤', 'EXISTS' => 'EXISTS', 'NOT EXISTS' => 'NOT EXISTS'];
                        foreach ($meta_compares as $mc_val => $mc_label): ?>
                        <option value="<?php echo esc_attr($mc_val); ?>"
                            <?php selected($aud_filters['meta_compare'] ?? '=', $mc_val); ?>>
                            <?php echo esc_html($mc_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="cpg_meta_value"
                           placeholder="valeur"
                           style="width:140px;"
                           value="<?php echo esc_attr($aud_filters['meta_value'] ?? ''); ?>">
                </div>
            </td>
        </tr>
    </table>

    <!-- Section: Planification -->
    <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;margin-top:28px;"><?php _e('Planification', 'cobra-ai'); ?></h3>
    <table class="form-table">
        <tr>
            <th><?php _e('Envoi', 'cobra-ai'); ?></th>
            <td>
                <?php
                $has_schedule = !empty($campaign['scheduled_at']);
                ?>
                <label style="display:block;margin-bottom:6px;">
                    <input type="radio" name="cpg_send_when" value="now"
                           <?php checked(!$has_schedule); ?>
                           onchange="cobraCpgScheduleToggle()">
                    <?php _e('Immédiatement (dès l\'enregistrement)', 'cobra-ai'); ?>
                </label>
                <label style="display:block;">
                    <input type="radio" name="cpg_send_when" value="schedule"
                           <?php checked($has_schedule); ?>
                           onchange="cobraCpgScheduleToggle()">
                    <?php _e('Planifier à une date précise', 'cobra-ai'); ?>
                </label>
                <div id="cpg-schedule-picker" style="margin-top:8px;<?php echo $has_schedule ? '' : 'display:none;'; ?>">
                    <input type="datetime-local" name="cpg_scheduled_at"
                           value="<?php echo $has_schedule ? esc_attr(date('Y-m-d\TH:i', strtotime($campaign['scheduled_at']))) : ''; ?>">
                    <p class="description"><?php _e('L\'email sera envoyé au prochain passage du cron après cette date.', 'cobra-ai'); ?></p>
                </div>
            </td>
        </tr>
    </table>

    <p style="margin-top:16px;">
        <input type="hidden" name="cpg_status" id="cpg_status_input" value="draft">
        <button type="submit" class="button button-primary" onclick="document.getElementById('cpg_status_input').value='draft';">
            <?php _e('Enregistrer comme brouillon', 'cobra-ai'); ?>
        </button>
        &nbsp;
        <button type="submit" class="button" style="background:#34a853;color:#fff;border-color:#2d9047;"
                onclick="document.getElementById('cpg_status_input').value='scheduled';">
            <?php _e('Planifier / Envoyer', 'cobra-ai'); ?>
        </button>
    </p>
</form>

<?php endif; ?>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
function cobraCpgAudienceToggle() {
    var val = document.querySelector('input[name="cpg_audience_type"]:checked').value;
    document.getElementById('aud-row-role').style.display = val === 'role' ? '' : 'none';
    document.getElementById('aud-row-meta').style.display = val === 'meta' ? '' : 'none';
}

function cobraCpgScheduleToggle() {
    var val = document.querySelector('input[name="cpg_send_when"]:checked').value;
    document.getElementById('cpg-schedule-picker').style.display = val === 'schedule' ? '' : 'none';
}

// Send Now button
document.querySelectorAll('.cobra-em-send-now').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('<?php echo esc_js(__('Envoyer cette campagne maintenant ? Les emails seront mis en file d\'attente immédiatement.', 'cobra-ai')); ?>')) return;
        var id    = btn.dataset.id;
        var nonce = btn.dataset.nonce;
        btn.disabled = true;
        btn.textContent = '...';
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'cobra_emailmarketing_send_campaign_now', campaign_id: id, nonce: nonce})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.href = location.href.split('?')[0] + '<?php echo esc_js('?page=' . $page_slug . '&tab=campaigns&cpg_sent=1'); ?>';
            } else {
                alert(data.data || '<?php echo esc_js(__('Erreur lors de l\'envoi.', 'cobra-ai')); ?>');
                btn.disabled = false;
                btn.textContent = '▶ <?php echo esc_js(__('Envoyer', 'cobra-ai')); ?>';
            }
        });
    });
});

// Delete campaign
document.querySelectorAll('.cobra-em-delete-campaign').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('<?php echo esc_js(__('Supprimer cette campagne ?', 'cobra-ai')); ?>')) return;
        var id    = btn.dataset.id;
        var nonce = btn.dataset.nonce;
        btn.disabled = true;
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'cobra_emailmarketing_delete_campaign', campaign_id: id, nonce: nonce})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                alert(data.data || '<?php echo esc_js(__('Erreur.', 'cobra-ai')); ?>');
                btn.disabled = false;
            }
        });
    });
});

// AI content generation
(function() {
    // Initialize Select2 after footer scripts have loaded (Select2 is in WP footer group)
    jQuery(document).ready(function($) {
        if ($('#cobra-ai-posts').length && typeof $.fn.select2 !== 'undefined') {
            $('#cobra-ai-posts').select2({
                placeholder:  '<?php echo esc_js(__('Rechercher des pages ou articles…', 'cobra-ai')); ?>',
                allowClear:   true,
                width:        '100%',
                language:     { noResults: function() { return '<?php echo esc_js(__('Aucun résultat', 'cobra-ai')); ?>'; } }
            });
        }
    });

    var genBtn   = document.getElementById('cobra-ai-generate-btn');
    var spinner  = document.getElementById('cobra-ai-gen-spinner');
    var errorEl  = document.getElementById('cobra-ai-gen-error');
    var promptEl = document.getElementById('cobra-ai-prompt');

    if (!genBtn) return;

    genBtn.addEventListener('click', function() {
        var prompt = promptEl ? promptEl.value.trim() : '';
        if (!prompt) {
            if (errorEl) { errorEl.textContent = '<?php echo esc_js(__('Veuillez saisir un prompt.', 'cobra-ai')); ?>'; errorEl.style.display = ''; }
            return;
        }
        if (errorEl) { errorEl.style.display = 'none'; }
        genBtn.disabled = true;
        if (spinner) spinner.style.visibility = 'visible';

        var selectedPosts = [];
        if (typeof jQuery !== 'undefined') {
            selectedPosts = jQuery('#cobra-ai-posts').val() || [];
        }

        var params = new URLSearchParams({
            action:   'cobra_emailmarketing_generate_campaign_content',
            nonce:    genBtn.dataset.nonce,
            prompt:   prompt,
        });
        selectedPosts.forEach(function(id) { params.append('post_ids[]', id); });

        fetch(ajaxurl, {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    params.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            genBtn.disabled = false;
            if (spinner) spinner.style.visibility = 'hidden';
            if (data.success && data.data && data.data.content) {
                // Inject into wp_editor (TinyMCE or plain textarea)
                var content = data.data.content;
                if (typeof tinymce !== 'undefined') {
                    var ed = tinymce.get('cpg_body');
                    if (ed) { ed.setContent(content); return; }
                }
                var textarea = document.getElementById('cpg_body');
                if (textarea) textarea.value = content;
            } else {
                if (errorEl) {
                    errorEl.textContent = (data.data && data.data.message) || '<?php echo esc_js(__('Erreur lors de la génération.', 'cobra-ai')); ?>';
                    errorEl.style.display = '';
                }
            }
        })
        .catch(function() {
            genBtn.disabled = false;
            if (spinner) spinner.style.visibility = 'hidden';
        });
    });
})();
</script>
