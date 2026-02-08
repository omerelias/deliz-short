jQuery(function($) {
    // Unblock IP
    $('.unblock-ip').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const ip = $button.data('ip');

        if (!confirm(ocSmsAuthAdmin.i18n.confirm_unblock)) {
            return;
        }

        $.ajax({
            url: ocSmsAuthAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'oc_sms_auth_unblock_ip',
                ip: ip,
                nonce: ocSmsAuthAdmin.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        if ($('.blocked-ips-table-wrap tbody tr').length === 0) {
                            $('.blocked-ips-table-wrap tbody').append(
                                '<tr><td colspan="4">' + 
                                ocSmsAuthAdmin.i18n.no_blocked_ips + 
                                '</td></tr>'
                            );
                        }
                    });
                } else {
                    alert(response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    // Clear logs
    $('.clear-logs').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);

        if (!confirm(ocSmsAuthAdmin.i18n.confirm_clear_logs)) {
            return;
        }

        $.ajax({
            url: ocSmsAuthAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'oc_sms_auth_clear_logs',
                nonce: ocSmsAuthAdmin.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) { 
                    // Remove all existing rows
                    $('.auth-logs-table-wrap tbody').empty();
                    // Add the "no logs" row
                    $('.auth-logs-table-wrap tbody').append(
                        '<tr><td colspan="5">' +
                        ocSmsAuthAdmin.i18n.no_logs + 
                        '</td></tr>'
                    );
                } else {
                    alert(response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    // Provider settings toggle
    function toggleProviderSettings() {
        const provider = $('#sms_provider').val();
        $('.activetrail-setting, .twilio-setting').closest('tr').hide();
        $('.' + provider + '-setting').closest('tr').show();
    }

    $('#sms_provider').on('change', toggleProviderSettings);
    $(document).ready(toggleProviderSettings);
}); 