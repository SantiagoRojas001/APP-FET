import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, confirmInline, emptyState, icon, modalityTag, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

const ESTADOS = [
  ['sin_promedio', 'Sin promedio'],
  ['habilitados', 'Habilitados'],
  ['', 'Todos'],
];

const decimal = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 2 });

/**
 * Estudiantes registrados con el listado de modalidades de grado (de su
 * programa). El docente registra el promedio una sola vez: si alcanza el
 * mínimo, el estudiante recibe su usuario y contraseña; si no, recibe el
 * motivo por correo y su registro se elimina.
 */
export default async function estudiantesDocenteView({ root, navigate }) {
  const filters = { estado: 'sin_promedio', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Estudiantes', session.user.programa?.nombre ?? '', html`<button class="btn sec" id="cargar">${icon('upload')}Cargar listado</button>`)}
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por estado">
          ${ESTADOS.map(([v, l]) => html`<button data-estado="${v}" aria-pressed="${filters.estado === v}">${l}</button>`)}
        </div>
        <label class="input-ic search">${icon('search')}<input class="input" id="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const promedio = (s) =>
    s.promedio !== null && s.promedio !== undefined
      ? html`<b class="num">${decimal.format(s.promedio)}</b><small>${badge('ok', 'Credenciales enviadas')}</small>`
      : html`<form class="actions" data-promedio="${s.id}" novalidate>
          <input class="input narrow" name="promedio" inputmode="decimal" placeholder="4,25" aria-label="Promedio de ${s.nombre_completo}" />
          <button class="btn sm" type="submit">Registrar</button>
        </form>`;

  async function load() {
    const res = await api.get('/docente/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Estudiante</th><th>Semestre</th><th>Modalidad</th><th>Promedio</th></tr></thead>
            <tbody>${res.data.map(
              (s) => html`<tr>
                <td><b>${s.nombre_completo}</b><small class="mono">${s.correo}</small></td>
                <td>${s.semestre_actual}.º<small>Ciclo ${s.ciclo.etiqueta.toLowerCase()}${session.user.programa ? '' : ` · ${s.programa.nombre}`}</small></td>
                <td>${s.modalidad_grado ? modalityTag(s.modalidad_grado) : ''}</td>
                <td>${promedio(s)}</td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('users', 'No hay estudiantes con este filtro'),
    );
    const { current_page: page, last_page: last } = res.meta;
    mount(
      root.querySelector('#pager'),
      last > 1
        ? html`<button class="btn sec sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Anterior</button>
            <span class="fine">Página ${page} de ${last}</span>
            <button class="btn sec sm" data-page="${page + 1}" ${page >= last ? 'disabled' : ''}>Siguiente</button>`
        : '',
    );
  }

  root.querySelector('#cargar').addEventListener('click', () => navigate('/registro'));

  root.addEventListener('click', (e) => {
    const estado = e.target.closest('[data-estado]');
    if (estado) {
      filters.estado = estado.dataset.estado;
      filters.page = 1;
      root.querySelectorAll('[data-estado]').forEach((b) => b.setAttribute('aria-pressed', String(b === estado)));
      return load();
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      load();
    }
  });

  let debounce;
  root.querySelector('#q').addEventListener('input', (e) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      filters.q = e.target.value.trim();
      filters.page = 1;
      load();
    }, 250);
  });

  // El promedio se registra una sola vez y dispara el correo: se confirma en pantalla.
  list.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-promedio]');
    if (!form) return;
    e.preventDefault();
    const valor = form.promedio.value.trim();
    if (!valor) return showFieldErrors(form, { promedio: ['Escribe el promedio.'] });
    confirmInline(
      form.querySelector('[type=submit]'),
      'registrar y enviar el correo',
      (si) =>
        withBusy(si, async () => {
          try {
            const res = await api.patch(`/docente/estudiantes/${form.dataset.promedio}/promedio`, { promedio: valor });
            toast(res.eliminado ? res.message : `${res.nombres} cumple el promedio: le enviamos su usuario y contraseña.`);
            load();
          } catch (error) {
            showFieldErrors(form, error.errors) || toast(error.message);
          }
        }),
      `Promedio ${valor}. Si no alcanza el mínimo, el estudiante recibe el motivo y su registro se elimina.`,
    );
  });

  await load();
}
