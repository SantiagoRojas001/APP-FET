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
  // Estudiante que aún busca pasantía: vacantes y postulaciones.
  ESTUDIANTE: [
    { path: '/vacantes', label: 'Vacantes', icon: 'briefcase', load: () => import('./views/estudiante/vacantes.js') },
    { path: '/postulaciones', label: 'Mis postulaciones', icon: 'inbox', load: () => import('./views/estudiante/postulaciones.js') },
    ...HOJA_VIDA,
    { path: '/elegibilidad', label: 'Elegibilidad', icon: 'shield-check', load: () => import('./views/estudiante/elegibilidad.js') },
  ],
  // Estudiante con pasantía activa: seguimiento de su práctica.
  ESTUDIANTE_PASANTIA: [
    { path: '/pasantia', label: 'Mi pasantía', icon: 'building', load: () => import('./views/estudiante/pasantia.js') },
    { path: '/seguimiento', label: 'Seguimiento', icon: 'list-check', load: () => import('./views/estudiante/seguimiento.js') },
    ...HOJA_VIDA,
  ],
  DOCENTE: [
    { path: '/postulaciones', label: 'Postulaciones', icon: 'inbox', load: () => import('./views/docente/postulaciones.js') },
    { path: '/en-curso', label: 'Prácticas en curso', icon: 'users', load: () => import('./views/docente/vinculaciones.js') },
    { path: '/estudiantes', label: 'Estudiantes', icon: 'school', load: () => import('./views/estudiantes.js') },
  ],
  EMPRESA: [
    { path: '/postulantes', label: 'Postulantes', icon: 'inbox', load: () => import('./views/empresa/postulantes.js') },
    { path: '/practicantes', label: 'Mis practicantes', icon: 'users', load: () => import('./views/empresa/practicantes.js') },
    { path: '/vacantes', label: 'Mis vacantes', icon: 'briefcase', load: () => import('./views/empresa/vacantes.js') },
    { path: '/estudiantes', label: 'Buscar talento', icon: 'search', load: () => import('./views/estudiantes.js') },
  ],
  ADMIN: [
    { path: '/resumen', label: 'Resumen', icon: 'chart-bar', load: () => import('./views/admin/resumen.js') },
    { path: '/empresas', label: 'Empresas', icon: 'building', load: () => import('./views/admin/empresas.js') },
    { path: '/reglas', label: 'Reglas de práctica', icon: 'settings', load: () => import('./views/admin/reglas.js') },
    { path: '/usuarios', label: 'Usuarios', icon: 'users', load: () => import('./views/admin/usuarios.js') },
  ],
};

/** Rutas con parámetros, comunes a varios roles. */
export const SHARED_ROUTES = [
  { pattern: /^\/vinculacion\/(\d+)$/, load: () => import('./views/seguimiento.js') },
  { pattern: /^\/estudiantes\/(\d+)$/, roles: ['EMPRESA', 'DOCENTE', 'ADMIN'], load: () => import('./views/perfil-estudiante.js') },
];
