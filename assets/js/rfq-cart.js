
(function($){
    // Actualiza la cantidad en el input sin recargar el HTML
    function updateCartItem(cartKey, quantity, $input) {
        $input.prop('disabled', true);
        $.ajax({
            url: rfq_cart_data.ajax_url,
            type: 'POST',
            data: {
                action: 'rfq_cart_update_item',
                cart_key: cartKey,
                quantity: quantity,
                _ajax_nonce: rfq_cart_data.nonce
            },
            success: function(response) {
                if(response.success) {
                    $input.val(quantity);
                } else {
                    alert(response.data ? response.data : 'Error al actualizar el carrito');
                }
            },
            complete: function(){
                $input.prop('disabled', false);
            }
        });
    }

    // Elimina el producto y remueve el item del DOM
    function removeCartItem(cartKey, $item) {
        $item.find('.rfq-cart-remove-item').prop('disabled', true);
        $.ajax({
            url: rfq_cart_data.ajax_url,
            type: 'POST',
            data: {
                action: 'rfq_cart_remove_item',
                cart_key: cartKey,
                _ajax_nonce: rfq_cart_data.nonce
            },
            success: function(response) {
                if(response.success) {
                    $item.fadeOut(300, function(){ $(this).remove(); });
                } else {
                    alert(response.data ? response.data : 'Error al eliminar el producto');
                }
            }
        });
    }

    // Redirige al checkout
    $(document).on('click', '.rfq-cart-submit-btn', function(e){
        e.preventDefault();
        window.location.href = rfq_cart_data.checkout_url;
    });


    // Botón +
    $(document).on('click', '.rfq-cart-quantity-plus', function(){
        var $item = $(this).closest('.rfq-cart-item');
        var cartKey = $item.data('cart-key');
        var $input = $item.find('.rfq-cart-quantity-input');
        var quantity = parseInt($input.val(), 10) + 1;
        updateCartItem(cartKey, quantity, $input);
    });

    // Botón -
    $(document).on('click', '.rfq-cart-quantity-minus', function(){
        var $item = $(this).closest('.rfq-cart-item');
        var cartKey = $item.data('cart-key');
        var $input = $item.find('.rfq-cart-quantity-input');
        var quantity = Math.max(1, parseInt($input.val(), 10) - 1);
        updateCartItem(cartKey, quantity, $input);
    });

    // Input manual: blur o Enter
    $(document).on('change blur', '.rfq-cart-quantity-input', function(e){
        var $input = $(this);
        var $item = $input.closest('.rfq-cart-item');
        var cartKey = $item.data('cart-key');
        var val = parseInt($input.val(), 10);
        var quantity = isNaN(val) ? 1 : Math.max(1, val);
        $input.val(quantity);
        updateCartItem(cartKey, quantity, $input);
    });
    $(document).on('keydown', '.rfq-cart-quantity-input', function(e){
        if(e.key === 'Enter') {
            $(this).trigger('blur');
        }
    });

    // Botón eliminar
    $(document).on('click', '.rfq-cart-remove-item', function(){
        var $item = $(this).closest('.rfq-cart-item');
        var cartKey = $item.data('cart-key');
        if(confirm('¿Eliminar este producto del carrito?')) {
            removeCartItem(cartKey, $item);
        }
    });
})(jQuery);
