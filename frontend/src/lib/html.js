/**
 * Plantillas HTML seguras. Todo valor interpolado se escapa salvo que venga
 * de otra plantilla `html` o se marque con `raw()`. Así los datos que llegan
 * de la API (nombres, descripciones, observaciones) no pueden inyectar HTML.
 */
class SafeHtml {
  constructor(value) {
    this.value = value;
  }

  toString() {
    return this.value;
  }
}

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

export const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ESCAPES[c]);

/** Marca un texto como HTML confiable. Úsalo solo con contenido propio. */
export const raw = (value) => new SafeHtml(value);

function render(value) {
  if (value === null || value === undefined || value === false) return '';
  if (value instanceof SafeHtml) return value.value;
  if (Array.isArray(value)) return value.map(render).join('');
  return escapeHtml(value);
}

export function html(strings, ...values) {
  return new SafeHtml(strings.reduce((out, str, i) => out + str + (i < values.length ? render(values[i]) : ''), ''));
}

/** Reemplaza el contenido de un elemento con una plantilla. */
export function mount(element, template) {
  element.innerHTML = render(template);
}
