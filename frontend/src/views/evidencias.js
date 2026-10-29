import { api } from '../api/client.js';
import { html } from '../lib/html.js';
import { formatShortDate } from '../lib/format.js';
import { emptyState, icon, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';

/**
 * Horas semanales con su evidencia. La misma tabla la ven el estudiante (que
 * corrige las semanas devueltas), la empresa (que aprueba o devuelve con un
 * comentario) y la FET (solo lectura). Después de montarla, llama a
 * `wireEvidencias` para conectar los botones.
 */
export function evidenciasTabla(items, { revisar = false, corregir = false, vacio = '' } = {}) {
  if (!items.length) return emptyState('clock-hour-4', 'Aún no hay semanas reportadas', vacio);

  return html`<div class="tbl">
    <table>
      <thead>
        <tr>
          <th>Semana</th>
          <th>Horas</th>
          <th>Actividades</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(
          (e) => html`<tr>
            <td class="num">${formatShortDate(e.semana_inicio)} a ${formatShortDate(e.semana_fin)}</td>
            <td class="num">${e.horas}</td>
            <td>
              ${e.actividades}
              ${e.url_archivo ? html`<small><button class="link" data-evfile="${e.url_archivo}">${icon('download')}Ver soporte</button></small>` : ''}
            </td>
            <td>
              ${statusBadge(e.estado)}${e.comentario ? html`<small>Empresa: ${e.comentario}</small>` : ''}
              ${revisar && e.estado.valor === 'PENDIENTE' ? revisionForm(e) : ''}
              ${corregir && e.estado.valor === 'OBSERVADO' ? correccionForm(e) : ''}
            </td>
          </tr>`,
        )}
      </tbody>
    </table>
  </div>`;
}

const revisionForm = (e) =>
  html`<form class="decide" data-evrev="${e.id}" novalidate>
    <div class="field">
      <label for="com${e.id}">Comentario</label
      ><input class="input" id="com${e.id}" name="comentario" placeholder="Obligatorio si la devuelves" />
    </div>
    <button class="btn good sm" type="submit" value="APROBADO">${icon('check')}Aprobar</button>
    <button class="btn danger sm" type="submit" value="OBSERVADO">Devolver</button>
  </form>`;

const correccionForm = (e) =>
  html`<form class="decide" data-evfix="${e.id}" novalidate>
    <div class="field">
      <label for="h${e.id}">Horas</label
      ><input class="input" type="number" id="h${e.id}" name="horas" min="0.5" max="60" step="0.5" value="${e.horas}" />
    </div>
    <div class="field">
      <label for="a${e.id}">Actividades</label
      ><textarea class="input" id="a${e.id}" name="actividades" maxlength="2000">${e.actividades}</textarea>
    </div>
    <div class="field">
      <label for="f${e.id}">Soporte nuevo (opcional)</label
      ><input class="input" type="file" id="f${e.id}" name="archivo" accept="application/pdf,image/jpeg,image/png" />
    </div>
    <button class="btn sm" type="submit">${icon('upload')}Enviar corrección</button>
  </form>`;

/** Conecta descargas, revisiones, correcciones y la aprobación en bloque (`[data-evall]`). */
export function wireEvidencias(root, refresh) {
  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-evfile]');
    if (file) api.open(file.dataset.evfile).catch((err) => toast(err.message));

    const all = e.target.closest('[data-evall]');
    if (all) {
      withBusy(all, async () => {
        try {
          const { message } = await api.post(`/vinculaciones/${all.dataset.evall}/evidencias/aprobacion`);
          toast(message);
          refresh();
        } catch (error) {
          toast(error.message);
        }
      });
    }
  });

  root.querySelectorAll('[data-evrev]').forEach((form) =>
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const estado = e.submitter?.value;
      withBusy(e.submitter, async () => {
        try {
          await api.patch(`/evidencias/${form.dataset.evrev}/revision`, {
            estado,
            comentario: form.comentario.value.trim() || null,
          });
          toast(estado === 'APROBADO' ? 'Horas aprobadas.' : 'Semana devuelta. Le avisamos al practicante.');
          refresh();
        } catch (error) {
          showFieldErrors(form, error.errors) || toast(error.message);
        }
      });
    }),
  );

  root.querySelectorAll('[data-evfix]').forEach((form) =>
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const data = new FormData(form);
      if (!form.archivo.files.length) data.delete('archivo');
      withBusy(form.querySelector('[type=submit]'), async () => {
        try {
          await api.post(`/evidencias/${form.dataset.evfix}/correccion`, data);
          toast('Corrección enviada a la empresa.');
          refresh();
        } catch (error) {
          showFieldErrors(form, error.errors) || toast(error.message);
        }
      });
    }),
  );
}
