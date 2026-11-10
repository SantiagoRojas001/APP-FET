import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDateTime, initials, todayIso } from '../../lib/format.js';
import { confirmInline, emptyState, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

/**
 * Citas programadas de la empresa. Una vez realizada la cita, la empresa
 * acepta al estudiante (con la fecha de inicio) o lo rechaza. Si lo acepta,
 * recibe la carta de presentación del director del programa y el
 * practicante aparece en Mis practicantes.
 */
export default async function citasEmpresaView({ root }) {
  let citas = (await api.get('/empresa/citas')).filter((c) => c.estado.valor === 'PROGRAMADA');

  const acciones = (c) => {
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
    html`${pageHeader('Citas')}
      <section class="panel flush" id="list"></section>`,
  );

  const list = root.querySelector('#list');
  const draw = () =>
    mount(list, citas.length ? citas.map(row) : emptyState('calendar-event', 'No tienes citas programadas', 'Asigna citas desde Hojas de vida.'));

  // Una cita decidida o cancelada sale de la lista.
  const actualizar = (cita) => {
    citas = citas.filter((c) => c.id !== cita.id);
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
            toast(aceptar ? 'Estudiante aceptado. Recibirás la carta de presentación del director de su programa.' : 'Estudiante rechazado.');
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
