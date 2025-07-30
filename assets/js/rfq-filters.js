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
                // Scroll automático al tope de la página
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Actualizar URL con parámetros de filtro
                var newUrl = new URL(window.location);
                if (status) {
                    newUrl.searchParams.set('status', status);
                } else {
                    newUrl.searchParams.delete('status');
                }
                if (order) {
                    newUrl.searchParams.set('order', order);
                } else {
                    newUrl.searchParams.delete('order');
                }
                newUrl.searchParams.delete('paged'); // Eliminar paged al filtrar
                window.history.pushState({}, '', newUrl);
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
                // Scroll automático al tope de la página
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Actualizar URL con parámetros de filtro
                var newUrl = new URL(window.location);
                if (status) {
                    newUrl.searchParams.set('status', status);
                } else {
                    newUrl.searchParams.delete('status');
                }
                if (order) {
                    newUrl.searchParams.set('order', order);
                } else {
                    newUrl.searchParams.delete('order');
                }
                newUrl.searchParams.delete('paged'); // Eliminar paged al filtrar
                window.history.pushState({}, '', newUrl);
            },
            error: function() {
                $container.html('<p class="rfq-error">' + (rfqManagerL10n.error || 'Error al cargar las solicitudes') + '</p>');
            }
        });
    });

    // Interceptar clicks en enlaces de paginación para AJAX
    $(document).on('click', '.rfq-pagination .page-numbers', function(e) {
        e.preventDefault();
        var $link = $(this);
        
        // Ignorar si es el enlace actual o está deshabilitado
        if ($link.hasClass('current') || $link.hasClass('prev') && $link.attr('aria-disabled') === 'true') {
            return;
        }
        
        // Extraer número de página desde el href o texto del enlace
        var href = $link.attr('href');
        var paged = 1;
        
        if (href) {
            var pagedMatch = href.match(/paged=(\d+)/);
            if (pagedMatch) {
                paged = parseInt(pagedMatch[1], 10);
            }
        } else if ($link.hasClass('prev')) {
            // Botón anterior: obtener página actual y restar 1
            var $current = $('.rfq-pagination .page-numbers.current');
            if ($current.length) {
                paged = Math.max(1, parseInt($current.text(), 10) - 1);
            }
        } else if ($link.hasClass('next')) {
            // Botón siguiente: obtener página actual y sumar 1
            var $current = $('.rfq-pagination .page-numbers.current');
            if ($current.length) {
                paged = parseInt($current.text(), 10) + 1;
            }
        } else {
            // Enlace de número específico
            paged = parseInt($link.text(), 10) || 1;
        }
        
        // Obtener filtros y orden actuales
        var $activeTab = $('.rfq-status-tab.active');
        var status = $activeTab.length ? $activeTab.data('status') : '';
        var $orderDropdown = $('.rfq-order-dropdown');
        var order = $orderDropdown.length ? $orderDropdown.val() : '';
        
        // Loader en el contenedor
        var $container = $('#rfq-solicitudes-table-container');
        $container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + (rfqManagerL10n.loading || 'Cargando...') + '</div>');
        
        // AJAX con paginación
        var ajaxData = {
            action: 'rfq_filter_solicitudes',
            status: status,
            paged: paged,
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
                // Scroll automático al tope de la página
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Actualizar URL con parámetros de paginación
                var newUrl = new URL(window.location);
                if (status) {
                    newUrl.searchParams.set('status', status);
                } else {
                    newUrl.searchParams.delete('status');
                }
                if (order) {
                    newUrl.searchParams.set('order', order);
                } else {
                    newUrl.searchParams.delete('order');
                }
                if (paged > 1) {
                    newUrl.searchParams.set('paged', paged);
                } else {
                    newUrl.searchParams.delete('paged');
                }
                window.history.pushState({}, '', newUrl);
            },
            error: function() {
                $container.html('<p class="rfq-error">' + (rfqManagerL10n.error || 'Error al cargar las solicitudes') + '</p>');
            }
        });
    });

    
});
