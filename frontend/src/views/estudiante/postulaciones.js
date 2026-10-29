import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { emptyState, modalityTag, pageHeader, statusBadge } from '../../lib/ui.js';

export default async function postulacionesView({ root, navigate }) {
  const items = await api.get('/estudiante/postulaciones');

  mount(
    root,
    html`${pageHeader('Mis postulaciones', 'Tu hoja de vida llega directo a la empresa. Si te selecciona, tu docente autoriza la práctica y recibes la carta de bienvenida.')}
      ${items.length
        ? html`<section class="panel flush"><div class="tbl"><table>
            <thead><tr><th>Vacante</th><th>Modalidad</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
              ${items.map(
                (p) => html`<tr>
                  <td><b>${p.vacante.titulo}</b><small>${p.vacante.empresa.razon_social}</small></td>
                  <td>${modalityTag(p.vacante.modalidad)}</td>
                  <td class="num">${formatDate(p.fecha)}</td>
                  <td>${statusBadge(p.estado)}${p.observacion ? html`<small>${p.observacion}</small>` : ''}</td>
                </tr>`,
              )}
            </tbody></table></div></section>`
        : html`<section class="panel">${emptyState('inbox', 'Aún no te has postulado', 'Elige una vacante y aplica desde la sección Vacantes.', html`<button class="btn sec" id="toVac">Ver vacantes</button>`)}</section>`}`,
  );

  root.querySelector('#toVac')?.addEventListener('click', () => navigate('/vacantes'));
}
