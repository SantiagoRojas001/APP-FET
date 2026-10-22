import { html } from '../lib/html.js';

/** Tarjeta centrada para las pantallas de acceso fuera de la app (recuperar y restablecer). */
export const authCard = (content) =>
  html`<div class="auth-page">
    <div class="auth-card panel">
      <div class="brand"><img class="brand-logo" src="/logo-fet-sello.png" alt="APP-FET" /><small>Pasantías y prácticas</small></div>
      ${content}
    </div>
  </div>`;
