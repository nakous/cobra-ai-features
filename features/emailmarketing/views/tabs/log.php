<?php
defined('ABSPATH') || exit;
/** @var \CobraAI\Features\Emailmarketing\Feature $this */

$type_labels = [
    'onboarding_j0'     => 'Onboarding J+0',
    'onboarding_j2'     => 'Onboarding J+2',
    'onboarding_j7'     => 'Onboarding J+7',
    're_engagement_7j'  => 'Re-engagement 7j',
    're_engagement_30j' => 'Re-engagement 30j',
    'weekly_report'     => 'Rapport hebdo',
    'tips'              => 'Conseils',
    'milestone'         => 'Félicitations',
    'test'              => 'Test',
];

$status_cfg = [
    'sent'    => ['label' => 'Envoyé',   'color' => '#1e8449', 'bg' => '#eafaf1', 'icon' => '✓'],
    'failed'  => ['label' => 'Échoué',   'color' => '#c0392b', 'bg' => '#fdedec', 'icon' => '✕'],
    'bounced' => ['label' => 'Rejeté',   'color' => '#d35400', 'bg' => '#fef5e7', 'icon' => '↩'],
    'spam'    => ['label' => 'Spam',     'color' => '#7b241c', 'bg' => '#f9ebea', 'icon' => '⚠'],
    'skipped' => ['label' => 'Ignoré',   'color' => '#626567', 'bg' => '#f2f3f4', 'icon' => '—'],
];

$filters = [
    'status'     => sanitize_key($_GET['filter_status'] ?? ''),
    'email_type' => sanitize_key($_GET['filter_type']   ?? ''),
    'date_from'  => sanitize_text_field($_GET['date_from'] ?? ''),
    'date_to'    => sanitize_text_field($_GET['date_to']   ?? ''),
];

$per_page = 30;
$page_num = max(1, intval($_GET['paged'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$rows  = $this->sender ? $this->sender->get_log($filters, $per_page, $offset) : [];
$total = $this->sender ? $this->sender->count_log($filters) : 0;
$pages = $total > 0 ? ceil($total / $per_page) : 1;

// Stats globales (sans filtre)
global $wpdb;
$log_table   = $this->get_table_name('email_log');
$queue_table = $this->get_table_name('email_queue');
$stats = $wpdb->get_results(
    "SELECT status, COUNT(*) as n FROM {$log_table} GROUP BY status",
    ARRAY_A
);
$by_status = array_column($stats, 'n', 'status');
$total_all = array_sum($by_status);
$pending_queue = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status='pending'");
?>

<!-- ========================================================
     STATS CARDS
======================================================== -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
    <?php
    $cards = [
        ['label' => 'Total emails',   'value' => number_format($total_all),              'color' => '#1a73e8', 'bg' => '#e8f0fe'],
        ['label' => 'Envoyés',        'value' => number_format($by_status['sent']  ?? 0), 'color' => '#1e8449', 'bg' => '#eafaf1'],
        ['label' => 'Échoués',        'value' => number_format($by_status['failed'] ?? 0),'color' => '#c0392b', 'bg' => '#fdedec'],
        ['label' => 'En queue',       'value' => number_format($pending_queue),            'color' => '#7d6608', 'bg' => '#fef9e7'],
        ['label' => 'Ignorés',        'value' => number_format($by_status['skipped'] ?? 0),'color' => '#626567', 'bg' => '#f2f3f4'],
    ];
    foreach ($cards as $c): ?>
    <div style="background:<?php echo $c['bg']; ?>;border-radius:8px;padding:14px 20px;min-width:120px;text-align:center;">
        <div style="font-size:22px;font-weight:700;color:<?php echo $c['color']; ?>;"><?php echo $c['value']; ?></div>
        <div style="font-size:12px;color:#555;margin-top:2px;"><?php echo $c['label']; ?></div>
    </div>
    <?php endforeach; ?>

    <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
        <button type="button" class="button" id="cobra-em-export-log">
            ⬇ <?php _e('Exporter CSV', 'cobra-ai'); ?>
        </button>
        <?php if (($by_status['failed'] ?? 0) > 0): ?>
        <button type="button" class="button" id="cobra-em-clear-failed"
            style="color:#c0392b;border-color:#c0392b;">
            🗑 Vider les échoués (<?php echo number_format($by_status['failed'] ?? 0); ?>)
        </button>
        <?php endif; ?>
        <?php if ($total_all > 0): ?>
        <button type="button" class="button" id="cobra-em-clear-all-log"
            style="color:#7b241c;border-color:#7b241c;">
            🗑 Vider tout l'historique
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     FILTRES
======================================================== -->
<form method="get" style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:6px;padding:14px 16px;display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;">
    <input type="hidden" name="page" value="cobra-ai-<?php echo esc_attr($this->get_feature_id()); ?>">
    <input type="hidden" name="tab" value="log">

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;"><?php _e('Statut', 'cobra-ai'); ?></label>
        <select name="filter_status" style="min-width:130px;">
            <option value=""><?php _e('Tous les statuts', 'cobra-ai'); ?></option>
            <?php foreach ($status_cfg as $sk => $sv): ?>
            <option value="<?php echo $sk; ?>" <?php selected($filters['status'], $sk); ?>>
                <?php echo $sv['icon'] . ' ' . $sv['label']; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;"><?php _e('Type d\'email', 'cobra-ai'); ?></label>
        <select name="filter_type" style="min-width:180px;">
            <option value=""><?php _e('Tous les types', 'cobra-ai'); ?></option>
            <?php foreach ($type_labels as $tk => $tv): ?>
            <option value="<?php echo $tk; ?>" <?php selected($filters['email_type'], $tk); ?>><?php echo esc_html($tv); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;"><?php _e('Du', 'cobra-ai'); ?></label>
        <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
    </div>

    <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#333;"><?php _e('Au', 'cobra-ai'); ?></label>
        <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
    </div>

    <div style="display:flex;gap:8px;">
        <?php submit_button(__('Filtrer', 'cobra-ai'), 'primary', 'filter', false); ?>
        <?php if (array_filter($filters)): ?>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'cobra-ai-' . $this->get_feature_id(), 'tab' => 'log'], admin_url('admin.php'))); ?>"
           class="button"><?php _e('Réinitialiser', 'cobra-ai'); ?></a>
        <?php endif; ?>
    </div>
