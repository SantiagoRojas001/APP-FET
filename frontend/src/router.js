import { api } from './api/client.js';
import { mount } from './lib/html.js';
import { deviceTheme, mountThemeSwitch } from './lib/theme.js';
import { errorBox, skeleton } from './lib/ui.js';
import { ROUTES, SHARED_ROUTES } from './routes.js';
import { session } from './session.js';
import { renderShell } from './views/shell.js';

const PUBLIC = {
  '/login': () => import('./views/login.js'),
  '/recuperar': () => import('./views/recuperar.js'),
  '/restablecer': () => import('./views/restablecer.js'),
  '/activar': () => import('./views/activar.js'),
};

/** Rutas públicas sin # (las que se comparten como enlace directo). */
const PUBLIC_PATHS = {
  '/empresasRegistro': () => import('./views/registro-empresa.js'),
};

/** Ruta del hash sin parámetros: '#/restablecer?token=x' → '/restablecer'. */
export const currentPath = () => location.hash.replace(/^#/, '').split('?')[0] || '/';

/** Parámetros del hash: '#/restablecer?token=x' → URLSearchParams { token: 'x' }. */
export const currentQuery = () => new URLSearchParams(location.hash.split('?')[1] ?? '');

export function navigate(path) {
  if (currentPath() === path) render();
  else location.hash = path;
}

function resolve(path) {
  const own = ROUTES[session.environment] ?? [];
  const match = own.find((r) => r.path === path);
  if (match) return { load: match.load, params: [] };

  for (const route of SHARED_ROUTES) {
    const m = path.match(route.pattern);
    if (m && (!route.roles || route.roles.includes(session.role))) return { load: route.load, params: m.slice(1) };
  }
  return null;
}

let renderId = 0;
let lastCheck = 0;

/**
 * Si una empresa acepta al estudiante (o termina su práctica) mientras tiene
 * la sesión abierta, su entorno cambia. Se revisa como máximo cada 30 s.
 */
async function refreshStudentEnvironment() {
  if (session.role !== 'ESTUDIANTE' || Date.now() - lastCheck < 30_000) return;
  lastCheck = Date.now();
  try {
    session.setUser(await api.get('/auth/yo'));
  } catch {
    // Si falla, se sigue con los datos que ya había.
  }
}

/**
 * Dibuja la vista de la ruta actual. Cada render crea un contenedor nuevo,
 * así los eventos de la vista anterior no se acumulan.
 */
export async function render() {
  const id = ++renderId;
  const path = currentPath();
  const app = document.getElementById('app');

  // Fuera de la app (ingreso y recuperación) el selector de tema flota arriba a la derecha;
  // dentro, vive en el menú lateral.
  const themeFloat = document.getElementById('themeFloat');
  themeFloat.hidden = Boolean(session.user);
  if (!session.user) mountThemeSwitch(themeFloat, deviceTheme);

  // El registro de empresas se abre en /empresasRegistro, con o sin sesión.
  const publicPath = PUBLIC_PATHS[location.pathname];
  if (publicPath) {
    themeFloat.hidden = false;
    mountThemeSwitch(themeFloat, deviceTheme);
    const { default: view } = await publicPath();
    if (id === renderId) view({ root: app, navigate, query: currentQuery() });
    return;
  }

  if (!session.user) {
    const load = PUBLIC[path] ?? PUBLIC['/login'];
    const { default: view } = await load();
    if (id === renderId) view({ root: app, navigate, query: currentQuery() });
    return;
  }

  await refreshStudentEnvironment();
  if (id !== renderId) return;

  const route = resolve(path);
  if (!route) return navigate(ROUTES[session.environment][0].path);

  const main = renderShell(app, path, navigate);
  mount(main, skeleton());

  const ctx = {
    root: main,
    params: route.params,
    navigate,
    refresh: () => id === renderId && render(),
  };

  try {
    const { default: view } = await route.load();
    if (id !== renderId) return;
    await view(ctx);
  } catch (error) {
    if (id !== renderId) return;
    console.error(error);
    mount(main, errorBox(error.message || 'No se pudo cargar esta sección.'));
  }
  main.classList.add('fade');
}

export function startRouter() {
  window.addEventListener('hashchange', render);
  render();
}
