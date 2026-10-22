import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { badge, emptyState, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

export default async function vinculacionesDocenteView({ root, navigate, refresh }) {
  const items = await api.get('/docente/vinculaciones');

  mount(
    root,
    html`${pageHeader('Prácticas en curso', 'Revisa los documentos de seguimiento y cierra cada práctica al finalizar.')}
      <section class="panel flush">
        ${items.length
          ? html`<div class="tbl"><table>
              <thead><tr><th>Estudiante</th><th>Empresa</th><th>Inicio</th><th>Documentos</th><th>Tutor</th><th><span class="fine">Acción</span></th></tr></thead>
              <tbody>${items.map(
                (v) => html`<tr>
                  <td><b>${v.estudiante.nombre_completo}</b><small>${v.estudiante.programa.nombre}, ${v.semestre_al_vincular}.º</small></td>
                  <td>${v.empresa.razon_social}<small>${v.tipo.etiqueta}</small></td>
                  <td class="num">${formatDate(v.fecha_inicio)}<small>${statusBadge(v.estado)}</small></td>
                  <td>${v.documentos_pendientes ? badge('warn', `${v.documentos_pendientes} por revisar`) : badge('ok', 'Al día')}<small>${v.documentos.length} entregados</small></td>
                  <td>${v.tutor ? (v.tutor.id === session.user.id ? 'Tú' : v.tutor.correo) : html`<button class="btn sec sm" data-tutor="${v.id}">Asignarme</button>`}</td>
                  <td><button class="btn sm" data-open="${v.id}">Seguimiento</button></td>
                </tr>`,
              )}</tbody></table></div>`
          : emptyState('users', 'No hay prácticas registradas')}
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const open = e.target.closest('[data-open]');
    if (open) return navigate(`/vinculacion/${open.dataset.open}`);
    const tutor = e.target.closest('[data-tutor]');
    if (tutor) {
      withBusy(tutor, async () => {
        try {
          await api.patch(`/docente/vinculaciones/${tutor.dataset.tutor}`, { asignarme_tutor: true });
          toast('Quedaste como docente tutor.');
          refresh();
        } catch (error) {
          toast(error.message);
        }
      });
    }
  });
}
