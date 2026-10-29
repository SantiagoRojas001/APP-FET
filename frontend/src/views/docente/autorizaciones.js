import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDateTime, initials, todayIso } from '../../lib/format.js';
import { badge, emptyState, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const TABS = [
  ['SELECCIONADO', 'Por aprobar'],
  ['AUTORIZADO', 'Aprobados'],
  ['NO_AUTORIZADO', 'No aprobados'],
];

function row(c) {
  const s = c.estudiante;
  const hv = s.hoja_vida?.porcentaje ?? 0;

  return html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(s.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre</div></div>
        ${statusBadge(c.estado)}
      </div>
      <dl class="facts">
        <div><dt>Empresa</dt><dd>${c.empresa.razon_social}</dd></div>
        ${c.vacante ? html`<div><dt>Vacante</dt><dd>${c.vacante.titulo}</dd></div>` : ''}
        <div><dt>Cita</dt><dd>${formatDateTime(c.fecha_hora)}</dd></div>
      </dl>
      <div class="req">
        ${s.modalidad_grado ? modalityTag(s.modalidad_grado, true) : ''}
        ${badge(hv === 100 ? 'ok' : 'warn', `Hoja de vida ${hv} %`)}
        <a class="fine" href="#/estudiantes/${s.id}">Ver hoja de vida</a>
      </div>
      ${c.estado.valor === 'SELECCIONADO'
        ? html`<form class="decide" data-id="${c.id}" novalidate>
            <div class="field"><label for="ini${c.id}">Inicio de la práctica</label><input class="input" type="date" id="ini${c.id}" name="fecha_inicio" value="${todayIso()}" min="${todayIso()}" /></div>
            <div class="field"><label for="obs${c.id}">Observación</label><input class="input" id="obs${c.id}" name="observacion" placeholder="Obligatoria si no apruebas" /></div>
            <button class="btn good" type="submit" value="1">${icon('check')}Aprobar</button>
            <button class="btn danger" type="submit" value="0">${icon('x')}No aprobar</button>
          </form>`
        : c.observacion
          ? html`<p class="note-box">Observación: ${c.observacion}</p>`
          : ''}
    </div>
  </article>`;
}

/**
 * Estudiantes que una empresa seleccionó después de la cita. El docente los
 * vuelve a aprobar (queda como tutor) y el director de programa envía la carta
 * de presentación.
 */
export default async function autorizacionesView({ root }) {
  let estado = 'SELECCIONADO';

  mount(
    root,
    html`${pageHeader('Aprobaciones', 'Estudiantes que una empresa seleccionó después de su cita. Al aprobar quedas como tutor y el director de programa envía la carta de presentación.')}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar">
        ${TABS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${estado === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>`,
  );
  const list = root.querySelector('#list');

  async function load() {
    const items = await api.get('/docente/citas', { estado });
    mount(list, items.length ? items.map(row) : emptyState('circle-check', 'No hay estudiantes aquí', 'Cuando una empresa seleccione a un estudiante, aparecerá en Por aprobar.'));
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
    const autorizar = e.submitter?.value === '1';
    withBusy(e.submitter, async () => {
      try {
        await api.patch(`/docente/citas/${form.dataset.id}`, {
          autorizar,
          observacion: form.observacion.value.trim() || null,
          fecha_inicio: form.fecha_inicio.value || null,
        });
        toast(autorizar ? 'Aprobado. Avisamos al director de programa para que envíe la carta.' : 'No aprobado. El estudiante y la empresa verán tu observación.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
