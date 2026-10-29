import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { relativeDays } from '../lib/format.js';
import { currentQuery } from '../router.js';
import {
  emptyState,
  errorBox,
  icon,
  pageHeader,
  showFieldErrors,
  statusBadge,
  toast,
  withBusy,
} from '../lib/ui.js';
import { session } from '../session.js';

const CATEGORIAS = [
  ['FALLA_PLATAFORMA', 'Falla de la plataforma'],
  ['PRACTICA', 'Problema en la práctica'],
  ['DATOS', 'Datos incorrectos'],
  ['OTRO', 'Otro'],
];

/** Prácticas sobre las que el usuario puede reportar, según su rol. */
async function practicas() {
  const fuente = {
    ESTUDIANTE: '/estudiante/vinculaciones',
    EMPRESA: '/empresa/vinculaciones',
    DOCENTE: '/docente/vinculaciones',
  }[session.role];
  if (!fuente) return [];
  const items = await api.get(fuente);
  return items.filter((v) => v.estado.valor === 'ACTIVA');
}

const nombre = (v) =>
  session.role === 'ESTUDIANTE'
    ? `${v.empresa.razon_social} (${v.tipo.etiqueta})`
    : `${v.estudiante.nombre_completo}, ${v.empresa.razon_social}`;

/**
 * Cualquier usuario reporta un problema a la coordinación de prácticas y
 * sigue sus reportes. Si es de una práctica, el reporte queda ligado a ella.
 */
export default async function reportarView({ root, refresh }) {
  const [mios, vinculaciones] = await Promise.all([api.get('/reportes'), practicas()]);
  const preseleccion = currentQuery().get('vinculacion');

  mount(
    root,
    html`${pageHeader('Reportar un problema', 'Cuéntale a la coordinación de prácticas qué pasó. Te avisaremos cuando lo atiendan.')}
      <section class="panel">
        <form id="repForm" class="form-grid" novalidate>
          <div class="field">
            <label for="categoria">Tipo de problema</label
            ><select class="input" id="categoria" name="categoria">
              ${CATEGORIAS.map(([v, l]) => html`<option value="${v}" ${preseleccion && v === 'PRACTICA' ? 'selected' : ''}>${l}</option>`)}
            </select>
          </div>
          ${
            vinculaciones.length
              ? html`<div class="field">
                  <label for="vinculacion_id">Práctica relacionada</label
                  ><select class="input" id="vinculacion_id" name="vinculacion_id">
                    <option value="">Ninguna</option>
                    ${vinculaciones.map((v) => html`<option value="${v.id}" ${String(v.id) === preseleccion ? 'selected' : ''}>${nombre(v)}</option>`)}
                  </select>
                </div>`
              : ''
          }
          <div class="field ${vinculaciones.length ? '' : 'wide'}">
            <label for="asunto">Asunto</label
            ><input class="input" id="asunto" name="asunto" maxlength="150" />
          </div>
          <div class="field wide">
            <label for="descripcion">Describe el problema</label
            ><textarea
              class="input"
              id="descripcion"
              name="descripcion"
              maxlength="5000"
              placeholder="Qué pasó, cuándo y, si aplica, en qué pantalla."
            ></textarea>
          </div>
          <div><button class="btn" type="submit">${icon('send')}Enviar reporte</button></div>
        </form>
        <div id="repError"></div>
      </section>
      <section class="panel">
        <h2>Mis reportes</h2>
        ${
          mios.length
            ? html`<div>
                ${mios.map(
                  (r) =>
                    html`<article class="report">
                      <div class="report-h"><b>${r.asunto}</b>${statusBadge(r.estado)}</div>
                      <span class="fine"
                        >${r.categoria.etiqueta}${r.vinculacion ? ` · Práctica de ${r.vinculacion.estudiante} en ${r.vinculacion.empresa}` : ''}
                        · ${relativeDays(r.creado_en)}</span
                      >
                      <p>${r.descripcion}</p>
                      ${r.respuesta ? html`<p class="note-box">Respuesta de la coordinación: ${r.respuesta}</p>` : ''}
                    </article>`,
                )}
              </div>`
            : emptyState('flag', 'No has enviado reportes')
        }
      </section>`,
  );

  const form = root.querySelector('#repForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/reportes', {
          categoria: form.categoria.value,
          asunto: form.asunto.value.trim(),
          descripcion: form.descripcion.value.trim(),
          vinculacion_id: form.vinculacion_id?.value ? Number(form.vinculacion_id.value) : null,
        });
        toast('Reporte enviado a la coordinación de prácticas.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#repError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
