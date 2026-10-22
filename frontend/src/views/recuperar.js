import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, withBusy } from '../lib/ui.js';
import { authCard } from './auth-layout.js';

/**
 * Paso 1 de la recuperación: el usuario escribe su correo y recibe un enlace.
 * La respuesta es la misma exista o no la cuenta.
 */
export default function recuperarView({ root, navigate }) {
  const draw = (sentMessage = '') => {
    mount(
      root,
      authCard(
        sentMessage
          ? html`<div class="auth-done">
              <div class="auth-icon">${icon('mail')}</div>
              <h2>Revisa tu correo</h2>
              <p class="muted">${sentMessage}</p>
              <p class="help">El enlace vence en 60 minutos. Si no lo ves, revisa la carpeta de spam.</p>
              <button class="btn block" type="button" data-login>Volver a ingresar</button>
            </div>`
          : html`<form id="recForm" class="auth-form" novalidate>
              <div class="auth-head">
                <h2>¿Olvidaste tu contraseña?</h2>
                <p class="muted">Escribe el correo con el que ingresas y te enviaremos un enlace para crear una nueva.</p>
              </div>
              <div class="field">
                <label for="correo">Correo</label>
                <div class="input-ic">${icon('mail')}<input class="input" id="correo" name="correo" type="email" autocomplete="username" required autofocus /></div>
              </div>
              <div id="recError"></div>
              <button class="btn block" type="submit">Enviar enlace ${icon('arrow-right')}</button>
              <p class="auth-links"><button type="button" class="link" data-login>Volver a ingresar</button></p>
            </form>`,
      ),
    );

    root.querySelectorAll('[data-login]').forEach((b) => b.addEventListener('click', () => navigate('/login')));

    const form = root.querySelector('#recForm');
    form?.addEventListener('submit', (e) => {
      e.preventDefault();
      const errorEl = root.querySelector('#recError');
      if (!form.correo.value.trim()) {
        mount(errorEl, errorBox('Escribe tu correo.'));
        return;
      }
      withBusy(form.querySelector('[type=submit]'), async () => {
        try {
          const { message } = await api.post('/auth/olvide-contrasena', { correo: form.correo.value.trim() });
          draw(message);
        } catch (error) {
          mount(errorEl, errorBox(error.message));
          showFieldErrors(form, error.errors);
        }
      });
    });
  };

  draw();
}
