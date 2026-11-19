import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { initials, relativeDays } from '../../lib/format.js';
import { badge, emptyState, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const TABS = [
  ['EN_REVISION', 'Por revisar'],
  ['CON_OBSERVACIONES', 'Con recomendaciones'],
  ['APROBADA', 'Aprobadas'],
];

function row(s) {
  const hv = s.hoja_vida;

  return html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(s.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre (ciclo ${s.ciclo.etiqueta.toLowerCase()})</div></div>
        ${statusBadge(hv.estado)}
      </div>
      <div class="req">
        ${s.modalidad_grado ? modalityTag(s.modalidad_grado, true) : ''}
        ${badge(hv.porcentaje === 100 ? 'ok' : 'warn', `Completa al ${hv.porcentaje} %`)}
        ${badge(s.proyectos?.length ? 'ok' : 'warn', `${s.proyectos?.length ?? 0} ${s.proyectos?.length === 1 ? 'proyecto' : 'proyectos'}`)}
        ${hv.enviada_en ? html`<span class="fine">Enviada ${relativeDays(hv.enviada_en).toLowerCase()}</span>` : ''}
      </div>
      <div class="actions">
        <a class="btn sec sm" href="#/estudiantes/${s.id}">${icon('file-cv')}Ver hoja de vida completa</a>
      </div>
      ${hv.estado?.valor === 'EN_REVISION'
        ? html`<form class="decide" data-id="${s.id}" novalidate>
            <div class="field"><label for="obs${s.id}">Recomendaciones para el estudiante</label><textarea class="input" id="obs${s.id}" name="observaciones" maxlength="2000" placeholder="Obligatorias si la devuelves: qué debe corregir"></textarea></div>
            <button class="btn good" type="submit" value="1">${icon('check')}Aprobar</button>
            <button class="btn danger" type="submit" value="0">Devolver para corregir</button>
          </form>`
        : hv.observaciones
          ? html`<p class="note-box">Recomendaciones: ${hv.observaciones}</p>`
          : ''}
    </div>
  </article>`;
}

/**
 * Revisión docente de las hojas de vida. Las aprobadas las ven las empresas
 * con acuerdo vigente, que asignan citas; las demás vuelven al estudiante con
 * recomendaciones para corregir. Quien aprueba queda como tutor si el
 * estudiante empieza la práctica.
 */
export default async function revisionView({ root }) {
  let estado = 'EN_REVISION';

  mount(
    root,
    html`${pageHeader('Hojas de vida')}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar">
        ${TABS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${estado === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>`,
  );
  const list = root.querySelector('#list');

  async function load() {
    const items = await api.get('/docente/hojas-vida', { estado });
    mount(list, items.length ? items.map(row) : emptyState('circle-check', 'No hay hojas de vida aquí', 'Cuando un estudiante envíe la suya, aparecerá en Por revisar.'));
  }

  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (!f) return;
    estado = f.dataset.f;
    root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
    load();
  });

  root.addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    const aprobar = e.submitter?.value === '1';
    withBusy(e.submitter, async () => {
      try {
        await api.patch(`/docente/hojas-vida/${form.dataset.id}`, { aprobar, observaciones: form.observaciones.value.trim() || null });
        toast(aprobar ? 'Hoja de vida aprobada. Ya la ven las empresas.' : 'Devuelta con tus recomendaciones. Le avisamos al estudiante.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
