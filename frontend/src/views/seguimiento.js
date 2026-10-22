import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate, todayIso } from '../lib/format.js';
import { emptyState, errorBox, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';
import { session } from '../session.js';

/**
 * Seguimiento de una vinculación: el estudiante sube documentos; el docente
 * y la empresa los revisan. Los permisos los decide la API; aquí solo se
 * muestran las acciones que corresponden al rol.
 */
export default async function seguimientoView({ root, params, refresh }) {
  const [v, cat] = await Promise.all([api.get(`/vinculaciones/${params[0]}`), api.get('/catalogos')]);
  const role = session.role;
  const canUpload = role === 'ESTUDIANTE' && v.estado.valor === 'ACTIVA';
  const canReview = role === 'DOCENTE' || role === 'EMPRESA';

  const reviewForm = (d) => html`<form class="decide" data-review="${d.id}" novalidate>
    <div class="field"><label for="obs${d.id}">Observaciones</label><input class="input" id="obs${d.id}" name="observaciones" placeholder="Obligatorias si hay observaciones" /></div>
    <button class="btn good sm" type="submit" name="estado_revision" value="APROBADO">${icon('check')}Aprobar</button>
    <button class="btn danger sm" type="submit" name="estado_revision" value="OBSERVADO">Con observaciones</button>
  </form>`;

  mount(
    root,
    html`${pageHeader(
        v.vacante?.titulo ?? v.tipo.etiqueta,
        `${v.estudiante.nombre_completo} en ${v.empresa.razon_social}, desde el ${formatDate(v.fecha_inicio)}.`,
        statusBadge(v.estado),
      )}
      <section class="panel">
        <div class="tags">${modalityTag(v.tipo, true)}</div>
        <dl class="kv">
          <div><dt>Estudiante</dt><dd>${v.estudiante.nombre_completo}</dd></div>
          <div><dt>Programa</dt><dd>${v.estudiante.programa?.nombre}</dd></div>
          <div><dt>Semestre al vincular</dt><dd>${v.semestre_al_vincular}.º</dd></div>
          <div><dt>Docente tutor</dt><dd>${v.tutor?.correo ?? 'Sin asignar'}</dd></div>
          ${v.fecha_fin ? html`<div><dt>Finalizó</dt><dd>${formatDate(v.fecha_fin)}</dd></div>` : ''}
          ${v.reconocida_como_practica !== null
            ? html`<div><dt>Reconocida como práctica</dt><dd>${v.reconocida_como_practica ? 'Sí' : 'No'}</dd></div>`
            : ''}
        </dl>
        ${v.observacion_final ? html`<p class="note-box">Observación final: ${v.observacion_final}</p>` : ''}
      </section>
      ${canUpload
        ? html`<section class="panel"><h2>Subir documento</h2>
            <form id="upForm" class="form-grid" novalidate>
              <div class="field"><label for="tipo">Tipo</label><select class="input" id="tipo" name="tipo">
                ${cat.tipos_documento.map((t) => html`<option value="${t.valor}">${t.etiqueta}</option>`)}
              </select></div>
              <div class="field"><label for="periodo_inicio">Periodo desde</label><input class="input" type="date" id="periodo_inicio" name="periodo_inicio" max="${todayIso()}" /></div>
              <div class="field"><label for="periodo_fin">Periodo hasta</label><input class="input" type="date" id="periodo_fin" name="periodo_fin" /></div>
              <div class="field wide"><label for="archivo">Archivo PDF (máx. 10 MB)</label><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" /></div>
              <div><button class="btn" type="submit">${icon('upload')}Enviar a revisión</button></div>
            </form><div id="upError"></div></section>`
        : ''}
      <section class="panel flush">
        ${v.documentos.length
          ? html`<div class="tbl"><table>
              <thead><tr><th>Documento</th><th>Periodo</th><th>Entregado</th><th>Estado</th></tr></thead>
              <tbody>${v.documentos.map(
                (d) => html`<tr>
                  <td><b>${d.tipo.etiqueta}</b><small><button class="link" data-file="${d.url_descarga}">${icon('download')}Ver PDF</button></small></td>
                  <td class="num">${formatDate(d.periodo_inicio)} a ${formatDate(d.periodo_fin)}</td>
                  <td class="num">${formatDate(d.cargado_en)}</td>
                  <td>
                    ${statusBadge(d.estado_revision)}${d.observaciones ? html`<small>${d.observaciones}</small>` : ''}
                    ${canReview && d.estado_revision.valor === 'PENDIENTE' ? reviewForm(d) : ''}
                  </td>
                </tr>`,
              )}</tbody></table></div>`
          : emptyState('file-text', 'Aún no hay documentos', canUpload ? 'Sube tu primer control de asistencia o diario de campo.' : '')}
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) api.open(file.dataset.file).catch((err) => toast(err.message));
  });

  root.querySelector('#upForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${v.id}/documentos`, new FormData(form));
        toast('Documento enviado a revisión.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#upError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

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
