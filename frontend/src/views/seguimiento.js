import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate, relativeDays, todayIso } from '../lib/format.js';
import {
  badge,
  emptyState,
  errorBox,
  hoursMeter,
  icon,
  modalityTag,
  pageHeader,
  showFieldErrors,
  statusBadge,
  toast,
  withBusy,
} from '../lib/ui.js';
import { session } from '../session.js';
import { evidenciasTabla, wireEvidencias } from './evidencias.js';

/**
 * Seguimiento de una vinculación: horas semanales, documentos, evaluaciones
 * y cierre. El estudiante sube documentos (ARL, bitácora, asistencias e
 * informes) y el docente los revisa. La empresa solo aprueba las horas
 * semanales y, al final, evalúa al practicante: con eso la práctica culmina.
 * El docente puede finalizarla antes si acumula reportes. Los permisos los
 * decide la API (`v.puede`).
 */
export default async function seguimientoView({ root, params, navigate, refresh }) {
  const id = params[0];
  const role = session.role;
  const esFet = role === 'DOCENTE' || role === 'ADMIN';
  const [v, cat, evidencias, reportes] = await Promise.all([
    api.get(`/vinculaciones/${id}`),
    api.get('/catalogos'),
    role === 'ESTUDIANTE' ? [] : api.get(`/vinculaciones/${id}/evidencias`),
    esFet ? api.get(`/vinculaciones/${id}/reportes`) : [],
  ]);
  const canUpload = role === 'ESTUDIANTE' && v.estado.valor === 'ACTIVA';
  const canReview = v.puede.revisar_documentos;
  const activa = v.estado.valor === 'ACTIVA';
  const sinArl = activa && !v.documentos.some((d) => d.tipo.valor === 'ARL');

  const reviewForm = (d) =>
    html`<form class="decide" data-review="${d.id}" novalidate>
      <div class="field">
        <label for="obs${d.id}">Observaciones</label
        ><input
          class="input"
          id="obs${d.id}"
          name="observaciones"
          placeholder="Obligatorias si hay observaciones"
        />
      </div>
      <button class="btn good sm" type="submit" name="estado_revision" value="APROBADO">
        ${icon('check')}Aprobar
      </button>
      <button class="btn danger sm" type="submit" name="estado_revision" value="OBSERVADO">
        Con observaciones
      </button>
    </form>`;

  const cierreEmpresa = () => {
    if (role !== 'EMPRESA' || !activa) return '';
    const faltaHoras = !v.horas.cumplidas_en;
    return html`<section class="panel">
      <h2>Cierre de la práctica</h2>
      <p class="muted">
        No hay un paso de aprobación aparte: cuando el practicante complete las horas, responde su evaluación y
        la práctica culmina. El estudiante recibe el aviso.
      </p>
      <ul class="fine">
        <li>
          ${faltaHoras ? `Faltan horas: lleva ${v.horas.aprobadas} de ${v.horas.requeridas} aprobadas.` : 'El practicante completó las horas.'}
        </li>
      </ul>
      ${v.evaluaciones.habilitadas && !v.evaluaciones.mia
        ? html`<div class="actions"><button class="btn" data-go="/vinculacion/${v.id}/evaluacion">${icon('clipboard-check')}Evaluar y culminar la práctica</button></div>`
        : ''}
    </section>`;
  };

  const cierreDocente = () => {
    if (!v.puede.finalizar) return '';
    const habilitado = v.reportes >= v.reportes_para_finalizar;
    return html`<section class="panel">
      <h2>Finalizar anticipadamente</h2>
      <p class="muted">
        Puedes finalizar la práctica cuando acumula ${v.reportes_para_finalizar} o más reportes de problemas
        (lleva ${v.reportes}).
        ${v.horas.cumplidas_en ? 'El estudiante ya cumplió las horas.' : 'Como el estudiante no ha completado las horas, debes justificar la decisión.'}
      </p>
      ${
        habilitado
          ? html`<form id="finForm" class="form-grid" novalidate>
                <div class="field wide">
                  <label for="justificacion">Justificación</label
                  ><textarea
                    class="input"
                    id="justificacion"
                    name="justificacion"
                    maxlength="2000"
                  ></textarea>
                </div>
                <div><button class="btn danger" type="submit">Finalizar práctica</button></div>
              </form>
              <div id="finError"></div>`
          : ''
      }
    </section>`;
  };

  mount(
    root,
    html`${pageHeader(
        v.vacante?.titulo ?? v.tipo.etiqueta,
        `${v.estudiante.nombre_completo} en ${v.empresa.razon_social}, desde el ${formatDate(v.fecha_inicio)}.`,
        statusBadge(v.estado),
      )}
      <section class="panel">
        <div class="tags">
          ${modalityTag(v.tipo, true)}${v.periodo ? html`<span class="tag">Periodo ${v.periodo}</span>` : ''}
        </div>
        ${hoursMeter(v.horas)}
        <dl class="kv">
          <div>
            <dt>Estudiante</dt>
            <dd>${v.estudiante.nombre_completo}</dd>
          </div>
          <div>
            <dt>Programa</dt>
            <dd>${v.estudiante.programa?.nombre}</dd>
          </div>
          <div>
            <dt>Semestre al vincular</dt>
            <dd>${v.semestre_al_vincular}.º</dd>
          </div>
          <div>
            <dt>Docente tutor</dt>
            <dd>${v.tutor?.correo ?? 'Sin asignar'}</dd>
          </div>
          ${
            v.fecha_fin
              ? html`<div>
                  <dt>Finalizó</dt>
                  <dd>${formatDate(v.fecha_fin)}</dd>
                </div>`
              : ''
          }
          ${
            v.reconocida_como_practica !== null
              ? html`<div>
                  <dt>Reconocida como práctica</dt>
                  <dd>${v.reconocida_como_practica ? 'Sí' : 'No'}</dd>
                </div>`
              : ''
          }
        </dl>
        ${v.observacion_final ? html`<p class="note-box">Justificación del cierre: ${v.observacion_final}</p>` : ''}
        <div class="actions">
          ${
            v.evaluaciones.habilitadas && (v.puede.evaluar || esFet)
              ? html`<button class="btn sec sm" data-go="/vinculacion/${v.id}/evaluacion">
                  ${icon('clipboard-check')}${v.puede.evaluar && !v.evaluaciones.mia ? 'Responder evaluación' : 'Ver evaluaciones'}
                </button>`
              : ''
          }
          <button class="btn sec sm" id="carta">${icon('download')}Carta de bienvenida</button>
          ${activa && role !== 'ADMIN' ? html`<button class="btn sec sm" data-go="/reportar?vinculacion=${v.id}">${icon('flag')}Reportar un problema</button>` : ''}
        </div>
      </section>
      ${sinArl && role !== 'EMPRESA' ? html`<p class="note-box">${icon('alert-circle')} ${role === 'ESTUDIANTE' ? 'Sube tu certificado de ARL: es obligatorio para iniciar la práctica.' : 'El practicante aún no sube su certificado de ARL.'}</p>` : ''}
      ${cierreEmpresa()}${cierreDocente()}
      ${
        esFet
          ? html`<section class="panel">
              <div class="panel-h">
                <h2>Reportes de la práctica</h2>
                ${badge(v.reportes >= v.reportes_para_finalizar ? 'bad' : 'neutral', `${v.reportes} de ${v.reportes_para_finalizar}`)}
              </div>
              ${
                reportes.length
                  ? html`<div>
                      ${reportes.map(
                        (r) =>
                          html`<article class="report">
                            <div class="report-h"><b>${r.asunto}</b>${statusBadge(r.estado)}</div>
                            <span class="fine"
                              >${r.autor.rol.etiqueta} · ${r.autor.correo} ·
                              ${relativeDays(r.creado_en)}</span
                            >
                            <p>${r.descripcion}</p>
                          </article>`,
                      )}
                    </div>`
                  : html`<p class="fine">Nadie ha reportado problemas de esta práctica.</p>`
              }
            </section>`
          : ''
      }
      ${
        canUpload
          ? html`<section class="panel">
              <h2>Subir documento</h2>
              <form id="upForm" class="form-grid" novalidate>
                <div class="field">
                  <label for="tipo">Tipo</label
                  ><select class="input" id="tipo" name="tipo">
                    ${cat.tipos_documento.map((t) => html`<option value="${t.valor}">${t.etiqueta}</option>`)}
                  </select>
                </div>
                <div class="field">
                  <label for="periodo_inicio">Desde (periodo o vigencia)</label
                  ><input
                    class="input"
                    type="date"
                    id="periodo_inicio"
                    name="periodo_inicio"
                    max="${todayIso()}"
                  />
                </div>
                <div class="field">
                  <label for="periodo_fin">Hasta</label
                  ><input class="input" type="date" id="periodo_fin" name="periodo_fin" />
                </div>
                <div class="field wide">
                  <label for="archivo">Archivo PDF (máx. 10 MB)</label
                  ><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" />
                </div>
                <div><button class="btn" type="submit">${icon('upload')}Enviar a revisión docente</button></div>
              </form>
              <div id="upError"></div>
            </section>`
          : ''
      }
      <section class="panel"><h2>Documentos</h2></section>
      <section class="panel flush">
        ${
          v.documentos.length
            ? html`<div class="tbl">
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
                    ${v.documentos.map(
                      (d) =>
                        html`<tr>
                          <td>
                            <b>${d.tipo.etiqueta}</b
                            ><small
                              ><button class="link" data-file="${d.url_descarga}">
                                ${icon('download')}Ver PDF
                              </button></small
                            >
                          </td>
                          <td class="num">${formatDate(d.periodo_inicio)} a ${formatDate(d.periodo_fin)}</td>
                          <td class="num">${formatDate(d.cargado_en)}</td>
                          <td>
                            ${statusBadge(d.estado_revision)}${d.observaciones ? html`<small>${d.observaciones}</small>` : ''}
                            ${canReview && d.estado_revision.valor === 'PENDIENTE' ? reviewForm(d) : ''}
                          </td>
                        </tr>`,
                    )}
                  </tbody>
                </table>
              </div>`
            : emptyState(
                'file-text',
                'Aún no hay documentos',
                canUpload ? 'Empieza por tu certificado de ARL; luego sube tu bitácora y el control de asistencia.' : '',
              )
        }
      </section>
      ${
        role !== 'ESTUDIANTE'
          ? html`<section class="panel">
                <div class="panel-h">
                  <h2>Horas semanales</h2>
                  ${v.puede.revisar_evidencias && v.horas.semanas_por_revisar
                    ? html`<button class="btn good sm" data-evall="${v.id}">${icon('check')}Aprobar las ${v.horas.semanas_por_revisar} pendientes</button>`
                    : ''}
                </div>
                ${v.puede.revisar_evidencias ? html`<p class="help">Aprueba cada semana o devuélvela con un comentario sobre las horas para que el practicante la corrija.</p>` : ''}
              </section>
              <section class="panel flush">${evidenciasTabla(evidencias, { revisar: v.puede.revisar_evidencias })}</section>`
          : ''
      }`,
  );

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) return api.open(file.dataset.file).catch((err) => toast(err.message));
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });

  root.querySelector('#carta').addEventListener('click', (e) =>
    withBusy(e.currentTarget, () =>
      api.download(`/api/vinculaciones/${v.id}/carta-bienvenida.pdf`, 'carta-de-bienvenida.pdf').catch((err) => toast(err.message)),
    ),
  );
  wireEvidencias(root, refresh);

  const finForm = root.querySelector('#finForm');
  finForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!confirm('¿Finalizar la práctica? Esta acción no se puede deshacer.')) return;
    withBusy(finForm.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/docente/vinculaciones/${v.id}/finalizacion`, {
          justificacion: finForm.justificacion.value.trim() || null,
        });
        toast('Práctica finalizada. Avisamos al estudiante y a la empresa.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#finError'), errorBox(error.message));
        showFieldErrors(finForm, error.errors);
      }
    });
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
