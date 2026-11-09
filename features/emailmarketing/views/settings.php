<?php
defined('ABSPATH') || exit;

$settings    = $this->get_settings();
$current_tab = sanitize_key($_GET['tab'] ?? 'general');
$page_slug   = 'cobra-ai-' . $this->get_feature_id();
$tabs        = [
    'general'   => __('Général', 'cobra-ai'),
    'templates' => __('Templates', 'cobra-ai'),
    'triggers'  => __('Déclencheurs', 'cobra-ai'),
    'campaigns' => __('Campagnes', 'cobra-ai'),
    'log'       => __('Historique', 'cobra-ai'),
    'bounce'    => __('Bounce / Brevo', 'cobra-ai'),
];
?>

<div class="wrap cobra-em-wrap">
    <h1>
        <?php echo esc_html($this->name); ?>
        <span class="title-count" style="font-size:14px;font-weight:normal;color:#666;margin-left:8px;">
            v<?php echo esc_html($this->version); ?>
        </span>
    </h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Paramètres enregistrés.', 'cobra-ai'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php _e('Erreur lors de l\'enregistrement.', 'cobra-ai'); ?></p></div>
    <?php endif; ?>

    <!-- Queue status bar -->
    <?php
    $pending = $this->queue ? $this->queue->get_pending_count() : 0;
    if ($pending > 0):
    ?>
    <div class="notice notice-info" style="display:flex;align-items:center;gap:12px;">
        <span><?php printf(__('%d email(s) en attente dans la queue.', 'cobra-ai'), $pending); ?></span>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <nav class="nav-tab-wrapper" style="margin-bottom:0;">
        <?php foreach ($tabs as $tab_id => $tab_label): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => $page_slug, 'tab' => $tab_id], admin_url('admin.php'))); ?>"
               class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="cobra-em-tab-content" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:20px 24px;">

        <?php if (in_array($current_tab, ['log', 'bounce', 'templates', 'triggers', 'campaigns'], true)): ?>
            <?php include __DIR__ . '/tabs/' . $current_tab . '.php'; ?>
        <?php else: ?>
        <!-- Settings form — onglet Général uniquement (layout/footer inclus) -->
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
            <input type="hidden" name="action"     value="cobra_ai_save_feature_settings">
            <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
            <input type="hidden" name="tab"        value="<?php echo esc_attr($current_tab); ?>">

            <?php include __DIR__ . '/tabs/' . $current_tab . '.php'; ?>

            <p style="margin-top:24px;">
                <?php submit_button(__('Enregistrer les paramètres', 'cobra-ai'), 'primary', 'submit', false); ?>
            </p>
        </form>
        <?php endif; ?>

    </div>
</div>
