import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { archivoAcciones, wireArchivos } from '../lib/archivos.js';
import { formatDate, todayIso } from '../lib/format.js';
import { badge, emptyState, errorBox, icon, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';

/**
 * Documentos de seguimiento de una práctica (ARL, bitácora, control de
 * asistencia e informes), cada uno con vista previa y descarga. Los ven el
 * estudiante, la empresa y la FET; el docente los revisa. Después de
 * montarla, llama a `wireDocumentos` para conectar los botones.
 */
export function documentosTabla(docs, { revisar = false, aprobarEmpresa = false, vacio = '' } = {}) {
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
            <td><b>${d.tipo.etiqueta}</b>${d.subido_por && d.subido_por.valor !== 'ESTUDIANTE' ? html`<small>Subido por: ${d.subido_por.etiqueta.toLowerCase()}</small>` : ''}<small>${archivoAcciones(d.url_descarga, `${d.tipo.etiqueta} ${d.periodo_inicio}.pdf`)}</small></td>
            <td class="num">${formatDate(d.periodo_inicio)} a ${formatDate(d.periodo_fin)}</td>
            <td class="num">${formatDate(d.cargado_en)}</td>
            <td>
              ${statusBadge(d.estado_revision)}${d.observaciones ? html`<small>${d.observaciones}</small>` : ''}
              ${d.aprobado_empresa_en ? html`<small>${badge('ok', 'Aprobado por la empresa')}</small>` : ''}
              ${aprobarEmpresa && !d.aprobado_empresa_en ? html`<button class="btn sec sm" data-aprobar-empresa="${d.id}">${icon('check')}Dar visto bueno</button>` : ''}
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

/** Formulario para subir un documento a la práctica. */
export const subidaDocumento = (v, tipos, tipoInicial = '') =>
  html`<section class="panel" id="subir" hidden>
    <h2>Subir documento</h2>
    <form id="upForm" class="form-grid" novalidate>
      <div class="field">
        <label for="tipo">Tipo</label>
        <select class="input" id="tipo" name="tipo">
          ${tipos.map((t) => html`<option value="${t.valor}" ${t.valor === tipoInicial ? 'selected' : ''}>${t.etiqueta}</option>`)}
        </select>
      </div>
      <div class="field">
        <label for="periodo_inicio">Desde</label>
        <input class="input" type="date" id="periodo_inicio" name="periodo_inicio" min="${v.fecha_inicio}" max="${todayIso()}" />
      </div>
      <div class="field">
        <label for="periodo_fin">Hasta</label>
        <input class="input" type="date" id="periodo_fin" name="periodo_fin" />
      </div>
      <div class="field wide">
        <label for="archivo">Archivo PDF (máx. 10 MB)</label>
        <input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" />
      </div>
      <div class="actions">
        <button class="btn" type="submit">${icon('upload')}Subir</button>
        <button class="btn sec" type="button" id="cancelar">Cancelar</button>
      </div>
    </form>
    <div id="upError"></div>
  </section>`;

/** Conecta el botón #nuevo con el formulario de subida. */
export function wireSubidaDocumento(root, vinculacionId, refresh) {
  const subir = root.querySelector('#subir');
  if (!subir) return;
  const mostrar = (visible) => {
    subir.hidden = !visible;
    if (visible) subir.querySelector('select').focus();
  };
  root.querySelector('#nuevo')?.addEventListener('click', () => mostrar(subir.hidden));
  root.querySelector('#cancelar').addEventListener('click', () => mostrar(false));

  const form = root.querySelector('#upForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${vinculacionId}/documentos`, new FormData(form));
        toast('Documento subido.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#upError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}

/** Conecta la vista previa, la descarga, la revisión docente y el visto bueno de la empresa. */
export function wireDocumentos(root, refresh) {
  wireArchivos(root);

  root.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-aprobar-empresa]');
    if (!boton) return;
    withBusy(boton, async () => {
      try {
        await api.post(`/documentos/${boton.dataset.aprobarEmpresa}/aprobacion-empresa`);
        toast('Visto bueno registrado.');
        refresh();
      } catch (error) {
        toast(error.message);
      }
    });
  });

  // Delegado en el contenedor: sirve aunque la tabla se vuelva a dibujar.
  root.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-review]');
    if (!form) return;
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
  });
}
