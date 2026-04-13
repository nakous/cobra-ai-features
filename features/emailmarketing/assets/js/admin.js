/* global ajaxurl, cobraAIAdminEmailmarketing */
(function ($) {
    'use strict';

    const cfg = window.cobraAIAdminEmailmarketing || {};
    const nonce = cfg.nonce || '';

    // -------------------------------------------------------------------------
    // TEST EMAIL — open modal
    // -------------------------------------------------------------------------

    $(document).on('click', '.cobra-em-test-btn', function () {
        const type = $(this).data('type');
        $('#cobra-em-test-type').val(type);
        $('#cobra-em-test-result').text('').css('color', '');
        $('#cobra-em-test-modal').css('display', 'flex');
    });

    $('#cobra-em-test-cancel').on('click', function () {
        $('#cobra-em-test-modal').css('display', 'none');
    });

    $('#cobra-em-test-send').on('click', function () {
        const btn  = $(this);
        const type = $('#cobra-em-test-type').val();
        const to   = $('#cobra-em-test-to').val();

        if (!to) {
            $('#cobra-em-test-result').text('Entrez une adresse email.').css('color', '#d93025');
            return;
        }

        btn.prop('disabled', true).text('Envoi...');

        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_test',
            nonce: nonce,
            email_type: type,
            to: to,
            user_id: cfg.current_user_id || 0,
        })
        .done(function (res) {
            const msg   = res.data || '';
            const color = res.success ? '#34a853' : '#d93025';
            $('#cobra-em-test-result').text(msg).css('color', color);
            if (res.success) {
                setTimeout(function () {
                    $('#cobra-em-test-modal').css('display', 'none');
                }, 2000);
            }
        })
        .fail(function () {
            $('#cobra-em-test-result').text('Erreur de connexion.').css('color', '#d93025');
        })
        .always(function () {
            btn.prop('disabled', false).text('Envoyer');
        });
    });

    // -------------------------------------------------------------------------
    // RESET TEMPLATE
    // -------------------------------------------------------------------------

    $('#cobra-em-reset-tpl').on('click', function () {
        if (!confirm('Restaurer le template par défaut ? Vos modifications seront perdues.')) return;

        const btn  = $(this);
        const type = btn.data('type');

        btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_reset_template',
            nonce: nonce,
            template_type: type,
        })
        .done(function (res) {
            if (res.success && res.data && res.data.content !== undefined) {
                $('#cobra-em-tpl-editor').val(res.data.content);
            }
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // EXPORT CSV
    // -------------------------------------------------------------------------

    $('#cobra-em-export-log').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text('Export...');

        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_export_log',
            nonce: nonce,
        })
        .done(function (res) {
            if (res.success && res.data.csv) {
                const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' });
                const url  = URL.createObjectURL(blob);
                const a    = document.createElement('a');
                a.href     = url;
                a.download = 'email-log-' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
                URL.revokeObjectURL(url);
            }
        })
        .always(function () {
            btn.prop('disabled', false).text('Exporter CSV');
        });
    });

    // -------------------------------------------------------------------------
    // UNBLOCK USER
    // -------------------------------------------------------------------------

    $(document).on('click', '.cobra-em-unblock', function () {
        const btn    = $(this);
        const userId = btn.data('user-id');

        if (!confirm('Débloquer cet utilisateur ? Il recevra à nouveau des emails.')) return;

        btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'cobra_emailmarketing_unblock_user',
            nonce: nonce,
            user_id: userId,
        })
        .done(function (res) {
            if (res.success) {
                btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(res.data || 'Erreur.');
                btn.prop('disabled', false);
            }
        })
        .fail(function () {
            alert('Erreur de connexion.');
            btn.prop('disabled', false);
        });
    });

}(jQuery));
