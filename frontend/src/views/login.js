import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, withBusy } from '../lib/ui.js';
import { session } from '../session.js';
import { publicFooter } from './auth-layout.js';

// Cuentas creadas por DemoSeeder (solo en desarrollo). Contraseña: demo1234.
const DEMO = import.meta.env.DEV
  ? [
      [
        'Estudiantes',
        [
          ['mateo.suarez@fet.edu.co', 'Mateo Suárez', 'Hoja de vida aprobada, con citas'],
          ['julian.castro@fet.edu.co', 'Julián Castro', 'Primer ingreso'],
          ['andres.rojas@fet.edu.co', 'Andrés Rojas', 'En pasantía'],
        ],
      ],
      [
        'FET y empresa',
        [
          ['p.vargas@fet.edu.co', 'Patricia Vargas', 'Docente'],
          ['direccion.software@fet.edu.co', 'Dirección de Ingeniería de Software', 'Director de programa'],
          ['talento@nododigital.co', 'Nodo Digital', 'Empresa con convenio'],
          ['admin@fet.edu.co', 'Administración', 'Configuración general'],
        ],
      ],
    ]
  : [];

export default function loginView({ root, navigate }) {
  mount(
    root,
    html`<div class="login-wrap"><div class="login">
      <section class="l-art" aria-label="Presentación">
        <a class="l-logo" href="/#/" aria-label="APP-FET, ir al inicio"><img src="/logo-fet.png" alt="APP-FET, Agencia para Pasantes y Practicantes. Fundación Escuela Tecnológica de Neiva" /></a>
        <div class="l-body">
          <div class="l-copy">
            <h1>Tu pasantía, de la hoja de vida al informe final</h1>
            <p>Hoja de vida, citas con empresas, aprobación de la FET y seguimiento en un solo lugar.</p>
          </div>
        </div>
      </section>
      <section class="l-form">
        <form class="l-card" id="loginForm" novalidate>
          <div style="display:flex;flex-direction:column;gap:6px">
            <h2>Ingresar</h2>
            <p class="muted">Usa tu correo institucional o el correo registrado de tu empresa.</p>
          </div>
          <div class="field">
            <label for="correo">Correo</label>
            <div class="input-ic">${icon('mail')}<input class="input" id="correo" name="correo" type="email" autocomplete="username" required /></div>
          </div>
          <div class="field">
            <label for="password">Contraseña</label>
            <div class="input-ic">${icon('lock')}<input class="input" id="password" name="password" type="password" autocomplete="current-password" required /></div>
          </div>
          <p class="forgot"><button type="button" class="link" id="toRecuperar">¿Olvidaste tu contraseña?</button></p>
          <div id="loginError"></div>
          <button class="btn block" type="submit">Ingresar ${icon('arrow-right')}</button>
          <p class="help">¿Representas a una empresa? <a class="link" href="/empresasRegistro">Registra tu empresa</a> para publicar vacantes.</p>
          ${DEMO.length
            ? html`<div class="demo-groups" aria-label="Cuentas de ejemplo">
                  ${DEMO.map(
                    ([group, list]) => html`<div>
                      <h3>${group}</h3>
                      <ul>
                        ${list.map(
                          ([email, name, desc]) =>
                            html`<li><button type="button" class="demo" data-demo="${email}"><b>${name}</b><span>${desc}</span></button></li>`,
                        )}
                      </ul>
                    </div>`,
                  )}
                </div>
                <p class="help">Cuentas de ejemplo (solo en desarrollo). Contraseña: demo1234.</p>`
            : ''}
        </form>
      </section>
    </div>
    ${publicFooter()}</div>`,
  );

  const form = root.querySelector('#loginForm');

  root.querySelector('#toRecuperar').addEventListener('click', () => navigate('/recuperar'));

  root.querySelector('.demo-groups')?.addEventListener('click', (e) => {
    const demo = e.target.closest('[data-demo]');
    if (!demo) return;
    form.correo.value = demo.dataset.demo;
    form.password.value = 'demo1234';
    form.querySelector('[type=submit]').focus();
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const errorEl = root.querySelector('#loginError');
    if (!form.correo.value || !form.password.value) {
      mount(errorEl, errorBox('Escribe tu correo y tu contraseña.'));
      return;
    }
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        const { token, usuario } = await api.post('/auth/login', {
          correo: form.correo.value.trim(),
          password: form.password.value,
        });
        mount(errorEl, '');
        session.start(token, usuario);
      } catch (error) {
        mount(errorEl, errorBox(error.errors?.correo?.[0] ?? error.message));
      }
    });
  });
}
