import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { relativeDays } from '../../lib/format.js';
import { emptyState, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const ESTADOS = [
  ['', 'Todos'],
  ['ABIERTO', 'Abiertos'],
  ['EN_REVISION', 'En revisión'],
  ['RESUELTO', 'Resueltos'],
];

/** La administración atiende los reportes de problemas de todos los usuarios. */
export default async function reportesAdminView({ root, navigate }) {
  const filters = { estado: 'ABIERTO', page: 1 };

  mount(
    root,
    html`${pageHeader('Reportes', 'Problemas reportados por estudiantes, docentes y empresas. Quien reporta recibe un aviso cuando respondes.')}
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por estado">
          ${ESTADOS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${filters.estado === v}">${l}</button>`)}
        </div>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  async function load() {
    const res = await api.get('/admin/reportes', filters);
    mount(
      list,
      res.data.length
        ? res.data.map(
            (r) =>
              html`<article class="report">
                <div class="report-h"><b>${r.asunto}</b>${statusBadge(r.estado)}</div>
                <span class="fine"
                  >${r.categoria.etiqueta} · ${r.autor.rol.etiqueta} ${r.autor.correo} ·
                  ${relativeDays(r.creado_en)}</span
                >
                ${
                  r.vinculacion
                    ? html`<span class="fine"
                        >Práctica de ${r.vinculacion.estudiante} en ${r.vinculacion.empresa} ·
                        <button class="link" data-go="/vinculacion/${r.vinculacion.id}">
                          Ver seguimiento
                        </button></span
                      >`
                    : ''
                }
                <p>${r.descripcion}</p>
                ${r.respuesta ? html`<p class="note-box">Respuesta: ${r.respuesta}</p>` : ''}
                ${
                  r.estado.valor !== 'RESUELTO'
                    ? html`<form class="decide" data-report="${r.id}" novalidate>
                        <div class="field">
                          <label for="res${r.id}">Respuesta</label
                          ><input
                            class="input"
                            id="res${r.id}"
                            name="respuesta"
                            value="${r.respuesta ?? ''}"
                            placeholder="Obligatoria para resolver"
                          />
                        </div>
                        ${r.estado.valor === 'ABIERTO' ? html`<button class="btn sec sm" type="submit" value="EN_REVISION">Tomar en revisión</button>` : html`<span></span>`}
                        <button class="btn good sm" type="submit" value="RESUELTO">Marcar resuelto</button>
                      </form>`
                    : ''
                }
              </article>`,
          )
        : emptyState('flag', 'No hay reportes con este filtro'),
    );
    const { current_page: page, last_page: last } = res.meta;
    mount(
      root.querySelector('#pager'),
      last > 1
        ? html`<button class="btn sec sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>
              Anterior
            </button>
            <span class="fine">Página ${page} de ${last}</span>
            <button class="btn sec sm" data-page="${page + 1}" ${page >= last ? 'disabled' : ''}>
              Siguiente
            </button>`
        : '',
    );

    list.querySelectorAll('[data-report]').forEach((form) =>
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        withBusy(e.submitter, async () => {
          try {
            await api.patch(`/admin/reportes/${form.dataset.report}`, {
              estado: e.submitter.value,
              respuesta: form.respuesta.value.trim() || null,
            });
            toast('Reporte actualizado. Le avisamos a quien lo envió.');
            load();
          } catch (error) {
            showFieldErrors(form, error.errors) || toast(error.message);
          }
        });
      }),
    );
  }

  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (f) {
      filters.estado = f.dataset.f;
      filters.page = 1;
      root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
      return load();
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      return load();
    }
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });

  await load();
}
