import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { badge, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../lib/ui.js';

const TIPOS_INPUT = { texto: 'text', fecha: 'date', hora: 'time', numero: 'number' };

const control = (c, nombre, valor, id = nombre) => {
  if (c.tipo === 'area') return html`<textarea class="input" id="${id}" name="${nombre}" maxlength="3000">${valor ?? ''}</textarea>`;
  if (c.tipo === 'opciones')
    return html`<select class="input" id="${id}" name="${nombre}">
      <option value="">Elige</option>
      ${c.opciones.map((o) => html`<option value="${o}" ${o === valor ? 'selected' : ''}>${o}</option>`)}
    </select>`;
  return html`<input class="input" type="${TIPOS_INPUT[c.tipo]}" id="${id}" name="${nombre}" value="${valor ?? ''}" />`;
};

const filaTabla = (campo, fila = {}) =>
  html`<tr>
    ${campo.columnas.map((c) => html`<td>${control(c, c.id, fila[c.id], `${campo.id}-${c.id}-${Math.random().toString(36).slice(2, 8)}`)}</td>`)}
    <td><button type="button" class="btn sec sm" data-quitar>Quitar</button></td>
  </tr>`;

const campoForm = (f, c) =>
  c.tipo === 'tabla'
    ? html`<div class="wide" data-tabla="${c.id}">
        <h3>${c.etiqueta}</h3>
        <div class="tbl"><table>
          <thead><tr>${c.columnas.map((k) => html`<th>${k.etiqueta}</th>`)}<th></th></tr></thead>
          <tbody>${(f.datos[c.id]?.length ? f.datos[c.id] : [{}]).map((fila) => filaTabla(c, fila))}</tbody>
        </table></div>
        <button type="button" class="btn sec sm" data-agregar>${icon('plus')}Agregar fila</button>
      </div>`
    : html`<div class="field ${c.tipo === 'area' ? 'wide' : ''}"><label for="${f.tipo}-${c.id}">${c.etiqueta}</label>${control(c, c.id, f.datos[c.id], `${f.tipo}-${c.id}`)}</div>`;

const tarjeta = (f) =>
  html`<section class="panel" data-formato="${f.tipo}">
    <div class="panel-h">
      <div><h2>${f.titulo}</h2>${f.codigo ? html`<span class="fine">${f.codigo}</span>` : ''}</div>
      <div class="actions">
        ${f.puede_editar ? html`<button class="btn sec sm" data-editar>${icon('pencil')}Diligenciar</button>` : ''}
        ${f.puede_firmar ? html`<button class="btn sm" data-firmar>${icon('check')}Firmar</button>` : ''}
        ${f.disponible ? html`<button class="btn sec sm" data-pdf="${f.url_pdf}">${icon('download')}PDF</button>` : ''}
      </div>
    </div>
    <div class="tags">
      ${f.firmas.map((x) => badge(x.firmada_en ? 'ok' : 'neutral', `${x.etiqueta}: ${x.firmada_en ? formatDate(x.firmada_en) : 'sin firma'}`))}
    </div>
    ${f.puede_editar
      ? html`<form class="form-grid" hidden novalidate>
          ${f.campos.map((c) => campoForm(f, c))}
          <div class="actions"><button class="btn" type="submit">Guardar</button></div>
          <div class="wide" data-error></div>
        </form>`
      : ''}
  </section>`;

/** Formatos institucionales de una práctica: diligenciar, firmar y descargar. */
export default async function formatosView({ root, params, navigate, refresh }) {
  const id = params[0];
  const [v, formatos] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get(`/vinculaciones/${id}/formatos`)]);
  const porTipo = Object.fromEntries(formatos.map((f) => [f.tipo, f]));

  mount(
    root,
    html`${pageHeader('Formatos', v.estudiante.nombre_completo, html`<button class="btn sec" id="back">Volver al seguimiento</button>`)}
      ${formatos.map(tarjeta)}`,
  );

  root.querySelector('#back').addEventListener('click', () => navigate(`/vinculacion/${id}`));

  root.addEventListener('click', (e) => {
    const panel = e.target.closest('[data-formato]');
    if (!panel) return;
    const f = porTipo[panel.dataset.formato];

    if (e.target.closest('[data-editar]')) panel.querySelector('form').hidden = !panel.querySelector('form').hidden;
    if (e.target.closest('[data-quitar]')) e.target.closest('tr').remove();

    const agregar = e.target.closest('[data-agregar]');
    if (agregar) {
      const bloque = agregar.closest('[data-tabla]');
      const campo = f.campos.find((c) => c.id === bloque.dataset.tabla);
      const cuerpo = document.createElement('tbody');
      mount(cuerpo, filaTabla(campo));
      bloque.querySelector('tbody').append(...cuerpo.children);
    }

    const pdf = e.target.closest('[data-pdf]');
    if (pdf) withBusy(pdf, () => api.download(pdf.dataset.pdf, `${f.tipo.toLowerCase()}.pdf`).catch((error) => toast(error.message)));

    const firmar = e.target.closest('[data-firmar]');
    if (firmar) {
      withBusy(firmar, async () => {
        try {
          await api.post(`/vinculaciones/${id}/formatos/${f.tipo}/firma`);
          toast('Formato firmado.');
          refresh();
        } catch (error) {
          toast(error.message);
        }
      });
    }
  });

  root.addEventListener('submit', (e) => {
    const panel = e.target.closest('[data-formato]');
    if (!panel) return;
    e.preventDefault();
    const form = e.target;
    const f = porTipo[panel.dataset.formato];
    const datos = {};
    f.campos.forEach((c) => {
      if (c.tipo === 'tabla') {
        datos[c.id] = [...form.querySelectorAll(`[data-tabla="${c.id}"] tbody tr`)].map((tr) =>
          Object.fromEntries(c.columnas.map((k) => [k.id, tr.querySelector(`[name="${k.id}"]`).value.trim()])),
        );
      } else {
        datos[c.id] = form.elements[c.id].value.trim() || null;
      }
    });
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put(`/vinculaciones/${id}/formatos/${f.tipo}`, datos);
        toast('Formato guardado. Si ya tenía firmas, deben firmarlo de nuevo.');
        refresh();
      } catch (error) {
        mount(form.querySelector('[data-error]'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
