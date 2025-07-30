jQuery(document).ready(function($) {

    'use strict';

    // Formatea como moneda CON símbolo
    function formatMoney(amount) {
        const formatted = Number(amount).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const symbol = (typeof rfqCotizarL10n !== 'undefined' && rfqCotizarL10n.currencySymbol) ? rfqCotizarL10n.currencySymbol : '€';
        return formatted + ' ' + symbol;
    }

    // Solo ejecutar si existe el bloque de cotización
    if ($('.rfq-productos-wrapper').length && $('.rfq-input-precio').length) {

        // Calcula el subtotal de una fila
        function calcularSubtotalFila($row) {
            const precio = parseFloat($row.find('.rfq-input-precio').val()) || 0;
            const cantidad = parseFloat($row.closest('.rfq-producto-row').find('.rfq-producto-col-cantidad').text().replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
            const iva = parseFloat($row.find('.rfq-input-iva').val()) || 0;
            const subtotalBase = precio * cantidad;
            const ivaMonto = subtotalBase * (iva / 100);
            const subtotal = subtotalBase + ivaMonto;
            return { subtotal, subtotalBase, ivaMonto };
        }

        // Actualiza el subtotal de una fila
        function actualizarSubtotalFila($row) {
            const { subtotal } = calcularSubtotalFila($row);
            $row.find('.rfq-output-subtotal').html(formatMoney(subtotal));
        }

        // Actualiza el total general
        function actualizarTotalGeneral() {
            let total = 0;
            $('.rfq-output-subtotal').each(function() {
                let val = $(this).text();
                // Extraer solo números y comas/puntos decimales, quitar símbolo de moneda
                val = (val + '').replace(/[^\d.,]/g, '').replace(',', '.');
                total += parseFloat(val) || 0;
            });
            $('.rfq-total-amount').html(formatMoney(total));
        }

        // Eventos: recalcular al cambiar precio o IVA y validar
        $(document).on('input change', '.rfq-input-precio, .rfq-input-iva', function() {
            const $row = $(this).closest('.rfq-producto-row');
            actualizarSubtotalFila($row);
            actualizarTotalGeneral();
            validatePrice($(this));
            updateSubmitButton();
        });

        // Inicializar subtotales y total al cargar
        $('.rfq-producto-row').each(function() {
            actualizarSubtotalFila($(this));
        });
        actualizarTotalGeneral();
        updateSubmitButton();
    }

    // Función para validar el precio
    function validatePrice(input) {
        const $input = $(input);
        // Buscar la fila de producto (puede ser div, no tr)
        const $row = $input.closest('.rfq-producto-row');
        const originalPrice = parseFloat($input.data('original-price')) || 0;
        const newPrice = parseFloat($input.val()) || 0;

        // Remover mensajes de error anteriores
        $row.find('.error-message').remove();
        $row.removeClass('error-row');
        $input.removeClass('error');
        // Eliminar fila de error si existe
        $row.next('.rfq-producto-error-row').remove();

        if (originalPrice > 0 && newPrice > originalPrice) {
            // Agregar clases de error
            $row.addClass('error-row');
            $input.addClass('error');

            // Mostrar mensaje de error en una fila extra debajo del producto
            const formattedPrice = formatMoney(originalPrice);
            const errorHtml = '<div class="rfq-producto-error-row"><div class="error-message error-price">' +
                rfqCotizarL10n.priceTooHigh.replace('%s', formattedPrice) + '</div></div>';
            $row.after(errorHtml);

            return false;
        }

        return true;
    }

    // Función para verificar si hay errores en el formulario
    function hasFormErrors() {
        let hasErrors = false;
        $('.rfq-input-precio').each(function() {
            if (!validatePrice(this)) {
                hasErrors = true;
            }
        });
        return hasErrors;
    }

    // Función para actualizar el estado del botón de envío
    function updateSubmitButton() {
        const $submitBtn = $('.rfq-submit-btn');
        const hasErrors = hasFormErrors();
        const hasEmptyFields = $('.rfq-input-precio').filter(function() {
            return !$(this).val();
        }).length > 0;
        const isStockChecked = $('#rfq-stock-checkbox').is(':checked');

        if (hasErrors || hasEmptyFields || !isStockChecked) {
            $submitBtn.prop('disabled', true).addClass('disabled');
        } else {
            $submitBtn.prop('disabled', false).removeClass('disabled');
        }
    }

    // Validar al enviar el formulario
    $(document).on('submit', '#rfq-cotizar-form', function(e) {
        const hasErrors = hasFormErrors();
        const hasEmptyFields = $('.rfq-input-precio').filter(function() {
            return !$(this).val();
        }).length > 0;
        const isStockChecked = $('#rfq-stock-checkbox').is(':checked');
        if (hasErrors || hasEmptyFields || !isStockChecked) {
            e.preventDefault();
            updateSubmitButton();
            if (hasErrors) {
                alert(rfqCotizarL10n.priceTooHigh.replace('%s', ''));
            } else if (hasEmptyFields) {
                alert(rfqCotizarL10n.completePrices);
            } else if (!isStockChecked) {
                alert('Debes confirmar que tienes stock para todos los productos.');
            }
            return false;
        }
        // Si todo OK, permitir envío
        return true;
    });

    // Habilitar/deshabilitar el botón al cambiar el checkbox
    $(document).on('change', '#rfq-stock-checkbox', function() {
        updateSubmitButton();
    });

    // El resto de la lógica de validación y helpers puede ir aquí si es necesario
});