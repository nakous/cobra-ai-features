<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\EmailMarketing\Feature $this */

$email_labels = [
    'onboarding_j0'    => __('Onboarding J+0 — Bienvenue', 'cobra-ai'),
    'onboarding_j2'    => __('Onboarding J+2 — Prise en main', 'cobra-ai'),
    'onboarding_j7'    => __('Onboarding J+7 — Bilan', 'cobra-ai'),
    're_engagement_7j' => __('Re-engagement 7 jours inactif', 'cobra-ai'),
    're_engagement_30j'=> __('Re-engagement 30 jours inactif', 'cobra-ai'),
    'weekly_report'    => __('Rapport hebdomadaire', 'cobra-ai'),
    'tips'             => __('Conseils personnalisés', 'cobra-ai'),
    'milestone'        => __('Félicitations (étapes clés)', 'cobra-ai'),
];

$email_triggers = [
    'onboarding_j0'    => __('À la confirmation du compte (immédiat)', 'cobra-ai'),
    'onboarding_j2'    => __('2 jours après la confirmation du compte', 'cobra-ai'),
    'onboarding_j7'    => __('7 jours après la confirmation du compte', 'cobra-ai'),
    're_engagement_7j' => __('Cron quotidien — utilisateur inactif depuis 7 jours', 'cobra-ai'),
    're_engagement_30j'=> __('Cron quotidien — utilisateur inactif depuis 30 jours', 'cobra-ai'),
    'weekly_report'    => __('Cron hebdomadaire (jour et heure configurables ci-dessous)', 'cobra-ai'),
    'tips'             => __('Déclenchement manuel via hook (non planifié par défaut)', 'cobra-ai'),
    'milestone'        => __('Sur action utilisateur qui franchit un palier (hook applicatif)', 'cobra-ai'),
];
?>

<h2 style="margin-top:8px;"><?php _e('Paramètres généraux', 'cobra-ai'); ?></h2>

