import { html, raw } from './html.js';
import { ICONS } from './icons.js';

export const icon = (name, cls = '') =>
  raw(`<svg class="ic ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] ?? ''}</svg>`);

/** Colores de estado por valor de enum de la API. */
const TONES = {
  ENVIADA: 'warn',
  APROBADA: 'info',
  RECHAZADA: 'bad',
  ACEPTADA: 'ok',
  DESCARTADA: 'neutral',
  PENDIENTE: 'warn',
  VALIDADA: 'ok',
  APROBADO: 'ok',
  OBSERVADO: 'bad',
  ACTIVA: 'ok',
  FINALIZADA: 'info',
  CANCELADA: 'neutral',
  ABIERTA: 'ok',
  CERRADA: 'neutral',
};

export const badge = (tone, text) => html`<span class="badge b-${tone}">${text}</span>`;

/** Badge para un enum `{valor, etiqueta}` de la API. */
export const statusBadge = (e) => (e ? badge(TONES[e.valor] ?? 'neutral', e.etiqueta) : '');

const MODALITY_ICONS = { PASANTIA: 'school', PRACTICA: 'briefcase' };

export const modalityTag = (m, on = false) =>
  html`<span class="tag${on ? ' on' : ''}">${icon(MODALITY_ICONS[m.valor] ?? 'briefcase')}${m.etiqueta}</span>`;

export const pageHeader = (title, subtitle = '', actions = '') =>
  html`<header class="ph">
    <div>
      <h1>${title}</h1>
      ${subtitle ? html`<p>${subtitle}</p>` : ''}
    </div>
    ${actions}
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
    <div class="sk" style="height:64px;border-radius:12px"></div>
    <div class="sk" style="height:220px;border-radius:12px"></div>`;

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
