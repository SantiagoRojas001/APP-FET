import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { emptyState, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../lib/ui.js';

/**
 * Evaluación cuantitativa de la práctica (PM-GFO-F-015), por momentos. La
 * empresa califica todos sus ítems; el docente deja sin nota los que no evalúa.
 */
export default async function calificacionView({ root, params, navigate, refresh }) {
  const id = params[0];
  const [v, data] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get(`/vinculaciones/${id}/calificaciones`)]);
  const esDocente = data.parte?.valor === 'DOCENTE';

  const opciones = html`<option value="">${esDocente ? 'No evaluar' : 'Nota'}</option>
    ${Object.entries(data.escala).map(([n, nombre]) => html`<option value="${n}">${n}. ${nombre}</option>`)}`;

  const formulario = (m) =>
    html`<form class="form-grid" data-momento="${m.momento}" novalidate>
      ${data.categorias.map(
        (c) => html`<div class="wide">
          <h3>${c.etiqueta} (${c.peso} %)</h3>
          <div class="lines">
            ${c.items.map(
              (i) => html`<div class="line field">
                <label for="m${m.momento}${i.id}">${i.texto}</label>
                <select class="input narrow" id="m${m.momento}${i.id}" name="${i.id}">${opciones}</select>
              </div>`,
            )}
          </div>
        </div>`,
      )}
      <div class="field wide"><label for="obs${m.momento}">Observaciones</label><textarea class="input" id="obs${m.momento}" name="observaciones" maxlength="2000"></textarea></div>
      <div class="actions"><button class="btn" type="submit">${icon('send')}Enviar calificación</button></div>
      <div class="wide" data-error></div>
    </form>`;

  const registro = (r) =>
    html`<div>
      <div class="panel-h"><h3>${r.parte.etiqueta}</h3><span class="fine">${formatDate(r.creada_en)}</span></div>
      <div class="lines">${r.puntajes.map((p) => html`<div class="line"><div><span>${p.texto}</span></div><b>${p.puntaje}</b></div>`)}</div>
      ${r.observaciones ? html`<p class="note-box">${r.observaciones}</p>` : ''}
    </div>`;

  mount(
    root,
    html`${pageHeader('Evaluación cuantitativa', v.estudiante.nombre_completo, html`<button class="btn sec" id="back">Volver al seguimiento</button>`)}
      ${data.momentos.map(
        (m) => html`<section class="panel">
          <h2>Momento ${m.momento} (${m.peso} %)</h2>
          ${data.parte && m.habilitado && !m.mia ? formulario(m) : ''}
          ${m.registros.map(registro)}
          ${!m.registros.length && !(data.parte && m.habilitado && !m.mia) ? emptyState('clipboard-check', 'Sin calificaciones') : ''}
        </section>`,
      )}`,
  );

  root.querySelector('#back').addEventListener('click', () => navigate(`/vinculacion/${id}`));

  root.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-momento]');
    if (!form) return;
    e.preventDefault();
    const puntajes = Object.fromEntries(
      data.categorias.flatMap((c) => c.items).flatMap((i) => (form.elements[i.id].value ? [[i.id, Number(form.elements[i.id].value)]] : [])),
    );
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${id}/calificaciones`, {
          momento: Number(form.dataset.momento),
          puntajes,
          observaciones: form.observaciones.value.trim() || null,
        });
        toast('Calificación registrada.');
        refresh();
      } catch (error) {
        mount(form.querySelector('[data-error]'), errorBox(error.errors?.puntajes?.[0] ?? 'Revisa los ítems marcados.'));
        showFieldErrors(form, Object.fromEntries(Object.entries(error.errors ?? {}).map(([k, m]) => [k.replace('puntajes.', ''), m])));
      }
    });
  });
}