</form>

<p style="color:#666;font-size:13px;margin-bottom:8px;">
    <?php printf(_n('%d résultat', '%d résultats', $total, 'cobra-ai'), $total); ?>
    <?php if (array_filter($filters)): ?>
    <span style="color:#888;">— filtre actif</span>
    <?php endif; ?>
</p>

<!-- ========================================================
     TABLE
======================================================== -->
<table class="wp-list-table widefat fixed" style="border-radius:6px;overflow:hidden;">
    <thead style="background:#f0f0f1;">
        <tr>
            <th style="width:180px;"><?php _e('Utilisateur', 'cobra-ai'); ?></th>
            <th style="width:200px;"><?php _e('Destinataire', 'cobra-ai'); ?></th>
            <th style="width:160px;"><?php _e('Type d\'email', 'cobra-ai'); ?></th>
            <th><?php _e('Sujet', 'cobra-ai'); ?></th>
            <th style="width:110px;"><?php _e('Statut', 'cobra-ai'); ?></th>
            <th style="width:130px;"><?php _e('Date', 'cobra-ai'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="6" style="text-align:center;padding:32px;color:#888;font-size:14px;">
                <?php _e('Aucun email enregistré.', 'cobra-ai'); ?>
            </td>
        </tr>
        <?php else: foreach ($rows as $i => $row):
            $meta      = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];
            $error_msg = $meta['error'] ?? $meta['reason'] ?? '';
            $cfg       = $status_cfg[$row['status']] ?? ['label' => $row['status'], 'color' => '#333', 'bg' => '#fff', 'icon' => '?'];
            $type_lbl  = $type_labels[$row['email_type']] ?? $row['email_type'];
            $bg        = $i % 2 === 0 ? '#fff' : '#fafafa';
        ?>
        <tr style="background:<?php echo $bg; ?>;">

            <!-- Utilisateur -->
            <td>
                <?php if (!empty($row['display_name'])): ?>
                <a href="<?php echo esc_url(get_edit_user_link($row['user_id'])); ?>"
                   style="font-weight:600;text-decoration:none;color:#1a73e8;">
                    <?php echo esc_html($row['display_name']); ?>
                </a>
                <div style="font-size:11px;color:#888;">#<?php echo $row['user_id']; ?></div>
                <?php else: ?>
                <span style="color:#888;">#<?php echo $row['user_id']; ?></span>
                <?php endif; ?>
            </td>

            <!-- Destinataire -->
            <td style="font-size:12px;color:#444;word-break:break-all;">
                <?php echo esc_html($row['email_to'] ?: '—'); ?>
            </td>

            <!-- Type -->
            <td>
                <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#e8f0fe;color:#1a73e8;">
                    <?php echo esc_html($type_lbl); ?>
                </span>
            </td>

            <!-- Sujet -->
            <td style="font-size:13px;color:#333;">
                <?php if (!empty($row['subject'])): ?>
                    <?php echo esc_html($row['subject']); ?>
                <?php else: ?>
                    <span style="color:#bbb;font-style:italic;">—</span>
                <?php endif; ?>
                <?php if ($row['status'] === 'failed' && $error_msg): ?>
                <div style="font-size:11px;color:#c0392b;margin-top:3px;border-left:2px solid #c0392b;padding-left:6px;">
                    <?php echo esc_html($error_msg); ?>
                </div>
                <?php endif; ?>
            </td>

            <!-- Statut -->
            <td>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>;">
                    <span><?php echo $cfg['icon']; ?></span>
                    <span><?php echo $cfg['label']; ?></span>
                </span>
            </td>

            <!-- Date -->
            <td style="font-size:12px;color:#555;white-space:nowrap;">
                <?php echo esc_html(date_i18n('d/m/Y', strtotime($row['sent_at']))); ?><br>
                <span style="color:#999;"><?php echo esc_html(date_i18n('H:i', strtotime($row['sent_at']))); ?></span>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<!-- ========================================================
     PAGINATION
