import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDateTime, initials, todayIso } from '../../lib/format.js';
import { badge, emptyState, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const ABIERTAS = ['PROGRAMADA', 'ACEPTADO'];

/**
 * Hojas de vida de estudiantes habilitados que aprobó un docente. Primero aparecen los
 * que un docente recomendó para tus vacantes. Con convenio vigente, la empresa
 * asigna una cita y el estudiante recibe la empresa, la dirección, la fecha y
 * la hora.
 */
export default async function hojasVidaView({ root, navigate }) {
  const [cat, convenios, vacantes] = await Promise.all([api.get('/catalogos'), api.get('/empresa/acuerdos'), api.get('/empresa/vacantes')]);
  const conAcuerdo = convenios.some((a) => a.vigente);
  const filters = { programa_id: '', modalidad: '', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Hojas de vida')}
      ${conAcuerdo
        ? ''
        : html`<section class="callout">
            ${icon('alert-circle')}
            <p><b>Para asignar citas necesitas un convenio aprobado y vigente con la FET.</b> Puedes revisar las hojas de vida mientras tanto.</p>
            <button class="btn sm" id="toAcuerdo">Subir convenio</button>
          </section>`}
      <form class="toolbar" id="filters">
        <label class="input-ic search">${icon('search')}<input class="input" name="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
        <div class="actions">
          <select class="input" name="programa_id" aria-label="Programa" style="width:auto">
            <option value="">Todos los programas</option>
            ${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre}</option>`)}
          </select>
          <select class="input" name="modalidad" aria-label="Modalidad" style="width:auto">
            <option value="">Pasantía y práctica</option>
            ${cat.modalidades.map((m) => html`<option value="${m.valor}">${m.etiqueta}</option>`)}
          </select>
        </div>
      </form>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const citaForm = (s) => html`<form class="form-grid" data-cita="${s.id}" hidden novalidate>
    <div class="field"><label for="fh${s.id}">Fecha y hora</label><input class="input" type="datetime-local" id="fh${s.id}" name="fecha_hora" min="${todayIso()}T00:00" /></div>
    <div class="field" style="grid-column:span 2"><label for="dir${s.id}">Dirección</label><input class="input" id="dir${s.id}" name="direccion" maxlength="200" placeholder="Calle, número, oficina y ciudad" /></div>
    ${vacantes.length
      ? html`<div class="field"><label for="vac${s.id}">Vacante (opcional)</label><select class="input" id="vac${s.id}" name="vacante_id">
          <option value="">Sin vacante</option>
          ${vacantes.map((v) => html`<option value="${v.id}">${v.titulo}</option>`)}
        </select></div>`
      : ''}
    <div class="field wide"><label for="ind${s.id}">Indicaciones (opcional)</label><input class="input" id="ind${s.id}" name="indicaciones" maxlength="1000" placeholder="Por ejemplo: pregunta por Talento humano y trae tu documento" /></div>
    <div class="actions"><button class="btn" type="submit">${icon('send')}Asignar cita</button><button class="btn sec" type="button" data-cerrar="${s.id}">Cancelar</button></div>
  </form>`;

  const row = (s) => {
    const abierta = s.citas?.find((c) => ABIERTAS.includes(c.estado.valor));
    return html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
      <div class="av lg">${initials(s.nombre_completo)}</div>
      <div class="rowi-main">
        <div class="panel-h">
          <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre (ciclo ${s.ciclo.etiqueta.toLowerCase()})</div></div>
          ${s.recomendado ? badge('info', 'Recomendado por la FET') : ''}
        </div>
        ${s.recomendaciones?.map((r) => html`<p class="note-box">${icon('star')} Recomendado para «${r.vacante}»${r.comentario ? `: ${r.comentario}` : ''}</p>`)}
        <div class="tags">
          ${s.modalidad_grado ? modalityTag(s.modalidad_grado, true) : ''}
          ${s.competencias?.map((c) => html`<span class="tag">${c.nombre}</span>`)}
        </div>
        <div class="actions">
          <a class="btn sec sm" href="#/estudiantes/${s.id}">${icon('file-cv')}Ver hoja de vida</a>
          ${abierta
            ? html`${statusBadge(abierta.estado)}<span class="fine">${formatDateTime(abierta.fecha_hora)}</span>`
            : html`<button class="btn sm" data-abrir="${s.id}" ${conAcuerdo ? '' : 'disabled'}>${icon('calendar-event')}Asignar cita</button>`}
        </div>
        ${abierta ? '' : citaForm(s)}
      </div>
    </article>`;
  };

  async function load() {
    const res = await api.get('/empresa/hojas-vida', filters);
    mount(list, res.data.length ? res.data.map(row) : emptyState('file-cv', 'No hay hojas de vida con esos filtros', 'Cuando los docentes aprueben más hojas de vida, aparecerán aquí.'));
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

  const filtros = root.querySelector('#filters');
  let debounce;
  filtros.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      Object.assign(filters, { q: filtros.q.value.trim(), programa_id: filtros.programa_id.value, modalidad: filtros.modalidad.value, page: 1 });
      load();
    }, 250);
  });
  filtros.addEventListener('submit', (e) => e.preventDefault());

  root.addEventListener('click', (e) => {
    if (e.target.closest('#toAcuerdo')) return navigate('/convenio');
    const abrir = e.target.closest('[data-abrir]');
    if (abrir) {
      const form = root.querySelector(`[data-cita="${abrir.dataset.abrir}"]`);
      form.hidden = false;
      abrir.hidden = true;
      return form.fecha_hora.focus();
    }
    const cerrar = e.target.closest('[data-cerrar]');
    if (cerrar) {
      root.querySelector(`[data-cita="${cerrar.dataset.cerrar}"]`).hidden = true;
      root.querySelector(`[data-abrir="${cerrar.dataset.cerrar}"]`).hidden = false;
      return;
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      load();
    }
  });

  list.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-cita]');
    if (!form) return;
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/empresa/citas', {
          estudiante_id: Number(form.dataset.cita),
          fecha_hora: form.fecha_hora.value,
          direccion: form.direccion.value.trim(),
          indicaciones: form.indicaciones.value.trim() || null,
          vacante_id: form.vacante_id?.value ? Number(form.vacante_id.value) : null,
        });
        toast('Cita asignada. Le avisamos al estudiante con la dirección, la fecha y la hora.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
