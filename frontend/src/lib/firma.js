/**
 * Recuadro para dibujar la firma con el mouse, el dedo o un lápiz. La firma
 * queda en un PNG transparente con trazos de un solo color oscuro (el color
 * `--tinta` del recuadro), que es lo que la API acepta como firma.
 */
export function padDeFirma(lienzo) {
  const escala = 2; // más resolución que el tamaño en pantalla, para que se vea nítida en el PDF
  const ctx = lienzo.getContext('2d');
  let trazos = 0;
  let anterior = null;

  const ajustar = () => {
    const { width, height } = lienzo.getBoundingClientRect();
    lienzo.width = Math.round(width * escala);
    lienzo.height = Math.round(height * escala);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = 2.4 * escala;
    ctx.strokeStyle = getComputedStyle(lienzo).getPropertyValue('--tinta').trim();
    trazos = 0;
  };

  const punto = (e) => {
    const r = lienzo.getBoundingClientRect();
    return [(e.clientX - r.left) * escala, (e.clientY - r.top) * escala];
  };

  lienzo.addEventListener('pointerdown', (e) => {
    lienzo.setPointerCapture(e.pointerId);
    anterior = punto(e);
    ctx.beginPath();
    ctx.arc(anterior[0], anterior[1], ctx.lineWidth / 2, 0, Math.PI * 2);
    ctx.fillStyle = ctx.strokeStyle;
    ctx.fill();
    trazos++;
  });
  lienzo.addEventListener('pointermove', (e) => {
    if (!anterior) return;
    const actual = punto(e);
    ctx.beginPath();
    ctx.moveTo(...anterior);
    ctx.lineTo(...actual);
    ctx.stroke();
    anterior = actual;
  });
  const soltar = () => (anterior = null);
  lienzo.addEventListener('pointerup', soltar);
  lienzo.addEventListener('pointercancel', soltar);

  ajustar();

  return {
    vacia: () => trazos === 0,
    limpiar: () => {
      ctx.clearRect(0, 0, lienzo.width, lienzo.height);
      trazos = 0;
    },
    png: () => new Promise((resolve) => lienzo.toBlob(resolve, 'image/png')),
  };
}
