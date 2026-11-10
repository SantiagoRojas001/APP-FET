import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, initials } from '../../lib/format.js';
import { badge, emptyState, hoursMeter, icon, pageHeader, statusBadge } from '../../lib/ui.js';
import { documentosTabla, wireDocumentos } from '../documentos.js';
import { evidenciasTabla, wireEvidencias } from '../evidencias.js';

/**
 * Practicantes de la empresa. Cada uno se despliega aquí mismo con sus horas
 * semanales (para aprobarlas o devolverlas, con su soporte) y sus entregas
 * (ARL, bitácora, control de asistencia e informes) con vista previa y
 * descarga. El seguimiento completo (evaluación y cierre) sigue en su página.
 */
export default async function practicantesView({ root, navigate }) {
  const items = await api.get('/empresa/vinculaciones');

  const tarjeta = (v) => html`<section class="panel" data-practicante="${v.id}">
    <div class="who">
      <div class="av lg">${initials(v.estudiante.nombre_completo)}</div>
      <div><b>${v.estudiante.nombre_completo}</b><span>${v.vacante?.titulo ?? v.tipo.etiqueta}, desde el ${formatDate(v.fecha_inicio)}</span></div>
      ${statusBadge(v.estado)}
    </div>
    ${hoursMeter(v.horas)}
    <div class="actions">
      ${v.estado.valor === 'ACTIVA' && v.horas.cumplidas_en && !v.evaluaciones.mia ? badge('info', 'Lista para evaluar') : ''}
      ${v.horas.semanas_por_revisar ? badge('warn', `${v.horas.semanas_por_revisar} semanas por aprobar`) : badge('ok', 'Horas al día')}
      <button class="btn sm" data-desplegar="${v.id}" aria-expanded="false" aria-controls="detalle${v.id}">${icon('chevron-down')}Horas y entregas</button>
      <a class="btn sec sm" href="#/estudiantes/${v.estudiante.id}">${icon('file-cv')}Hoja de vida</a>
      <button class="btn sec sm" data-go="/vinculacion/${v.id}">Seguimiento completo</button>
    </div>
    <div class="practicante-detalle" id="detalle${v.id}" hidden></div>
  </section>`;

  mount(
    root,
    html`${pageHeader('Mis practicantes')}
      ${items.length ? items.map(tarjeta) : html`<section class="panel">${emptyState('users', 'No tienes practicantes', 'Cuando el director te envíe la carta de presentación de un estudiante, aparecerá aquí.')}</section>`}`,
  );

  async function desplegar(boton) {
    const id = boton.dataset.desplegar;
    const detalle = root.querySelector(`#detalle${id}`);
    const abierto = boton.getAttribute('aria-expanded') === 'true';
    boton.setAttribute('aria-expanded', String(!abierto));
    detalle.hidden = abierto;
    if (abierto || detalle.dataset.cargado) return;

    detalle.dataset.cargado = '';
    mount(detalle, html`<p class="fine">Cargando…</p>`);
    await cargar(id, detalle);
  }

  // Al aprobar o devolver una semana se recarga solo este practicante, sin cerrar el panel.
  async function cargar(id, detalle) {
    const [v, evidencias] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get(`/vinculaciones/${id}/evidencias`)]);
    mount(
      detalle,
      html`<div class="panel-h">
          <h3>Horas semanales</h3>
          ${v.puede.revisar_evidencias && v.horas.semanas_por_revisar
            ? html`<button class="btn good sm" data-evall="${v.id}">${icon('check')}Aprobar las ${v.horas.semanas_por_revisar} pendientes</button>`
            : ''}
        </div>
        ${evidenciasTabla(evidencias, { revisar: v.puede.revisar_evidencias, vacio: 'El practicante aún no reporta semanas.' })}
        <h3>Entregas</h3>
        ${documentosTabla(v.documentos, { vacio: 'El practicante aún no sube documentos.' })}`,
    );
    if (!detalle.dataset.conectado) {
      detalle.dataset.conectado = '';
      const recargar = () => cargar(id, detalle);
      wireEvidencias(detalle, recargar);
      wireDocumentos(detalle, recargar);
    }
  }

  root.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-desplegar]');
    if (boton) return desplegar(boton);
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });
}
