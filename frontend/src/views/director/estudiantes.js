import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, emptyState, icon, modalityTag, pageHeader } from '../../lib/ui.js';
import { session } from '../../session.js';

const CICLOS = [
  ['', 'Todos'],
  ['Técnico', 'Técnico'],
  ['Tecnólogo', 'Tecnólogo'],
  ['Profesional', 'Profesional'],
];
const PRACTICA = [
  ['', 'Todos'],
  ['PASANTIA', 'En pasantía'],
  ['PRACTICA', 'En práctica'],
  ['NINGUNA', 'Aún no'],
];

/** Estudiantes del programa del director, con su ciclo académico y si están en pasantía, práctica o aún no. */
export default async function estudiantesDirectorView({ root }) {
  const filters = { ciclo: '', practica: '', q: '', page: 1 };
  const programa = session.user.programa?.nombre ?? 'tu programa';

  mount(
    root,
    html`${pageHeader('Estudiantes', `Estudiantes de ${programa}, con su ciclo académico y si están realizando pasantía, práctica o aún no.`)}
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por ciclo">
          ${CICLOS.map(([v, l]) => html`<button data-ciclo="${v}" aria-pressed="${filters.ciclo === v}">${l}</button>`)}
        </div>
        <label class="input-ic search">${icon('search')}<input class="input" id="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
      </div>
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por práctica">
          ${PRACTICA.map(([v, l]) => html`<button data-practica="${v}" aria-pressed="${filters.practica === v}">${l}</button>`)}
        </div>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const estado = (s) =>
    s.practica_actual
      ? html`${badge('ok', `En ${s.practica_actual.tipo.etiqueta.toLowerCase()}`)}<small>${s.practica_actual.empresa ?? ''}</small>`
      : s.habilitado === false
        ? badge('neutral', 'No habilitado')
        : s.hoja_vida?.publicada_en
          ? badge('info', 'Hoja de vida publicada')
          : badge('warn', s.habilitado ? 'Aún no publica' : 'Aún no');

  async function load() {
    const res = await api.get('/director/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Estudiante</th><th>Semestre</th><th>Ciclo</th><th>Modalidad de grado</th><th>Práctica</th></tr></thead>
            <tbody>${res.data.map(
              (s) => html`<tr>
                <td><b><a href="#/estudiantes/${s.id}">${s.nombre_completo}</a></b><small class="mono">${s.correo ?? ''}</small></td>
                <td class="num">${s.semestre_actual}.º</td>
                <td>${s.ciclo}</td>
                <td>${s.modalidad_grado ? modalityTag(s.modalidad_grado) : html`<span class="fine">No registra</span>`}</td>
                <td>${estado(s)}</td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('school', 'No hay estudiantes con este filtro'),
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

  root.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-ciclo], [data-practica], [data-page]');
    if (!boton) return;
    if ('page' in boton.dataset) {
      filters.page = Number(boton.dataset.page);
      return load();
    }
    const clave = 'ciclo' in boton.dataset ? 'ciclo' : 'practica';
    filters[clave] = boton.dataset[clave];
    filters.page = 1;
    root.querySelectorAll(`[data-${clave}]`).forEach((b) => b.setAttribute('aria-pressed', String(b === boton)));
    load();
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
