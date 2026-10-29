import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { relativeDays } from '../lib/format.js';
import { emptyState, icon, pageHeader, toast, withBusy } from '../lib/ui.js';
import { refreshBadge } from './shell.js';

/** Avisos del usuario: horas cumplidas, prácticas cerradas, respuestas a reportes… */
export default async function notificacionesView({ root, navigate, refresh }) {
  const { data, no_leidas: unread } = await api.get('/notificaciones');

  mount(
    root,
    html`${pageHeader(
        'Notificaciones',
        unread ? `Tienes ${unread} sin leer.` : 'Estás al día.',
        unread
          ? html`<button class="btn sec" id="all">${icon('check')}Marcar todas como leídas</button>`
          : '',
      )}
      <section class="panel flush">
        ${
          data.length
            ? data.map(
                (n) =>
                  html`<button
                    class="notif${n.leida ? '' : ' unread'}"
                    data-id="${n.id}"
                    data-link="${n.enlace ?? ''}"
                  >
                    <span class="dot" aria-hidden="true"></span>
                    <span
                      ><b>${n.titulo}</b>
                      <p>${n.mensaje}</p></span
                    >
                    <span class="fine">${relativeDays(n.creada_en)}</span>
                  </button>`,
              )
            : emptyState(
                'bell',
                'No tienes notificaciones',
                'Aquí verás los avisos sobre tu práctica y tus reportes.',
              )
        }
      </section>`,
  );

  root.querySelector('#all')?.addEventListener('click', (e) =>
    withBusy(e.currentTarget, async () => {
      await api.patch('/notificaciones/leidas');
      toast('Notificaciones marcadas como leídas.');
      refresh();
    }),
  );

  root.addEventListener('click', async (e) => {
    const item = e.target.closest('[data-id]');
    if (!item) return;
    if (item.classList.contains('unread')) {
      await api.patch(`/notificaciones/${item.dataset.id}/leida`).catch(() => {});
      item.classList.remove('unread');
      refreshBadge();
    }
    if (item.dataset.link) navigate(item.dataset.link);
  });
}
