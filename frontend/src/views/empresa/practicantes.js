import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, initials } from '../../lib/format.js';
import { badge, emptyState, pageHeader, statusBadge } from '../../lib/ui.js';

export default async function practicantesView({ root, navigate }) {
  const items = await api.get('/empresa/vinculaciones');

  mount(
    root,
    html`${pageHeader('Mis practicantes', 'Revisa los controles de asistencia e informes de cada practicante.')}
      ${items.length
        ? items.map(
            (v) => html`<section class="panel">
              <div class="who">
                <div class="av lg">${initials(v.estudiante.nombre_completo)}</div>
                <div><b>${v.estudiante.nombre_completo}</b><span>${v.vacante?.titulo ?? v.tipo.etiqueta}, desde el ${formatDate(v.fecha_inicio)}</span></div>
                ${statusBadge(v.estado)}
              </div>
              <div class="actions">
                ${v.documentos_pendientes ? badge('warn', `${v.documentos_pendientes} documentos por revisar`) : badge('ok', 'Documentos al día')}
                <button class="btn sm" data-open="${v.id}">Ver seguimiento</button>
              </div>
            </section>`,
          )
        : html`<section class="panel">${emptyState('users', 'No tienes practicantes', 'Cuando aceptes un postulante, aparecerá aquí.')}</section>`}`,
  );

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-open]');
    if (btn) navigate(`/vinculacion/${btn.dataset.open}`);
  });
}
