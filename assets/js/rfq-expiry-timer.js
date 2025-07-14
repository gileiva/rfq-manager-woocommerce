(function() {
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
                return;
            }
            el.innerHTML = renderCountdownHTML(hours, minutes, seconds);
        }
        tick();
        var timer = setInterval(tick, 1000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var countdowns = document.querySelectorAll('.rfq-expiry-countdown');
        countdowns.forEach(function(el) {
            var expiryStr = el.getAttribute('data-expiry');
            if (!expiryStr) return;
            
            var expiryDate = new Date(expiryStr);
            if (isNaN(expiryDate.getTime())) return;
            
            updateCountdown(el, expiryDate);
        });
    });
})();
