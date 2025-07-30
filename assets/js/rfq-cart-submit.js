(function($){
    $(document).on('click', '.rfq-cart-submit-btn', function(e){
        e.preventDefault();
        // Redirigir al checkout de WooCommerce
        window.location.href = rfq_cart_submit.checkout_url;
    });
})(jQuery);
