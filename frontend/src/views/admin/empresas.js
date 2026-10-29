import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import {
  badge,
  emptyState,
  errorBox,
  icon,
  modalityTag,
  pageHeader,
  showFieldErrors,
  statusBadge,
  toast,
  withBusy,
} from '../../lib/ui.js';
import { empresaData, empresaFields } from './empresa-form.js';

/**
 * Las empresas entran por invitación de la FET: quedan validadas y reciben un
 * correo para activar su cuenta. Desde aquí también se editan y se suspenden.
 */
export default async function empresasView({ root, navigate, refresh }) {
  const items = await api.get('/admin/empresas');

  const acciones = (c) =>
    html`<div class="actions">
      ${
        c.estado_validacion.valor === 'VALIDADA'
          ? html`<button class="btn danger sm" data-id="${c.id}" data-estado="RECHAZADA">Suspender</button>`
          : html`<button class="btn good sm" data-id="${c.id}" data-estado="VALIDADA">
              ${c.estado_validacion.valor === 'PENDIENTE' ? 'Validar' : 'Reactivar'}
            </button>`
      }
      ${c.pendiente_activacion ? html`<button class="btn sec sm" data-invite="${c.usuario_id}">${icon('send')}Reenviar invitación</button>` : ''}
      <button class="btn sec sm" data-edit="${c.id}">${icon('pencil')}Editar</button>
    </div>`;

  mount(
    root,
    html`${pageHeader('Empresas', 'Solo las empresas validadas pueden ingresar y publicar vacantes.')}
      <section class="panel">
        <h2>Invitar empresa</h2>
        <form id="empForm" class="form-grid" novalidate>
          ${empresaFields({}, { conCorreo: true })}
          <div><button class="btn" type="submit">${icon('send')}Crear e invitar</button></div>
        </form>
        <div id="empError"></div>
      </section>
      <section class="panel flush">
        ${
          items.length
            ? html`<div class="tbl">
                <table>
                  <thead>
                    <tr>
                      <th>Empresa</th>
                      <th>NIT</th>
                      <th>Contacto</th>
                      <th>Interés</th>
                      <th>Estado</th>
                      <th><span class="fine">Acciones</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    ${items.map(
                      (c) =>
                        html`<tr>
                          <td><b>${c.razon_social}</b><small>${c.sector ?? ''}</small></td>
                          <td class="num mono">${c.nit}</td>
                          <td>
                            ${c.contacto_nombre ?? '—'}<small
                              >${c.correo} · ${c.contacto_telefono ?? ''}</small
                            >
                          </td>
                          <td>${modalityTag(c.modalidad_interes)}</td>
                          <td>
                            ${statusBadge(c.estado_validacion)}${c.pendiente_activacion ? html`<small>${badge('warn', 'Invitación pendiente')}</small>` : ''}
                          </td>
                          <td>${acciones(c)}</td>
                        </tr>`,
                    )}
                  </tbody>
                </table>
              </div>`
            : emptyState('building', 'No hay empresas registradas')
        }
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-edit]');
    if (edit) return navigate(`/empresas/${edit.dataset.edit}`);
    const invite = e.target.closest('[data-invite]');
    if (invite) {
      return withBusy(invite, async () => {
        try {
          await api.post(`/admin/usuarios/${invite.dataset.invite}/invitacion`);
          toast('Invitación enviada de nuevo.');
        } catch (error) {
          toast(error.message);
        }
      });
    }
    const btn = e.target.closest('[data-estado]');
    if (!btn) return;
    if (
      btn.dataset.estado === 'RECHAZADA' &&
      !confirm('¿Suspender la empresa? Se cerrarán sus sesiones y no podrá ingresar.')
    )
      return;
    withBusy(btn, async () => {
      await api.patch(`/admin/empresas/${btn.dataset.id}/validacion`, {
        estado_validacion: btn.dataset.estado,
      });
      toast(
        btn.dataset.estado === 'VALIDADA' ? 'Empresa habilitada. Ya puede ingresar.' : 'Empresa suspendida.',
      );
      refresh();
    });
  });

  const form = root.querySelector('#empForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/admin/empresas', empresaData(form));
        toast('Empresa creada. Le enviamos la invitación a su correo.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#empError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
