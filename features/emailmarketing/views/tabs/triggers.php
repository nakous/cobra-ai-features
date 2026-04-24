<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\Emailmarketing\Feature $this */

$action     = sanitize_key($_GET['action'] ?? 'list');
$trigger_id = (int) ($_GET['trigger_id'] ?? 0);
$page_slug  = 'cobra-ai-' . $this->get_feature_id();
$nonce      = wp_create_nonce('cobra-ai-admin-emailmarketing');

$known_hooks = [
    // Cobra AI — Register feature
    'cobra_register_user_confirmed'  => __('Cobra : compte utilisateur confirmé', 'cobra-ai'),
    'user_register'                  => __('WordPress : inscription utilisateur', 'cobra-ai'),
    // Cobra AI — Stripe
    'cobra_stripe_payment_success'   => __('Cobra : paiement Stripe réussi', 'cobra-ai'),
    // Canvas / Quiz
    'canvas_quiz_session_completed'  => __('Canvas : session quiz terminée', 'cobra-ai'),
    // WooCommerce (optionnel)
    'woocommerce_order_status_completed' => __('WooCommerce : commande complétée', 'cobra-ai'),
    // Generic
    'wp_login'                       => __('WordPress : connexion utilisateur', 'cobra-ai'),
];

$trigger_types = [
    'hook'           => __('Hook WordPress (immédiat)', 'cobra-ai'),
    'cron_delay'     => __('Délai après un hook (cron)', 'cobra-ai'),
    'cron_schedule'  => __('Planification récurrente (cron)', 'cobra-ai'),
    'manual'         => __('Manuel (admin uniquement)', 'cobra-ai'),
];

$send_modes = [
    'once_per_user' => __('Une seule fois par utilisateur', 'cobra-ai'),
    'cooldown'      => __('Avec délai minimum entre envois', 'cobra-ai'),
    'always'        => __('Toujours (à chaque déclenchement)', 'cobra-ai'),
];

