import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { emptyState, errorBox, icon, pageHeader, toast, withBusy } from '../lib/ui.js';

const pregunta = (p) =>
  p.opciones
    ? html`<fieldset class="q">
        <legend>${p.texto}</legend>
        <div class="opts">
          ${p.opciones.map((o) => html`<label class="opt"><input type="radio" name="${p.id}" value="${o}" />${o}</label>`)}
        </div>
      </fieldset>`
    : html`<div class="field q">
        <label for="q-${p.id}"
          >${p.texto}${p.opcional ? html` <span class="fine">(opcional)</span>` : ''}</label
        >
        <textarea class="input" id="q-${p.id}" name="${p.id}" maxlength="2000"></textarea>
      </div>`;

/** Muestra el error de cada pregunta debajo de ella (las de opción múltiple son grupos de radios). */
function marcarErrores(form, errors) {
  form.querySelectorAll('.field-error').forEach((el) => el.remove());
  let first = null;
  for (const [key, messages] of Object.entries(errors)) {
    const input = form.querySelector(`[name="${key.replace('respuestas.', '')}"]`);
    if (!input) continue;
    const msg = Object.assign(document.createElement('span'), {
      className: 'field-error',
      textContent: messages[0],
    });
    input.closest('.q').append(msg);
    first ??= input;
  }
  first?.focus();
}

const respondida = (e) =>
  html`<section class="panel">
    <div class="panel-h">
      <h2>${e.tipo.etiqueta}</h2>
      <span class="fine">${formatDate(e.creada_en)}</span>
    </div>
    <dl class="answers">
      ${e.respuestas.map(
        (r) =>
          html`<div>
            <dt>${r.pregunta}</dt>
            <dd>${r.respuesta ?? '—'}</dd>
          </div>`,
      )}
    </dl>
  </section>`;

/**
 * Cuestionario de cierre de una práctica (opción múltiple y preguntas
 * abiertas) y las evaluaciones ya respondidas que el usuario puede leer.
 */
export default async function evaluacionView({ root, params, navigate, refresh }) {
  const id = params[0];
  const [v, data] = await Promise.all([
    api.get(`/vinculaciones/${id}`),
    api.get(`/vinculaciones/${id}/evaluaciones`),
  ]);
  const { cuestionario, habilitada, evaluaciones } = data;
  const pendiente = cuestionario && !evaluaciones.some((e) => e.tipo.valor === cuestionario.tipo.valor);

  mount(
    root,
    html`${pageHeader(
      'Evaluación de la práctica',
      `${v.estudiante.nombre_completo} en ${v.empresa.razon_social}.`,
      html`<button class="btn sec" id="back">Volver al seguimiento</button>`,
    )}
    ${
      pendiente && habilitada
        ? html`<section class="panel">
            <h2>${cuestionario.tipo.etiqueta}</h2>
            <p class="muted">Tus respuestas no se pueden cambiar después de enviarlas.</p>
            <form id="evalForm" class="quiz" novalidate>
              ${cuestionario.preguntas.map(pregunta)}
              <div id="evalError"></div>
              <div><button class="btn" type="submit">${icon('send')}Enviar evaluación</button></div>
            </form>
          </section>`
        : ''
    }
    ${
      pendiente && !habilitada
        ? html`<section class="panel">
            ${emptyState('clipboard-check', 'La evaluación aún no está disponible', 'Se habilita cuando el practicante completa sus horas o la práctica termina.')}
          </section>`
        : ''
    }
    ${evaluaciones.map(respondida)}
    ${!pendiente && !evaluaciones.length ? html`<section class="panel">${emptyState('clipboard-check', 'Aún no hay evaluaciones')}</section>` : ''}`,
  );

  root.querySelector('#back').addEventListener('click', () => navigate(`/vinculacion/${id}`));

  const form = root.querySelector('#evalForm');
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const respuestas = Object.fromEntries(
      cuestionario.preguntas.map((p) => [p.id, new FormData(form).get(p.id) ?? '']),
    );
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${id}/evaluaciones`, { respuestas });
        toast('Evaluación enviada. ¡Gracias!');
        refresh();
      } catch (error) {
        mount(root.querySelector('#evalError'), errorBox('Revisa las preguntas marcadas.'));
        marcarErrores(form, error.errors ?? {});
      }
    });
  });
}
