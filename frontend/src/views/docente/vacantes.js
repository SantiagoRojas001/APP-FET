import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { initials, relativeDays } from '../../lib/format.js';
import { emptyState, icon, modalityTag, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/**
 * Vacantes de las empresas, que solo ven los docentes. Recomendar a un
 * estudiante hace que la empresa lo vea primero entre las hojas de vida.
 */
export default async function vacantesDocenteView({ root, refresh }) {
  const vacantes = await api.get('/docente/vacantes');

  const recomendado = (v, r) => html`<div class="line">
    <div>
      <b>${r.estudiante.nombre_completo}</b>
      <span>${r.estudiante.programa?.nombre}, ${r.estudiante.semestre_actual}.º semestre${r.comentario ? ` · «${r.comentario}»` : ''} · ${r.docente ?? ''}</span>
    </div>
    <div class="actions">
      <a class="btn sec sm" href="#/estudiantes/${r.estudiante.id}">Hoja de vida</a>
      <button class="btn danger sm" data-quitar="${r.id}" data-vacante="${v.id}">Quitar</button>
    </div>
  </div>`;

  const vacante = (v) => html`<section class="panel">
    <div class="who">
      <div class="logo" aria-hidden="true">${initials(v.empresa.razon_social)}</div>
      <div><b>${v.titulo}</b><span>${v.empresa.razon_social}, publicada ${relativeDays(v.publicada_en).toLowerCase()}</span></div>
      ${modalityTag(v.modalidad, true)}
    </div>
    ${v.descripcion ? html`<p class="muted">${v.descripcion}</p>` : ''}
    <div class="lines">${v.recomendaciones.length ? v.recomendaciones.map((r) => recomendado(v, r)) : html`<p class="fine">Aún no recomiendas estudiantes para esta vacante.</p>`}</div>
    <form class="form-grid" data-recomendar="${v.id}" novalidate>
      <div class="field">
        <label for="buscar${v.id}">Buscar estudiante disponible</label>
        <div class="input-ic">${icon('search')}<input class="input" id="buscar${v.id}" type="search" data-buscar="${v.id}" placeholder="Nombre o apellido" autocomplete="off" /></div>
      </div>
      <div class="field">
        <label for="est${v.id}">Estudiante</label>
        <select class="input" id="est${v.id}" name="estudiante_id"><option value="">Escribe para buscar</option></select>
      </div>
      <div class="field"><label for="com${v.id}">Comentario para la empresa</label><input class="input" id="com${v.id}" name="comentario" maxlength="500" /></div>
      <div><button class="btn sm" type="submit">${icon('star')}Recomendar</button></div>
    </form>
  </section>`;

  mount(
    root,
    html`${pageHeader('Vacantes')}
      ${vacantes.length ? vacantes.map(vacante) : html`<section class="panel">${emptyState('briefcase', 'No hay vacantes abiertas')}</section>`}`,
  );

  // Busca estudiantes disponibles (habilitados, con hoja de vida aprobada y sin práctica).
  const timers = {};
  root.addEventListener('input', (e) => {
    const input = e.target.closest('[data-buscar]');
    if (!input) return;
    clearTimeout(timers[input.dataset.buscar]);
    timers[input.dataset.buscar] = setTimeout(async () => {
      const select = root.querySelector(`#est${input.dataset.buscar}`);
      const res = await api.get('/estudiantes', { disponibles: 1, q: input.value.trim() });
      mount(
        select,
        res.data.length
          ? res.data.map((s) => html`<option value="${s.id}">${s.nombre_completo}, ${s.programa.nombre} (${s.semestre_actual}.º)</option>`)
          : html`<option value="">Sin resultados</option>`,
      );
    }, 250);
  });

  root.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-recomendar]');
    if (!form) return;
    e.preventDefault();
    if (!form.estudiante_id.value) return showFieldErrors(form, { estudiante_id: ['Busca y elige un estudiante.'] });
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/docente/vacantes/${form.dataset.recomendar}/recomendaciones`, {
          estudiante_id: Number(form.estudiante_id.value),
          comentario: form.comentario.value.trim() || null,
        });
        toast('Recomendación enviada a la empresa.');
        refresh();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-quitar]');
    if (!btn) return;
    withBusy(btn, async () => {
      try {
        await api.delete(`/docente/vacantes/${btn.dataset.vacante}/recomendaciones/${btn.dataset.quitar}`);
        toast('Recomendación retirada.');
        refresh();
      } catch (error) {
        toast(error.message);
      }
    });
  });
}
