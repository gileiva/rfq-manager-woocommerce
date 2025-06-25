jQuery(document).ready(function($) {
    // console.log("[RFQ] Cargando rfq-filters.js");
    // Escuchar clicks en los tabs de estado
    $(document).on('click', '.rfq-status-tab', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var status = $btn.data('status');

        // Marcar el tab activo
        $btn.closest('.rfq-status-tabs').find('.rfq-status-tab').removeClass('active');
        $btn.addClass('active');

        // Loader en el contenedor de la tabla
        var $container = $('#rfq-solicitudes-table-container');
        $container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + (rfqManagerL10n.loading || 'Cargando...') + '</div>');

        // AJAX para filtrar solicitudes
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfq_filter_solicitudes',
                status: status,
                nonce: rfqManagerL10n.nonce
            },
            success: function(response) {
                if (typeof response === 'object' && response.success === false && response.data && response.data.message) {
                    $container.html('<p class="rfq-error">' + response.data.message + '</p>');
                } else {
                    $container.html(response);
                }
            },
            error: function() {
                $container.html('<p class="rfq-error">' + (rfqManagerL10n.error || 'Error al cargar las solicitudes') + '</p>');
            }
        });
    });

    // TODO: Revisar conflicto con el modal de cancelación que aparece en páginas no deseadas.
});
