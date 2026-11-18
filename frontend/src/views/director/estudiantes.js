import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, emptyState, icon, modalityTag, pageHeader, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

const CICLOS = [
  ['', 'Todos'],
  ['TECNICO', 'Técnico'],
  ['TECNOLOGO', 'Tecnólogo'],
  ['PROFESIONAL', 'Profesional'],
];
const PRACTICA = [
  ['', 'Todos'],
  ['PASANTIA', 'En pasantía'],
  ['PRACTICA', 'En práctica'],
  ['NINGUNA', 'Aún no'],
];

/** Estudiantes del programa del director, con su ciclo, su práctica y su docente tutor, que él asigna. */
export default async function estudiantesDirectorView({ root }) {
  const filters = { ciclo: '', practica: '', q: '', page: 1 };
  const docentes = await api.get('/director/docentes');
  mount(
    root,
    html`${pageHeader('Estudiantes', session.user.programa?.nombre ?? '')}
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
      <div class="toolbar">
        <select class="input" id="tutor" aria-label="Docente tutor">
          <option value="">Sin tutor</option>
          ${docentes.map((d) => html`<option value="${d.id}">${d.nombre}</option>`)}
        </select>
        <button class="btn sm" id="asignar">${icon('users')}Asignar a los marcados</button>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const estado = (s) =>
    s.practica_actual
      ? html`${badge('ok', `En ${s.practica_actual.tipo.etiqueta.toLowerCase()}`)}<small>${s.practica_actual.empresa ?? ''}</small>`
      : s.hoja_vida?.estado
        ? html`${badge('neutral', 'Aún no')}<small>Hoja de vida: ${s.hoja_vida.estado.etiqueta.toLowerCase()}</small>`
        : badge('neutral', 'Aún no');

  async function load() {
    const res = await api.get('/director/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th><span class="fine">Marcar</span></th><th>Estudiante</th><th>Tutor</th><th>Semestre</th><th>Ciclo</th><th>Modalidad de grado</th><th>Práctica</th></tr></thead>
            <tbody>${res.data.map(
              (s) => html`<tr>
                <td><input type="checkbox" data-estudiante="${s.id}" aria-label="Marcar a ${s.nombre_completo}" /></td>
                <td><b><a href="#/estudiantes/${s.id}">${s.nombre_completo}</a></b><small class="mono">${s.correo ?? ''}</small></td>
                <td>${s.tutor?.nombre ?? html`<span class="fine">Sin asignar</span>`}</td>
                <td class="num">${s.semestre_actual}.º</td>
                <td>${s.ciclo.etiqueta}</td>
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

  root.querySelector('#asignar').addEventListener('click', (e) => {
    const estudiante_ids = [...list.querySelectorAll('[data-estudiante]:checked')].map((c) => Number(c.dataset.estudiante));
    if (!estudiante_ids.length) return toast('Marca al menos un estudiante.');
    const tutor_id = Number(root.querySelector('#tutor').value) || null;
    withBusy(e.currentTarget, async () => {
      try {
        await api.post('/director/estudiantes/tutor', { estudiante_ids, tutor_id });
        toast('Tutor actualizado.');
        load();
      } catch (error) {
        toast(error.message);
      }
    });
  });

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
