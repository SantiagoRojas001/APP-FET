const TOKEN_KEY = 'appfet.token';

/**
 * El token vive en sessionStorage: sobrevive a recargar la página, pero se
 * borra al cerrar la pestaña, así la sesión no queda abierta.
 */
function storage() {
  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
}

// Antes el token se guardaba en localStorage y no se borraba al cerrar.
try {
  window.localStorage.removeItem(TOKEN_KEY);
} catch {
  // Sin acceso al almacenamiento: no hay nada que borrar.
}

/**
 * Estado de la sesión: token de Sanctum y usuario autenticado. Se avisa a los
 * suscriptores cuando cambia (por ejemplo, al expirar el token).
 */
export const session = {
  token: storage()?.getItem(TOKEN_KEY) ?? null,
  user: null,
  listeners: new Set(),

  start(token, user) {
    this.token = token;
    this.user = user;
    storage()?.setItem(TOKEN_KEY, token);
    this.emit();
  },

  setUser(user) {
    this.user = user;
  },

  clear() {
    this.token = null;
    this.user = null;
    storage()?.removeItem(TOKEN_KEY);
  },

  expire() {
    this.clear();
    this.emit('expired');
  },

  get role() {
    return this.user?.rol?.valor ?? null;
  },

  /** Entorno de la interfaz: el rol, o ESTUDIANTE_PASANTIA si el estudiante ya está en práctica. */
  get environment() {
    if (this.role === 'ESTUDIANTE' && this.user.vinculacion_activa_id) return 'ESTUDIANTE_PASANTIA';
    return this.role;
  },

  onChange(fn) {
    this.listeners.add(fn);
  },

  emit(reason) {
    this.listeners.forEach((fn) => fn(reason));
  },
};
