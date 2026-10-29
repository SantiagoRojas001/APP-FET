import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, formatDateTime, initials } from '../../lib/format.js';
import { confirmInline, emptyState, icon, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';

/**
 * Cartas de presentación: estudiantes del programa que una empresa aceptó
 * después de su cita. Al enviar la carta empieza la práctica, el
 * estudiante recibe la carta de bienvenida y su hoja de vida se deshabilita.
 */
export default async function cartasView({ root, refresh, navigate }) {
  const citas = await api.get('/director/cartas');
  const pendientes = citas.filter((c) => c.estado.valor === 'ACEPTADO');
  const enviadas = citas.filter((c) => c.estado.valor === 'PRESENTADO');

  const row = (c) => html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(c.estudiante.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${c.estudiante.nombre_completo}</h3><div class="sub">${c.estudiante.programa.nombre}, ${c.estudiante.semestre_actual}.º semestre</div></div>
        ${statusBadge(c.estado)}
      </div>
      <dl class="facts">
        <div><dt>Empresa</dt><dd>${c.empresa.razon_social}</dd></div>
        ${c.vacante ? html`<div><dt>Vacante</dt><dd>${c.vacante.titulo}</dd></div>` : ''}
        <div><dt>Cita</dt><dd>${formatDateTime(c.fecha_hora)}</dd></div>
        <div><dt>Inicio</dt><dd>${formatDate(c.fecha_inicio_practica)}</dd></div>
        <div><dt>Tutor (aprobó la hoja de vida)</dt><dd>${c.tutor ?? 'No registra'}</dd></div>
      </dl>
      <div class="actions">
        <button class="btn sec sm" data-file="${c.url_carta}">${icon('download')}Ver carta</button>
        <a class="btn sec sm" href="#/estudiantes/${c.estudiante.id}">${icon('file-cv')}Hoja de vida</a>
        ${c.estado.valor === 'ACEPTADO'
          ? html`<button class="btn sm" data-enviar="${c.id}">${icon('send')}Enviar carta a la empresa</button>`
          : c.vinculacion_id
            ? html`<button class="btn sec sm" data-go="/vinculacion/${c.vinculacion_id}">Ver práctica</button>`
            : ''}
      </div>
    </div>
  </article>`;

  mount(
    root,
    html`${pageHeader('Cartas de presentación', 'Estudiantes de tu programa que una empresa aceptó después de su cita. Al enviar la carta a la empresa empieza la práctica.')}
      <section class="panel"><h2>Por enviar</h2></section>
      <section class="panel flush">${pendientes.length ? pendientes.map(row) : emptyState('send', 'No hay cartas por enviar')}</section>
      ${enviadas.length
        ? html`<section class="panel"><h2>Enviadas</h2></section>
            <section class="panel flush">${enviadas.map(row)}</section>`
        : ''}`,
  );

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) return api.open(file.dataset.file).catch((err) => toast(err.message));
    const go = e.target.closest('[data-go]');
    if (go) return navigate(go.dataset.go);
    const enviar = e.target.closest('[data-enviar]');
    if (enviar) {
      confirmInline(
        enviar,
        'enviar la carta',
        (si) =>
          withBusy(si, async () => {
            try {
              await api.post(`/director/cartas/${enviar.dataset.enviar}`);
              toast('Carta enviada. La práctica quedó registrada y el estudiante recibió la bienvenida.');
              refresh();
            } catch (error) {
              toast(error.message);
            }
          }),
        'Con la carta empieza la práctica y se cierran los demás procesos del estudiante.',
      );
    }
  });
}
