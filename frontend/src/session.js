const TOKEN_KEY = 'appfet.token';

function storage() {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
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
