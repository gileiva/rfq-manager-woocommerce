jQuery(document).ready(function($) {
    // console.log('RFQ Manager JS inicializado');

    // Variable para mantener el ID del acordeón actualmente abierto
    let openAccordionId = null;
    let currentSolicitudId = null;
    const $modal = $('#rfq-confirm-modal');

    // Funcionalidad del acordeón
    $('.rfq-toggle-details').on('click', function(e) {
        e.preventDefault();
        
        const solicitudId = $(this).data('solicitud');
        const detailsContent = $('#solicitud-' + solicitudId);
        const button = $(this);
        
        // Si el acordeón actualmente abierto es diferente al que se está intentando abrir
        if (openAccordionId !== null && openAccordionId !== solicitudId) {
            // Cerrar el acordeón anterior
            $('#solicitud-' + openAccordionId).slideUp(300);
            $('.rfq-toggle-details[data-solicitud="' + openAccordionId + '"]')
                .text(rfqManagerL10n.showDetails)
                .removeClass('active');
        }
        
        // Toggle del acordeón actual
        if (detailsContent.is(':visible')) {
            detailsContent.slideUp(300);
            button.text(rfqManagerL10n.showDetails).removeClass('active');
            openAccordionId = null;
        } else {
            detailsContent.slideDown(300);
            button.text(rfqManagerL10n.hideDetails).addClass('active');
            openAccordionId = solicitudId;
        }
    });

    // Funcionalidad del formulario de cotización
    function calculateSubtotal(row) {
        const qty = parseFloat(row.find('td:nth-child(2)').text());
        const price = parseFloat(row.find('.rfq-precio-input').val()) || 0;
        const subtotal = qty * price;
        row.find('.rfq-subtotal').text(subtotal.toFixed(2));
        return subtotal;
    }

    function calculateTotal() {
        let total = 0;
        $('.rfq-cotizar-table tbody tr').each(function() {
            total += calculateSubtotal($(this));
        });
        $('.rfq-total-amount').text(total.toFixed(2));
    }

    // Calcular subtotales y total cuando cambia el precio
    $('.rfq-precio-input').on('input', function() {
        calculateSubtotal($(this).closest('tr'));
        calculateTotal();
    });

    // Validación del formulario
    $('#rfq-cotizar-form').on('submit', function(e) {
        e.preventDefault();
        
        // Verificar que todos los precios estén completos
        let isValid = true;
        $('.rfq-precio-input').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        if (!isValid) {
            alert(rfqManagerL10n.completePrices);
            return;
        }

        // Enviar el formulario
        this.submit();
    });

    // Manejar el cambio en el filtro de estado
    $('#rfq-status-filter').on('change', function() {
        const status = $(this).val();
        const container = $('#rfq-solicitudes-table-container');
        const select = $(this);
        
        // Deshabilitar el select durante la carga
        select.prop('disabled', true);
        
        // Mostrar indicador de carga con animación
        container.fadeOut(200, function() {
            container.html('<div class="rfq-loading"><div class="rfq-spinner"></div>' + rfqManagerL10n.loading + '</div>');
            container.fadeIn(200);
        });
        
        // Realizar la petición AJAX
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfq_filter_solicitudes',
                nonce: rfqManagerL10n.nonce,
                status: status
            },
            success: function(response) {
                container.fadeOut(200, function() {
                    container.html(response);
                    container.fadeIn(200);
                    select.prop('disabled', false);
                });
            },
            error: function() {
                container.fadeOut(200, function() {
                    container.html('<p class="rfq-error">' + rfqManagerL10n.error + '</p>');
                    container.fadeIn(200);
                    select.prop('disabled', false);
                });
            }
        });
    });

    // Manejar la cancelación de solicitudes
    $(document).on('click', '.rfq-cancel-btn', function(e) {
        // console.log('Botón de cancelar clickeado');
        e.preventDefault();
        
        const $button = $(this);
        currentSolicitudId = $button.data('solicitud');
        // console.log('ID de solicitud:', currentSolicitudId);
        
        // Mostrar el modal
        $modal.fadeIn(300);
    });

    // Manejar el botón de cancelar en el modal
    $('.rfq-modal-cancel').on('click', function() {
        $modal.fadeOut(300);
        currentSolicitudId = null;
    });

    // Manejar el botón de confirmar en el modal
    $('.rfq-modal-confirm').on('click', function() {
        if (!currentSolicitudId) return;
        
        // console.log('Iniciando proceso de cancelación');
        const $button = $('.rfq-cancel-btn[data-solicitud="' + currentSolicitudId + '"]');
        
        // Deshabilitar el botón y mostrar estado de carga
        $button.prop('disabled', true).addClass('loading');
        
        // Realizar la petición AJAX
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfq_cancel_solicitud',
                solicitud_id: currentSolicitudId,
                nonce: rfqManagerL10n.nonce
            },
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                if (response.success) {
                    // console.log('Cancelación exitosa');
                    // Cerrar el modal
                    $modal.fadeOut(300);
                    
                    // Mostrar mensaje de éxito
                    const $successMessage = $('<div class="rfq-success-message">' + rfqManagerL10n.cancelSuccess + '</div>');
                    $('body').append($successMessage);
                    setTimeout(() => {
                        $successMessage.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                    
                    // Si estamos en la vista individual, redirigir a la lista
                    if ($('.rfq-solicitud-view').length) {
                        console.log('Redirigiendo a la lista desde vista individual');
                        window.location.href = window.location.pathname.replace('ver-solicitud', '');
                    } else {
                        // Si estamos en la lista, recargar la página
                        // console.log('Recargando página desde lista');
                        window.location.reload();
                    }
                } else {
                    console.log('Error en la cancelación:', response.data);
                    // Mostrar mensaje de error
                    const $errorMessage = $('<div class="rfq-error-message">' + (response.data || rfqManagerL10n.cancelError) + '</div>');
                    $('body').append($errorMessage);
                    setTimeout(() => {
                        $errorMessage.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                    
                    $button.prop('disabled', false).removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                // console.error('Error AJAX:', {xhr, status, error});
                // Mostrar mensaje de error
                const $errorMessage = $('<div class="rfq-error-message">' + rfqManagerL10n.cancelError + '</div>');
                $('body').append($errorMessage);
                setTimeout(() => {
                    $errorMessage.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
                
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });
}); 