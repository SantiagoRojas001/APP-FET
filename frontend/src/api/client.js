import { session } from '../session.js';

/** Error de la API con el código HTTP y, si es de validación, los errores por campo. */
export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

const MENSAJES = {
  401: 'Tu sesión terminó. Ingresa de nuevo.',
  403: 'No tienes permiso para esta acción.',
  404: 'No encontramos lo que buscas.',
  429: 'Demasiadas solicitudes. Espera un momento.',
  500: 'Ocurrió un error en el servidor. Inténtalo más tarde.',
};

/**
 * Llama a la API de Laravel. Agrega el token, convierte el cuerpo a JSON (o
 * envía FormData tal cual) y lanza ApiError si la respuesta no es exitosa.
 */
export async function request(method, path, body) {
  const headers = { Accept: 'application/json' };
  const token = session.token;
  if (token) headers.Authorization = `Bearer ${token}`;

  const options = { method, headers };
  if (body instanceof FormData) {
    options.body = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`/api${path}`, options);
  } catch {
    throw new ApiError(0, 'No hay conexión con el servidor. Revisa que la API esté encendida.');
  }

  if (response.status === 204) return null;

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401 && token) session.expire();
    throw new ApiError(response.status, data.message || MENSAJES[response.status] || MENSAJES[500], data.errors);
  }

  return data;
}

async function fetchFile(path) {
  const response = await fetch(path, { headers: { Authorization: `Bearer ${session.token}` } });
  if (!response.ok) throw new ApiError(response.status, MENSAJES[response.status] || MENSAJES[500]);
  return response.blob();
}

export const api = {
  get: (path, params) => {
    const query = params ? new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)) : '';
    return request('GET', query && query.size ? `${path}?${query}` : path);
  },
  post: (path, body) => request('POST', path, body),
  put: (path, body) => request('PUT', path, body),
  patch: (path, body) => request('PATCH', path, body),

  /** Descarga un archivo protegido (con token) y lo abre en una pestaña nueva. */
  async open(path) {
    const url = URL.createObjectURL(await fetchFile(path));
    window.open(url, '_blank', 'noopener');
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  },

  /** URL local (blob) de un archivo protegido, para mostrarlo en un <img>. */
  async blobUrl(path) {
    return URL.createObjectURL(await fetchFile(path));
  },

  /** Descarga un archivo protegido (con token) y lo guarda con el nombre dado. */
  async download(path, filename) {
    const url = URL.createObjectURL(await fetchFile(path));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  },
};
