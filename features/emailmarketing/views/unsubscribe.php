<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\EmailMarketing\Feature $feature */

$token  = sanitize_text_field($_GET['token'] ?? '');
$type   = sanitize_key($_GET['type'] ?? 'all');
$result = $token ? get_transient('cobra_unsub_result_' . $token) : null;

$type_labels = [
    'all'              => __('tous les emails', 'cobra-ai'),
    'weekly_report'    => __('les rapports hebdomadaires', 'cobra-ai'),
    'tips'             => __('les conseils personnalisés', 'cobra-ai'),
    're_engagement_7j' => __('les relances à 7 jours', 'cobra-ai'),
    're_engagement_30j'=> __('les relances à 30 jours', 'cobra-ai'),
    'milestone'        => __('les emails de félicitations', 'cobra-ai'),
];

$type_label = $type_labels[$type] ?? $type;
?>

<div style="max-width:500px;margin:40px auto;font-family:Arial,sans-serif;text-align:center;">

    <?php if ($result === 'success'): ?>
        <div style="background:#f0fff4;border:2px solid #34a853;border-radius:10px;padding:32px;">
            <div style="font-size:48px;margin-bottom:16px;">✅</div>
            <h2 style="color:#34a853;margin:0 0 12px;"><?php _e('Désinscription confirmée', 'cobra-ai'); ?></h2>
            <p><?php printf(__('Vous avez été désinscrit(e) de %s.', 'cobra-ai'), '<strong>' . esc_html($type_label) . '</strong>'); ?></p>
            <p style="color:#666;font-size:14px;">
                <?php _e('Vous pouvez vous réinscrire à tout moment depuis votre espace personnel.', 'cobra-ai'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/')); ?>"
               style="display:inline-block;margin-top:16px;background:#34a853;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">
                <?php _e('Retour au site', 'cobra-ai'); ?>
            </a>
        </div>

    <?php elseif ($result === 'invalid'): ?>
        <div style="background:#fff5f5;border:2px solid #d93025;border-radius:10px;padding:32px;">
            <div style="font-size:48px;margin-bottom:16px;">❌</div>
            <h2 style="color:#d93025;margin:0 0 12px;"><?php _e('Lien invalide', 'cobra-ai'); ?></h2>
            <p><?php _e('Ce lien de désinscription est invalide ou a expiré.', 'cobra-ai'); ?></p>
            <a href="<?php echo esc_url(home_url('/')); ?>"
               style="display:inline-block;margin-top:16px;background:#1a73e8;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">
                <?php _e('Retour au site', 'cobra-ai'); ?>
            </a>
        </div>

    <?php else: ?>
        <div style="background:#fff;border:2px solid #ddd;border-radius:10px;padding:32px;">
            <div style="font-size:48px;margin-bottom:16px;">📧</div>
            <h2 style="margin:0 0 12px;"><?php _e('Désinscription', 'cobra-ai'); ?></h2>
            <p><?php printf(__('Voulez-vous vraiment vous désinscrire de %s ?', 'cobra-ai'), '<strong>' . esc_html($type_label) . '</strong>'); ?></p>
            <?php if ($token): ?>
            <a href="<?php echo esc_url(add_query_arg(['cobra_unsubscribe' => 1, 'token' => $token, 'type' => $type, 'confirmed' => 1], home_url('/'))); ?>"
               style="display:inline-block;margin-top:16px;background:#d93025;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-right:8px;">
                <?php _e('Oui, me désinscrire', 'cobra-ai'); ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo esc_url(home_url('/')); ?>"
               style="display:inline-block;margin-top:16px;background:#eee;color:#333;padding:10px 24px;border-radius:6px;text-decoration:none;">
                <?php _e('Annuler', 'cobra-ai'); ?>
            </a>
        </div>
    <?php endif; ?>

</div>
