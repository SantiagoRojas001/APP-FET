import { api } from '../api/client.js';
import { html, mount } from './html.js';
import { icon, toast, withBusy } from './ui.js';

/**
 * Botones «Ver» y «Descargar» de un archivo protegido (documentos de
 * seguimiento, soportes de horas, convenios). «Ver» lo abre en un visor
 * dentro de la app. Conecta los botones con `wireArchivos`.
 */
export const archivoAcciones = (url, nombre) =>
  html`<span class="file-actions">
    <button type="button" class="link" data-ver="${url}" data-nombre="${nombre}">${icon('eye')}Ver</button>
    <button type="button" class="link" data-descargar="${url}" data-nombre="${nombre}">${icon('download')}Descargar</button>
  </span>`;

export function wireArchivos(root) {
  if (root.dataset.archivos) return; // una sola vez por vista, aunque la llamen varias tablas
  root.dataset.archivos = '';
  root.addEventListener('click', (e) => {
    const ver = e.target.closest('[data-ver]');
    if (ver) return withBusy(ver, () => abrirVisor(ver.dataset.ver, ver.dataset.nombre));
    const bajar = e.target.closest('[data-descargar]');
    if (bajar) withBusy(bajar, () => api.download(bajar.dataset.descargar, bajar.dataset.nombre).catch((err) => toast(err.message)));
  });
}

/** Visor modal: PDF en un marco e imágenes a tamaño de la pantalla. */
async function abrirVisor(url, nombre) {
  let blob;
  try {
    blob = await api.blob(url);
  } catch (error) {
    return toast(error.message);
  }
  const src = URL.createObjectURL(blob);
  const dialog = document.createElement('dialog');
  dialog.className = 'visor';
  mount(
    dialog,
    html`<header class="visor-h">
        <b>${nombre}</b>
        <div class="actions">
          <a class="btn sec sm" href="${src}" download="${nombre}">${icon('download')}Descargar</a>
          <button type="button" class="btn sec sm" data-cerrar>${icon('x')}Cerrar</button>
        </div>
      </header>
      <div class="visor-body">
        ${blob.type.startsWith('image/') ? html`<img src="${src}" alt="${nombre}" />` : html`<iframe src="${src}" title="${nombre}"></iframe>`}
      </div>`,
  );
  dialog.addEventListener('close', () => {
    URL.revokeObjectURL(src);
    dialog.remove();
  });
  dialog.querySelector('[data-cerrar]').addEventListener('click', () => dialog.close());
  document.body.append(dialog);
  dialog.showModal();
}
