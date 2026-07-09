<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\EmailMarketing\Feature $this */
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

<hr style="margin:32px 0;">

<h2><?php _e('Mise en page des emails', 'cobra-ai'); ?></h2>
<p class="description" style="margin-bottom:16px;">
    <?php _e('Le layout entoure tous les emails. Utilisez la variable <code>{{content}}</code> pour insérer le corps, <code>{{footer}}</code> pour le pied de page.', 'cobra-ai'); ?>
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="tpl_layout"><?php _e('Layout HTML global', 'cobra-ai'); ?></label></th>
        <td>
            <?php
            wp_editor(
                $settings['templates']['layout'] ?? '',
                'tpl_layout',
                [
                    'textarea_name' => 'settings[templates][layout]',
                    'media_buttons' => false,
                    'textarea_rows' => 14,
                    'teeny'         => false,
                    'tinymce'       => ['toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,code,undo,redo'],
                ]
            );
            ?>
            <p class="description"><?php _e('Variables disponibles : <code>{{content}}</code> <code>{{footer}}</code> <code>{{site_name}}</code> <code>{{subject}}</code> etc.', 'cobra-ai'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="tpl_footer"><?php _e('Pied de page (footer)', 'cobra-ai'); ?></label></th>
        <td>
            <?php
            wp_editor(
                $settings['templates']['footer'] ?? '',
                'tpl_footer',
                [
                    'textarea_name' => 'settings[templates][footer]',
                    'media_buttons' => false,
                    'textarea_rows' => 6,
                    'teeny'         => true,
                ]
            );
            ?>
            <p class="description"><?php _e('Variables disponibles : <code>{{site_name}}</code> <code>{{unsubscribe_url}}</code> etc.', 'cobra-ai'); ?></p>
        </td>
    </tr>
</table>


