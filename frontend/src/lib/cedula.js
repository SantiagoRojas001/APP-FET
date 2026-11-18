import { html, mount } from './html.js';

/**
 * Muestra la foto del frente de la cédula para que el estudiante marque con
 * un recuadro dónde está su firma. Resuelve con ese recorte en PNG, o null
 * si cancela.
 */
export function marcarFirma(contenedor, archivo) {
  const url = URL.createObjectURL(archivo);
  mount(
    contenedor,
    html`<p class="hv-sub">Marca tu firma en la foto</p>
      <div class="recorte-foto"><img src="${url}" alt="Frente de tu cédula" draggable="false" /><div class="recorte-marca" hidden></div></div>
      <div class="actions">
        <button type="button" class="btn sm" data-ok disabled>Usar esta foto</button>
        <button type="button" class="btn sec sm" data-no>Cancelar</button>
      </div>`,
  );
  contenedor.hidden = false;

  const foto = contenedor.querySelector('img');
  const zona = contenedor.querySelector('.recorte-foto');
  const marca = contenedor.querySelector('.recorte-marca');
  const ok = contenedor.querySelector('[data-ok]');
  let inicio = null;
  let recorte = null;

  const punto = (e) => {
    const r = zona.getBoundingClientRect();
    return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))];
  };

  zona.addEventListener('pointerdown', (e) => {
    zona.setPointerCapture(e.pointerId);
    inicio = punto(e);
  });
  zona.addEventListener('pointermove', (e) => {
    if (!inicio) return;
    const [x, y] = punto(e);
    recorte = { x: Math.min(x, inicio[0]), y: Math.min(y, inicio[1]), ancho: Math.abs(x - inicio[0]), alto: Math.abs(y - inicio[1]) };
    marca.hidden = false;
    Object.assign(marca.style, {
      left: `${recorte.x * 100}%`,
      top: `${recorte.y * 100}%`,
      width: `${recorte.ancho * 100}%`,
      height: `${recorte.alto * 100}%`,
    });
    ok.disabled = recorte.ancho < 0.05 || recorte.alto < 0.02;
  });
  zona.addEventListener('pointerup', () => (inicio = null));

  const recortar = () =>
    new Promise((listo) => {
      const lienzo = document.createElement('canvas');
      lienzo.width = Math.min(1200, Math.round(recorte.ancho * foto.naturalWidth));
      lienzo.height = Math.round((lienzo.width * recorte.alto * foto.naturalHeight) / (recorte.ancho * foto.naturalWidth));
      lienzo
        .getContext('2d')
        .drawImage(
          foto,
          recorte.x * foto.naturalWidth,
          recorte.y * foto.naturalHeight,
          recorte.ancho * foto.naturalWidth,
          recorte.alto * foto.naturalHeight,
          0,
          0,
          lienzo.width,
          lienzo.height,
        );
      lienzo.toBlob(listo, 'image/png');
    });

  return new Promise((resolve) => {
    const cerrar = (valor) => {
      URL.revokeObjectURL(url);
      contenedor.hidden = true;
      mount(contenedor, '');
      resolve(valor);
    };
    ok.addEventListener('click', async () => cerrar(await recortar()));
    contenedor.querySelector('[data-no]').addEventListener('click', () => cerrar(null));
  });
}