======================================================== -->
<?php if ($pages > 1): ?>
<div style="margin-top:16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
    <?php
    $base_url = add_query_arg([
        'page'          => 'cobra-ai-' . $this->get_feature_id(),
        'tab'           => 'log',
        'filter_status' => $filters['status'],
        'filter_type'   => $filters['email_type'],
        'date_from'     => $filters['date_from'],
        'date_to'       => $filters['date_to'],
    ], admin_url('admin.php'));

    // Previous
    if ($page_num > 1) {
        echo '<a href="' . esc_url(add_query_arg('paged', $page_num - 1, $base_url)) . '" class="button button-small">‹</a>';
    }
    // Page numbers (max 10 visible)
    $start = max(1, $page_num - 4);
    $end   = min($pages, $start + 9);
    for ($p = $start; $p <= $end; $p++) {
        $active = $p === $page_num ? 'button-primary' : '';
        echo '<a href="' . esc_url(add_query_arg('paged', $p, $base_url)) . '" class="button button-small ' . $active . '">' . $p . '</a>';
    }
    // Next
    if ($page_num < $pages) {
        echo '<a href="' . esc_url(add_query_arg('paged', $page_num + 1, $base_url)) . '" class="button button-small">›</a>';
    }
    echo '<span style="color:#888;font-size:12px;margin-left:8px;">Page ' . $page_num . '/' . $pages . ' — ' . number_format($total) . ' entrées</span>';
    ?>
</div>
<?php endif; ?>

<script>
jQuery(function($) {
    // Clear failed
    $('#cobra-em-clear-failed').on('click', function() {
        if (!confirm('Supprimer toutes les entrées "échoué" du journal ? Cette action est irréversible.')) return;
        const btn = $(this).prop('disabled', true).text('Suppression...');
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_clear_failed_log',
            nonce: window.cobraAIAdminEmailmarketing?.nonce || '',
        })
        .done(function(res) {
            if (res.success) location.reload();
            else alert(res.data || 'Erreur.');
        })
        .always(function() { btn.prop('disabled', false); });
    });

    // Clear all
    $('#cobra-em-clear-all-log').on('click', function() {
        if (!confirm('Vider tout l\'historique des emails ? Cette action est irréversible.')) return;
        const btn = $(this).prop('disabled', true).text('Suppression...');
        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_clear_all_log',
            nonce: window.cobraAIAdminEmailmarketing?.nonce || '',
        })
        .done(function(res) {
            if (res.success) location.reload();
            else alert(res.data || 'Erreur.');
        })
        .always(function() { btn.prop('disabled', false); });
    });
});
</script>
