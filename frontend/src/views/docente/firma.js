import { api } from '../../api/client.js';
import { padDeFirma } from '../../lib/firma.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { hydrateFoto } from '../hoja-vida-detalle.js';

/** Nombre, documento y firma con los que el docente firma los formatos. */
export default async function firmaDocenteView({ root, refresh }) {
  const firmante = await api.get('/docente/firma');

  mount(
    root,
    html`${pageHeader('Mi firma')}
      <section class="panel">
        ${firmante.url_firma ? html`<div class="firma-actual"><img class="firma-img" data-foto="${firmante.url_firma}" alt="Tu firma" /></div>` : ''}
        <form id="firmaForm" class="form-grid" novalidate>
          <div class="field"><label for="nombre">Nombre completo</label><input class="input" id="nombre" name="nombre" value="${firmante.nombre ?? ''}" /></div>
          <div class="field"><label for="documento">Documento de identidad</label><input class="input" id="documento" name="documento" value="${firmante.documento ?? ''}" /></div>
          <div class="field wide">
            <label for="firmaPad">Firma</label>
            <canvas class="firma-pad" id="firmaPad" aria-label="Recuadro para dibujar tu firma"></canvas>
            <input type="hidden" name="firma" />
          </div>
          <div class="actions"><button class="btn" type="submit">Guardar</button><button class="btn sec" type="button" id="borrar">Borrar trazo</button></div>
          <div class="wide" id="firmaError"></div>
        </form>
      </section>`,
  );
  hydrateFoto(root);

  const form = root.querySelector('#firmaForm');
  const pad = padDeFirma(root.querySelector('#firmaPad'));
  root.querySelector('#borrar').addEventListener('click', () => pad.limpiar());

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (pad.vacia() && !firmante.url_firma) return showFieldErrors(form, { firma: ['Dibuja tu firma.'] });
    withBusy(form.querySelector('[type=submit]'), async () => {
      const data = new FormData();
      data.append('nombre', form.nombre.value.trim());
      data.append('documento', form.documento.value.trim());
      if (!pad.vacia()) data.append('firma', await pad.png(), 'firma.png');
      try {
        await api.post('/docente/firma', data);
        toast('Firma guardada.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#firmaError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
