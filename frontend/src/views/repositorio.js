import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { relativeDays } from '../lib/format.js';
import { icon } from '../lib/ui.js';

// Colores con los que GitHub identifica cada lenguaje (dato externo, no de la paleta de la app).
const LANGUAGE_COLORS = {
  JavaScript: '#f1e05a',
  TypeScript: '#3178c6',
  PHP: '#4F5D95',
  Blade: '#f7523f',
  Python: '#3572A5',
  Dart: '#00B4AB',
  Java: '#b07219',
  Kotlin: '#A97BFF',
  'C++': '#f34b7d',
  C: '#555555',
  'C#': '#178600',
  HTML: '#e34c26',
  CSS: '#663399',
  Vue: '#41b883',
  Shell: '#89e051',
  Swift: '#F05138',
  Cython: '#fedf5b',
};
const languageColor = (name) => LANGUAGE_COLORS[name] ?? 'var(--ink-3)';

/** Hueco donde se carga la vista previa de un proyecto (ver `hydrateRepositories`). */
export const repositorySlot = (proyecto) =>
  html`<div class="gh" data-repo="${proyecto.id}" aria-busy="true">
    <div class="sk" style="height:18px;width:50%"></div>
    <div class="sk" style="height:12px;width:80%"></div>
    <div class="sk" style="height:8px"></div>
  </div>`;

function card(r) {
  return html`<div class="gh-head">
      <img class="gh-avatar" src="${r.propietario.avatar}" alt="" width="36" height="36" loading="lazy" />
      <div>
        <a class="gh-name" href="${r.url}" target="_blank" rel="noopener noreferrer">${icon('brand-github')}${r.nombre}</a>
        ${r.descripcion ? html`<p class="fine">${r.descripcion}</p>` : ''}
      </div>
    </div>
    <div class="gh-stats">
      <span>${icon('star')}${r.estrellas.toLocaleString('es-CO')} estrellas</span>
      <span>${icon('git-branch')}${r.rama}</span>
      <span>${icon('git-commit')}Último cambio: ${relativeDays(r.actualizado_en)}</span>
    </div>
    ${r.lenguajes.length
      ? html`<div>
          <div class="langbar" role="img" aria-label="Lenguajes del repositorio">
            ${r.lenguajes.map((l) => html`<span style="width:${l.porcentaje}%;background:${languageColor(l.nombre)}"></span>`)}
          </div>
          <div class="langs">${r.lenguajes.map((l) => html`<span><i style="background:${languageColor(l.nombre)}"></i>${l.nombre} ${l.porcentaje} %</span>`)}</div>
        </div>`
      : ''}
    ${r.commits.length
      ? html`<div>
          <div class="sub-h">Últimos commits</div>
          <div class="commits">${r.commits.map(
            (c) => html`<div class="commit">
              <a href="${c.url}" target="_blank" rel="noopener noreferrer"><code>${c.sha}</code></a>
              <span>${c.mensaje}${c.autor ? html` <span class="fine">· ${c.autor}</span>` : ''}</span>
              <small>${c.fecha ? relativeDays(c.fecha) : ''}</small>
            </div>`,
          )}</div>
        </div>`
      : ''}`;
}

/** Carga la vista previa de GitHub de cada hueco `[data-repo]` dentro de `root`. */
export function hydrateRepositories(root) {
  root.querySelectorAll('[data-repo]').forEach(async (slot) => {
    try {
      const r = await api.get(`/proyectos/${slot.dataset.repo}/repositorio`);
      mount(slot, r.disponible ? card(r) : html`<p class="fine">${icon('alert-circle')} ${r.motivo}</p>`);
    } catch (error) {
      mount(slot, html`<p class="fine">${icon('alert-circle')} ${error.message}</p>`);
    }
    slot.removeAttribute('aria-busy');
  });
}
