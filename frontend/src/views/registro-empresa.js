import { api } from '../api/client.js';
import { padDeFirma } from '../lib/firma.js';
import { html, mount } from '../lib/html.js';
import { errorBox, icon, showFieldErrors, withBusy } from '../lib/ui.js';
import { authCard } from './auth-layout.js';

/**
 * Registro público de empresas (/empresasRegistro). Lo firma el representante
 * legal. Con convenio vigente, la empresa lo adjunta, define su contraseña y
 * espera la validación de la FET. Sin convenio, envía una solicitud: la FET
 * le envía el convenio por correo y la empresa vuelve aquí a registrarse.
 */
export default async function registroEmpresaView({ root }) {
  const cat = await api.get('/catalogos').catch(() => ({ modalidades: [], version_politica: '' }));
  const toLogin = () => location.assign('/#/login');
  let conConvenio = true;

  const done = (titulo, message) => {
    mount(
      root,
      authCard(
        html`<div class="auth-done">
          <div class="auth-icon">${icon('circle-check')}</div>
          <h2>${titulo}</h2>
          <p class="muted">${message}</p>
          <button class="btn block" type="button" id="toLogin">Ir al ingreso</button>
        </div>`,
      ),
    );
    root.querySelector('#toLogin').addEventListener('click', toLogin);
  };

  const campo = (name, label, attrs = '', wide = false) =>
    html`<div class="field${wide ? ' wide' : ''}"><label for="${name}">${label}</label><input class="input" id="${name}" name="${name}" ${attrs} /></div>`;

  mount(
    root,
    authCard(
      html`<form id="regForm" class="auth-form" novalidate>
        <div class="auth-head"><h2>Registra tu empresa</h2></div>
        <div class="field">
          <span class="sub-h">¿Tu empresa ya tiene convenio con la FET?</span>
          <div class="seg" role="group" aria-label="Convenio con la FET">
            <button type="button" data-convenio="1" aria-pressed="true">Sí, tengo el convenio</button>
            <button type="button" data-convenio="0" aria-pressed="false">No, quiero solicitarlo</button>
          </div>
        </div>
        <p class="note-box" data-solo="sin" hidden>
          ${icon('alert-circle')} Enviaremos tu solicitud a la FET. Te llegará el convenio por correo y, cuando lo tengas
          firmado, vuelves a esta página para registrar tu empresa con él.
        </p>

        <h3 class="sub-h">Empresa</h3>
        <div class="form-grid">
          ${campo('razon_social', 'Razón social', html`autocomplete="organization" required`, true)}
          ${campo('nit', 'NIT', html`placeholder="900.123.456-7" required`)}
          ${campo('sector', 'Sector', html`placeholder="Ej.: Tecnología"`)}
          ${campo('contacto_nombre', 'Persona de contacto', html`autocomplete="name" required`)}
          ${campo('contacto_telefono', 'Teléfono de contacto', html`type="tel" autocomplete="tel" required`)}
          <div class="field">
            <label for="modalidad_interes">Modalidad de interés</label>
            <select class="input" id="modalidad_interes" name="modalidad_interes">
              ${cat.modalidades.map((m) => html`<option value="${m.valor}">${m.etiqueta}</option>`)}
            </select>
          </div>
          <div class="field wide"><label for="correo">Correo de la empresa</label><div class="input-ic">${icon('mail')}<input class="input" id="correo" name="correo" type="email" autocomplete="username" required /></div></div>
        </div>

        <h3 class="sub-h">Representante legal</h3>
        <div class="form-grid">
          ${campo('representante_nombre', 'Nombre completo', html`required`)}
          ${campo('representante_documento', 'Documento de identidad', html`inputmode="numeric" required`)}
          <div class="field wide">
            <label for="firmaPad">Firma del representante legal</label>
            <canvas class="firma-pad" id="firmaPad" aria-label="Recuadro para la firma del representante legal"></canvas>
            <div class="actions"><button type="button" class="btn sec sm" id="firmaLimpiar">Borrar firma</button></div>
            <input type="hidden" name="firma" />
          </div>
        </div>

        <div data-solo="con">
          <h3 class="sub-h">Convenio con la FET</h3>
          <div class="form-grid">
            <div class="field wide"><label for="convenio">Convenio firmado (PDF, máx. 10 MB)</label><input class="input" type="file" id="convenio" name="convenio" accept="application/pdf" /></div>
            ${campo('vigencia_inicio', 'Vigente desde', html`type="date"`)}
            ${campo('vigencia_fin', 'Vigente hasta', html`type="date"`)}
          </div>
          <h3 class="sub-h">Acceso</h3>
          <div class="form-grid">
            <div class="field"><label for="password">Contraseña</label><div class="input-ic">${icon('lock')}<input class="input" id="password" name="password" type="password" autocomplete="new-password" /></div><span class="help">Mínimo 8 caracteres, con letras y números.</span></div>
            <div class="field"><label for="password_confirmation">Confirmar contraseña</label><div class="input-ic">${icon('lock')}<input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" /></div></div>
          </div>
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
        <button class="btn block" type="submit"><span id="enviarTexto">Enviar registro</span> ${icon('arrow-right')}</button>
        <p class="auth-links"><button type="button" class="link" id="toLogin">¿Tu empresa ya está registrada? Ingresa</button></p>
      </form>`,
      { wide: true },
    ),
  );

  root.querySelector('#toLogin').addEventListener('click', toLogin);

  const form = root.querySelector('#regForm');
  const pad = padDeFirma(root.querySelector('#firmaPad'));
  root.querySelector('#firmaLimpiar').addEventListener('click', () => pad.limpiar());

  form.addEventListener('click', (e) => {
    const opcion = e.target.closest('[data-convenio]');
    if (!opcion) return;
    conConvenio = opcion.dataset.convenio === '1';
    form.querySelectorAll('[data-convenio]').forEach((b) => b.setAttribute('aria-pressed', String(b === opcion)));
    form.querySelectorAll('[data-solo]').forEach((el) => (el.hidden = el.dataset.solo !== (conConvenio ? 'con' : 'sin')));
    root.querySelector('#enviarTexto').textContent = conConvenio ? 'Enviar registro' : 'Solicitar convenio';
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (pad.vacia()) return showFieldErrors(form, { firma: ['El representante legal debe firmar el registro.'] });
    if (conConvenio && form.password.value !== form.password_confirmation.value) {
      return showFieldErrors(form, { password_confirmation: ['Las contraseñas no coinciden.'] });
    }

    withBusy(form.querySelector('[type=submit]'), async () => {
      const data = new FormData();
      data.append('con_convenio', conConvenio ? '1' : '0');
      for (const name of ['razon_social', 'nit', 'sector', 'contacto_nombre', 'contacto_telefono', 'modalidad_interes', 'correo', 'representante_nombre', 'representante_documento']) {
        const valor = form[name].value.trim();
        if (valor) data.append(name, valor);
      }
      data.append('acepta_politica', form.acepta_politica.checked ? '1' : '0');
      data.append('firma', await pad.png(), 'firma.png');
      if (conConvenio) {
        for (const name of ['vigencia_inicio', 'vigencia_fin', 'password', 'password_confirmation']) data.append(name, form[name].value);
        if (form.convenio.files.length) data.append('convenio', form.convenio.files[0]);
      }
      try {
        const { message } = await api.post('/registro/empresa', data);
        done(conConvenio ? 'Registro recibido' : 'Solicitud enviada', message);
      } catch (error) {
        mount(root.querySelector('#regError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
