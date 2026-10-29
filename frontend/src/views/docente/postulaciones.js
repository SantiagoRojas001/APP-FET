import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, initials, todayIso } from '../../lib/format.js';
import { badge, emptyState, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const TABS = [
  ['SELECCIONADA', 'Por autorizar'],
  ['ACEPTADA', 'Autorizadas'],
  ['RECHAZADA', 'No autorizadas'],
  ['ENVIADA', 'En la empresa'],
  ['', 'Todas'],
];

function row(p) {
  const s = p.estudiante;
  const hv = s.hoja_vida?.porcentaje ?? 0;

  return html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(s.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre</div></div>
        ${statusBadge(p.estado)}
      </div>
      <dl class="facts">
        <div><dt>Vacante</dt><dd>${p.vacante.titulo}</dd></div>
        <div><dt>Empresa</dt><dd>${p.vacante.empresa.razon_social}</dd></div>
        <div><dt>Postulación</dt><dd>${formatDate(p.fecha)}</dd></div>
      </dl>
      <div class="req">
        ${modalityTag(p.vacante.modalidad, true)}
        ${badge(s.habilitado_para_practica ? 'ok' : 'bad', `${s.semestre_actual}.º semestre ${s.habilitado_para_practica ? 'habilitado' : 'no habilitado'}`)}
        ${badge(hv === 100 ? 'ok' : 'warn', `Hoja de vida ${hv} %`)}
        ${badge(s.proyectos?.length ? 'ok' : 'warn', `${s.proyectos?.length ?? 0} proyectos`)}
        <a class="fine" href="#/estudiantes/${s.id}">Ver perfil completo</a>
      </div>
      ${p.estado.valor === 'SELECCIONADA'
        ? html`<form class="decide" data-id="${p.id}" novalidate>
            <div class="field"><label for="ini${p.id}">Inicio de la práctica</label><input class="input" type="date" id="ini${p.id}" name="fecha_inicio" value="${todayIso()}" min="${todayIso()}" /></div>
            <div class="field"><label for="obs${p.id}">Observación</label><input class="input" id="obs${p.id}" name="observacion" placeholder="Obligatoria si no autorizas" /></div>
            <button class="btn good" type="submit" value="ACEPTADA">${icon('check')}Autorizar y enviar carta</button>
            <button class="btn danger" type="submit" value="RECHAZADA">${icon('x')}No autorizar</button>
          </form>`
        : p.observacion
          ? html`<p class="note-box">Observación: ${p.observacion}</p>`
          : ''}
    </div>
  </article>`;
}

export default async function postulacionesDocenteView({ root }) {
  let estado = 'SELECCIONADA';

  mount(
    root,
    html`${pageHeader('Postulaciones', 'Estudiantes que una empresa seleccionó. Al autorizar quedas como tutor, el estudiante recibe la carta de bienvenida y su hoja de vida se deshabilita.')}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar postulaciones">
        ${TABS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${estado === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>`,
  );
  const list = root.querySelector('#list');

  async function load() {
    const items = await api.get('/docente/postulaciones', { estado });
    mount(list, items.length ? items.map(row) : emptyState('circle-check', 'No hay postulaciones aquí', 'Cuando una empresa seleccione a un estudiante, aparecerá en Por autorizar.'));
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
    const decision = e.submitter?.value;
    withBusy(e.submitter, async () => {
      try {
        await api.patch(`/docente/postulaciones/${form.dataset.id}`, {
          decision,
          observacion: form.observacion.value.trim() || null,
          fecha_inicio: form.fecha_inicio.value || null,
        });
        toast(decision === 'ACEPTADA' ? 'Práctica autorizada. Enviamos la carta de bienvenida.' : 'No autorizada. El estudiante y la empresa verán tu observación.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
