import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { initials } from '../lib/format.js';
import { applyTheme, mountThemeSwitch, syncThemeWithUser } from '../lib/theme.js';
import { icon, toast } from '../lib/ui.js';
import { session } from '../session.js';

/** Segunda línea del perfil: semestre y programa, sector de la empresa o el rol. */
function subtitulo(user) {
  if (user.estudiante) return `${user.estudiante.semestre_actual}.º semestre, ${user.estudiante.programa?.nombre ?? ''}`;
  if (user.empresa) return `Empresa${user.empresa.sector ? `, ${user.empresa.sector}` : ''}`;
  if (user.programa) return user.rol.valor === 'DIRECTOR' ? `Dirección de ${user.programa.nombre}` : `Docente de ${user.programa.nombre}`;
  return user.rol.etiqueta;
}

/** Tercera línea, solo para estudiantes: dónde cursa la práctica, sus citas o en qué va su hoja de vida. */
function estado(perfil) {
  if (!perfil) return '';
  if (perfil.empresa_practica) return html`<span class="ok">${icon('building')}En pasantía en ${perfil.empresa_practica}</span>`;
  if (perfil.citas_programadas) return html`<span class="ok">${icon('calendar-event')}${perfil.citas_programadas === 1 ? '1 cita programada' : `${perfil.citas_programadas} citas programadas`}</span>`;
  const estado = perfil.hoja_vida_estado?.valor;
  if (estado === 'APROBADA') return html`<span class="ok">${icon('circle-check')}Hoja de vida aprobada</span>`;
  if (estado === 'EN_REVISION') return html`<span class="warn">${icon('clock-hour-4')}Hoja de vida en revisión</span>`;
  if (estado === 'CON_OBSERVACIONES') return html`<span class="warn">${icon('alert-circle')}Corrige tu hoja de vida</span>`;
  return html`<span class="warn">${icon('clock-hour-4')}Envía tu hoja de vida a revisión</span>`;
}

function avatar(user) {
  const perfil = user.perfil;
  const av = perfil?.url_foto
    ? html`<img class="av" data-foto-perfil="${perfil.url_foto}" alt="" />`
    : html`<span class="av" aria-hidden="true">${initials(user.nombre)}</span>`;
  if (!perfil) return av;
  const p = perfil.hoja_vida_porcentaje;
  return html`<span class="ring" style="--p:${p}" title="Hoja de vida completa al ${p} %">${av}<em>${p}%</em></span>`;
}

/**
 * Campana de notificaciones y perfil con su menú, arriba a la derecha del
 * encabezado de cada página (el hueco `[data-perfil-slot]` de `pageHeader`).
 * El menú reúne Mis prácticas, Reportar un problema, el recorrido guiado, el
 * tema y Cerrar sesión.
 */
export function mountPerfil(slot, { path, navigate }) {
  const user = session.user;
  const perfil = user.perfil;
  const enNotificaciones = path === '/notificaciones';

  mount(
    slot,
    html`<button type="button" class="bell" id="bell" aria-label="Notificaciones" ${enNotificaciones ? html`aria-current="page"` : ''}>
        ${icon('bell')}<span class="count" data-badge hidden></span>
      </button>
      <div class="profile">
        <button type="button" class="profile-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="perfilMenu">
          ${avatar(user)}
          <span class="profile-t">
            <b>${user.nombre}</b>
            <span>${subtitulo(user)}</span>
            ${estado(perfil)}
          </span>
          ${icon('chevron-down', 'chev')}
        </button>
        <div class="profile-menu" id="perfilMenu" role="menu" hidden>
          <div class="pm-head">
            <b>${user.nombre}</b>
            <span class="mono">${user.correo}</span>
            ${perfil ? html`<a class="link" href="#/hoja-vida" role="menuitem">Hoja de vida completa al ${perfil.hoja_vida_porcentaje} %</a>` : ''}
          </div>
          ${perfil?.tiene_practicas_terminadas ? html`<button type="button" class="pm-item" role="menuitem" data-go="/mis-practicas">${icon('certificate')}Mis prácticas</button>` : ''}
          ${user.rol.valor !== 'ADMIN' ? html`<button type="button" class="pm-item" role="menuitem" data-go="/reportar">${icon('flag')}Reportar un problema</button>` : ''}
          <button type="button" class="pm-item" role="menuitem" id="tour">${icon('help-circle')}Ver recorrido de la app</button>
          <div class="pm-theme"><span class="fine">Tema</span><div id="themeSwitch"></div></div>
          <button type="button" class="pm-item pm-out" role="menuitem" id="logout">${icon('logout')}Cerrar sesión</button>
        </div>
      </div>`,
  );

  const btn = slot.querySelector('.profile-btn');
  const menu = slot.querySelector('.profile-menu');

  const abrir = (abierto) => {
    menu.hidden = !abierto;
    btn.setAttribute('aria-expanded', String(abierto));
  };
  const cerrar = (devolverFoco = false) => {
    if (menu.hidden) return;
    abrir(false);
    if (devolverFoco) btn.focus();
  };

  btn.addEventListener('click', () => abrir(menu.hidden));
  slot.querySelector('#bell').addEventListener('click', () => navigate('/notificaciones'));

  menu.addEventListener('click', (e) => {
    const go = e.target.closest('[data-go]');
    if (go) {
      cerrar();
      navigate(go.dataset.go);
    }
    if (e.target.closest('a[href]')) cerrar();
  });

  // Se cierra al hacer clic fuera y con Esc. Los oyentes se quitan cuando el
  // encabezado desaparece (cada navegación dibuja uno nuevo).
  const fuera = (e) => {
    if (!slot.isConnected) return quitar();
    if (!slot.querySelector('.profile').contains(e.target)) cerrar();
  };
  const esc = (e) => {
    if (!slot.isConnected) return quitar();
    if (e.key === 'Escape') cerrar(true);
  };
  const quitar = () => {
    document.removeEventListener('click', fuera);
    document.removeEventListener('keydown', esc);
  };
  document.addEventListener('click', fuera);
  document.addEventListener('keydown', esc);

  // El tema se guarda en la cuenta del usuario.
  mountThemeSwitch(slot.querySelector('#themeSwitch'), {
    get: () => session.user.tema ?? 'system',
    set: (tema) => {
      const anterior = session.user.tema;
      session.user.tema = tema;
      syncThemeWithUser(session.user);
      api.patch('/auth/preferencias', { tema }).catch(() => {
        session.user.tema = anterior;
        applyTheme(anterior);
        toast('No se pudo guardar el tema. Inténtalo de nuevo.');
      });
    },
  });

  slot.querySelector('#tour').addEventListener('click', async () => {
    cerrar();
    (await import('./tutorial.js')).iniciarTutorial();
  });

  slot.querySelector('#logout').addEventListener('click', async () => {
    await api.post('/auth/logout').catch(() => {});
    session.clear();
    session.emit();
  });

  const foto = slot.querySelector('[data-foto-perfil]');
  if (foto) {
    api
      .blobUrl(foto.dataset.fotoPerfil)
      .then((url) => (foto.src = url))
      .catch(() => foto.replaceWith(Object.assign(document.createElement('span'), { className: 'av', textContent: initials(user.nombre) })));
  }
}
