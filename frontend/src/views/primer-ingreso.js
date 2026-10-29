import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, toast, withBusy } from '../lib/ui.js';
import { session } from '../session.js';
import { authCard } from './auth-layout.js';

/**
 * Primer ingreso del estudiante: cambia la contraseña que recibió por correo
 * y acepta la política de datos. Hasta hacerlo no ve nada más de la app.
 */
export default async function primerIngresoView({ root, navigate }) {
  const cat = await api.get('/catalogos').catch(() => ({ version_politica: '' }));

  mount(
    root,
    authCard(
      html`<form id="piForm" class="auth-form" novalidate>
        <div class="auth-head">
          <h2>Cambia tu contraseña</h2>
          <p class="muted">
            Hola, ${session.user.nombre}. Es tu primer ingreso a APP FET: reemplaza la contraseña que recibiste por
            correo por una que solo tú conozcas. Después crearás tu hoja de vida.
          </p>
        </div>
        <div class="field">
          <label for="password">Nueva contraseña</label>
          <div class="input-ic">${icon('lock')}<input class="input" id="password" name="password" type="password" autocomplete="new-password" required autofocus /></div>
          <span class="help">Mínimo 8 caracteres, con letras y números.</span>
        </div>
        <div class="field">
          <label for="password_confirmation">Confirmar contraseña</label>
          <div class="input-ic">${icon('lock')}<input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></div>
        </div>
        <div class="field">
          <label class="check"
            ><input type="checkbox" name="acepta_politica" />
            <span
              >Autorizo a la Fundación Escuela Tecnológica de Neiva a tratar mis datos personales para gestionar mis
              pasantías y prácticas, según la Ley 1581 de 2012 y su política de tratamiento de datos (versión
              ${cat.version_politica}).</span
            ></label
          >
        </div>
        <div id="piError"></div>
        <button class="btn block" type="submit">Guardar y continuar ${icon('arrow-right')}</button>
        <p class="auth-links"><button type="button" class="link" id="salir">Salir</button></p>
      </form>`,
    ),
  );

  root.querySelector('#salir').addEventListener('click', async () => {
    await api.post('/auth/logout').catch(() => {});
    session.clear();
    session.emit();
  });

  const form = root.querySelector('#piForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (form.password.value !== form.password_confirmation.value) {
      showFieldErrors(form, { password_confirmation: ['Las contraseñas no coinciden.'] });
      return;
    }
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        const usuario = await api.post('/auth/primer-ingreso', {
          password: form.password.value,
          password_confirmation: form.password_confirmation.value,
          acepta_politica: form.acepta_politica.checked,
        });
        session.setUser(usuario);
        toast('Contraseña actualizada. Ahora crea tu hoja de vida.');
        navigate('/hoja-vida');
      } catch (error) {
        mount(root.querySelector('#piError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
