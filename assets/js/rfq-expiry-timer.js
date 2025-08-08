(function() {
    let hasExpired = false;
    let redirectTimer = null;
    
    function pad(n) {
        return n < 10 ? '0' + n : n;
    }

    function renderCountdownHTML(hours, minutes, seconds) {
        return `
  <div class="rfq-countdown-block">
    <span class="rfq-countdown-value">${pad(hours)}</span>
    <span class="rfq-countdown-label">Hrs</span>
  </div>
  <span class="rfq-countdown-separator">:</span>
  <div class="rfq-countdown-block">
    <span class="rfq-countdown-value">${pad(minutes)}</span>
    <span class="rfq-countdown-label">Min</span>
  </div>
  <span class="rfq-countdown-separator">:</span>
  <div class="rfq-countdown-block">
    <span class="rfq-countdown-value">${pad(seconds)}</span>
    <span class="rfq-countdown-label">Sec</span>
  </div>
`;
    }

    function renderExpiredHTML() {
        // Show 00:00:00 with same layout, but visually indicate expired
        return `
  <div class="rfq-countdown-block rfq-expired">
    <span class="rfq-countdown-value">00</span>
    <span class="rfq-countdown-label">Hrs</span>
  </div>
  <span class="rfq-countdown-separator">:</span>
  <div class="rfq-countdown-block rfq-expired">
    <span class="rfq-countdown-value">00</span>
    <span class="rfq-countdown-label">Min</span>
  </div>
  <span class="rfq-countdown-separator">:</span>
  <div class="rfq-countdown-block rfq-expired">
    <span class="rfq-countdown-value">00</span>
    <span class="rfq-countdown-label">Sec</span>
  </div>
`;
    }

    function showExpirationNotice() {
        // Crear y mostrar mensaje de expiración
        const notice = document.createElement('div');
        notice.className = 'rfq-expiry-notice';
        notice.innerHTML = `
            <div class="rfq-notice-content">
                <strong>⏰ Solicitud Expirada</strong><br>
                La solicitud se venció y ya no se pueden enviar cotizaciones. Serás redirigido a la lista de solicitudes.
            </div>
        `;
        notice.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #d63638;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 9999;
            max-width: 300px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
            line-height: 1.4;
        `;
        
        document.body.appendChild(notice);
        
        // Deshabilitar formularios de cotización
        disableCotizationForms();
        
        // Programar redirección después de 3 segundos
        redirectTimer = setTimeout(() => {
            window.location.href = '/lista-solicitudes/';
        }, 3000);
    }

    function disableCotizationForms() {
        // Buscar y deshabilitar formularios de cotización
        const cotizationForms = document.querySelectorAll('form[action*="cotizacion"], .cotizacion-form, #cotizacion-form');
        cotizationForms.forEach(form => {
            form.style.opacity = '0.5';
            form.style.pointerEvents = 'none';
            
            // Deshabilitar todos los inputs y buttons
            const inputs = form.querySelectorAll('input, textarea, button, select');
            inputs.forEach(input => {
                input.disabled = true;
            });
        });

        // También buscar botones de envío por clase común
        const submitButtons = document.querySelectorAll('[data-rfq-submit], .rfq-submit-cotizacion, input[name="submit_cotizacion"]');
        submitButtons.forEach(button => {
            button.disabled = true;
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
        });
    }

    function updateCountdown(el, expiryDate) {
        function tick() {
            var now = new Date();
            var diff = Math.floor((expiryDate - now) / 1000);
            var hours = Math.floor(diff / 3600);
            var minutes = Math.floor((diff % 3600) / 60);
            var seconds = diff % 60;
            
            if (diff <= 0) {
                el.innerHTML = renderExpiredHTML();
                clearInterval(timer);
                
                // Ejecutar acciones de expiración solo una vez
                if (!hasExpired) {
                    hasExpired = true;
                    showExpirationNotice();
                }
                return;
            }
            el.innerHTML = renderCountdownHTML(hours, minutes, seconds);
        }
        tick();
        var timer = setInterval(tick, 1000);
    }

    function checkInitialExpiration() {
        // Verificar si la página se carga con una solicitud ya expirada
        const countdowns = document.querySelectorAll('.rfq-expiry-countdown');
        countdowns.forEach(function(el) {
            const expiryStr = el.getAttribute('data-expiry');
            if (!expiryStr) return;
            
            const expiryDate = new Date(expiryStr);
            if (isNaN(expiryDate.getTime())) return;
            
            const now = new Date();
            const diff = Math.floor((expiryDate - now) / 1000);
            
            if (diff <= 0 && !hasExpired) {
                hasExpired = true;
                el.innerHTML = renderExpiredHTML();
                showExpirationNotice();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si ya está expirado al cargar
        checkInitialExpiration();
        
        // Inicializar contadores
        var countdowns = document.querySelectorAll('.rfq-expiry-countdown');
        countdowns.forEach(function(el) {
            var expiryStr = el.getAttribute('data-expiry');
            if (!expiryStr) return;
            
            var expiryDate = new Date(expiryStr);
            if (isNaN(expiryDate.getTime())) return;
            
            updateCountdown(el, expiryDate);
        });

        // Interceptar intentos de envío de formularios en solicitudes expiradas
        document.addEventListener('submit', function(e) {
            if (hasExpired) {
                e.preventDefault();
                e.stopPropagation();
                alert('Esta solicitud ha expirado y no puede recibir más cotizaciones.');
                return false;
            }
        });
    });
})();
