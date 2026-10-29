import { html } from '../lib/html.js';

/** Tarjeta centrada para las pantallas de acceso fuera de la app (recuperar, restablecer, registro). */
export const authCard = (content, { wide = false } = {}) =>
  html`<div class="auth-page">
    <div class="auth-card panel${wide ? ' wide' : ''}">
      <div class="brand"><img class="brand-logo" src="/logo-fet-sello.png" alt="APP-FET" /><small>Pasantías y prácticas</small></div>
      ${content}
    </div>
  </div>`;
