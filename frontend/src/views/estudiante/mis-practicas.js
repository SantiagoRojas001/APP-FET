import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { badge, emptyState, hoursMeter, modalityTag, pageHeader, statusBadge } from '../../lib/ui.js';

/** Prácticas anteriores del estudiante, con su resultado y la evaluación a la empresa. */
export default async function misPracticasView({ root, navigate }) {
  const items = await api.get('/estudiante/vinculaciones');

  mount(
    root,
    html`${pageHeader('Mis prácticas', 'Pasantías y prácticas que has realizado.')}
    ${
      items.length
        ? items.map(
            (v) =>
              html`<section class="panel">
                <div class="panel-h">
                  <div>
                    <h2>${v.vacante?.titulo ?? v.tipo.etiqueta}</h2>
                    <span class="fine"
                      >${v.empresa.razon_social}, ${formatDate(v.fecha_inicio)} a
                      ${v.fecha_fin ? formatDate(v.fecha_fin) : 'hoy'}</span
                    >
                  </div>
                  ${statusBadge(v.estado)}
                </div>
                <div class="tags">
                  ${modalityTag(v.tipo)}${v.periodo ? html`<span class="tag">Periodo ${v.periodo}</span>` : ''}
                </div>
                ${hoursMeter(v.horas)}
                ${v.observacion_final ? html`<p class="note-box">Observación del docente: ${v.observacion_final}</p>` : ''}
                <div class="actions">
                  <button class="btn sec sm" data-go="/vinculacion/${v.id}">Ver seguimiento</button>
                  ${
                    v.evaluaciones.habilitadas
                      ? v.evaluaciones.mia
                        ? badge('ok', 'Evaluaste la empresa')
                        : html`<button class="btn sm" data-go="/vinculacion/${v.id}/evaluacion">
                            Evaluar la empresa
                          </button>`
                      : ''
                  }
                </div>
              </section>`,
          )
        : html`<section class="panel">
            ${emptyState('certificate', 'Aún no tienes prácticas', 'Cuando una empresa te acepte, tu práctica aparecerá aquí.')}
          </section>`
    }`,
  );

  root.addEventListener('click', (e) => {
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });
}
