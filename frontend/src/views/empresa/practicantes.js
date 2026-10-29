import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, initials } from '../../lib/format.js';
import { badge, emptyState, hoursMeter, pageHeader, statusBadge } from '../../lib/ui.js';

export default async function practicantesView({ root, navigate }) {
  const items = await api.get('/empresa/vinculaciones');

  mount(
    root,
    html`${pageHeader('Mis practicantes', 'Aprueba las horas semanales de cada practicante. Cuando complete sus horas, evalúalo: con tu evaluación la práctica culmina.')}
      ${items.length
        ? items.map(
            (v) => html`<section class="panel">
              <div class="who">
                <div class="av lg">${initials(v.estudiante.nombre_completo)}</div>
                <div><b>${v.estudiante.nombre_completo}</b><span>${v.vacante?.titulo ?? v.tipo.etiqueta}, desde el ${formatDate(v.fecha_inicio)}</span></div>
                ${statusBadge(v.estado)}
              </div>
              ${hoursMeter(v.horas)}
              <div class="actions">
                ${v.estado.valor === 'ACTIVA' && v.horas.cumplidas_en && !v.evaluaciones.mia ? badge('info', 'Lista para evaluar') : ''}
                ${v.horas.semanas_por_revisar ? badge('warn', `${v.horas.semanas_por_revisar} semanas por aprobar`) : badge('ok', 'Horas al día')}
                <button class="btn sm" data-open="${v.id}">${v.horas.semanas_por_revisar ? 'Aprobar horas' : 'Ver seguimiento'}</button>
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
