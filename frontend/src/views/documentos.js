import { api } from '../api/client.js';
import { html } from '../lib/html.js';
import { archivoAcciones, wireArchivos } from '../lib/archivos.js';
import { formatDate } from '../lib/format.js';
import { emptyState, icon, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';

/**
 * Documentos de seguimiento de una práctica (ARL, bitácora, control de
 * asistencia e informes), cada uno con vista previa y descarga. Los ven el
 * estudiante, la empresa y la FET; el docente los revisa. Después de
 * montarla, llama a `wireDocumentos` para conectar los botones.
 */
export function documentosTabla(docs, { revisar = false, vacio = '' } = {}) {
  if (!docs.length) return emptyState('file-text', 'Aún no hay documentos', vacio);

  return html`<div class="tbl">
    <table>
      <thead>
        <tr>
          <th>Documento</th>
          <th>Periodo</th>
          <th>Entregado</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        ${docs.map(
          (d) => html`<tr>
            <td><b>${d.tipo.etiqueta}</b><small>${archivoAcciones(d.url_descarga, `${d.tipo.etiqueta} ${d.periodo_inicio}.pdf`)}</small></td>
            <td class="num">${formatDate(d.periodo_inicio)} a ${formatDate(d.periodo_fin)}</td>
            <td class="num">${formatDate(d.cargado_en)}</td>
            <td>
              ${statusBadge(d.estado_revision)}${d.observaciones ? html`<small>${d.observaciones}</small>` : ''}
              ${revisar && d.estado_revision.valor === 'PENDIENTE' ? revisionForm(d) : ''}
            </td>
          </tr>`,
        )}
      </tbody>
    </table>
  </div>`;
}

const revisionForm = (d) =>
  html`<form class="decide" data-review="${d.id}" novalidate>
    <div class="field">
      <label for="obs${d.id}">Observaciones</label
      ><input class="input" id="obs${d.id}" name="observaciones" placeholder="Obligatorias si hay observaciones" />
    </div>
    <button class="btn good sm" type="submit" value="APROBADO">${icon('check')}Aprobar</button>
    <button class="btn danger sm" type="submit" value="OBSERVADO">Con observaciones</button>
  </form>`;

/** Conecta la vista previa, la descarga y la revisión docente. */
export function wireDocumentos(root, refresh) {
  wireArchivos(root);

  root.querySelectorAll('[data-review]').forEach((form) =>
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const estado = e.submitter?.value;
      withBusy(e.submitter, async () => {
        try {
          await api.patch(`/documentos/${form.dataset.review}/revision`, {
            estado_revision: estado,
            observaciones: form.observaciones.value.trim() || null,
          });
          toast('Revisión guardada.');
          refresh();
        } catch (error) {
          showFieldErrors(form, error.errors) || toast(error.message);
        }
      });
    }),
  );
}
