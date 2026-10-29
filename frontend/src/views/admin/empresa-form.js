import { html } from '../../lib/html.js';

/** Campos de una empresa, compartidos por la invitación y la edición. */
export const empresaFields = (c = {}, { conCorreo = false } = {}) =>
  html` ${conCorreo ? html`<div class="field"><label for="correo">Correo de la cuenta</label><input class="input" type="email" id="correo" name="correo" /></div>` : ''}
    <div class="field">
      <label for="razon_social">Razón social</label
      ><input class="input" id="razon_social" name="razon_social" value="${c.razon_social ?? ''}" />
    </div>
    <div class="field">
      <label for="nit">NIT</label><input class="input" id="nit" name="nit" value="${c.nit ?? ''}" />
    </div>
    <div class="field">
      <label for="sector">Sector (opcional)</label
      ><input class="input" id="sector" name="sector" value="${c.sector ?? ''}" />
    </div>
    <div class="field">
      <label for="contacto_nombre">Persona de contacto</label
      ><input class="input" id="contacto_nombre" name="contacto_nombre" value="${c.contacto_nombre ?? ''}" />
    </div>
    <div class="field">
      <label for="contacto_telefono">Teléfono de contacto</label
      ><input
        class="input"
        id="contacto_telefono"
        name="contacto_telefono"
        value="${c.contacto_telefono ?? ''}"
      />
    </div>
    <div class="field">
      <label for="modalidad_interes">Modalidad de interés</label
      ><select class="input" id="modalidad_interes" name="modalidad_interes">
        <option value="PASANTIA" ${c.modalidad_interes?.valor === 'PASANTIA' ? 'selected' : ''}>
          Pasantía
        </option>
        <option value="PRACTICA" ${c.modalidad_interes?.valor === 'PRACTICA' ? 'selected' : ''}>
          Práctica profesional
        </option>
      </select>
    </div>`;

/** Lee los campos del formulario de empresa. */
export const empresaData = (form) => ({
  ...(form.correo ? { correo: form.correo.value.trim() } : {}),
  razon_social: form.razon_social.value.trim(),
  nit: form.nit.value.trim(),
  sector: form.sector.value.trim() || null,
  contacto_nombre: form.contacto_nombre.value.trim(),
  contacto_telefono: form.contacto_telefono.value.trim(),
  modalidad_interes: form.modalidad_interes.value,
});
