import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { initials } from '../lib/format.js';
import { applyTheme, mountThemeSwitch, syncThemeWithUser } from '../lib/theme.js';
import { icon, toast } from '../lib/ui.js';
import { ROUTES } from '../routes.js';
import { session } from '../session.js';

function subtitle(user) {
  if (user.estudiante) return `${user.estudiante.programa?.nombre ?? ''}, ${user.estudiante.semestre_actual}.º semestre`;
  return user.rol.etiqueta;
}

/**
 * Estructura de la app autenticada: menú lateral y área principal.
 * Devuelve el contenedor donde se dibuja la vista.
 */
export function renderShell(app, path, navigate) {
  const user = session.user;
  const items = ROUTES[session.environment];

  mount(
    app,
    html`<div class="app">
      <aside class="side" aria-label="Navegación">
        <div class="brand">
          <img class="brand-logo" src="/logo-fet-sello.png" alt="APP-FET" />
          <small>Pasantías y prácticas</small>
        </div>
        <div class="me">
          <div class="av">${initials(user.nombre)}</div>
          <div><b>${user.nombre}</b><span>${subtitle(user)}</span></div>
        </div>
        <nav class="nav">
          ${items.filter((r) => !r.hidden).map(
            (r) =>
              html`<button data-path="${r.path}" aria-current="${path === r.path || path.startsWith(`${r.path}/`) ? 'page' : 'false'}">
                ${icon(r.icon)}<span>${r.label}</span><span></span>
              </button>`,
          )}
        </nav>
        <div class="side-foot">
          <div id="themeSwitch"></div>
          <button id="logout">${icon('logout')}Cerrar sesión</button>
        </div>
      </aside>
      <main class="main"><div class="main-in" id="view"></div></main>
    </div>`,
  );

  // El tema de la sesión se guarda en la cuenta del usuario.
  mountThemeSwitch(app.querySelector('#themeSwitch'), {
    get: () => session.user.tema ?? 'system',
    set: (tema) => {
      const anterior = session.user.tema;
      session.user.tema = tema;
      syncThemeWithUser(session.user);
      api.patch('/auth/preferencias', { tema }).catch(() => {
        session.user.tema = anterior;
        applyTheme(anterior);
        toast('No se pudo guardar el tema. Inténtalo de nuevo.');
      });
    },
  });

  app.querySelector('.nav').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-path]');
    if (btn) {
      navigate(btn.dataset.path);
      window.scrollTo({ top: 0 });
    }
  });

  app.querySelector('#logout').addEventListener('click', async () => {
    await api.post('/auth/logout').catch(() => {});
    session.clear();
    session.emit();
  });

  return app.querySelector('#view');
}
