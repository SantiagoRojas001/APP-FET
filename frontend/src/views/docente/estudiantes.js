import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, emptyState, icon, modalityTag, pageHeader } from '../../lib/ui.js';
import { session } from '../../session.js';

const decimal = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 2 });

/**
 * Estudiantes del programa del docente registrados con el listado de
 * modalidades de grado. Todos quedaron habilitados con su promedio al
 * cargarlos; aquí se consultan.
 */
export default async function estudiantesDocenteView({ root, navigate }) {
  const filters = { q: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Estudiantes', session.user.programa?.nombre ?? '', html`<button class="btn sec" id="cargar">${icon('upload')}Cargar listado</button>`)}
      <div class="toolbar">
        <label class="input-ic search">${icon('search')}<input class="input" id="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  // Hoja de vida: en qué va, o si ya está en práctica.
  const estado = (s) =>
    s.practica_actual
      ? badge('ok', `En ${s.practica_actual.tipo.etiqueta.toLowerCase()}`)
      : s.hoja_vida?.estado
        ? html`<span class="fine">Hoja de vida: ${s.hoja_vida.estado.etiqueta.toLowerCase()}</span>`
        : '';

  async function load() {
    const res = await api.get('/docente/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Estudiante</th><th>Semestre</th><th>Modalidad</th><th>Promedio</th><th>Estado</th></tr></thead>
            <tbody>${res.data.map(
              (s) => html`<tr>
                <td><b>${s.nombre_completo}</b><small class="mono">${s.correo}</small></td>
                <td>${s.semestre_actual}.º<small>Ciclo ${s.ciclo.etiqueta.toLowerCase()}${session.user.programa ? '' : ` · ${s.programa.nombre}`}</small></td>
                <td>${s.modalidad_grado ? modalityTag(s.modalidad_grado) : ''}</td>
                <td class="num">${s.promedio !== null ? decimal.format(s.promedio) : ''}</td>
                <td>${estado(s)}</td>
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

  await load();
}
