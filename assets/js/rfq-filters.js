jQuery(document).ready(function($) {
    // console.log("[RFQ] Cargando rfq-filters.js");
    // Escuchar clicks en los tabs de estado
    $(document).on('click', '.rfq-status-tab', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var status = $btn.data('status');

        // Obtener el valor actual del dropdown de orden (si existe)
        var $orderDropdown = $('.rfq-order-dropdown');
        var order = $orderDropdown.length ? $orderDropdown.val() : '';

        // Marcar el tab activo
        $btn.closest('.rfq-status-tabs').find('.rfq-status-tab').removeClass('active');
        $btn.addClass('active');

        // Loader en el contenedor de la tabla
        var $container = $('#rfq-solicitudes-table-container');
        $container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + (rfqManagerL10n.loading || 'Cargando...') + '</div>');

        // AJAX para filtrar solicitudes
        var ajaxData = {
            action: 'rfq_filter_solicitudes',
            status: status,
            nonce: rfqManagerL10n.nonce
        };
        if (order) {
            ajaxData.order = order;
        }
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: ajaxData,
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

    // Filtrado dinámico por orden
    $(document).on('change', '.rfq-order-dropdown', function(e) {
        var order = $(this).val();
        var $activeTab = $('.rfq-status-tab.active');
        var status = $activeTab.length ? $activeTab.data('status') : '';
        var $container = $('#rfq-solicitudes-table-container');
        $container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + (rfqManagerL10n.loading || 'Cargando...') + '</div>');

        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfq_filter_solicitudes',
                status: status,
                order: order,
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

    
});
