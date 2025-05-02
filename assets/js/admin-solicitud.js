/**
 * JavaScript para la vista administrativa de solicitudes
 *
 * @package    GiVendor\GiPlugin
 * @since      0.1.0
 */

jQuery(document).ready(function($) {
    // Inicializar datepicker para el campo de fecha de expiración
    $('#rfq-expiry-date').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        onSelect: function(dateText) {
            // Añadir la hora actual al seleccionar una fecha
            var today = new Date();
            var h = today.getHours();
            var m = today.getMinutes();
            var s = today.getSeconds();
            
            // Añadir ceros iniciales si es necesario
            h = (h < 10) ? '0' + h : h;
            m = (m < 10) ? '0' + m : m;
            s = (s < 10) ? '0' + s : s;
            
            $(this).val(dateText + ' ' + h + ':' + m + ':' + s);
        }
    });
    
    // Simular comportamiento de acordeón para las ofertas (preparado para futuro)
    $('.rfq-oferta-header').on('click', function() {
        $(this).next('.rfq-oferta-content').slideToggle();
    });
    
    // Ocultar los contenidos del acordeón inicialmente
    $('.rfq-oferta-content').hide();
});