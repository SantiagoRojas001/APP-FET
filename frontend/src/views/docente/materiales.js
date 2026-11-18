import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { archivoAcciones, wireArchivos } from '../../lib/archivos.js';
import { formatDate } from '../../lib/format.js';
import { confirmInline, emptyState, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/** Archivos que el docente comparte con los estudiantes que asesora. */
export default async function materialesView({ root, refresh }) {
  const materiales = await api.get('/docente/materiales');

  mount(
    root,
    html`${pageHeader('Materiales')}
      <section class="panel">
        <form id="matForm" class="form-grid" novalidate>
          <div class="field"><label for="titulo">Título</label><input class="input" id="titulo" name="titulo" maxlength="150" /></div>
          <div class="field"><label for="archivo">Archivo PDF (máx. 10 MB)</label><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" /></div>
          <div class="actions"><button class="btn" type="submit">${icon('upload')}Compartir</button></div>
        </form>
        <div id="matError"></div>
      </section>
      <section class="panel">
        ${materiales.length
          ? html`<div class="lines">
              ${materiales.map(
                (m) => html`<div class="line">
                  <div><b>${m.titulo}</b><span>${formatDate(m.creado_en)}</span></div>
                  <div class="actions">${archivoAcciones(m.url_descarga, `${m.titulo}.pdf`)}<button class="btn danger sm" data-borrar="${m.id}">Quitar</button></div>
                </div>`,
              )}
            </div>`
          : emptyState('file-text', 'Aún no compartes materiales')}
      </section>`,
  );

  wireArchivos(root);

  const form = root.querySelector('#matForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/docente/materiales', new FormData(form));
        toast('Material compartido con tu grupo.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#matError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  root.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-borrar]');
    if (!boton) return;
    confirmInline(boton, 'quitar', (si) =>
      withBusy(si, async () => {
        await api.delete(`/docente/materiales/${boton.dataset.borrar}`);
        toast('Material quitado.');
        refresh();
      }),
    );
  });
}
