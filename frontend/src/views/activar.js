import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, toast, withBusy } from '../lib/ui.js';
import { authCard } from './auth-layout.js';

/**
 * Activación de una cuenta creada por la administración: se abre desde el
 * correo de invitación (#/activar?token=...&correo=...). La persona define su
 * contraseña y acepta la política de tratamiento de datos.
 */
export default async function activarView({ root, navigate, query }) {
  const token = query.get('token');
  const correo = query.get('correo');

  if (!token || !correo) {
    mount(
      root,
      authCard(
        html`<div class="auth-done">
          <div class="auth-icon warn">${icon('alert-circle')}</div>
          <h2>Enlace incompleto</h2>
          <p class="muted">
            Abre el enlace tal como llegó a tu correo. Si venció, pide a la coordinación de prácticas una
            invitación nueva.
          </p>
          <button class="btn block" type="button" id="again">Ir al ingreso</button>
        </div>`,
      ),
    );
    root.querySelector('#again').addEventListener('click', () => navigate('/login'));
    return;
  }

  const cat = await api.get('/catalogos').catch(() => ({ version_politica: '' }));

  mount(
    root,
    authCard(
      html`<form id="resForm" class="auth-form" novalidate>
        <div class="auth-head">
          <h2>Activa tu cuenta</h2>
          <p class="muted">
            La FET creó la cuenta <b>${correo}</b> en APP FET. Define tu contraseña para ingresar.
          </p>
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <div class="input-ic">
            ${icon('lock')}<input
              class="input"
              id="password"
              name="password"
              type="password"
              autocomplete="new-password"
              required
              autofocus
            />
          </div>
          <span class="help">Mínimo 8 caracteres, con letras y números.</span>
        </div>
        <div class="field">
          <label for="password_confirmation">Confirmar contraseña</label>
          <div class="input-ic">
            ${icon('lock')}<input
              class="input"
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>
        </div>
        <div class="field">
          <label class="check"
            ><input type="checkbox" name="acepta_politica" />
            <span
              >Autorizo a la Fundación Escuela Tecnológica de Neiva a tratar mis datos personales para
              gestionar mis pasantías y prácticas, según la Ley 1581 de 2012 y su política de tratamiento de
              datos (versión ${cat.version_politica}).</span
            ></label
          >
        </div>
        <div id="resError"></div>
        <button class="btn block" type="submit">Activar cuenta</button>
      </form>`,
    ),
  );

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
        const { message } = await api.post('/auth/activar-cuenta', {
          token,
          correo,
          password: form.password.value,
          password_confirmation: form.password_confirmation.value,
          acepta_politica: form.acepta_politica.checked,
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