<table class="form-table">
    <tr>
        <th><?php _e('Activer le feature', 'cobra-ai'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="settings[general][enabled]" value="1"
                    <?php checked(!empty($settings['general']['enabled'])); ?>>
                <?php _e('Activer tous les emails automatiques', 'cobra-ai'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <th><?php _e('Nom expéditeur', 'cobra-ai'); ?></th>
        <td>
            <input type="text" name="settings[general][from_name]" class="regular-text"
                value="<?php echo esc_attr($settings['general']['from_name'] ?? ''); ?>"
                placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <p class="description"><?php _e('Laissez vide pour utiliser le nom du site.', 'cobra-ai'); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php _e('Email expéditeur', 'cobra-ai'); ?></th>
        <td>
            <input type="email" name="settings[general][from_email]" class="regular-text"
                value="<?php echo esc_attr($settings['general']['from_email'] ?? ''); ?>"
                placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
            <p class="description"><?php _e('Laissez vide pour utiliser l\'email admin WordPress.', 'cobra-ai'); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php _e('Limite fréquence', 'cobra-ai'); ?></th>
        <td>
            <input type="number" name="settings[general][frequency_limit]" min="0" max="10" style="width:70px;"
                value="<?php echo intval($settings['general']['frequency_limit'] ?? 1); ?>">
            <span><?php _e('emails max par utilisateur par jour (0 = illimité)', 'cobra-ai'); ?></span>
        </td>
    </tr>
    <tr>
        <th><?php _e('Page de désinscription', 'cobra-ai'); ?></th>
        <td>
            <?php
            wp_dropdown_pages([
                'name'             => 'settings[general][unsubscribe_page]',
                'selected'         => intval($settings['general']['unsubscribe_page'] ?? 0),
                'show_option_none' => __('— Aucune page —', 'cobra-ai'),
                'option_none_value'=> '0',
            ]);
            ?>
            <p class="description">
                <?php _e('Page avec le shortcode', 'cobra-ai'); ?>
                <code>[cobra_emailmarketing_unsubscribe]</code>.
                <?php _e('Le lien de désinscription dans les emails pointera vers cette page.', 'cobra-ai'); ?>
            </p>
        </td>
    </tr>
</table>

<h2><?php _e('Activation par type d\'email', 'cobra-ai'); ?></h2>
<p class="description" style="margin-bottom:16px;">
    <?php _e('Activez/désactivez chaque type d\'email indépendamment. Modifiez les sujets dans l\'onglet Templates.', 'cobra-ai'); ?>
</p>

<table class="wp-list-table widefat fixed striped" style="max-width:1000px;">
    <thead>
        <tr>
            <th style="width:40px;"><?php _e('Actif', 'cobra-ai'); ?></th>
            <th style="width:220px;"><?php _e('Type', 'cobra-ai'); ?></th>
            <th><?php _e('Sujet & déclencheur', 'cobra-ai'); ?></th>
            <th style="width:130px;"><?php _e('Test', 'cobra-ai'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($email_labels as $type => $label): ?>
        <tr>
            <td style="text-align:center;">
                <input type="checkbox"
                    name="settings[emails][<?php echo esc_attr($type); ?>][enabled]"
                    value="1"
                    <?php checked(!empty($settings['emails'][$type]['enabled'])); ?>>
            </td>
            <td><strong><?php echo esc_html($label); ?></strong></td>
            <td>
                <input type="text"
                    name="settings[emails][<?php echo esc_attr($type); ?>][subject]"
                    value="<?php echo esc_attr($settings['emails'][$type]['subject'] ?? ''); ?>"
                    class="regular-text" style="width:100%;">
                <p class="description" style="margin:4px 0 0;">
                    <span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;vertical-align:-2px;color:#666;"></span>
                    <em><?php echo esc_html($email_triggers[$type] ?? ''); ?></em>
                </p>
            </td>
            <td>
                <button type="button" class="button button-small cobra-em-test-btn"
                    data-type="<?php echo esc_attr($type); ?>">
                    <?php _e('Envoyer test', 'cobra-ai'); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2 style="margin-top:32px;"><?php _e('Planification cron', 'cobra-ai'); ?></h2>
<table class="form-table">
    <tr>
        <th><?php _e('Jour du rapport hebdo', 'cobra-ai'); ?></th>
        <td>
            <select name="settings[cron][weekly_report_day]">
                <?php
                $days = ['monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi',
                         'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche'];
                $selected_day = $settings['cron']['weekly_report_day'] ?? 'monday';
                foreach ($days as $val => $lbl):
                ?>
                <option value="<?php echo $val; ?>" <?php selected($selected_day, $val); ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php _e('Heure d\'envoi', 'cobra-ai'); ?></th>
        <td>
            <select name="settings[cron][weekly_report_hour]">
                <?php for ($h = 0; $h < 24; $h++): ?>
                <option value="<?php echo $h; ?>" <?php selected((int)($settings['cron']['weekly_report_hour'] ?? 8), $h); ?>>
                    <?php echo sprintf('%02d:00', $h); ?>
                </option>
                <?php endfor; ?>
            </select>
        </td>
    </tr>
</table>

<!-- Modal test email -->
<div id="cobra-em-test-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:28px;border-radius:8px;width:400px;max-width:90%;">
        <h3 style="margin-top:0;"><?php _e('Envoyer un email de test', 'cobra-ai'); ?></h3>
        <p>
            <label><?php _e('Destinataire', 'cobra-ai'); ?></label><br>
            <input type="email" id="cobra-em-test-to" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="width:100%;margin-top:4px;">
        </p>
        <input type="hidden" id="cobra-em-test-type" value="">
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
            <button type="button" class="button" id="cobra-em-test-cancel"><?php _e('Annuler', 'cobra-ai'); ?></button>
            <button type="button" class="button button-primary" id="cobra-em-test-send"><?php _e('Envoyer', 'cobra-ai'); ?></button>
        </div>
        <p id="cobra-em-test-result" style="margin-top:12px;font-weight:bold;"></p>
    </div>
</div>