// Notices
if (isset($_GET['trg_saved'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Déclencheur enregistré.', 'cobra-ai'); ?></p></div>
<?php endif; if (isset($_GET['trg_deleted'])): ?>
<div class="notice notice-success is-dismissible"><p><?php _e('Déclencheur supprimé.', 'cobra-ai'); ?></p></div>
<?php endif;

// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit' || $action === 'new'):
    $trg = ($action === 'edit' && $trigger_id > 0)
        ? $this->trig_repo->get_by_id($trigger_id)
        : null;

    if ($action === 'edit' && !$trg): ?>
    <div class="notice notice-error"><p><?php _e('Déclencheur introuvable.', 'cobra-ai'); ?></p></div>
<?php else:
    $is_system   = (bool) ($trg['is_system']   ?? false);
    $sel_type    = $trg['trigger_type'] ?? 'hook';
    $sel_mode    = $trg['send_mode']    ?? 'once_per_user';
    $conditions  = $trg['conditions']   ?? [];
    $all_tpl     = $this->tpl_repo ? $this->tpl_repo->get_all(false) : [];
?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h2 style="margin:0;">
            <?php echo $trg ? esc_html($trg['label']) : __('Nouveau déclencheur', 'cobra-ai'); ?>
            <?php if ($is_system): ?>
                <span style="font-size:12px;color:#fff;background:#2271b1;border-radius:3px;padding:1px 6px;margin-left:8px;vertical-align:middle;"><?php _e('Système', 'cobra-ai'); ?></span>
            <?php endif; ?>
        </h2>
        <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'triggers'], admin_url('admin.php'))); ?>"
           class="button">&larr; <?php _e('Retour à la liste', 'cobra-ai'); ?></a>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:920px;">
        <?php wp_nonce_field('cobra_ai_save_email_trigger'); ?>
        <input type="hidden" name="action"     value="cobra_ai_save_email_trigger">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
        <?php if ($trg): ?>
        <input type="hidden" name="trigger_id" value="<?php echo (int) $trg['id']; ?>">
        <?php endif; ?>

        <!-- ── Champs principaux ──────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;">
            <h3 style="margin:0 0 16px;"><?php _e('Paramètres généraux', 'cobra-ai'); ?></h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="trg_label"><?php _e('Nom', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="text" id="trg_label" name="trg_label" class="large-text"
                               value="<?php echo esc_attr($trg['label'] ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_template_id"><?php _e('Template email', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_template_id" name="trg_template_id" required>
                            <option value=""><?php _e('— choisir un template —', 'cobra-ai'); ?></option>
                            <?php foreach ($all_tpl as $t): ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php selected((int)($trg['template_id'] ?? 0), (int)$t['id']); ?>>
                                <?php echo esc_html($t['label']); ?> (<?php echo esc_html($t['slug']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Le contenu de ce template sera envoyé quand le déclencheur s\'active.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_type"><?php _e('Type de déclencheur', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_type" name="trg_trigger_type">
                            <?php foreach ($trigger_types as $val => $lbl): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sel_type, $val); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Statut', 'cobra-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="trg_enabled" value="1"
                                   <?php checked((int)($trg['enabled'] ?? 1), 1); ?>>
                            <?php _e('Actif', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Config selon le type ──────────────────────────────────────── -->
        <div id="cobra-trg-hook-config" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;display:none;">
            <h3 style="margin:0 0 16px;"><?php _e('Hook WordPress', 'cobra-ai'); ?></h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="trg_hook_name"><?php _e('Nom du hook', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_hook_preset" style="margin-bottom:6px;">
                            <option value=""><?php _e('— hooks connus —', 'cobra-ai'); ?></option>
                            <?php foreach ($known_hooks as $h => $lbl): ?>
                            <option value="<?php echo esc_attr($h); ?>"><?php echo esc_html($lbl); ?> — <code><?php echo esc_html($h); ?></code></option>
                            <?php endforeach; ?>
                        </select>
                        <br>
                        <input type="text" id="trg_hook_name" name="trg_hook_name" class="large-text"
                               placeholder="ou_saisissez_un_hook_custom"
                               value="<?php echo esc_attr($trg['hook_name'] ?? ''); ?>">
                        <p class="description"><?php _e('Nom exact du hook WordPress. Peut être n\'importe quel do_action().', 'cobra-ai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_arg_index"><?php _e('Index argument user_id', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="number" id="trg_arg_index" name="trg_hook_user_arg_index"
                               value="<?php echo (int)($trg['hook_user_arg_index'] ?? 0); ?>"
                               min="0" max="10" style="width:80px;">
                        <p class="description"><?php _e('Position (0-based) de l\'argument contenant le user_id dans le hook. Généralement 0.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="cobra-trg-delay-config" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;display:none;">
            <h3 style="margin:0 0 16px;"><?php _e('Délai après hook', 'cobra-ai'); ?></h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label><?php _e('Hook de référence', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_delay_hook_preset" style="margin-bottom:6px;">
                            <option value=""><?php _e('— hooks connus —', 'cobra-ai'); ?></option>
                            <?php foreach ($known_hooks as $h => $lbl): ?>
                            <option value="<?php echo esc_attr($h); ?>"><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br>
                        <input type="text" name="trg_delay_ref_hook" class="large-text"
                               id="trg_delay_ref_hook"
                               placeholder="hook_declencheur_de_depart"
                               value="<?php echo esc_attr($trg['delay_ref_hook'] ?? ''); ?>">
                        <p class="description"><?php _e('Le hook qui démarre le compte à rebours.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_delay_days"><?php _e('Délai (jours)', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="number" id="trg_delay_days" name="trg_delay_days"
                               value="<?php echo (int)($trg['delay_days'] ?? 2); ?>"
                               min="1" max="365" style="width:100px;">
                        <p class="description"><?php _e('L\'email sera envoyé N jours après le hook de référence.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php _e('Index argument user_id', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="number" name="trg_hook_user_arg_index" value="<?php echo (int)($trg['hook_user_arg_index'] ?? 0); ?>" min="0" max="10" style="width:80px;">
                    </td>
                </tr>
            </table>
        </div>

        <div id="cobra-trg-cron-config" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;display:none;">
            <h3 style="margin:0 0 16px;"><?php _e('Planification récurrente', 'cobra-ai'); ?></h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="trg_cron_audience"><?php _e('Audience', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_cron_audience" name="trg_cron_audience">
                            <option value="all_active"       <?php selected($trg['cron_audience'] ?? '', 'all_active'); ?>><?php _e('Tous les utilisateurs actifs', 'cobra-ai'); ?></option>
                            <option value="inactive_7"       <?php selected($trg['cron_audience'] ?? '', 'inactive_7'); ?>><?php _e('Inactifs depuis 7 jours', 'cobra-ai'); ?></option>
                            <option value="inactive_30"      <?php selected($trg['cron_audience'] ?? '', 'inactive_30'); ?>><?php _e('Inactifs depuis 30 jours', 'cobra-ai'); ?></option>
                            <option value="all_subscribers"  <?php selected($trg['cron_audience'] ?? '', 'all_subscribers'); ?>><?php _e('Tous les abonnés', 'cobra-ai'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_cron_day"><?php _e('Jour(s)', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="text" id="trg_cron_day" name="trg_cron_day" style="width:200px;"
                               placeholder="monday ou daily"
                               value="<?php echo esc_attr($trg['cron_day'] ?? ''); ?>">
                        <p class="description"><?php _e('Jour de la semaine (monday, tuesday…) ou <code>daily</code>.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trg_cron_hour"><?php _e('Heure (0-23)', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="number" id="trg_cron_hour" name="trg_cron_hour"
                               value="<?php echo (int)($trg['cron_hour'] ?? 8); ?>"
                               min="0" max="23" style="width:80px;">
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Mode d'envoi ────────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;">
            <h3 style="margin:0 0 16px;"><?php _e('Mode d\'envoi', 'cobra-ai'); ?></h3>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="trg_send_mode"><?php _e('Mode', 'cobra-ai'); ?></label></th>
                    <td>
                        <select id="trg_send_mode" name="trg_send_mode">
                            <?php foreach ($send_modes as $val => $lbl): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sel_mode, $val); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr id="cobra-trg-cooldown-row" style="display:none;">
                    <th scope="row"><label for="trg_cooldown_days"><?php _e('Cooldown (jours)', 'cobra-ai'); ?></label></th>
                    <td>
                        <input type="number" id="trg_cooldown_days" name="trg_cooldown_days"
                               value="<?php echo (int)($trg['cooldown_days'] ?? 7); ?>"
                               min="1" max="365" style="width:100px;">
                        <p class="description"><?php _e('Minimum de jours entre deux envois pour le même utilisateur.', 'cobra-ai'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Conditions ──────────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;margin-bottom:20px;">
            <h3 style="margin:0 0 6px;"><?php _e('Conditions (facultatives)', 'cobra-ai'); ?></h3>
            <p style="color:#777;font-size:12px;margin:0 0 16px;">
                <?php _e('L\'email n\'est envoyé que si toutes/l\'une des conditions sont remplies.', 'cobra-ai'); ?>
            </p>

            <input type="hidden" name="trg_conditions" id="cobra-trg-conditions-json"
                   value="<?php echo esc_attr(wp_json_encode($conditions ?: (object)[])); ?>">

            <div style="margin-bottom:12px;">
                <label style="font-weight:600;"><?php _e('Opérateur entre groupes :', 'cobra-ai'); ?></label>
                <select id="cobra-trg-root-op" style="margin-left:8px;">
                    <option value="AND" <?php selected($conditions['operator'] ?? 'AND', 'AND'); ?>>AND — <?php _e('tous les groupes doivent passer', 'cobra-ai'); ?></option>
                    <option value="OR"  <?php selected($conditions['operator'] ?? 'AND', 'OR'); ?>>OR — <?php _e('au moins un groupe doit passer', 'cobra-ai'); ?></option>
                </select>
            </div>

            <div id="cobra-trg-groups"></div>

            <button type="button" class="button" id="cobra-trg-add-group">
                + <?php _e('Ajouter un groupe', 'cobra-ai'); ?>
            </button>
        </div>

        <p>
            <?php submit_button(__('Enregistrer le déclencheur', 'cobra-ai'), 'primary', 'submit', false); ?>
        </p>
    </form>

<?php
// ─ Initial conditions data for JS ──────────────────────────────────────────
$init_conditions = wp_json_encode($conditions ?: null);
$init_type       = wp_json_encode($sel_type);
$init_mode       = wp_json_encode($sel_mode);
?>
<script>
(function($){
    // ── Type de déclencheur — affichage sections ───────────────────────────
    function showTypeSection(type) {
        $('#cobra-trg-hook-config, #cobra-trg-delay-config, #cobra-trg-cron-config').hide();
        if (type === 'hook')          $('#cobra-trg-hook-config').show();
        else if (type === 'cron_delay') $('#cobra-trg-delay-config').show();
        else if (type === 'cron_schedule') $('#cobra-trg-cron-config').show();
    }
    $('#trg_type').on('change', function(){ showTypeSection(this.value); }).trigger('change');

    // Preset → input
    $('#trg_hook_preset').on('change', function(){
        if (this.value) $('#trg_hook_name').val(this.value);
    });
    $('#trg_delay_hook_preset').on('change', function(){
        if (this.value) $('#trg_delay_ref_hook').val(this.value);
    });

    // ── Mode d'envoi ───────────────────────────────────────────────────────
    $('#trg_send_mode').on('change', function(){
        $('#cobra-trg-cooldown-row').toggle(this.value === 'cooldown');
    }).trigger('change');

    // ── Condition builder ──────────────────────────────────────────────────
    var sources = {
        user_meta:           '<?php esc_js(_e('User meta', 'cobra-ai')); ?>',
        user_role:           '<?php esc_js(_e('Rôle utilisateur', 'cobra-ai')); ?>',
        hook_arg:            '<?php esc_js(_e('Argument du hook', 'cobra-ai')); ?>',
        user_credits:        '<?php esc_js(_e('Crédits utilisateur', 'cobra-ai')); ?>',
        days_since_register: '<?php esc_js(_e('Jours depuis inscription', 'cobra-ai')); ?>',
        post_count:          '<?php esc_js(_e('Nombre de posts', 'cobra-ai')); ?>',
        order_count:         '<?php esc_js(_e('Nombre de commandes', 'cobra-ai')); ?>',
    };
    var operators = ['=','!=','>','<','>=','<=','in','not_in','contains','not_contains','exists','not_exists'];

    function buildRuleHtml(rule) {
        rule = rule || {};
        var sel_src = '';
        $.each(sources, function(k, v) {
            sel_src += '<option value="' + k + '"' + (rule.source === k ? ' selected' : '') + '>' + v + '</option>';
        });
        var sel_op = '';
        $.each(operators, function(i, o) {
            sel_op += '<option value="' + o + '"' + (rule.operator === o ? ' selected' : '') + '>' + o + '</option>';
        });
        return '<div class="cobra-rule" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">'
            + '<select class="cobra-rule-source" style="width:180px;"><option value=""><?php esc_js(_e('Source', 'cobra-ai')); ?></option>' + sel_src + '</select>'
            + '<input type="text" class="cobra-rule-field" placeholder="<?php esc_js(_e('champ / index', 'cobra-ai')); ?>" style="width:130px;" value="' + (rule.field || '') + '">'
            + '<select class="cobra-rule-op" style="width:110px;">' + sel_op + '</select>'
            + '<input type="text" class="cobra-rule-value" placeholder="<?php esc_js(_e('valeur', 'cobra-ai')); ?>" style="width:150px;" value="' + (rule.value || '') + '">'
            + '<button type="button" class="button-link cobra-rule-remove" style="color:#dc3232;" title="Supprimer">&times;</button>'
            + '</div>';
    }

    function buildGroupHtml(group) {
        group = group || {};
        var op = group.operator || 'AND';
        var rules_html = '';
        $.each(group.rules || [], function(i, r) { rules_html += buildRuleHtml(r); });
        return '<div class="cobra-condition-group" style="background:#f9f9f9;border:1px solid #ddd;border-radius:5px;padding:14px;margin-bottom:12px;">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">'
            + '<div style="display:flex;align-items:center;gap:8px;">'
            + '<label style="font-weight:600;font-size:12px;"><?php esc_js(_e('Opérateur entre règles :', 'cobra-ai')); ?></label>'
            + '<select class="cobra-group-op">'
            + '<option value="AND"' + (op==='AND'?' selected':'') + '>AND</option>'
            + '<option value="OR"'  + (op==='OR' ?' selected':'') + '>OR</option>'
            + '</select>'
            + '</div>'
            + '<button type="button" class="button-link cobra-group-remove" style="color:#dc3232;"><?php esc_js(_e('Supprimer le groupe', 'cobra-ai')); ?></button>'
            + '</div>'
            + '<div class="cobra-rules-list">' + rules_html + '</div>'
            + '<button type="button" class="button button-small cobra-add-rule">+ <?php esc_js(_e('Règle', 'cobra-ai')); ?></button>'
            + '</div>';
    }

    function loadConditions(cond) {
        var $container = $('#cobra-trg-groups').empty();
        if (!cond || !cond.groups) return;
        $('#cobra-trg-root-op').val(cond.operator || 'AND');
        $.each(cond.groups, function(i, g) { $container.append(buildGroupHtml(g)); });
    }

    function serializeConditions() {
        var cond = { operator: $('#cobra-trg-root-op').val(), groups: [] };
        $('.cobra-condition-group').each(function(){
            var group = { operator: $(this).find('.cobra-group-op').val(), rules: [] };
            $(this).find('.cobra-rule').each(function(){
                group.rules.push({
                    source:   $(this).find('.cobra-rule-source').val(),
                    field:    $(this).find('.cobra-rule-field').val(),
                    operator: $(this).find('.cobra-rule-op').val(),
                    value:    $(this).find('.cobra-rule-value').val(),
                });
            });
            if (group.rules.length) cond.groups.push(group);
        });
        return cond;
    }

    // Events
    $(document).on('click', '#cobra-trg-add-group', function(){
        $('#cobra-trg-groups').append(buildGroupHtml(null));
    });
    $(document).on('click', '.cobra-add-rule', function(){
        $(this).closest('.cobra-condition-group').find('.cobra-rules-list').append(buildRuleHtml(null));
    });
    $(document).on('click', '.cobra-rule-remove', function(){
        $(this).closest('.cobra-rule').remove();
    });
    $(document).on('click', '.cobra-group-remove', function(){
        $(this).closest('.cobra-condition-group').remove();
    });

    // Serialize before submit
    $('form').on('submit', function(){
        var cond = serializeConditions();
        $('#cobra-trg-conditions-json').val(JSON.stringify(cond.groups.length ? cond : {}));
    });

    // Init
    var initCond = <?php echo $init_conditions ?? 'null'; ?>;
    loadConditions(initCond);
    showTypeSection(<?php echo $init_type; ?>);
    $('#trg_send_mode').trigger('change');

}(jQuery));
</script>

<?php endif; ?>

<?php else:
    // ─────────────────────────────────────────────────────────────────────────
    // LIST VIEW
    // ─────────────────────────────────────────────────────────────────────────
    $triggers = $this->trig_repo ? $this->trig_repo->get_all() : [];
    $type_labels = [
        'hook'          => __('Hook', 'cobra-ai'),
        'cron_delay'    => __('Délai', 'cobra-ai'),
        'cron_schedule' => __('Planifié', 'cobra-ai'),
        'manual'        => __('Manuel', 'cobra-ai'),
    ];
?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h2 style="margin:0;"><?php _e('Déclencheurs', 'cobra-ai'); ?></h2>
        <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => 'triggers', 'action' => 'new'], admin_url('admin.php'))); ?>"
           class="button button-primary">+ <?php _e('Nouveau déclencheur', 'cobra-ai'); ?></a>
    </div>

    <p style="color:#666;font-size:13px;max-width:700px;">
        <?php _e('Chaque déclencheur surveille un événement WordPress (hook, cron, ou action manuelle) et envoie automatiquement un email au bon utilisateur selon les conditions définies.', 'cobra-ai'); ?>
    </p>

    <?php if (empty($triggers)): ?>
        <div class="notice notice-warning inline"><p>
            <?php _e('Aucun déclencheur en base. Désactivez puis réactivez la fonctionnalité pour générer les déclencheurs système.', 'cobra-ai'); ?>
        </p></div>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th><?php _e('Nom', 'cobra-ai'); ?></th>
                <th><?php _e('Template', 'cobra-ai'); ?></th>
                <th style="width:100px;"><?php _e('Type', 'cobra-ai'); ?></th>
                <th><?php _e('Hook / Cron', 'cobra-ai'); ?></th>
                <th style="width:90px;"><?php _e('Mode envoi', 'cobra-ai'); ?></th>
                <th style="width:60px;text-align:center;"><?php _e('Actif', 'cobra-ai'); ?></th>
                <th style="width:170px;"><?php _e('Actions', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($triggers as $trg):
            $edit_url = add_query_arg(['page' => $page_slug, 'tab' => 'triggers', 'action' => 'edit', 'trigger_id' => $trg['id']], admin_url('admin.php'));
            $is_sys   = (bool) $trg['is_system'];
            $is_on    = (bool) $trg['enabled'];
            $t_type   = $trg['trigger_type'] ?? '';
            $hook_ref = '';
            if ($t_type === 'hook')          $hook_ref = $trg['hook_name'] ?? '';
            elseif ($t_type === 'cron_delay') $hook_ref = ($trg['delay_ref_hook'] ?? '') . ' + ' . ($trg['delay_days'] ?? 0) . 'j';
            elseif ($t_type === 'cron_schedule') $hook_ref = ($trg['cron_day'] ?? '') . ' ' . ($trg['cron_hour'] ?? 8) . 'h';
        ?>
            <tr>
                <td><?php echo (int) $trg['id']; ?></td>
                <td>
                    <a href="<?php echo esc_url($edit_url); ?>" style="font-weight:600;">
                        <?php echo esc_html($trg['label']); ?>
                        <?php if ($is_sys): ?><span style="color:#2271b1;font-size:11px;"> (sys)</span><?php endif; ?>
                    </a>
                    <?php if (!empty($trg['conditions']) && !empty($trg['conditions']['groups'])): ?>
                        <span title="Conditions définies" style="color:#999;font-size:11px;"> 🔒 <?php echo count($trg['conditions']['groups']); ?> cond.</span>
                    <?php endif; ?>
                </td>
                <td style="color:#555;"><?php echo esc_html($trg['template_label'] ?? ($trg['template_slug'] ?? '—')); ?></td>
                <td>
                    <span style="background:#f0f0f0;border-radius:3px;padding:1px 6px;font-size:11px;">
                        <?php echo esc_html($type_labels[$t_type] ?? $t_type); ?>
                    </span>
                </td>
                <td><code style="font-size:11px;"><?php echo esc_html($hook_ref); ?></code></td>
                <td style="font-size:11px;color:#555;"><?php echo esc_html($trg['send_mode'] ?? ''); ?></td>
                <td style="text-align:center;">
                    <?php echo $is_on ? '<span style="color:#46b450;font-size:16px;">&#10003;</span>' : '<span style="color:#dc3232;font-size:16px;">&times;</span>'; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php _e('Modifier', 'cobra-ai'); ?></a>

                    <?php if (!$is_sys): ?>
                    <button type="button" class="button button-small cobra-trg-delete"
                            data-id="<?php echo (int) $trg['id']; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            data-label="<?php echo esc_attr($trg['label']); ?>"
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
    $(document).on('click', '.cobra-trg-delete', function(){
        var $b = $(this), label = $b.data('label');
        if (!confirm('Supprimer le déclencheur "' + label + '" ?')) return;
        $b.prop('disabled', true).text('...');
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_delete_trigger',
            nonce:  $b.data('nonce'),
            trigger_id: $b.data('id')
        }, function(res){
            if (res.success) { $b.closest('tr').fadeOut(300, function(){ $(this).remove(); }); }
            else { alert(res.data || 'Erreur'); $b.prop('disabled', false).text('<?php echo esc_js(__('Supprimer', 'cobra-ai')); ?>'); }
        });
    });
}(jQuery));
</script>

<?php endif; // end action ?>
