import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDateTime, initials, todayIso } from '../../lib/format.js';
import { confirmInline, emptyState, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const TABS = [
  ['PROGRAMADA', 'Programadas'],
  ['PROCESO', 'En proceso'],
  ['', 'Todas'],
];
const EN_PROCESO = ['ACEPTADO', 'PRESENTADO'];

/**
 * Citas de la empresa. Una vez realizada la cita, la empresa acepta al
 * estudiante (con la fecha de inicio; el director de programa le envía la
 * carta de presentación) o lo rechaza.
 */
export default async function citasEmpresaView({ root, navigate }) {
  const citas = await api.get('/empresa/citas');
  let filtro = 'PROGRAMADA';

  const acciones = (c) => {
    if (c.estado.valor !== 'PROGRAMADA') {
      return c.estado.valor === 'PRESENTADO' && c.vinculacion_id
        ? html`<button class="btn sec sm" data-go="/vinculacion/${c.vinculacion_id}">Ver práctica</button>`
        : '';
    }
    if (!c.realizada) {
      return html`<form class="actions" data-cancelar="${c.id}" novalidate>
        <input class="input" name="motivo" placeholder="Motivo (opcional)" aria-label="Motivo de la cancelación" style="width:auto" />
        <button class="btn danger sm" type="submit">Cancelar cita</button>
      </form>`;
    }
    return html`<form class="form-grid" data-decidir="${c.id}" novalidate>
      <div class="field"><label for="ini${c.id}">Inicio de la práctica (si lo aceptas)</label><input class="input" type="date" id="ini${c.id}" name="fecha_inicio" value="${todayIso()}" min="${todayIso()}" /></div>
      <div class="field"><label for="obs${c.id}">Comentario (opcional)</label><input class="input" id="obs${c.id}" name="observacion" /></div>
      <div class="actions">
        <button class="btn good sm" type="submit" value="1">${icon('user-check')}Aceptar</button>
        <button class="btn danger sm" type="submit" value="0">Rechazar</button>
      </div>
    </form>`;
  };

  const row = (c) => html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(c.estudiante.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${c.estudiante.nombre_completo}</h3><div class="sub">${c.estudiante.programa.nombre}, ${c.estudiante.semestre_actual}.º semestre</div></div>
        ${statusBadge(c.estado)}
      </div>
      <dl class="facts">
        <div><dt>Fecha y hora</dt><dd>${formatDateTime(c.fecha_hora)}</dd></div>
        <div><dt>Dirección</dt><dd>${c.direccion}</dd></div>
        ${c.vacante ? html`<div><dt>Vacante</dt><dd>${c.vacante.titulo}</dd></div>` : ''}
      </dl>
      ${c.observacion ? html`<p class="note-box">${c.observacion}</p>` : ''}
      <div class="actions">
        <a class="btn sec sm" href="#/estudiantes/${c.estudiante.id}">${icon('file-cv')}Hoja de vida</a>
        ${acciones(c)}
      </div>
    </div>
  </article>`;

  mount(
    root,
    html`${pageHeader('Citas', 'Después de cada cita, acepta o rechaza al estudiante. Si lo aceptas, el director de su programa te envía la carta de presentación y empieza la práctica.')}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar citas">
        ${TABS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${filtro === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>`,
  );

  const list = root.querySelector('#list');
  const draw = () => {
    const items = citas.filter((c) => (filtro === 'PROCESO' ? EN_PROCESO.includes(c.estado.valor) : !filtro || c.estado.valor === filtro));
    mount(list, items.length ? items.map(row) : emptyState('calendar-event', 'No hay citas aquí', 'Asigna citas desde Hojas de vida.'));
  };

  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (f) {
      filtro = f.dataset.f;
      root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
      return draw();
    }
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });

  const actualizar = (cita) => {
    citas.splice(citas.findIndex((c) => c.id === cita.id), 1, { ...citas.find((c) => c.id === cita.id), ...cita });
    draw();
  };

  list.addEventListener('submit', (e) => {
    e.preventDefault();
    const decidir = e.target.closest('[data-decidir]');
    if (decidir) {
      const aceptar = e.submitter?.value === '1';
      const enviar = (boton) =>
        withBusy(boton, async () => {
          try {
            actualizar(
              await api.post(`/empresa/citas/${decidir.dataset.decidir}/decision`, {
                aceptar,
                observacion: decidir.observacion.value.trim() || null,
                fecha_inicio: aceptar ? decidir.fecha_inicio.value || null : null,
              }),
            );
            toast(aceptar ? 'Estudiante aceptado. El director de su programa te enviará la carta de presentación.' : 'Estudiante rechazado.');
          } catch (error) {
            showFieldErrors(decidir, error.errors) || toast(error.message);
          }
        });
      return aceptar ? enviar(e.submitter) : confirmInline(e.submitter, 'rechazar', enviar);
    }
    const cancelar = e.target.closest('[data-cancelar]');
    if (cancelar) {
      confirmInline(cancelar.querySelector('[type=submit]'), 'cancelar la cita', (si) =>
        withBusy(si, async () => {
          try {
            actualizar(await api.post(`/empresa/citas/${cancelar.dataset.cancelar}/cancelacion`, { motivo: cancelar.motivo.value.trim() || null }));
            toast('Cita cancelada. Le avisamos al estudiante.');
          } catch (error) {
            toast(error.message);
          }
        }),
      );
    }
  });

  draw();
}
