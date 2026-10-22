import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { emptyState, modalityTag, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';

export default async function empresasView({ root, refresh }) {
  const items = await api.get('/admin/empresas');

  mount(
    root,
    html`${pageHeader('Empresas', 'Solo las empresas validadas pueden ingresar y publicar vacantes.')}
      <section class="panel flush">
        ${items.length
          ? html`<div class="tbl"><table>
              <thead><tr><th>Empresa</th><th>NIT</th><th>Contacto</th><th>Interés</th><th>Estado</th><th><span class="fine">Acción</span></th></tr></thead>
              <tbody>${items.map(
                (c) => html`<tr>
                  <td><b>${c.razon_social}</b><small>${c.sector ?? ''}</small></td>
                  <td class="num mono">${c.nit}</td>
                  <td>${c.contacto_nombre ?? '—'}<small>${c.correo} · ${c.contacto_telefono ?? ''}</small></td>
                  <td>${modalityTag(c.modalidad_interes)}</td>
                  <td>${statusBadge(c.estado_validacion)}</td>
                  <td>${c.estado_validacion.valor === 'PENDIENTE'
                    ? html`<div class="actions">
                        <button class="btn good sm" data-id="${c.id}" data-estado="VALIDADA">Validar</button>
                        <button class="btn danger sm" data-id="${c.id}" data-estado="RECHAZADA">Rechazar</button>
                      </div>`
                    : ''}</td>
                </tr>`,
              )}</tbody></table></div>`
          : emptyState('building', 'No hay empresas registradas')}
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-estado]');
    if (!btn) return;
    withBusy(btn, async () => {
      await api.patch(`/admin/empresas/${btn.dataset.id}/validacion`, { estado_validacion: btn.dataset.estado });
      toast(btn.dataset.estado === 'VALIDADA' ? 'Empresa validada. Ya puede ingresar.' : 'Registro rechazado.');
      refresh();
    });
  });
}
