/**
 * Integración de pasarela RFQ con bloques de checkout
 *
 * @package GiVendor\GiPlugin
 * @since 0.1.0
 */

/* global wc */
(function() {
    console.log('RFQ Gateway: Iniciando registro del método de pago');
    
    // Esperar a que el DOM esté completamente cargado
    document.addEventListener('DOMContentLoaded', function() {
        // Intentar registrar inmediatamente
        tryRegisterPaymentMethod();
        
        // Si falla, intentarlo nuevamente después de un breve retraso
        // para dar tiempo a que los bloques de WooCommerce se inicialicen
        setTimeout(tryRegisterPaymentMethod, 1000);
        
        // Agregar listener para el botón de envío de pedido
        setTimeout(addCheckoutListeners, 2000);

        // Agregar patch para la API REST
        patchWcStoreApiPayment();
    });
    
    /**
     * Parche para interceptar y corregir solicitudes a la API REST de WooCommerce
     */
    function patchWcStoreApiPayment() {
        // Verificar si fetch está disponible
        if (typeof window.fetch !== 'function') {
            console.warn('RFQ Gateway: fetch no está disponible, no se puede aplicar parche a la API');
            return;
        }

        // Almacenar la función fetch original
        const originalFetch = window.fetch;

        // Sobreescribir la función fetch
        window.fetch = function(url, options) {
            // Verificar si la solicitud es al endpoint de checkout de la API de WooCommerce
            if (url && typeof url === 'string' && 
                url.includes('/wp-json/wc/store/v1/checkout') && 
                options && options.method === 'POST') {
                
                try {
                    // Parsear el cuerpo de la solicitud si es una cadena
                    if (options.body && typeof options.body === 'string') {
                        const body = JSON.parse(options.body);
                        
                        // Verificar si no hay método de pago en la solicitud original
                        if (!body.payment_method) {
                            console.log('RFQ Gateway: Corrigiendo solicitud sin método de pago');
                            
                            // Agregar el método de pago rfq_gateway
                            body.payment_method = 'rfq_gateway';
                            
                            // Crear un nuevo objeto options con el cuerpo modificado
                            options = {
                                ...options,
                                body: JSON.stringify(body)
                            };
                            
                            console.log('RFQ Gateway: Solicitud corregida:', body);
                        }
                    }
                } catch (e) {
                    console.error('RFQ Gateway: Error al intentar modificar la solicitud:', e);
                }
            }
            
            // Llamar a la función fetch original con los parámetros posiblemente modificados
            return originalFetch.apply(this, [url, options]);
        };
        
        console.log('RFQ Gateway: Parche aplicado a fetch para solucionar problemas de API');
    }
    
    /**
     * Agrega listeners al proceso de checkout para depurar el envío de datos
     */
    function addCheckoutListeners() {
        // Monitorear los eventos de checkout
        document.body.addEventListener('click', function(e) {
            // Verificar si es un botón de pedido
            if (e.target && (
                e.target.classList.contains('wc-block-components-checkout-place-order-button') || 
                e.target.id === 'place_order' ||
                (e.target.tagName === 'BUTTON' && e.target.type === 'submit' && 
                 (e.target.closest('form.woocommerce-checkout') || 
                  e.target.closest('.wc-block-checkout')))
            )) {
                console.log('RFQ Gateway: Botón de pedido clickeado');
                
                // Verificar el método de pago seleccionado
                var selectedMethod = '';
                
                // Para checkout clásico
                var radioInputs = document.querySelectorAll('input[name="payment_method"]');
                radioInputs.forEach(function(input) {
                    if (input.checked) {
                        selectedMethod = input.value;
                        console.log('RFQ Gateway: Método seleccionado (clásico):', selectedMethod);
                    }
                });
                
                // Para checkout de bloques
                if (!selectedMethod) {
                    // Intentar acceder al state de Checkout mediante la API de Store
                    try {
                        if (window.wc && window.wc.store && window.wc.store.getState) {
                            var state = window.wc.store.getState();
                            if (state && state.checkout && state.checkout.paymentMethodData) {
                                selectedMethod = state.checkout.paymentMethodData.paymentMethod;
                                console.log('RFQ Gateway: Método seleccionado (bloques):', selectedMethod);
                            }
                        }
                    } catch (err) {
                        console.error('RFQ Gateway: Error al acceder al state:', err);
                    }
                }
                
                // Inyectar método de pago si no está seleccionado
                if (!selectedMethod) {
                    console.log('RFQ Gateway: Inyectando método de pago rfq_gateway en el evento');
                    
                    // Intentar modificar el estado antes del envío
                    if (window.wc && window.wc.store && window.wc.store.dispatch) {
                        try {
                            window.wc.store.dispatch({
                                type: 'SET_PAYMENT_METHOD',
                                paymentMethod: 'rfq_gateway',
                                paymentMethodData: {}
                            });
                            console.log('RFQ Gateway: Método de pago inyectado en el store');
                        } catch (e) {
                            console.error('RFQ Gateway: Error al inyectar método de pago:', e);
                        }
                    }
                }
                
                // Agregar el método al localStorage para debug
                try {
                    localStorage.setItem('rfq_selected_payment_method', selectedMethod || 'rfq_gateway');
                } catch (e) {}
            }
        }, true);
        
        // Monitorear errores AJAX
        if (window.jQuery) {
            jQuery(document).ajaxError(function(event, jqxhr, settings, thrownError) {
                console.log('RFQ Gateway: Error AJAX detectado:', {
                    url: settings.url,
                    status: jqxhr.status,
                    responseText: jqxhr.responseText
                });
            });
        }
        
        console.log('RFQ Gateway: Listeners de checkout agregados');
    }
    
    /**
     * Intenta registrar el método de pago
     */
    function tryRegisterPaymentMethod() {
        try {
            // Verificar si las dependencias necesarias están disponibles
            if (!window.wc) {
                console.warn('RFQ Gateway: objeto "wc" no disponible');
                return;
            }
            
            if (!window.wc.wcBlocksRegistry) {
                console.warn('RFQ Gateway: objeto "wc.wcBlocksRegistry" no disponible');
                return;
            }
            
            if (!window.wc.wcBlocksRegistry.registerPaymentMethod) {
                console.warn('RFQ Gateway: función "registerPaymentMethod" no disponible');
                return;
            }

            const registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
            const wpElement = window.wp?.element || false;
            
            if (!wpElement) {
                console.warn('RFQ Gateway: objeto "wp.element" no disponible');
                return;
            }
            
            const Fragment = wpElement.Fragment;
            const createElement = wpElement.createElement;
            const __ = window.wp?.i18n?.__ || function(text) { return text; };
            
            // Verificar si estamos en la página de checkout
            const isCheckout = document.body.classList.contains('woocommerce-checkout') || 
                               document.querySelector('.wc-block-checkout') !== null || 
                               document.querySelector('.wp-block-woocommerce-checkout') !== null;
            
            console.log('RFQ Gateway: ¿Estamos en checkout?', isCheckout ? 'Sí' : 'No');

            // Verificar datos de configuración disponibles
            const paymentData = window.wcSettings?.payment_data?.rfq_gateway || null;
            console.log('RFQ Gateway: Datos de pago disponibles', paymentData ? 'Sí' : 'No');
            
            if (paymentData) {
                console.log('RFQ Gateway: Datos de configuración', paymentData);
            }

            /**
             * Componente para la pasarela de pago RFQ sin usar JSX
             */
            function RFQPaymentMethodComponent(props) {
                return createElement(
                    Fragment, 
                    null,
                    props.description ? createElement('p', null, props.description) : null
                );
            }

            /**
             * Configuración del método de pago
             */
            const RFQPaymentMethodConfig = {
                name: 'rfq_gateway',
                label: paymentData?.title || __('Solicitud de cotización', 'rfq-manager-woocommerce'),
                content: createElement(RFQPaymentMethodComponent, {
                    description: paymentData?.description || __('Enviar solicitud de cotización sin pago inmediato. Un representante se pondrá en contacto con usted.', 'rfq-manager-woocommerce')
                }),
                edit: createElement(RFQPaymentMethodComponent, {
                    description: paymentData?.description || __('Enviar solicitud de cotización sin pago inmediato. Un representante se pondrá en contacto con usted.', 'rfq-manager-woocommerce')
                }),
                canMakePayment: function() { 
                    console.log('RFQ Gateway: Verificando si puede hacer pago');
                    return true; 
                },
                ariaLabel: __('Solicitud de cotización', 'rfq-manager-woocommerce'),
                supports: {
                    features: paymentData?.supports || ['products'],
                },
                
                // Agregar información de procesamiento explícita
                paymentMethodId: 'rfq_gateway',
                
                // Función que procesa el pago en el frontend
                // Este método es crítico para bloques de checkout
                billing: {
                    billingData: {},
                    requiredFields: [],
                },
                
                // Agregar método de procesamiento específico para bloques
                onSubmit: function(data) {
                    console.log('RFQ Gateway: onSubmit llamado con datos', data);
                    // Asegurar que el método de pago esté configurado
                    localStorage.setItem('rfq_last_submit_data', JSON.stringify({
                        timestamp: Date.now(),
                        data: data
                    }));
                    return { type: 'success' };
                },
                
                // Métodos para manejar datos de pago
                getData: function() {
                    console.log('RFQ Gateway: getData llamado');
                    return { 
                        paymentMethodId: 'rfq_gateway',
                        payment_method: 'rfq_gateway'
                    };
                }
            };

            // Registrar el método de pago en los bloques de WooCommerce
            registerPaymentMethod(RFQPaymentMethodConfig);
            console.log('RFQ Gateway: Método de pago registrado correctamente');
            
            // Parche adicional para asegurar la selección del método
            if (isCheckout) {
                // Monitorear cambios en el DOM para verificar cuando se carga el checkout
                const observer = new MutationObserver(function(mutations) {
                    const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
                    paymentMethodInputs.forEach(function(input) {
                        if (input.value === 'rfq_gateway') {
                            console.log('RFQ Gateway: Encontrado input de método de pago en DOM');
                            // Verificar si está seleccionado
                            if (!input.checked && paymentMethodInputs.length === 1) {
                                console.log('RFQ Gateway: Seleccionando el método automáticamente');
                                input.checked = true;
                                
                                // Disparar evento change para notificar a WooCommerce
                                const event = new Event('change', { bubbles: true });
                                input.dispatchEvent(event);
                            }
                        }
                    });
                });
                
                // Iniciar observación de cambios en el checkout
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        } catch (error) {
            console.error('Error al registrar el método de pago RFQ:', error);
        }
    }
})();