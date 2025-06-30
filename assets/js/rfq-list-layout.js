// Ajusta dinámicamente cuántos productos se muestran en la galería de cada solicitud
function ajustarProductosVisibles() {
  document.querySelectorAll('.rfq-product-gallery').forEach(galeria => {
    const items = galeria.querySelectorAll('.rfq-product-item');
    const totalItems = items.length;
    const contenedorAncho = galeria.offsetWidth;
    const itemAncho = 90 + 40; // 90px + 40px gap
    const maxVisibles = Math.floor((contenedorAncho + 40) / itemAncho);

    // Mostrar los visibles, ocultar los demás
    items.forEach((item, index) => {
      item.style.display = index < maxVisibles ? 'block' : 'none';
    });

    // Quitar cualquier +N previo
    galeria.querySelectorAll('.rfq-product-overflow').forEach(el => el.remove());

    // Insertar +N si corresponde
    if (totalItems > maxVisibles) {
      const restantes = totalItems - maxVisibles;
      const plusN = document.createElement('li');
      plusN.classList.add('rfq-product-overflow');
      const h5 = document.createElement('h5');
      h5.innerText = `+${restantes}`;
      plusN.appendChild(h5);
      galeria.appendChild(plusN);
    }
  });
}

window.addEventListener('load', ajustarProductosVisibles);
window.addEventListener('resize', ajustarProductosVisibles);

console.log('[RFQ] JS activo en proveedor/usuario');
