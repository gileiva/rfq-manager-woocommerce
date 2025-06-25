jQuery(document).ready(function($) {
    function showRfqLoader(container) {
        container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + (rfqManagerL10n.loading || 'Cargando...') + '</div>');
    }

    $(document).on('click', '.rfq-status-tab', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var status = $btn.data('status');
        var $tabs = $btn.closest('.rfq-status-tabs').find('.rfq-status-tab');
        var $container = $('#rfq-solicitudes-table-container');

        $tabs.removeClass('active');
        $btn.addClass('active');
        showRfqLoader($container);

        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfq_filter_solicitudes',
                status: status,
                nonce: rfqManagerL10n.nonce
            },
            success: function(response) {
                $container.html(response);
            },
            error: function() {
                $container.html('<p class="rfq-error">' + (rfqManagerL10n.error || 'Error al cargar las solicitudes') + '</p>');
            }
        });
    });
});
