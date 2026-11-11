import { html } from '../lib/html.js';

/**
 * Enlaces del pie legal. Revisa las direcciones y los textos con la FET antes
 * de publicar: la política es la que se acepta al activar la cuenta.
 */
const LEGAL = {
  politica: 'https://www.fet.edu.co/',
  terminos: 'https://www.fet.edu.co/',
  correo: 'practicas@fet.edu.co',
};

/** Pie de las pantallas públicas (ingreso, recuperación, activación y registro). */
export const publicFooter = () =>
  html`<footer class="l-foot">
    <div>
      <p>© ${new Date().getFullYear()} Fundación Escuela Tecnológica de Neiva Jesús Oviedo Pérez. Todos los derechos reservados.</p>
      <p>Tus datos se tratan según la Ley 1581 de 2012 y la política de tratamiento de datos de la FET.</p>
      <p>APP-FET, proyecto de aula del programa de Ingeniería de Software.</p>
    </div>
    <nav aria-label="Información legal">
      <a class="link" href="${LEGAL.politica}" target="_blank" rel="noopener noreferrer">Política de datos</a>
      <a class="link" href="${LEGAL.terminos}" target="_blank" rel="noopener noreferrer">Términos de uso</a>
      <a class="link" href="mailto:${LEGAL.correo}">Contacto: ${LEGAL.correo}</a>
    </nav>
  </footer>`;

/** Tarjeta centrada para las pantallas de acceso fuera de la app (recuperar, restablecer, activar, registro). */
export const authCard = (content, { wide = false } = {}) =>
  html`<div class="login-wrap">
    <div class="auth-page">
      <div class="auth-card panel${wide ? ' wide' : ''}">
        <a class="brand" href="/#/" aria-label="APP FET, ir al inicio"><img class="brand-logo logo-claro" src="/logo-app-fet-claro.png" alt="APP FET, Prácticas y Pasantías" /><img class="brand-logo logo-oscuro" src="/logo-app-fet-oscuro.png" alt="APP FET, Prácticas y Pasantías" /></a>
        ${content}
      </div>
    </div>
    ${publicFooter()}
  </div>`;
