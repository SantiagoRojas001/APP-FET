/**
 * Rutas comunes a todos los roles que no salen en el menú lateral: a
 * Notificaciones se llega con la campana y a Reportar problema desde el menú
 * del perfil, ambos en el encabezado de la página.
 */
const NOTIFICACIONES = { path: '/notificaciones', hidden: true, load: () => import('./views/notificaciones.js') };
const COMUNES = [NOTIFICACIONES, { path: '/reportar', hidden: true, load: () => import('./views/reportar.js') }];

const HOJA_VIDA = [
  { path: '/hoja-vida', label: 'Hoja de vida', icon: 'file-cv', ayuda: 'Completa tu hoja de vida en el formato de la FET, con tu foto y tu firma, y envíala a revisión. Cuando tu docente la apruebe, las empresas podrán verla.', load: () => import('./views/estudiante/hoja-vida.js') },
  { path: '/hoja-vida/vista', hidden: true, load: () => import('./views/estudiante/hoja-vida-vista.js') },
];

/**
 * Rutas por entorno. Cada vista se carga bajo demanda (import dinámico) y su
 * entrada en el menú lateral sale de aquí; `ayuda` la explica en el recorrido
 * guiado del primer ingreso. El entorno es el rol, salvo para
 * el estudiante, que tiene dos (ver `session.environment`).
 */
