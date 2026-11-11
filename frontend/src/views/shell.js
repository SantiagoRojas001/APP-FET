import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { icon } from '../lib/ui.js';
import { ROUTES } from '../routes.js';
import { session } from '../session.js';
import { mountPerfil } from './perfil.js';

/**
 * Estructura de la app autenticada: menú lateral (marca y opciones) y área
 * principal. El logo siempre lleva al inicio: la primera opción del menú.
 * La campana y el perfil se montan en el encabezado de cada página, en
 * cuanto aparece su hueco `[data-perfil-slot]`. Devuelve el contenedor donde
 * se dibuja la vista.
 */
export function renderShell(app, path, navigate) {
  const items = ROUTES[session.environment];

  mount(
    app,
    html`<div class="app">
      <aside class="side" aria-label="Navegación">
        <a class="brand" href="#${items[0].path}" aria-label="APP FET, ir al inicio">
          <img class="brand-logo logo-claro" src="/logo-app-fet-claro.png" alt="APP FET, Prácticas y Pasantías" /><img class="brand-logo logo-oscuro" src="/logo-app-fet-oscuro.png" alt="APP FET, Prácticas y Pasantías" />
        </a>
        <nav class="nav">
          ${items.filter((r) => !r.hidden).map(
            (r) =>
              html`<button data-path="${r.path}" aria-current="${path === r.path || path.startsWith(`${r.path}/`) ? 'page' : 'false'}">
                ${icon(r.icon)}<span>${r.label}</span><span></span>
              </button>`,
          )}
        </nav>
      </aside>
      <main class="main"><div class="main-in" id="view"></div></main>
    </div>`,
  );

  app.querySelector('.nav').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-path]');
    if (btn) {
      navigate(btn.dataset.path);
      window.scrollTo({ top: 0 });
    }
  });

  const view = app.querySelector('#view');

  // Las vistas dibujan su encabezado con pageHeader; aquí se llena su hueco.
  const montarPerfil = () => {
    const slot = view.querySelector('[data-perfil-slot]:empty');
    if (!slot) return;
    mountPerfil(slot, { path, navigate });
    refreshBadge();
  };
  new MutationObserver(montarPerfil).observe(view, { childList: true, subtree: true });

  return view;
}

/** Actualiza el contador de avisos sin leer de la campana del encabezado. */
export async function refreshBadge() {
  const el = document.querySelector('[data-badge]');
  if (!el) return;
  const bell = el.closest('.bell');
  try {
    const { no_leidas: n } = await api.get('/notificaciones/no-leidas');
    el.textContent = n > 99 ? '99+' : String(n);
    el.hidden = n === 0;
    bell?.setAttribute('aria-label', n ? `Notificaciones, ${n} sin leer` : 'Notificaciones');
  } catch {
    el.hidden = true;
  }
}
