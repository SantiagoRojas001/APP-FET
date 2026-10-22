import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, toast, withBusy } from '../lib/ui.js';
import { authCard } from './auth-layout.js';

/**
 * Paso 2 de la recuperación: se abre desde el enlace del correo
 * (#/restablecer?token=...&correo=...) y define la contraseña nueva.
 */
export default function restablecerView({ root, navigate, query }) {
  const token = query.get('token');
  const correo = query.get('correo');

  if (!token || !correo) {
    mount(
      root,
      authCard(html`<div class="auth-done">
        <div class="auth-icon warn">${icon('alert-circle')}</div>
        <h2>Enlace incompleto</h2>
        <p class="muted">Abre el enlace tal como llegó a tu correo, o solicita uno nuevo.</p>
        <button class="btn block" type="button" id="again">Solicitar un enlace nuevo</button>
      </div>`),
    );
    root.querySelector('#again').addEventListener('click', () => navigate('/recuperar'));
    return;
  }

  mount(
    root,
    authCard(html`<form id="resForm" class="auth-form" novalidate>
      <div class="auth-head">
        <h2>Crea tu contraseña nueva</h2>
        <p class="muted">Para la cuenta <b>${correo}</b>.</p>
      </div>
      <div class="field">
        <label for="password">Contraseña nueva</label>
        <div class="input-ic">${icon('lock')}<input class="input" id="password" name="password" type="password" autocomplete="new-password" required autofocus /></div>
        <span class="help">Mínimo 8 caracteres, con letras y números.</span>
      </div>
      <div class="field">
        <label for="password_confirmation">Confirmar contraseña</label>
        <div class="input-ic">${icon('lock')}<input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></div>
      </div>
      <div id="resError"></div>
      <button class="btn block" type="submit">Guardar contraseña</button>
      <p class="auth-links"><button type="button" class="link" id="toRec">Solicitar un enlace nuevo</button></p>
    </form>`),
  );

  root.querySelector('#toRec').addEventListener('click', () => navigate('/recuperar'));

  const form = root.querySelector('#resForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const errorEl = root.querySelector('#resError');
    if (form.password.value !== form.password_confirmation.value) {
      showFieldErrors(form, { password_confirmation: ['Las contraseñas no coinciden.'] });
      return;
    }
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        const { message } = await api.post('/auth/restablecer-contrasena', {
          token,
          correo,
          password: form.password.value,
          password_confirmation: form.password_confirmation.value,
        });
        toast(message);
        navigate('/login');
      } catch (error) {
        mount(errorEl, errorBox(error.errors?.token?.[0] ?? error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
