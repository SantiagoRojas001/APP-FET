import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import {
  badge,
  emptyState,
  errorBox,
  icon,
  pageHeader,
  showFieldErrors,
  toast,
  withBusy,
} from '../../lib/ui.js';

/**
 * Periodos académicos. Cada práctica queda en el periodo de su fecha de
 * inicio. Al cerrar un periodo se cierran las vacantes abiertas.
 */
export default async function periodosView({ root, refresh }) {
  const periodos = await api.get('/admin/periodos');

  mount(
    root,
    html`${pageHeader('Periodos académicos', 'Agrupan las prácticas para los informes. Al cerrar un periodo se cierran las vacantes abiertas y las prácticas nuevas esperan al siguiente.')}
      <section class="panel">
        <h2>Nuevo periodo</h2>
        <form id="perForm" class="form-grid" novalidate>
          <div class="field">
            <label for="nombre">Nombre</label
            ><input class="input" id="nombre" name="nombre" placeholder="2027-1" />
          </div>
          <div class="field">
            <label for="fecha_inicio">Inicio</label
            ><input class="input" type="date" id="fecha_inicio" name="fecha_inicio" />
          </div>
          <div class="field">
            <label for="fecha_fin">Fin</label
            ><input class="input" type="date" id="fecha_fin" name="fecha_fin" />
          </div>
          <div><button class="btn" type="submit">${icon('plus')}Crear periodo</button></div>
        </form>
        <div id="perError"></div>
      </section>
      <section class="panel flush">
        ${
          periodos.length
            ? html`<div class="tbl">
                <table>
                  <thead>
                    <tr>
                      <th>Periodo</th>
                      <th>Fechas</th>
                      <th>Prácticas</th>
                      <th>Estado</th>
                      <th><span class="fine">Acción</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    ${periodos.map(
                      (p) =>
                        html`<tr>
                          <td><b>${p.nombre}</b></td>
                          <td class="num">${formatDate(p.fecha_inicio)} a ${formatDate(p.fecha_fin)}</td>
                          <td class="num">${p.vinculaciones}</td>
                          <td>
                            ${p.cerrado_en ? badge('neutral', `Cerrado el ${formatDate(p.cerrado_en)}`) : badge('ok', 'Abierto')}
                          </td>
                          <td>
                            ${p.cerrado_en ? '' : html`<button class="btn danger sm" data-close="${p.id}" data-name="${p.nombre}">Cerrar periodo</button>`}
                          </td>
                        </tr>`,
                    )}
                  </tbody>
                </table>
              </div>`
            : emptyState(
                'calendar-event',
                'No hay periodos',
                'Crea el periodo en curso para agrupar las prácticas.',
              )
        }
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-close]');
    if (!btn || !confirm(`¿Cerrar el periodo ${btn.dataset.name}? Se cerrarán todas las vacantes abiertas.`))
      return;
    withBusy(btn, async () => {
      try {
        await api.post(`/admin/periodos/${btn.dataset.close}/cierre`);
        toast('Periodo cerrado.');
        refresh();
      } catch (error) {
        toast(error.message);
      }
    });
  });

  const form = root.querySelector('#perForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/admin/periodos', {
          nombre: form.nombre.value.trim(),
          fecha_inicio: form.fecha_inicio.value,
          fecha_fin: form.fecha_fin.value,
        });
        toast('Periodo creado.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#perError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
