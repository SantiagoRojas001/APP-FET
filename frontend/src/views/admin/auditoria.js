import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { emptyState, pageHeader } from '../../lib/ui.js';

const ENTIDADES = [
  ['', 'Todo'],
  ['postulacion', 'Postulaciones'],
  ['vinculacion', 'Prácticas'],
  ['documento_seguimiento', 'Documentos'],
  ['evidencia_semanal', 'Horas semanales'],
  ['usuario', 'Cuentas'],
  ['empresa', 'Empresas'],
  ['reporte', 'Reportes'],
];

const fechaHora = new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' });

/** Texto corto con los datos del detalle de una acción. */
const detalle = (d) =>
  d
    ? Object.entries(d)
        .map(([k, v]) => `${k.replaceAll('_', ' ')}: ${Array.isArray(v) ? v.join(', ') : v}`)
        .join(' · ')
    : '';

/** Bitácora de auditoría: quién aprobó, rechazó, revisó o cerró qué y cuándo. */
export default async function auditoriaView({ root }) {
  const filters = { entidad: '', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Auditoría', 'Registro de las acciones relevantes: decisiones sobre postulaciones, revisiones, cierres de prácticas y cambios en cuentas.')}
      <div class="toolbar">
        <div class="seg sm" role="group" aria-label="Filtrar por tipo">
          ${ENTIDADES.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${filters.entidad === v}">${l}</button>`)}
        </div>
        <input class="input search" type="search" id="q" placeholder="Buscar por acción o correo" />
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  async function load() {
    const res = await api.get('/admin/auditoria', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl">
            <table>
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Quién</th>
                  <th>Acción</th>
                  <th>Sobre</th>
                  <th>Detalle</th>
                </tr>
              </thead>
              <tbody>
                ${res.data.map(
                  (a) =>
                    html`<tr>
                      <td class="num">${fechaHora.format(new Date(a.creado_en))}</td>
                      <td>
                        ${a.usuario ? html`${a.usuario.correo}<small>${a.usuario.rol.etiqueta}</small>` : html`<span class="fine">Sistema</span>`}
                      </td>
                      <td class="mono">${a.accion}</td>
                      <td>${a.entidad.replaceAll('_', ' ')} #${a.entidad_id}</td>
                      <td><span class="fine">${detalle(a.detalle)}</span></td>
                    </tr>`,
                )}
              </tbody>
            </table>
          </div>`
        : emptyState('history', 'No hay registros con este filtro'),
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
  }

  let timer;
  root.querySelector('#q').addEventListener('input', (e) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      filters.q = e.target.value.trim();
      filters.page = 1;
      load();
    }, 300);
  });

  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (f) {
      filters.entidad = f.dataset.f;
      filters.page = 1;
      root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
      return load();
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      load();
    }
  });

  await load();
}
