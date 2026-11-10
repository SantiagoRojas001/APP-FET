import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDateTime, initials } from '../../lib/format.js';
import { emptyState, icon, pageHeader, statusBadge } from '../../lib/ui.js';
import { session } from '../../session.js';

/**
 * Empresas interesadas: las que revisaron la hoja de vida aprobada y le
 * asignaron una cita al estudiante (puede tener varias).
 */
export default async function citasEstudianteView({ root, navigate }) {
  const citas = await api.get('/estudiante/citas');
  const perfil = session.user.perfil;

  const proximas = citas.filter((c) => c.estado.valor === 'PROGRAMADA' && !c.realizada);
  const resto = citas.filter((c) => !proximas.includes(c));

  const cita = (c) => html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr) auto">
    <div class="logo" aria-hidden="true">${initials(c.empresa.razon_social)}</div>
    <div class="rowi-main">
      <div><h3>${c.empresa.razon_social}</h3><div class="sub">${c.empresa.sector ?? 'Empresa'}${c.vacante ? `, ${c.vacante.titulo}` : ''}</div></div>
      <dl class="facts">
        <div><dt>Fecha y hora</dt><dd>${formatDateTime(c.fecha_hora)}</dd></div>
        <div><dt>Dirección</dt><dd>${c.direccion}</dd></div>
      </dl>
      ${c.indicaciones ? html`<p class="fine">${c.indicaciones}</p>` : ''}
      ${c.observacion && c.estado.valor !== 'PROGRAMADA' ? html`<p class="note-box">${c.observacion}</p>` : ''}
    </div>
    <div class="rowi-act">${statusBadge(c.estado)}</div>
  </article>`;

  mount(
    root,
    html`${pageHeader('Empresas interesadas')}
      ${perfil && perfil.hoja_vida_estado?.valor !== 'APROBADA'
        ? html`<section class="callout">
            ${icon('alert-circle')}
            <p><b>Las empresas aún no ven tu hoja de vida.</b> Estado: ${perfil.hoja_vida_estado?.etiqueta.toLowerCase() ?? 'sin enviar'}. Cuando tu docente la apruebe, las empresas podrán asignarte citas.</p>
            <button class="btn sm" id="toHv">Ir a mi hoja de vida</button>
          </section>`
        : ''}
      <section class="panel"><h2>Próximas citas</h2></section>
      <section class="panel flush">
        ${proximas.length ? proximas.map(cita) : emptyState('calendar-event', 'No tienes citas programadas', 'Cuando una empresa se interese en tu hoja de vida, la verás aquí.')}
      </section>
      ${resto.length
        ? html`<section class="panel"><h2>Historial</h2></section>
            <section class="panel flush">${resto.map(cita)}</section>`
        : ''}`,
  );

  root.querySelector('#toHv')?.addEventListener('click', () => navigate('/hoja-vida/vista'));
}
