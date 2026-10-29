/**
 * Rutas comunes a todos los roles que no salen en el menú lateral: a
 * Notificaciones se llega con la campana y a Reportar problema desde el menú
 * del perfil, ambos en el encabezado de la página.
 */
const NOTIFICACIONES = { path: '/notificaciones', hidden: true, load: () => import('./views/notificaciones.js') };
const COMUNES = [NOTIFICACIONES, { path: '/reportar', hidden: true, load: () => import('./views/reportar.js') }];

const HOJA_VIDA = [
  { path: '/hoja-vida', label: 'Hoja de vida', icon: 'file-cv', load: () => import('./views/estudiante/hoja-vida.js') },
  { path: '/hoja-vida/vista', hidden: true, load: () => import('./views/estudiante/hoja-vida-vista.js') },
];

/**
 * Rutas por entorno. Cada vista se carga bajo demanda (import dinámico) y su
 * entrada en el menú lateral sale de aquí. El entorno es el rol, salvo para
 * el estudiante, que tiene dos (ver `session.environment`).
 */
export const ROUTES = {
  // Estudiante que aún no cursa práctica: publica su hoja de vida y ve a las empresas interesadas.
  ESTUDIANTE: [
    ...HOJA_VIDA,
    { path: '/citas', label: 'Empresas interesadas', icon: 'calendar-event', load: () => import('./views/estudiante/citas.js') },
    // Desde el menú del perfil, cuando el estudiante tiene prácticas terminadas.
    { path: '/mis-practicas', hidden: true, load: () => import('./views/estudiante/mis-practicas.js') },
    ...COMUNES,
  ],
  // Estudiante con pasantía activa: seguimiento de su práctica.
  ESTUDIANTE_PASANTIA: [
    { path: '/pasantia', label: 'Mi pasantía', icon: 'building', load: () => import('./views/estudiante/pasantia.js') },
    { path: '/evidencias', label: 'Horas semanales', icon: 'clock-hour-4', load: () => import('./views/estudiante/evidencias.js') },
    { path: '/seguimiento', label: 'Seguimiento', icon: 'list-check', load: () => import('./views/estudiante/seguimiento.js') },
    ...HOJA_VIDA,
    ...COMUNES,
  ],
  DOCENTE: [
    { path: '/registro', label: 'Registro de estudiantes', icon: 'user-plus', load: () => import('./views/docente/registro.js') },
    { path: '/autorizaciones', label: 'Aprobaciones', icon: 'clipboard-check', load: () => import('./views/docente/autorizaciones.js') },
    { path: '/vacantes', label: 'Vacantes', icon: 'briefcase', load: () => import('./views/docente/vacantes.js') },
    { path: '/en-curso', label: 'Prácticas', icon: 'users', load: () => import('./views/docente/vinculaciones.js') },
    ...COMUNES,
  ],
  EMPRESA: [
    { path: '/hojas-vida', label: 'Hojas de vida', icon: 'file-cv', load: () => import('./views/empresa/hojas-vida.js') },
    { path: '/citas', label: 'Citas', icon: 'calendar-event', load: () => import('./views/empresa/citas.js') },
    { path: '/practicantes', label: 'Mis practicantes', icon: 'users', load: () => import('./views/empresa/practicantes.js') },
    { path: '/vacantes', label: 'Mis vacantes', icon: 'briefcase', load: () => import('./views/empresa/vacantes.js') },
    { path: '/acuerdo', label: 'Acuerdo con la FET', icon: 'file-text', load: () => import('./views/empresa/acuerdo.js') },
    ...COMUNES,
  ],
  DIRECTOR: [
    { path: '/programa', label: 'Estudiantes', icon: 'school', load: () => import('./views/director/estudiantes.js') },
    { path: '/cartas', label: 'Cartas de presentación', icon: 'send', load: () => import('./views/director/cartas.js') },
    { path: '/acuerdos', label: 'Acuerdos', icon: 'file-text', load: () => import('./views/acuerdos.js') },
    ...COMUNES,
  ],
  ADMIN: [
    { path: '/resumen', label: 'Resumen', icon: 'chart-bar', load: () => import('./views/admin/resumen.js') },
    { path: '/empresas', label: 'Empresas', icon: 'building', load: () => import('./views/admin/empresas.js') },
    { path: '/acuerdos', label: 'Acuerdos', icon: 'file-text', load: () => import('./views/acuerdos.js') },
    { path: '/reglas', label: 'Parámetros', icon: 'settings', load: () => import('./views/admin/reglas.js') },
    { path: '/usuarios', label: 'Usuarios', icon: 'users', load: () => import('./views/admin/usuarios.js') },
    { path: '/periodos', label: 'Periodos', icon: 'calendar-event', load: () => import('./views/admin/periodos.js') },
    { path: '/informes', label: 'Informes', icon: 'trending-up', load: () => import('./views/admin/informes.js') },
    { path: '/reportes', label: 'Reportes', icon: 'flag', load: () => import('./views/admin/reportes.js') },
    { path: '/auditoria', label: 'Auditoría', icon: 'history', load: () => import('./views/admin/auditoria.js') },
    NOTIFICACIONES,
  ],
};

/** Rutas con parámetros, comunes a varios roles. */
export const SHARED_ROUTES = [
  { pattern: /^\/vinculacion\/(\d+)$/, load: () => import('./views/seguimiento.js') },
  { pattern: /^\/vinculacion\/(\d+)\/evaluacion$/, load: () => import('./views/evaluacion.js') },
  { pattern: /^\/usuarios\/estudiante\/(\d+)$/, roles: ['ADMIN'], load: () => import('./views/admin/estudiante.js') },
  { pattern: /^\/empresas\/(\d+)$/, roles: ['ADMIN'], load: () => import('./views/admin/empresa.js') },
  { pattern: /^\/estudiantes\/(\d+)$/, roles: ['EMPRESA', 'DOCENTE', 'ADMIN', 'DIRECTOR'], load: () => import('./views/perfil-estudiante.js') },
];
