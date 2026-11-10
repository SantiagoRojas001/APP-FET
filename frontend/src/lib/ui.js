import { html, mount, raw } from './html.js';
import { ICONS } from './icons.js';

export const icon = (name, cls = '') =>
  raw(`<svg class="ic ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] ?? ''}</svg>`);

/** Colores de estado por valor de enum de la API. */
const TONES = {
  PROGRAMADA: 'info',
  ACEPTADO: 'warn',
  PRESENTADO: 'ok',
  BORRADOR: 'neutral',
  APROBADA: 'ok',
  CON_OBSERVACIONES: 'warn',
  RECHAZADA: 'bad',
  RECHAZADO: 'bad',
  PENDIENTE: 'warn',
  VALIDADA: 'ok',
  APROBADO: 'ok',
  OBSERVADO: 'bad',
  ACTIVA: 'ok',
  FINALIZADA: 'info',
  CANCELADA: 'neutral',
  ABIERTA: 'ok',
  CERRADA: 'neutral',
  ABIERTO: 'warn',
  EN_REVISION: 'info',
  RESUELTO: 'ok',
  ENVIADO: 'info',
  REGISTRADA: 'ok',
};

export const badge = (tone, text) => html`<span class="badge b-${tone}">${text}</span>`;

/** Badge para un enum `{valor, etiqueta}` de la API. */
export const statusBadge = (e) => (e ? badge(TONES[e.valor] ?? 'neutral', e.etiqueta) : '');

const MODALITY_ICONS = { PASANTIA: 'school', PRACTICA: 'briefcase' };

export const modalityTag = (m, on = false) =>
  html`<span class="tag${on ? ' on' : ''}">${icon(MODALITY_ICONS[m.valor] ?? 'briefcase')}${m.etiqueta}</span>`;

/**
 * Encabezado de página: título a la izquierda; a la derecha, la campana y el
 * perfil (los monta el shell en `[data-perfil-slot]`) y debajo las acciones.
 */
export const pageHeader = (title, subtitle = '', actions = '') =>
  html`<header class="ph">
    <div>
      <h1>${title}</h1>
      ${subtitle ? html`<p>${subtitle}</p>` : ''}
    </div>
    <div class="ph-right">
      <div class="ph-top" data-perfil-slot></div>
      ${actions}
    </div>
  </header>`;

export const emptyState = (iconName, title, text = '', action = '') =>
  html`<div class="empty">
    ${icon(iconName)}<b>${title}</b>${text ? html`<span class="fine">${text}</span>` : ''}${action}
  </div>`;

export const errorBox = (message) =>
  html`<p class="error" role="alert">${icon('alert-circle')}<span>${message}</span></p>`;

export const skeleton = () =>
  html`<div style="display:flex;flex-direction:column;gap:10px">
      <div class="sk" style="height:30px;width:min(420px,70%)"></div>
      <div class="sk" style="height:16px;width:min(560px,90%)"></div>
    </div>
    <div class="sk" style="height:64px"></div>
    <div class="sk" style="height:220px"></div>`;

let toastTimer;
export function toast(message) {
  const el = document.getElementById('toast');
  el.innerHTML = `${icon('circle-check')}`;
  el.append(message);
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (el.hidden = true), 3000);
}

/**
 * Muestra los errores de validación de Laravel junto a cada campo del
 * formulario (por atributo `name`). Devuelve true si mostró alguno.
 */
export function showFieldErrors(form, errors = {}) {
  form.querySelectorAll('.field-error').forEach((el) => el.remove());
  form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));

  let first = null;
  for (const [name, messages] of Object.entries(errors)) {
    const input = form.elements.namedItem(name);
    if (!input || !(input instanceof HTMLElement)) continue;
    input.setAttribute('aria-invalid', 'true');
    const msg = document.createElement('span');
    msg.className = 'field-error';
    msg.textContent = messages[0];
    (input.closest('.field') ?? input.parentElement).append(msg);
    first ??= input;
  }
  first?.focus();
  return first !== null;
}

/**
 * Confirmación en la misma pantalla, en lugar de confirm(): el primer clic
 * cambia el botón por el aviso, «Sí, {acción}» y «Cancelar». `onConfirm`
 * recibe el botón «Sí» (para deshabilitarlo con withBusy).
 */
export function confirmInline(button, accion, onConfirm, aviso = 'Esta acción no se puede deshacer.') {
  if (button.hidden) return;
  const box = document.createElement('span');
  box.className = 'confirm';
  box.setAttribute('role', 'group');
  mount(
    box,
    html`<span class="fine">${aviso}</span>
      <button type="button" class="btn danger sm" data-si>Sí, ${accion}</button>
      <button type="button" class="btn sec sm" data-no>Cancelar</button>`,
  );
  button.hidden = true;
  button.after(box);

  box.querySelector('[data-no]').addEventListener('click', (e) => {
    e.stopPropagation();
    box.remove();
    button.hidden = false;
    button.focus();
  });
  box.querySelector('[data-si]').addEventListener('click', (e) => {
    e.stopPropagation();
    onConfirm(e.currentTarget);
  });
  box.querySelector('[data-si]').focus();
}

/**
 * Agrega a cada campo de contraseña un botón para mostrarla u ocultarla.
 * Se llama una vez al iniciar: vigila la página y equipa los campos nuevos.
 */
export function passwordToggles(container = document.body) {
  const equipar = () =>
    container.querySelectorAll('input[type=password]:not([data-ver])').forEach((input) => {
      input.dataset.ver = '';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle';
      const pintar = (visible) => {
        btn.innerHTML = String(icon(visible ? 'eye-off' : 'eye'));
        btn.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        btn.setAttribute('aria-pressed', String(visible));
      };
      pintar(false);
      btn.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        pintar(visible);
        input.focus();
      });
      input.classList.add('has-toggle');
      input.after(btn);
    });
  equipar();
  new MutationObserver(equipar).observe(container, { childList: true, subtree: true });
}

/** Ejecuta una acción con el botón deshabilitado mientras dura. */
export async function withBusy(button, fn) {
  if (!button) return fn();
  button.disabled = true;
  try {
    return await fn();
  } finally {
    button.disabled = false;
  }
}

export const formData = (form) => Object.fromEntries(new FormData(form));

/** Barra de avance de horas aprobadas por la empresa (`v.horas` de la API). */
export function hoursMeter(horas) {
  if (!horas) return '';
  const pct = Math.min(100, Math.round((horas.aprobadas / horas.requeridas) * 100));
  const fmt = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
  return html`<div class="hours">
    <div class="hours-h"><b>${fmt.format(horas.aprobadas)} de ${horas.requeridas} horas aprobadas</b>${horas.cumplidas_en ? badge('ok', 'Horas cumplidas') : html`<span class="fine">${pct} %</span>`}</div>
    <div class="meter" role="progressbar" aria-valuemin="0" aria-valuemax="${horas.requeridas}" aria-valuenow="${horas.aprobadas}"><span style="width:${pct}%"></span></div>
  </div>`;
}
