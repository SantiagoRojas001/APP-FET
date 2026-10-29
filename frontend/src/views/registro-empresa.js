import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, withBusy } from '../lib/ui.js';
import { authCard } from './auth-layout.js';

/**
 * Registro público de empresas (/empresasRegistro). La empresa queda
 * pendiente hasta que la FET la valide; entonces recibe un correo y ya puede
 * ingresar.
 */
export default async function registroEmpresaView({ root }) {
  const cat = await api.get('/catalogos').catch(() => ({ modalidades: [], version_politica: '' }));
  const toLogin = () => location.assign('/#/login');

  const done = (message) => {
    mount(
      root,
      authCard(
        html`<div class="auth-done">
          <div class="auth-icon">${icon('circle-check')}</div>
          <h2>Registro recibido</h2>
          <p class="muted">${message}</p>
          <button class="btn block" type="button" id="toLogin">Ir al ingreso</button>
        </div>`,
      ),
    );
    root.querySelector('#toLogin').addEventListener('click', toLogin);
  };

  mount(
    root,
    authCard(
      html`<form id="regForm" class="auth-form" novalidate>
        <div class="auth-head">
          <h2>Registra tu empresa</h2>
          <p class="muted">
            Publica vacantes de pasantía y práctica y recibe las hojas de vida de estudiantes de la FET. La
            coordinación de prácticas valida cada empresa antes de habilitarla.
          </p>
        </div>
        <div class="form-grid">
          <div class="field wide"><label for="razon_social">Razón social</label><input class="input" id="razon_social" name="razon_social" autocomplete="organization" required /></div>
          <div class="field"><label for="nit">NIT</label><input class="input" id="nit" name="nit" placeholder="900.123.456-7" required /></div>
          <div class="field"><label for="sector">Sector</label><input class="input" id="sector" name="sector" placeholder="Ej.: Tecnología" /></div>
          <div class="field"><label for="contacto_nombre">Persona de contacto</label><input class="input" id="contacto_nombre" name="contacto_nombre" autocomplete="name" required /></div>
          <div class="field"><label for="contacto_telefono">Teléfono de contacto</label><input class="input" id="contacto_telefono" name="contacto_telefono" type="tel" autocomplete="tel" required /></div>
          <div class="field wide">
            <label for="modalidad_interes">Modalidad de interés</label>
            <select class="input" id="modalidad_interes" name="modalidad_interes">
              ${cat.modalidades.map((m) => html`<option value="${m.valor}">${m.etiqueta}</option>`)}
            </select>
          </div>
          <div class="field wide"><label for="correo">Correo de la empresa</label><div class="input-ic">${icon('mail')}<input class="input" id="correo" name="correo" type="email" autocomplete="username" required /></div></div>
          <div class="field"><label for="password">Contraseña</label><div class="input-ic">${icon('lock')}<input class="input" id="password" name="password" type="password" autocomplete="new-password" required /></div><span class="help">Mínimo 8 caracteres, con letras y números.</span></div>
          <div class="field"><label for="password_confirmation">Confirmar contraseña</label><div class="input-ic">${icon('lock')}<input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required /></div></div>
        </div>
        <div class="field">
          <label class="check"
            ><input type="checkbox" name="acepta_politica" />
            <span
              >Autorizo a la Fundación Escuela Tecnológica de Neiva a tratar los datos registrados para gestionar
              las pasantías y prácticas, según la Ley 1581 de 2012 y su política de tratamiento de datos (versión
              ${cat.version_politica}).</span
            ></label
          >
        </div>
        <div id="regError"></div>
        <button class="btn block" type="submit">Enviar registro ${icon('arrow-right')}</button>
        <p class="auth-links"><button type="button" class="link" id="toLogin">¿Tu empresa ya está registrada? Ingresa</button></p>
      </form>`,
      { wide: true },
    ),
  );

  root.querySelector('#toLogin').addEventListener('click', toLogin);

  const form = root.querySelector('#regForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (form.password.value !== form.password_confirmation.value) {
      showFieldErrors(form, { password_confirmation: ['Las contraseñas no coinciden.'] });
      return;
    }
    const data = Object.fromEntries(new FormData(form));
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        const { message } = await api.post('/registro/empresa', {
          ...data,
          correo: data.correo.trim(),
          sector: data.sector.trim() || null,
          acepta_politica: form.acepta_politica.checked,
        });
        done(message);
      } catch (error) {
        mount(root.querySelector('#regError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
