import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { ROUTES } from '../routes.js';
import { session } from '../session.js';

let activo = false;

/**
 * Recorrido guiado por la app: un paso por cada opción del menú lateral y
 * uno por la campana y el perfil. Sale solo en el primer ingreso (y cuando la
 * persona lo pide desde su perfil); se puede saltar en cualquier momento.
 */
export function iniciarTutorial() {
  if (activo) return;
  const nav = document.querySelector('.nav');
  if (!nav) return;
  activo = true;

  const pasos = [
    { titulo: `Hola, ${session.user.nombre}`, texto: 'Te mostramos en pocos pasos cómo moverte por APP FET.' },
    ...ROUTES[session.environment]
      .filter((r) => !r.hidden)
      .map((r) => ({ objetivo: nav.querySelector(`[data-path="${r.path}"]`), titulo: r.label, texto: r.ayuda })),
    {
      objetivo: document.querySelector('[data-perfil-slot]'),
      titulo: 'Notificaciones y perfil',
      texto: 'La campana te avisa de cada novedad. En tu perfil cambias el tema, reportas un problema, vuelves a ver este recorrido y cierras sesión.',
    },
  ];

  // El fondo bloquea la página; el foco oscurece todo menos el elemento del paso.
  const fondo = Object.assign(document.createElement('div'), { className: 'tour-fondo' });
  const foco = Object.assign(document.createElement('div'), { className: 'tour-foco' });
  const caja = Object.assign(document.createElement('div'), { className: 'tour' });
  caja.setAttribute('role', 'dialog');
  caja.setAttribute('aria-modal', 'true');
  caja.setAttribute('aria-labelledby', 'tourTitulo');
  document.body.append(fondo, foco, caja);

  let i = 0;

  const terminar = () => {
    activo = false;
    fondo.remove();
    foco.remove();
    caja.remove();
    document.removeEventListener('keydown', teclas);
    window.removeEventListener('resize', ubicar);
    window.removeEventListener('scroll', ubicar, true);
    if (!session.user.tutorial_visto) {
      session.user.tutorial_visto = true;
      api.patch('/auth/preferencias', { tutorial_visto: true }).catch(() => {});
    }
  };

  // Junto al elemento resaltado (a la derecha en escritorio, debajo en el
  // celular), sin salirse de la pantalla; sin elemento, al centro.
  function ubicar() {
    const objetivo = pasos[i].objetivo;
    if (!objetivo) {
      Object.assign(foco.style, { left: '50%', top: '50%', width: '0', height: '0' });
      Object.assign(caja.style, { left: '50%', top: '50%', transform: 'translate(-50%, -50%)' });
      return;
    }
    objetivo.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    const r = objetivo.getBoundingClientRect();
    Object.assign(foco.style, { left: `${r.left}px`, top: `${r.top}px`, width: `${r.width}px`, height: `${r.height}px` });
    const ancho = caja.offsetWidth;
    const alto = caja.offsetHeight;
    const margen = 16;
    const aLaDerecha = r.right + margen + ancho <= window.innerWidth;
    const left = aLaDerecha ? r.right + margen : Math.min(Math.max(margen, r.left), window.innerWidth - ancho - margen);
    const top = aLaDerecha
      ? Math.min(Math.max(margen, r.top), window.innerHeight - alto - margen)
      : Math.min(r.bottom + margen, window.innerHeight - alto - margen);
    Object.assign(caja.style, { left: `${left}px`, top: `${Math.max(margen, top)}px`, transform: 'none' });
  }

  function pintar() {
    const paso = pasos[i];
    const ultimo = i === pasos.length - 1;
    mount(
      caja,
      html`<span class="fine">Paso ${i + 1} de ${pasos.length}</span>
        <h2 id="tourTitulo">${paso.titulo}</h2>
        <p>${paso.texto}</p>
        <div class="tour-acciones">
          ${ultimo ? '' : html`<button type="button" class="link" data-saltar>Saltar recorrido</button>`}
          <span class="actions">
            ${i > 0 ? html`<button type="button" class="btn sec sm" data-atras>Anterior</button>` : ''}
            <button type="button" class="btn sm" data-sig>${ultimo ? 'Terminar' : 'Siguiente'}</button>
          </span>
        </div>`,
    );
    ubicar();
    caja.querySelector('[data-sig]').focus();
  }

  const mover = (paso) => {
    if (paso >= pasos.length) return terminar();
    i = Math.max(0, paso);
    pintar();
  };

  function teclas(e) {
    if (e.key === 'Escape') terminar();
    if (e.key === 'ArrowRight') mover(i + 1);
    if (e.key === 'ArrowLeft') mover(i - 1);
  }

  caja.addEventListener('click', (e) => {
    if (e.target.closest('[data-saltar]')) terminar();
    if (e.target.closest('[data-sig]')) mover(i + 1);
    if (e.target.closest('[data-atras]')) mover(i - 1);
  });
  document.addEventListener('keydown', teclas);
  window.addEventListener('resize', ubicar);
  window.addEventListener('scroll', ubicar, true);
  pintar();
}
