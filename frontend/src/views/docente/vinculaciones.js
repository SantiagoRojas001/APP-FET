import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { badge, emptyState, hoursMeter, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

const ESTADOS = [
  ['ACTIVA', 'En curso'],
  ['FINALIZADA', 'Finalizadas'],
  ['CANCELADA', 'Terminadas antes'],
  ['', 'Todas'],
];

export default async function vinculacionesDocenteView({ root, navigate }) {
  const filters = { estado: 'ACTIVA', mias: '' };

  mount(
    root,
    html`${pageHeader('Prácticas', 'Avance de horas, documentos y reportes de cada práctica.')}
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por estado">
          ${ESTADOS.map(([v, l]) => html`<button data-estado="${v}" aria-pressed="${filters.estado === v}">${l}</button>`)}
        </div>
        <label class="check"><input type="checkbox" id="mias" /> Solo las que tutoro</label>
      </div>
      <section class="panel flush" id="list"></section>`,
  );

  const list = root.querySelector('#list');

  async function load() {
    const items = await api.get('/docente/vinculaciones', filters);
    mount(
      list,
      items.length
        ? html`<div class="tbl">
            <table>
              <thead>
                <tr>
                  <th>Estudiante</th>
                  <th>Empresa</th>
                  <th>Horas</th>
                  <th>Documentos</th>
                  <th>Reportes</th>
                  <th>Tutor</th>
                  <th><span class="fine">Acción</span></th>
                </tr>
              </thead>
              <tbody>
                ${items.map(
                  (v) =>
                    html`<tr>
                      <td>
                        <b>${v.estudiante.nombre_completo}</b
                        ><small>${v.estudiante.programa.nombre}, ${v.semestre_al_vincular}.º</small>
                      </td>
                      <td>
                        ${v.empresa.razon_social}<small
                          >${v.tipo.etiqueta} desde el ${formatDate(v.fecha_inicio)}</small
                        ><small>${statusBadge(v.estado)}</small>
                      </td>
                      <td>${hoursMeter(v.horas)}</td>
                      <td>
                        ${v.documentos_pendientes ? badge('warn', `${v.documentos_pendientes} por revisar`) : badge('ok', 'Al día')}<small
                          >${v.documentos.length} entregados</small
                        >
                      </td>
                      <td>
                        ${v.reportes ? badge(v.reportes >= v.reportes_para_finalizar ? 'bad' : 'warn', String(v.reportes)) : html`<span class="fine">0</span>`}
                      </td>
                      <td>
                        ${v.tutor ? (v.tutor.id === session.user.id ? 'Tú' : v.tutor.correo) : html`<button class="btn sec sm" data-tutor="${v.id}">Asignarme</button>`}
                      </td>
                      <td><button class="btn sm" data-open="${v.id}">Seguimiento</button></td>
                    </tr>`,
                )}
              </tbody>
            </table>
          </div>`
        : emptyState('users', 'No hay prácticas con este filtro'),
    );
  }

  root.querySelector('#mias').addEventListener('change', (e) => {
    filters.mias = e.target.checked ? '1' : '';
    load();
  });

  root.addEventListener('click', (e) => {
    const estado = e.target.closest('[data-estado]');
    if (estado) {
      filters.estado = estado.dataset.estado;
      root
        .querySelectorAll('[data-estado]')
        .forEach((b) => b.setAttribute('aria-pressed', String(b === estado)));
      return load();
    }
    const open = e.target.closest('[data-open]');
    if (open) return navigate(`/vinculacion/${open.dataset.open}`);
    const tutor = e.target.closest('[data-tutor]');
    if (tutor) {
      withBusy(tutor, async () => {
        try {
          await api.patch(`/docente/vinculaciones/${tutor.dataset.tutor}`, { asignarme_tutor: true });
          toast('Quedaste como docente tutor.');
          load();
        } catch (error) {
          toast(error.message);
        }
      });
    }
  });

  await load();
}
