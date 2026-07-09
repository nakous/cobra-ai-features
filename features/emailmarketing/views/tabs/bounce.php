<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\EmailMarketing\Feature $this */

$blocked_users = $this->prefs->get_blocked_users(50, 0);
$webhook_url   = $this->bounce->get_webhook_url();
$brevo_enabled = !empty($settings['brevo']['enabled']);
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">

    <!-- Brevo API config -->
    <div>
        <h2 style="margin-top:8px;"><?php _e('Configuration Brevo', 'cobra-ai'); ?></h2>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
            <input type="hidden" name="action"     value="cobra_ai_save_feature_settings">
            <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
            <input type="hidden" name="tab"        value="bounce">

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th><?php _e('Activer Brevo API', 'cobra-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[brevo][enabled]" value="1"
                                <?php checked($brevo_enabled); ?>>
                            <?php _e('Activer la gestion automatique des bounces via Brevo', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Clé API Brevo', 'cobra-ai'); ?></th>
                    <td>
                        <input type="text" name="settings[brevo][api_key]" class="regular-text"
                            value="<?php echo esc_attr($settings['brevo']['api_key'] ?? ''); ?>"
                            placeholder="xkeysib-...">
                        <button type="button" class="button" id="cobra-em-test-brevo" style="margin-left:8px;">
                            <?php _e('Tester la connexion', 'cobra-ai'); ?>
                        </button>
                        <span id="cobra-em-brevo-result" style="margin-left:8px;font-weight:600;"></span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Token webhook secret', 'cobra-ai'); ?></th>
                    <td>
                        <input type="text" name="settings[brevo][webhook_token]" class="regular-text"
                            value="<?php echo esc_attr($settings['brevo']['webhook_token'] ?? ''); ?>"
                            placeholder="<?php _e('Générez un token aléatoire', 'cobra-ai'); ?>">
                        <button type="button" class="button" id="cobra-em-gen-token" style="margin-left:8px;">
                            <?php _e('Générer', 'cobra-ai'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Ce token sécurise l\'endpoint webhook. Entrez-le aussi dans Brevo.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php if (!empty($settings['brevo']['webhook_token'])): ?>
            <div style="background:#f0f4ff;border:1px solid #c3d0f0;border-radius:6px;padding:16px;margin-top:8px;">
                <strong><?php _e('URL du webhook à configurer dans Brevo :', 'cobra-ai'); ?></strong><br>
                <code style="display:block;margin-top:8px;padding:8px;background:#fff;border:1px solid #ddd;border-radius:4px;word-break:break-all;">
                    <?php echo esc_html($webhook_url); ?>
                </code>
                <p class="description" style="margin-top:8px;">
                    <?php _e('Dans Brevo → Transactional → Settings → Webhooks, ajoutez cette URL avec les événements : Hard bounce, Spam, Unsubscribe.', 'cobra-ai'); ?>
                </p>
            </div>
            <?php endif; ?>

            <p style="margin-top:16px;">
                <?php submit_button(__('Enregistrer', 'cobra-ai'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>

    <!-- Blocked users -->
    <div>
        <h2 style="margin-top:8px;">
            <?php _e('Utilisateurs bloqués', 'cobra-ai'); ?>
            <span style="font-size:14px;font-weight:normal;color:#666;margin-left:6px;">
                (<?php echo count($blocked_users); ?>)
            </span>
        </h2>

        <?php if (empty($blocked_users)): ?>
        <p style="color:#666;"><?php _e('Aucun utilisateur bloqué.', 'cobra-ai'); ?></p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Utilisateur', 'cobra-ai'); ?></th>
                    <th><?php _e('Email', 'cobra-ai'); ?></th>
                    <th><?php _e('Raison', 'cobra-ai'); ?></th>
                    <th><?php _e('Bounces', 'cobra-ai'); ?></th>
                    <th style="width:80px;"><?php _e('Action', 'cobra-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocked_users as $user): ?>
                <tr>
                    <td><?php echo esc_html($user['display_name'] ?? '#' . $user['user_id']); ?></td>
                    <td><?php echo esc_html($user['user_email'] ?? ''); ?></td>
                    <td>
                        <?php if ($user['bounced']): ?>
                            <span style="color:#d93025;">Bounce</span>
                        <?php elseif ($user['unsubscribed_all']): ?>
                            <span style="color:#888;">Désinscrit</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval($user['bounce_count']); ?></td>
                    <td>
                        <button type="button" class="button button-small cobra-em-unblock"
                            data-user-id="<?php echo intval($user['user_id']); ?>">
                            <?php _e('Débloquer', 'cobra-ai'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<script>
document.getElementById('cobra-em-gen-token')?.addEventListener('click', function() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 48; i++) token += chars.charAt(Math.floor(Math.random() * chars.length));
    document.querySelector('[name="settings[brevo][webhook_token]"]').value = token;
});

document.getElementById('cobra-em-test-brevo')?.addEventListener('click', function() {
    const btn    = this;
    const result = document.getElementById('cobra-em-brevo-result');
    btn.disabled = true;
    result.textContent = '<?php _e('Test en cours...', 'cobra-ai'); ?>';

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'cobra_emailmarketing_test_brevo',
            nonce: '<?php echo wp_create_nonce('cobra-ai-admin-emailmarketing'); ?>',
        })
    })
    .then(r => r.json())
    .then(d => {
        result.textContent = d.data;
        result.style.color = d.success ? '#34a853' : '#d93025';
    })
    .finally(() => { btn.disabled = false; });
});
</script>
