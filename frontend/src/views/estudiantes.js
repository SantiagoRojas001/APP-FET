import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { initials } from '../lib/format.js';
import { badge, emptyState, icon, pageHeader } from '../lib/ui.js';

/** Búsqueda de perfiles para empresas y docentes. */
export default async function estudiantesView({ root, navigate }) {
  const cat = await api.get('/catalogos');
  const filters = { programa_id: '', competencia_id: '', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Estudiantes', 'Perfiles de la comunidad FET. La etiqueta indica si ya pueden vincularse como pasantes o practicantes.')}
      <form class="toolbar" id="filters">
        <label class="input-ic search">${icon('search')}<input class="input" name="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
        <div class="actions">
          <select class="input" name="programa_id" aria-label="Programa" style="width:auto">
            <option value="">Todos los programas</option>
            ${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre}</option>`)}
          </select>
          <select class="input" name="competencia_id" aria-label="Competencia" style="width:auto">
            <option value="">Todas las competencias</option>
            ${cat.competencias.map((c) => html`<option value="${c.id}">${c.nombre}</option>`)}
          </select>
        </div>
      </form>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');
  const pager = root.querySelector('#pager');

  async function load() {
    const res = await api.get('/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? res.data.map(
            (s) => html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr) auto">
              <div class="av lg">${initials(s.nombre_completo)}</div>
              <div class="rowi-main">
                <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre${s.es_egresado ? ', egresado' : ''}</div></div>
                <div class="tags">
                  ${s.habilitado_para_practica ? badge('ok', 'Habilitado para práctica') : badge('neutral', 'Aún no habilitado')}
                  ${s.competencias.map((c) => html`<span class="tag">${c.nombre}</span>`)}
                </div>
              </div>
              <div class="rowi-act"><button class="btn sec sm" data-open="${s.id}">Ver perfil</button></div>
            </article>`,
          )
        : emptyState('users', 'No hay estudiantes con esos filtros'),
    );
    const { current_page: page, last_page: last } = res.meta;
    mount(
      pager,
      last > 1
        ? html`<button class="btn sec sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Anterior</button>
            <span class="fine">Página ${page} de ${last}</span>
            <button class="btn sec sm" data-page="${page + 1}" ${page >= last ? 'disabled' : ''}>Siguiente</button>`
        : '',
    );
  }

  let debounce;
  root.querySelector('#filters').addEventListener('input', (e) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      filters[e.target.name] = e.target.value.trim();
      filters.page = 1;
      load();
    }, 250);
  });
  root.querySelector('#filters').addEventListener('submit', (e) => e.preventDefault());

  root.addEventListener('click', (e) => {
    const open = e.target.closest('[data-open]');
    if (open) return navigate(`/estudiantes/${open.dataset.open}`);
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      load();
    }
  });

  await load();
}
