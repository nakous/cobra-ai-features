<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\Emailmarketing\Feature $this */

// ─── This tab is excluded from the global settings form (see settings.php) ───
// Layout / footer are now managed in the General tab.

$action      = sanitize_key($_GET['action'] ?? 'list');
$template_id = (int) ($_GET['template_id'] ?? 0);
$page_slug   = 'cobra-ai-' . $this->get_feature_id();
$nonce       = wp_create_nonce('cobra-ai-admin-emailmarketing');

// Notices
if (isset($_GET['tpl_saved'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Template enregistré.', 'cobra-ai'); ?></p></div>
<?php endif; if (isset($_GET['tpl_deleted'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Template supprimé.', 'cobra-ai'); ?></p></div>
<?php endif;

// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit' || $action === 'new'):
    $tpl = ($action === 'edit' && $template_id > 0)
        ? $this->tpl_repo->get_by_id($template_id)
        : null;

    if ($action === 'edit' && !$tpl): ?>
    <div class="notice notice-error"><p><?php _e('Template introuvable.', 'cobra-ai'); ?></p></div>
<?php else:
    $is_system = (bool) ($tpl['is_system'] ?? false); ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h2 style="margin:0;">
            <?php echo $tpl ? esc_html($tpl['label']) : __('Nouveau template', 'cobra-ai'); ?>
            <?php if ($is_system): ?>
                <span style="font-size:12px;color:#fff;background:#2271b1;border-radius:3px;padding:1px 6px;margin-left:8px;vertical-align:middle;"><?php _e('Système', 'cobra-ai'); ?></span>
            <?php endif; ?>
        </h2>
        <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'templates'], admin_url('admin.php'))); ?>"
           class="button">&larr; <?php _e('Retour à la liste', 'cobra-ai'); ?></a>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:900px;">
        <?php wp_nonce_field('cobra_ai_save_email_template'); ?>
        <input type="hidden" name="action"      value="cobra_ai_save_email_template">
        <input type="hidden" name="feature_id"  value="<?php echo esc_attr($this->get_feature_id()); ?>">
        <?php if ($tpl): ?>
        <input type="hidden" name="template_id" value="<?php echo (int) $tpl['id']; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="tpl_slug"><?php _e('Slug', 'cobra-ai'); ?></label></th>
                <td>
                    <input type="text" id="tpl_slug" name="tpl_slug" class="regular-text"
                           value="<?php echo esc_attr($tpl['slug'] ?? ''); ?>"
                           <?php echo $is_system ? 'readonly style="background:#f8f8f8;"' : ''; ?> required>
                    <p class="description"><?php _e('Identifiant unique (lettres, chiffres, tirets bas). Référencé par les déclencheurs.', 'cobra-ai'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpl_label"><?php _e('Nom', 'cobra-ai'); ?></label></th>
                <td>
                    <input type="text" id="tpl_label" name="tpl_label" class="large-text"
                           value="<?php echo esc_attr($tpl['label'] ?? ''); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpl_subject"><?php _e('Sujet', 'cobra-ai'); ?></label></th>
                <td>
                    <input type="text" id="tpl_subject" name="tpl_subject" class="large-text"
                           value="<?php echo esc_attr($tpl['subject'] ?? ''); ?>" required>
                    <p class="description"><?php _e('Variables : {{prenom}}, {{site_name}}, etc.', 'cobra-ai'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tpl_body"><?php _e('Corps du mail', 'cobra-ai'); ?></label></th>
                <td>
                    <?php
                    wp_editor(
                        wp_unslash($tpl['body'] ?? ''),
                        'tpl_body',
                        ['textarea_name' => 'tpl_body', 'textarea_rows' => 22, 'media_buttons' => false]
                    );
                    ?>
                    <p class="description" style="margin-top:8px;">
                        <?php _e('Variables globales disponibles :', 'cobra-ai'); ?>
                        <?php foreach (['{{prenom}}', '{{email}}', '{{site_name}}', '{{site_url}}', '{{account_url}}', '{{unsubscribe_url}}'] as $v): ?>
                            <code style="cursor:pointer;" onclick="navigator.clipboard.writeText('<?php echo esc_js($v); ?>')" title="Copier"><?php echo esc_html($v); ?></code>
                        <?php endforeach; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Statut', 'cobra-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="tpl_enabled" value="1"
                               <?php checked((int)($tpl['enabled'] ?? 1), 1); ?>>
                        <?php _e('Actif', 'cobra-ai'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p style="margin-top:20px;">
            <?php submit_button(__('Enregistrer le template', 'cobra-ai'), 'primary', 'submit', false); ?>
            &nbsp;
            <button type="button" class="button cobra-tpl-preview-btn"
                    data-slug="<?php echo esc_attr($tpl['slug'] ?? ''); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php _e('Aperçu', 'cobra-ai'); ?>
            </button>
        </p>
    </form>

<script>
(function($){
    $('.cobra-tpl-preview-btn').on('click', function(){
        var slug = $(this).data('slug') || 'custom';
        var body = (typeof tinyMCE !== 'undefined' && tinyMCE.get('tpl_body'))
            ? tinyMCE.get('tpl_body').getContent()
            : $('#tpl_body').val();
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_preview',
            nonce:  $(this).data('nonce'),
            type:   slug,
            html:   body
        }, function(res){
            if (!res.success){ alert(res.data || 'Erreur'); return; }
            var w = window.open('', '_blank', 'width=700,height=600');
            w.document.write('<title>Aperçu — ' + (res.data.subject || slug) + '</title>');
            w.document.write(res.data.body);
            w.document.close();
        });
    });
}(jQuery));
</script>

<?php endif; ?>

<?php else:
    // ─────────────────────────────────────────────────────────────────────────
    // LIST VIEW
    // ─────────────────────────────────────────────────────────────────────────
    $templates = $this->tpl_repo ? $this->tpl_repo->get_all() : [];
?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h2 style="margin:0;"><?php _e('Templates d\'emails', 'cobra-ai'); ?></h2>
        <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'templates', 'action' => 'new'], admin_url('admin.php'))); ?>"
           class="button button-primary">+ <?php _e('Nouveau template', 'cobra-ai'); ?></a>
    </div>

    <?php if (empty($templates)): ?>
        <div class="notice notice-warning inline"><p>
            <?php _e('Aucun template en base. Désactivez puis réactivez la fonctionnalité pour générer les templates système.', 'cobra-ai'); ?>
        </p></div>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th style="width:150px;"><?php _e('Slug', 'cobra-ai'); ?></th>
                <th><?php _e('Nom', 'cobra-ai'); ?></th>
                <th><?php _e('Sujet', 'cobra-ai'); ?></th>
                <th style="width:90px;"><?php _e('Type', 'cobra-ai'); ?></th>
                <th style="width:65px;text-align:center;"><?php _e('Actif', 'cobra-ai'); ?></th>
                <th style="width:220px;"><?php _e('Actions', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($templates as $tpl):
            $edit_url  = add_query_arg(['page' => $page_slug, 'tab' => 'templates', 'action' => 'edit', 'template_id' => $tpl['id']], admin_url('admin.php'));
            $is_sys    = (bool) $tpl['is_system'];
            $is_on     = (bool) $tpl['enabled'];
        ?>
            <tr>
                <td><?php echo (int) $tpl['id']; ?></td>
                <td><code><?php echo esc_html($tpl['slug']); ?></code></td>
                <td>
                    <a href="<?php echo esc_url($edit_url); ?>" style="font-weight:600;">
                        <?php echo esc_html($tpl['label']); ?>
                    </a>
                </td>
                <td style="color:#555;"><?php echo esc_html(wp_trim_words($tpl['subject'], 10)); ?></td>
                <td>
                    <?php if ($is_sys): ?>
                        <span style="background:#e8f0fb;color:#2271b1;border-radius:3px;padding:1px 6px;font-size:11px;"><?php _e('Système', 'cobra-ai'); ?></span>
                    <?php else: ?>
                        <span style="background:#f0f0f0;color:#555;border-radius:3px;padding:1px 6px;font-size:11px;"><?php _e('Custom', 'cobra-ai'); ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php echo $is_on ? '<span style="color:#46b450;font-size:16px;">&#10003;</span>' : '<span style="color:#dc3232;font-size:16px;">&times;</span>'; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php _e('Modifier', 'cobra-ai'); ?></a>

                    <button type="button" class="button button-small cobra-tpl-test"
                            data-slug="<?php echo esc_attr($tpl['slug']); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            style="margin-left:4px;">
                        <?php _e('Tester', 'cobra-ai'); ?>
                    </button>

                    <?php if (!$is_sys): ?>
                    <button type="button" class="button button-small cobra-tpl-delete"
                            data-id="<?php echo (int) $tpl['id']; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            data-label="<?php echo esc_attr($tpl['label']); ?>"
                            style="margin-left:4px;color:#dc3232;">
                        <?php _e('Supprimer', 'cobra-ai'); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<script>
(function($){
    // Delete
    $(document).on('click', '.cobra-tpl-delete', function(){
        var $b = $(this), label = $b.data('label');
        if (!confirm('Supprimer le template "' + label + '" ?')) return;
        $b.prop('disabled', true).text('...');
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_delete_template',
            nonce:  $b.data('nonce'),
            template_id: $b.data('id')
        }, function(res){
            if (res.success) { $b.closest('tr').fadeOut(300, function(){ $(this).remove(); }); }
            else { alert(res.data || 'Erreur'); $b.prop('disabled', false).text('<?php echo esc_js(__('Supprimer', 'cobra-ai')); ?>'); }
        });
    });

    // Test send
    $(document).on('click', '.cobra-tpl-test', function(){
        var $b = $(this);
        var to = prompt('<?php echo esc_js(__('Adresse email de test :', 'cobra-ai')); ?>');
        if (!to) return;
        $b.prop('disabled', true).text('...');
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_test',
            nonce:  $b.data('nonce'),
            email_type: $b.data('slug'),
            to: to,
            user_id: <?php echo (int) get_current_user_id(); ?>
        }, function(res){
            alert(res.data || (res.success ? 'OK' : 'Erreur'));
            $b.prop('disabled', false).text('<?php echo esc_js(__('Tester', 'cobra-ai')); ?>');
        });
    });
}(jQuery));
</script>

<?php endif; // end action list/edit/new ?>
