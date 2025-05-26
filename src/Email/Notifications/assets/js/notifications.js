jQuery(document).ready(function($) {
    // ... existing code ...

    $('.reset-notifications-btn').on('click', function(e) {
        e.preventDefault();
        
        const role = $(this).data('role');
        const $button = $(this);
        
        if (!confirm('¿Estás seguro de que deseas reiniciar todas las notificaciones para este rol?')) {
            return;
        }

        $button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'reset_notifications_for_role',
                role: role,
                nonce: rfqNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Notificaciones reiniciadas correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al procesar la solicitud');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
}); 