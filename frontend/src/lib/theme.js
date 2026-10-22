import { html, mount } from './html.js';
import { icon } from './ui.js';

/**
 * Tema visual. Hay dos preferencias:
 * - la de la cuenta (usuario.tema en la API), que manda dentro de la app;
 * - la del equipo (localStorage), que se usa en las pantallas de ingreso.
 * La de la cuenta también se copia en localStorage para aplicarla antes de
 * pintar la página al recargar (ver el script del <head> en index.html).
 */
const DEVICE_KEY = 'appfet.tema';
const USER_KEY = 'appfet.tema.usuario';
const VALID = ['light', 'dark', 'system'];

export const THEMES = [
  ['light', 'Claro', 'sun'],
  ['dark', 'Oscuro', 'moon'],
  ['system', 'Sistema', 'device-desktop'],
];

const read = (key) => {
  try {
    const value = localStorage.getItem(key);
    return VALID.includes(value) ? value : 'system';
  } catch {
    return 'system';
  }
};

const write = (key, value) => {
  try {
    if (value === null) localStorage.removeItem(key);
    else localStorage.setItem(key, value);
  } catch {
    // Sin almacenamiento (navegación privada): el tema aplica solo a esta visita.
  }
};

export function applyTheme(theme) {
  if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme;
  else delete document.documentElement.dataset.theme;
}

/** Preferencia del equipo, para las pantallas fuera de sesión. */
export const deviceTheme = {
  get: () => read(DEVICE_KEY),
  set: (theme) => {
    write(DEVICE_KEY, theme === 'system' ? null : theme);
    applyTheme(theme);
  },
};

/** Aplica el tema de la cuenta al iniciar sesión, o el del equipo al cerrarla. */
export function syncThemeWithUser(user) {
  if (user) {
    write(USER_KEY, user.tema ?? 'system');
    applyTheme(user.tema ?? 'system');
  } else {
    write(USER_KEY, null);
    applyTheme(deviceTheme.get());
  }
}

const bound = new WeakSet();

/**
 * Dibuja el selector de tema en `container`. `store` decide de dónde se lee
 * y dónde se guarda la preferencia ({ get(), set(theme) }).
 * Se puede llamar varias veces sobre el mismo contenedor.
 */
export function mountThemeSwitch(container, store) {
  container.themeStore = store;

  const draw = () => {
    const current = container.themeStore.get();
    mount(
      container,
      html`<div class="theme-switch" role="radiogroup" aria-label="Tema de la página">
        ${THEMES.map(
          ([value, label, iconName]) =>
            html`<button type="button" role="radio" data-theme-value="${value}" aria-checked="${current === value}" title="Tema ${label.toLowerCase()}">
              ${icon(iconName)}<span>${label}</span>
            </button>`,
        )}
      </div>`,
    );
  };

  if (!bound.has(container)) {
    bound.add(container);
    container.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-theme-value]');
      if (!btn) return;
      container.themeStore.set(btn.dataset.themeValue);
      draw();
      container.querySelector(`[data-theme-value="${btn.dataset.themeValue}"]`)?.focus();
    });
  }

  draw();
}
