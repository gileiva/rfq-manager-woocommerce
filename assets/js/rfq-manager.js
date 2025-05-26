jQuery(document).ready(function($) {
    // Agregar estilos del modal
    $('head').append(`
        <style>
            .rfq-modal {
                display: none;               
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                display: flex;               
                justify-content: center;
                align-items: center;
                }

            .rfq-modal-content {
                background-color: #fff;
                padding: 20px;
                border-radius: 5px;
                max-width: 500px;
                width: 90%;
                position: relative;
            }
            .rfq-modal-buttons {
                margin-top: 20px;
                text-align: right;
            }
            .rfq-modal-buttons button {
                margin-left: 10px;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .rfq-modal-cancel {
                background-color: #f1f1f1;
                color: #333;
            }
            .rfq-modal-confirm {
                background-color: #dc3545;
                color: white;
            }
        </style>
    `);

    // Agregar el modal al body si no existe
    if ($('#rfq-confirm-modal').length === 0) {
        $('body').append(`
            <div id="rfq-confirm-modal" class="rfq-modal">
                <div class="rfq-modal-content">
                    <h3>${rfqManagerL10n.cancelConfirmTitle}</h3>
                    <p>${rfqManagerL10n.cancelConfirm}</p>
                    <div class="rfq-modal-buttons">
                        <button class="rfq-modal-cancel">${rfqManagerL10n.cancelNo}</button>
                        <button class="rfq-modal-confirm">${rfqManagerL10n.cancelYes}</button>
                    </div>
                </div>
            </div>
        `);
    }

    // Manejar el cierre del modal
    $(document).on('click', '.rfq-modal-cancel', function() {
        $('#rfq-confirm-modal').fadeOut();
    });

    // console.log('RFQ Manager JS inicializado');

    // Variable para mantener el ID del acordeón actualmente abierto
    let openAccordionId = null;
    let currentSolicitudId = null;

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

    // Función para mostrar notificación toast
    function showToast(message, isError = false) {
        const toast = $("<div class=\"rfq-toast-notification" + (isError ? " error" : "") + "\">" + message + "</div>");
        $("body").append(toast);
        
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // Manejar el modal y la acción AJAX para aceptar cotización
    $(document).on('click', '.rfq-aceptar-cotizacion-btn:not([disabled])', function() {
        var cotizacionId = $(this).data('cotizacion-id');
        console.log('[RFQ] Click en botón Aceptar cotización. ID:', cotizacionId);
        window._rfqCotizacionToAccept = cotizacionId;
        $('#rfq-aceptar-modal').fadeIn();
    });
    $(document).on('click', '#rfq-aceptar-modal .rfq-modal-cancel', function() {
        window._rfqCotizacionToAccept = null;
        $('#rfq-aceptar-modal').fadeOut();
    });
    $(document).on('click', '#rfq-aceptar-modal .rfq-modal-confirm-aceptar', function() {
        var cotizacionId = window._rfqCotizacionToAccept;
        if (!cotizacionId) return;
        console.log('[RFQ] Confirmando aceptación de cotización. ID:', cotizacionId);
        var $btn = $('.rfq-aceptar-cotizacion-btn[data-cotizacion-id="' + cotizacionId + '"]');
        $btn.prop('disabled', true).text('Aceptando...');
        $(this).prop('disabled', true);
        $.post(rfqManagerL10n.ajaxurl, {
            action: 'accept_quote',
            cotizacion_id: cotizacionId,
            nonce: rfqManagerL10n.nonce
        }, function(resp) {
            if (resp.success) {
                console.log('[RFQ] Cotización aceptada correctamente. ID:', cotizacionId);
                // Resaltar la fila aceptada
                var $row = $('tr[data-cotizacion-id="' + cotizacionId + '"]');
                $row.removeClass('rfq-cotizacion-no-aceptada').addClass('rfq-cotizacion-aceptada');
                $row.find('.rfq-aceptar-cotizacion-btn').prop('disabled', true).css({'background':'#4caf50','color':'#fff','cursor':'default'}).text('Aceptada');
                // Mostrar botón pagar
                if ($row.find('.rfq-pagar-cotizacion-btn').length === 0) {
                    $row.find('td:last').append(' <button type="button" class="button rfq-pagar-cotizacion-btn" data-cotizacion-id="' + cotizacionId + '" onclick="window.location.href=\'#\';">Pagar</button>');
                }
                // Bajar opacidad y ocultar botones aceptar de las demás
                $('.rfq-cotizaciones-table tr').each(function() {
                    var $tr = $(this);
                    if ($tr.data('cotizacion-id') != cotizacionId) {
                        $tr.removeClass('rfq-cotizacion-aceptada').addClass('rfq-cotizacion-no-aceptada');
                        $tr.find('.rfq-aceptar-cotizacion-btn').hide();
                    }
                });
                // Cerrar modal y resetear botones
                $('#rfq-aceptar-modal').fadeOut();
                $('#rfq-aceptar-modal .rfq-modal-confirm-aceptar').prop('disabled', false);
            } else {
                alert((resp.data && resp.data.msg) ? resp.data.msg : 'Error inesperado');
                $btn.prop('disabled', false).text('Aceptar');
                $('#rfq-aceptar-modal .rfq-modal-confirm-aceptar').prop('disabled', false);
            }
        });
    });

    // Handler para botones de pago
    $(document).on('click', '.rfq-pagar-cotizacion-btn', function() {
        var cotizacionId = $(this).data('cotizacion-id');
        var paymentUrl = window.location.origin + '/pagar-cotizacion/' + cotizacionId + '/';
        window.location.href = paymentUrl;
    });
}); 