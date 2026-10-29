import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { initials, relativeDays } from '../../lib/format.js';
import { emptyState, icon, modalityTag, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';

const FILTERS = [
  ['', 'Todas'],
  ['PASANTIA', 'Pasantías'],
  ['PRACTICA', 'Prácticas'],
];

function row(v) {
  let action;
  if (v.mi_postulacion) {
    action = html`${statusBadge(v.mi_postulacion.estado)}<span class="fine">Ya te postulaste</span>`;
  } else if (v.puede_postularse) {
    action = html`<button class="btn" data-apply="${v.id}">Aplicar</button><span class="fine">Tu hoja de vida llega a la empresa</span>`;
  } else {
    action = html`<button class="btn" disabled>No disponible</button><span class="fine">${v.motivo_no_disponible}</span>`;
  }

  return html`<article class="rowi${v.puede_postularse || v.mi_postulacion ? '' : ' dim'}">
    <div class="logo" aria-hidden="true">${initials(v.empresa.razon_social)}</div>
    <div class="rowi-main">
      <div><h3>${v.titulo}</h3><div class="sub">${v.empresa.razon_social}${v.empresa.sector ? `, ${v.empresa.sector}` : ''}</div></div>
      <div class="tags">${modalityTag(v.modalidad, true)}</div>
      ${v.descripcion ? html`<p class="fine">${v.descripcion}</p>` : ''}
      <dl class="facts"><div><dt>Publicada</dt><dd>${relativeDays(v.publicada_en)}</dd></div></dl>
    </div>
    <div class="rowi-act">${action}</div>
  </article>`;
}

export default async function vacantesView({ root, refresh }) {
  const state = { modalidad: '', q: '' };

  mount(
    root,
    html`${pageHeader('Vacantes', 'Vacantes de empresas validadas por la FET. Tu hoja de vida llega directo a la empresa; si te selecciona, tu docente autoriza la práctica.')}
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por modalidad">
          ${FILTERS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${state.modalidad === v}">${l}</button>`)}
        </div>
        <label class="input-ic search">${icon('search')}<input class="input" id="q" type="search" placeholder="Buscar cargo o empresa" aria-label="Buscar vacantes" /></label>
      </div>
      <section class="panel flush" id="list"></section>`,
  );

  const list = root.querySelector('#list');

  async function load() {
    const vacantes = await api.get('/estudiante/vacantes', state);
    mount(
      list,
      vacantes.length
        ? vacantes.map(row)
        : emptyState('search', 'No hay vacantes con ese filtro', 'Prueba con otra modalidad o borra la búsqueda.'),
    );
  }

  let debounce;
  root.querySelector('#q').addEventListener('input', (e) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      state.q = e.target.value.trim();
      load();
    }, 250);
  });

  root.addEventListener('click', async (e) => {
    const f = e.target.closest('[data-f]');
    if (f) {
      state.modalidad = f.dataset.f;
      root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
      return load();
    }
    const apply = e.target.closest('[data-apply]');
    if (apply) {
      await withBusy(apply, async () => {
        try {
          await api.post(`/estudiante/vacantes/${apply.dataset.apply}/postulaciones`);
          toast('Postulación enviada. Tu hoja de vida llegó a la empresa.');
          refresh();
        } catch (error) {
          toast(error.message);
        }
      });
    }
  });

  await load();
}