export const ROUTES = {
  // Estudiante que aún no cursa práctica: publica su hoja de vida y ve a las empresas interesadas.
  ESTUDIANTE: [
    ...HOJA_VIDA,
    { path: '/citas', label: 'Empresas interesadas', icon: 'calendar-event', ayuda: 'Aquí ves las citas que te asignan las empresas: dirección, fecha y hora.', load: () => import('./views/estudiante/citas.js') },
    // Desde el menú del perfil, cuando el estudiante tiene prácticas terminadas.
    { path: '/mis-practicas', hidden: true, load: () => import('./views/estudiante/mis-practicas.js') },
    ...COMUNES,
  ],
  // Estudiante con pasantía activa: reporta sus horas y entrega sus documentos.
  ESTUDIANTE_PASANTIA: [
    { path: '/pasantia', label: 'Mi pasantía', icon: 'building', ayuda: 'El resumen de tu práctica: empresa, tutor, avance de horas y estado de tus documentos.', load: () => import('./views/estudiante/pasantia.js') },
    { path: '/evidencias', label: 'Horas semanales', icon: 'clock-hour-4', ayuda: 'Cada semana reporta tus horas y actividades. Cuentan cuando la empresa las aprueba.', load: () => import('./views/estudiante/evidencias.js') },
    { path: '/entregas', label: 'Entregas', icon: 'file-text', ayuda: 'Sube tu ARL, bitácora, control de asistencia e informes, y revisa lo que te observa tu docente.', load: () => import('./views/estudiante/entregas.js') },
    ...HOJA_VIDA,
    ...COMUNES,
  ],
  DOCENTE: [
    { path: '/registro', label: 'Cargar listado', icon: 'upload', ayuda: 'Sube el listado de modalidades de grado. La app filtra a quienes eligieron pasantía o práctica y registra a los habilitados.', load: () => import('./views/docente/registro.js') },
    { path: '/estudiantes', label: 'Estudiantes', icon: 'user-plus', ayuda: 'Los estudiantes registrados. Al escribir su promedio, la app les envía el correo con el resultado.', load: () => import('./views/docente/estudiantes.js') },
    { path: '/revision', label: 'Hojas de vida', icon: 'clipboard-check', ayuda: 'Aprueba las hojas de vida que envían tus estudiantes o devuélvelas con recomendaciones.', load: () => import('./views/docente/revision.js') },
    { path: '/vacantes', label: 'Vacantes', icon: 'briefcase', ayuda: 'Vacantes de las empresas: recomienda a tus estudiantes y la empresa los verá primero.', load: () => import('./views/docente/vacantes.js') },
    { path: '/en-curso', label: 'Prácticas', icon: 'users', ayuda: 'Sigue las prácticas en curso: horas, documentos por revisar y reportes.', load: () => import('./views/docente/vinculaciones.js') },
    ...COMUNES,
  ],
  EMPRESA: [
    { path: '/hojas-vida', label: 'Hojas de vida', icon: 'file-cv', ayuda: 'Hojas de vida aprobadas por la FET. Asigna una cita al estudiante que te interese.', load: () => import('./views/empresa/hojas-vida.js') },
    { path: '/citas', label: 'Citas', icon: 'calendar-event', ayuda: 'Tus citas programadas. Después de cada una, acepta o rechaza al estudiante.', load: () => import('./views/empresa/citas.js') },
    { path: '/practicantes', label: 'Mis practicantes', icon: 'users', ayuda: 'Aprueba las horas de tus practicantes con todo lo que te enviaron y evalúalos al terminar.', load: () => import('./views/empresa/practicantes.js') },
    { path: '/vacantes', label: 'Mis vacantes', icon: 'briefcase', ayuda: 'Publica tus vacantes. Los docentes de la FET te recomiendan estudiantes.', load: () => import('./views/empresa/vacantes.js') },
    { path: '/convenio', label: 'Convenio con la FET', icon: 'file-text', ayuda: 'Sube tu convenio con la FET. Con el convenio aprobado y vigente puedes asignar citas.', load: () => import('./views/empresa/acuerdo.js') },
    ...COMUNES,
  ],
  DIRECTOR: [
    { path: '/programa', label: 'Estudiantes', icon: 'school', ayuda: 'Los estudiantes de tu programa, con su ciclo y si están en pasantía, práctica o aún no.', load: () => import('./views/director/estudiantes.js') },
    { path: '/cartas', label: 'Cartas de presentación', icon: 'send', ayuda: 'Estudiantes aceptados por una empresa. Al enviar la carta de presentación empieza su práctica.', load: () => import('./views/director/cartas.js') },
    { path: '/convenios', label: 'Convenios', icon: 'file-text', ayuda: 'Los convenios de las empresas con la FET: vigentes y pendientes.', load: () => import('./views/acuerdos.js') },
    ...COMUNES,
  ],
  ADMIN: [
    { path: '/resumen', label: 'Resumen', icon: 'chart-bar', ayuda: 'Lo que está por atender y las cifras del periodo.', load: () => import('./views/admin/resumen.js') },
    { path: '/empresas', label: 'Empresas', icon: 'building', ayuda: 'Valida a las empresas que se registran y corrige sus datos.', load: () => import('./views/admin/empresas.js') },
    { path: '/convenios', label: 'Convenios', icon: 'file-text', ayuda: 'Aprueba o rechaza los convenios que suben las empresas.', load: () => import('./views/acuerdos.js') },
    { path: '/reglas', label: 'Parámetros', icon: 'settings', ayuda: 'Promedio mínimo, semestres habilitados y horas de práctica por ciclo.', load: () => import('./views/admin/reglas.js') },
    { path: '/usuarios', label: 'Usuarios', icon: 'users', ayuda: 'Crea docentes, directores y administradores y asigna su programa.', load: () => import('./views/admin/usuarios.js') },
    { path: '/informes', label: 'Informes', icon: 'trending-up', ayuda: 'Cifras por periodo para acreditación, exportables a Excel.', load: () => import('./views/admin/informes.js') },
    { path: '/reportes', label: 'Reportes', icon: 'flag', ayuda: 'Problemas que reportan los usuarios.', load: () => import('./views/admin/reportes.js') },
    { path: '/auditoria', label: 'Auditoría', icon: 'history', ayuda: 'Registro de las acciones importantes y de quién las hizo.', load: () => import('./views/admin/auditoria.js') },
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
