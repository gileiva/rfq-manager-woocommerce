jQuery(document).ready(function($) {
    'use strict';

    // Función para formatear números como moneda
    function formatMoney(amount) {
        return parseFloat(amount).toFixed(2);
    }

    // Función para calcular el subtotal
    function calculateSubtotal(row) {
        const price = parseFloat(row.find('.rfq-precio-input').val()) || 0;
        const qty = parseInt(row.find('td:nth-child(2)').text()) || 0;
        const iva = parseFloat(row.find('.rfq-iva-select').val()) || 0;
        
        const subtotalSinIva = price * qty;
        const ivaAmount = subtotalSinIva * (iva / 100);
        const subtotal = subtotalSinIva + ivaAmount;
        
        return {
            subtotalSinIva: subtotalSinIva,
            ivaAmount: ivaAmount,
            subtotal: subtotal
        };
    }

    // Función para actualizar el total
    function updateTotal() {
        let total = 0;
        $('.rfq-cotizar-table tbody tr').each(function() {
            const subtotal = calculateSubtotal($(this)).subtotal;
            total += subtotal;
        });
        
        $('.rfq-total-amount').text(formatMoney(total));
    }

    // Función para actualizar todos los subtotales al cargar la página
    function updateAllSubtotals() {
        $('.rfq-cotizar-table tbody tr').each(function() {
            const $row = $(this);
            const subtotal = calculateSubtotal($row);
            $row.find('.rfq-subtotal').text(formatMoney(subtotal.subtotal));
        });
        updateTotal();
    }

    // Función para validar el precio
    function validatePrice(input) {
        const $input = $(input);
        const $row = $input.closest('tr');
        const originalPrice = parseFloat($input.data('original-price')) || 0;
        const newPrice = parseFloat($input.val()) || 0;
        
        // Remover mensajes de error anteriores
        $row.find('.error-message').remove();
        $row.removeClass('error-row');
        $input.removeClass('error');
        
        if (originalPrice > 0 && newPrice > originalPrice) {
            // Agregar clases de error
            $row.addClass('error-row');
            $input.addClass('error');
            
            // Mostrar mensaje de error con el precio original formateado
            const formattedPrice = formatMoney(originalPrice);
            const errorMessage = $('<div class="error-message"></div>')
                .text(rfqCotizarL10n.priceTooHigh.replace('%s', formattedPrice))
                .insertAfter($input);
            
            return false;
        }
        
        return true;
    }

    // Función para verificar si hay errores en el formulario
    function hasFormErrors() {
        let hasErrors = false;
        $('.rfq-precio-input').each(function() {
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
        const hasEmptyFields = $('.rfq-precio-input').filter(function() {
            return !$(this).val();
        }).length > 0;

        if (hasErrors || hasEmptyFields) {
            $submitBtn.prop('disabled', true).addClass('disabled');
        } else {
            $submitBtn.prop('disabled', false).removeClass('disabled');
        }
    }

    // Eventos para los inputs de precio
    $('.rfq-precio-input').on('input', function() {
        const $row = $(this).closest('tr');
        const subtotal = calculateSubtotal($row);
        
        $row.find('.rfq-subtotal').text(formatMoney(subtotal.subtotal));
        updateTotal();
        
        validatePrice(this);
        updateSubmitButton();
    });

    // Eventos para los selects de IVA
    $('.rfq-iva-select').on('change', function() {
        const $row = $(this).closest('tr');
        const subtotal = calculateSubtotal($row);
        
        $row.find('.rfq-subtotal').text(formatMoney(subtotal.subtotal));
        updateTotal();
    });

    // Validación del formulario
    $('#rfq-cotizar-form').on('submit', function(e) {
        e.preventDefault();
        
        // Validar precios
        let hasEmptyPrices = false;
        let hasInvalidPrices = false;
        let originalPrice = null;
        
        $('.rfq-precio-input').each(function() {
            const price = parseFloat($(this).val());
            const originalPriceValue = parseFloat($(this).data('original-price'));
            
            if (!price) {
                hasEmptyPrices = true;
                return false;
            }
            
            if (price <= 0) {
                hasInvalidPrices = true;
                return false;
            }
            
            if (originalPriceValue && price > originalPriceValue) {
                originalPrice = originalPriceValue;
                return false;
            }
        });

        // Validar checkbox de stock
        if (!$('#stock_confirmation').is(':checked')) {
            alert(rfqCotizarL10n.stockConfirmation);
            return false;
        }

        if (hasEmptyPrices) {
            alert(rfqCotizarL10n.completePrices);
            return false;
        }

        if (hasInvalidPrices) {
            alert(rfqCotizarL10n.invalidPrice);
            return false;
        }

        if (originalPrice) {
            alert(rfqCotizarL10n.priceTooHigh.replace('%s', originalPrice));
            return false;
        }

        // Si todo está bien, enviar el formulario
        this.submit();
    });

    // Eliminar el tooltip del campo de precio unitario
    $('.rfq-precio-input').removeAttr('title');

    // Al cargar la página, actualizar todos los subtotales y el total
    updateAllSubtotals();
    updateSubmitButton();
}); 