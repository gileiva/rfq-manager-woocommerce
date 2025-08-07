jQuery(document).ready(function($) {
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
        // Guardar referencia a la tarjeta para actualizar luego
        window._rfqCardToAccept = $(this).closest('.rfq-cotizacion-card');
        $('#rfq-aceptar-modal').fadeIn();
    });
    $(document).on('click', '#rfq-aceptar-modal .rfq-modal-cancel', function() {
        window._rfqCotizacionToAccept = null;
        window._rfqCardToAccept = null;
        $('#rfq-aceptar-modal').fadeOut();
    });
    $(document).on('click', '#rfq-aceptar-modal .rfq-modal-confirm-aceptar', function() {
        var cotizacionId = window._rfqCotizacionToAccept;
        var $card = window._rfqCardToAccept;
        if (!cotizacionId || !$card || $card.length === 0) return;
        console.log('[RFQ] Confirmando aceptación de cotización. ID:', cotizacionId);
        var $btn = $card.find('.rfq-aceptar-cotizacion-btn[data-cotizacion-id="' + cotizacionId + '"]');
        $btn.prop('disabled', true).text('Aceptando...');
        $(this).prop('disabled', true);
        $.post(rfqManagerL10n.ajaxurl, {
            action: 'accept_quote',
            cotizacion_id: cotizacionId,
            nonce: rfqManagerL10n.nonce
        }, function(resp) {
            console.log('[RFQ][DEBUG] Respuesta AJAX accept_quote:', resp);
            if (resp.success) {
                console.log('[RFQ] Cotización aceptada correctamente. ID:', cotizacionId);
                
                // Si hay URL de checkout y redirect es true, redirigir inmediatamente
                if (resp.data && resp.data.checkout_url && resp.data.redirect) {
                    console.log('[RFQ] Redirigiendo al checkout:', resp.data.checkout_url);
                    window.location.href = resp.data.checkout_url;
                    return; // Evitar ejecutar el resto del código
                }
                
                // Marcar la tarjeta aceptada
                $card.removeClass('rfq-cotizacion-no-aceptada').addClass('rfq-cotizacion-aceptada');
                $btn.prop('disabled', true)
                    .css({'background':'#4caf50','color':'#fff','cursor':'default'})
                    .text('Aceptada');
                // Mostrar botón pagar si no existe
                if ($card.find('.rfq-pagar-cotizacion-btn').length === 0) {
                    $card.find('.rfq-cotizacion-actions').append(' <button type="button" class="button rfq-pagar-cotizacion-btn" data-cotizacion-id="' + cotizacionId + '">' + (rfqManagerL10n.quotePayText || 'Pagar') + '</button>');
                }
                // Ocultar botones aceptar de las demás tarjetas
                $('.rfq-cotizacion-card').each(function() {
                    var $otherCard = $(this);
                    if ($otherCard.data('cotizacion-id') != cotizacionId) {
                        $otherCard.removeClass('rfq-cotizacion-aceptada').addClass('rfq-cotizacion-no-aceptada');
                        $otherCard.find('.rfq-aceptar-cotizacion-btn').hide();
                    }
                });
                // Cerrar modal y resetear botones
                $('#rfq-aceptar-modal').fadeOut();
                $('#rfq-aceptar-modal .rfq-modal-confirm-aceptar').prop('disabled', false);
                window._rfqCotizacionToAccept = null;
                window._rfqCardToAccept = null;
            } else {
                // Mostrar mensaje de error real si existe
                var msg = (resp.data && (resp.data.message || resp.data.msg)) ? (resp.data.message || resp.data.msg) : 'Error inesperado';
                console.error('[RFQ][DEBUG] Error al aceptar cotización:', msg, resp);
                $btn.prop('disabled', false).text('Aceptar');
                alert(msg);
                $('#rfq-aceptar-modal .rfq-modal-confirm-aceptar').prop('disabled', false);
            }
        });
    });

    // Handler para botones de pago
    $(document).on('click', '.rfq-pagar-cotizacion-btn', function() {
        var cotizacionId = $(this).data('cotizacion-id');
        var orderId = $(this).data('order-id');
        
        console.log('[RFQ-PAGO] Click en botón pagar - Cotización:', cotizacionId, 'Orden:', orderId);
        
        // Si tenemos order-id, obtener la URL de pago via AJAX
        if (orderId) {
            console.log('[RFQ-PAGO] Obteniendo URL de pago para orden:', orderId);
            
            // Hacer una petición AJAX para obtener la URL de pago correcta
            $.ajax({
                url: rfqManagerL10n.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rfq_get_payment_url',
                    order_id: orderId,
                    nonce: rfqManagerL10n.nonce
                },
                success: function(response) {
                    if (response.success && response.data.payment_url) {
                        console.log('[RFQ-PAGO] URL de pago obtenida:', response.data.payment_url);
                        window.location.href = response.data.payment_url;
                    } else {
                        console.error('[RFQ-PAGO] Error obteniendo URL de pago:', response);
                        showToast('Error obteniendo URL de pago. Por favor, inténtelo de nuevo.', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[RFQ-PAGO] Error AJAX obteniendo URL de pago:', error);
                    showToast('Error de conexión. Por favor, inténtelo de nuevo.', true);
                }
            });
        } else {
            // Sin order-id, mostrar error al usuario
            console.error('[RFQ-PAGO] No se encontró ID de orden para la cotización:', cotizacionId);
            showToast('Error: No se pudo encontrar la orden de pago. Por favor, contacte con soporte.', true);
        }
    });

    // Handler para botón Repetir Solicitud
    $(document).on('click', '.rfq-repeat-btn', function(e) {
        e.preventDefault();
        console.log('[RFQ] Click en botón repetir solicitud');
        var $btn = $(this);
        var solicitudId = $btn.data('solicitud');
        console.log('[RFQ] Solicitud ID:', solicitudId);
        if (!solicitudId) {
            console.error('[RFQ] ID de solicitud inválido');
            showToast('ID de solicitud inválido.', true);
            return;
        }
        $btn.prop('disabled', true);
        showToast(rfqManagerL10n.loading);
        console.log('[RFQ] Enviando AJAX para repetir solicitud');
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'repeat_solicitud',
                solicitud_id: solicitudId,
                nonce: rfqManagerL10n.nonce
            },
            success: function(response) {
                console.log('[RFQ] Respuesta AJAX repetir:', response);
                if (response.success && response.data && response.data.success) {
                    console.log('[RFQ] Redirigiendo al carrito:', rfqManagerL10n.cartUrl);
                    window.location.href = rfqManagerL10n.cartUrl;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : (response.message || 'Error inesperado');
                    console.error('[RFQ] Error en respuesta:', msg);
                    showToast(msg, true);
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error('[RFQ] Error AJAX repetir:', xhr);
                var msg = 'Error inesperado';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                showToast(msg, true);
                $btn.prop('disabled', false);
            }
        });
    });

    // Handler para botón Pago Pendiente
    $(document).on('click', '.rfq-pending-payment-btn', function(e) {
        e.preventDefault();
        console.log('[RFQ-PAGO] Click en botón pago pendiente');
        
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        
        console.log('[RFQ-PAGO] Order ID:', orderId);
        
        if (!orderId) {
            console.error('[RFQ-PAGO] ID de orden inválido');
            showToast('Error: ID de orden inválido.', true);
            return;
        }
        
        // Verificar que rfqManagerL10n esté disponible
        if (typeof rfqManagerL10n === 'undefined') {
            console.error('[RFQ-PAGO] rfqManagerL10n no disponible');
            showToast('Error: No se pudo verificar la seguridad. Recargue la página.', true);
            return;
        }
        
        // Usar el nonce disponible (puede ser 'nonce' o 'solicitudStatusNonce')
        var nonceToUse = rfqManagerL10n.solicitudStatusNonce || rfqManagerL10n.nonce;
        if (!nonceToUse) {
            console.error('[RFQ-PAGO] Ningún nonce disponible en rfqManagerL10n:', rfqManagerL10n);
            showToast('Error: No se pudo verificar la seguridad. Recargue la página.', true);
            return;
        }
        
        console.log('[RFQ-PAGO] Usando nonce:', nonceToUse);
        
        // Deshabilitar botón y mostrar loading
        $btn.prop('disabled', true);
        var originalText = $btn.html();
        $btn.html('<span class="rfq-payment-icon">⏳</span> Procesando...');
        
        console.log('[RFQ-PAGO] Obteniendo URL de pago para orden:', orderId);
        
        $.ajax({
            url: rfqManagerL10n.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rfq_get_payment_url',
                order_id: orderId,
                nonce: nonceToUse
            },
            success: function(response) {
                console.log('[RFQ-PAGO] Respuesta AJAX:', response);
                
                if (response.success && response.data && response.data.payment_url) {
                    console.log('[RFQ-PAGO] Redirigiendo a URL de pago:', response.data.payment_url);
                    window.location.href = response.data.payment_url;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Error obteniendo URL de pago';
                    console.error('[RFQ-PAGO] Error:', msg);
                    showToast(msg, true);
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            },
            error: function(xhr) {
                console.error('[RFQ-PAGO] Error AJAX:', xhr);
                var msg = 'Error de conexión al obtener URL de pago';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    msg = xhr.responseJSON.data;
                }
                showToast(msg, true);
                $btn.prop('disabled', false);
                $btn.html(originalText);
            }
        });
    });
    
    // =============================
    // RFQ CHECKOUT PROTECTION
    // =============================
    
    // Protección adicional del lado cliente para order-pay
    if (window.location.href.includes('order-pay')) {
        $(document).ready(function() {
            // Bloquear intentos de modificación manual
            $('.product-quantity, .quantity input, .qty').prop('readonly', true);
            $('.remove, .product-remove').remove();
            
            // Observer para cambios dinámicos
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        $(mutation.addedNodes).find('.product-quantity, .quantity input, .qty').prop('readonly', true);
                        $(mutation.addedNodes).find('.remove, .product-remove').remove();
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            console.log('[RFQ-PROTECCION] Protección client-side activada para order-pay');
        });
    }
    
    // Prevenir manipulación de formularios de pago RFQ
    $(document).on('submit', 'form.woocommerce-checkout', function(e) {
        const form = $(this);
        const orderId = form.find('input[name="order_id"]').val();
        
        if (orderId && window.rfqProtectedOrders && window.rfqProtectedOrders.includes(orderId)) {
            console.log('[RFQ-PROTECCION] Validando envío de formulario para orden protegida:', orderId);
            
            // Aquí podrías agregar validaciones adicionales client-side si es necesario
            // Por ejemplo, verificar que ciertos campos no hayan sido modificados
        }
    });
});