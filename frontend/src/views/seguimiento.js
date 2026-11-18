import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate, relativeDays } from '../lib/format.js';
import {
  badge,
  confirmInline,
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
import { documentosTabla, subidaDocumento, wireDocumentos, wireSubidaDocumento } from './documentos.js';
import { evidenciasTabla, wireEvidencias } from './evidencias.js';

/**
 * Seguimiento de una vinculación: horas semanales, documentos, notas y
 * cierre. La empresa aprueba las horas y califica lo actitudinal y lo
 * procedimental; el docente revisa los documentos, califica lo cognitivo y
 * cierra la práctica. El estudiante sube sus documentos en Entregas. Los
 * permisos los decide la API (`v.puede`).
 */
export default async function seguimientoView({ root, params, navigate, refresh }) {
  const id = params[0];
  const role = session.role;
  const esFet = role === 'DOCENTE' || role === 'ADMIN';
  const [v, evidencias, reportes, catalogos] = await Promise.all([
    api.get(`/vinculaciones/${id}`),
    role === 'ESTUDIANTE' ? [] : api.get(`/vinculaciones/${id}/evidencias`),
    esFet ? api.get(`/vinculaciones/${id}/reportes`) : [],
    api.get('/catalogos'),
  ]);
  const subirTerceros = role !== 'ESTUDIANTE' && v.puede.subir_documento;
  const activa = v.estado.valor === 'ACTIVA';
  const sinArl = activa && !v.documentos.some((d) => d.tipo.valor === 'ARL');

  const notas = () => {
    const marca = (hecha) => badge(hecha ? 'ok' : 'neutral', hecha ? 'Registrada' : 'Pendiente');
    return html`<section class="panel">
      <div class="panel-h">
        <h2>Evaluación cuantitativa</h2>
        <button class="btn sec sm" data-go="/vinculacion/${v.id}/calificacion">${icon('clipboard-check')}${v.puede.calificar ? 'Calificar' : 'Ver notas'}</button>
      </div>
      <div class="lines">
        ${v.calificaciones.momentos.map(
          (m) => html`<div class="line">
            <div><b>Momento ${m.momento}</b><span>${m.habilitado ? 'Abierto' : 'Aún no disponible'}</span></div>
            <div class="tags"><span class="fine">Empresa</span>${marca(m.empresa)}<span class="fine">Docente</span>${marca(m.docente)}</div>
          </div>`,
        )}
      </div>
    </section>`;
  };

  const cierreDocente = () => {
    if (!v.puede.finalizar) return '';
    const completa = Boolean(v.horas.cumplidas_en) && v.calificaciones.completas;
    const habilitado = completa || v.reportes >= v.reportes_para_finalizar;
    return html`<section class="panel">
      <h2>${completa ? 'Cerrar la práctica' : 'Finalizar anticipadamente'}</h2>
      ${completa
        ? ''
        : html`<p class="fine">Reportes: ${v.reportes} de ${v.reportes_para_finalizar}. ${v.horas.cumplidas_en ? 'Faltan calificaciones para el cierre normal.' : 'Faltan horas: debes justificar el cierre.'}</p>`}
      ${
        habilitado
          ? html`<form id="finForm" class="form-grid" novalidate>
                <div class="field wide">
                  <label for="justificacion">${completa ? 'Observación' : 'Justificación'}</label
                  ><textarea class="input" id="justificacion" name="justificacion" maxlength="2000"></textarea>
                </div>
                <div><button class="btn ${completa ? '' : 'danger'}" type="submit">${completa ? 'Cerrar práctica' : 'Finalizar práctica'}</button></div>
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
                  ${icon('clipboard-check')}${v.puede.evaluar && !v.evaluaciones.mia ? 'Evaluar la empresa' : 'Ver evaluaciones'}
                </button>`
              : ''
          }
          ${role === 'ESTUDIANTE' ? '' : html`<a class="btn sec sm" href="#/estudiantes/${v.estudiante.id}">${icon('file-cv')}Hoja de vida</a>`}
          ${activa && role !== 'ADMIN' ? html`<button class="btn sec sm" data-go="/reportar?vinculacion=${v.id}">${icon('flag')}Reportar un problema</button>` : ''}
        </div>
      </section>
      ${sinArl ? html`<p class="note-box">${icon('alert-circle')} ${role === 'ESTUDIANTE' ? 'Sube tu certificado de ARL: sin él no puedes reportar horas.' : 'El practicante aún no sube su certificado de ARL.'}</p>` : ''}
      ${notas()}${cierreDocente()}
      ${
        role !== 'ESTUDIANTE'
          ? html`<section class="panel">
                <div class="panel-h">
                  <h2>Horas semanales</h2>
                  ${v.puede.revisar_evidencias && v.horas.semanas_por_revisar
                    ? html`<button class="btn good sm" data-evall="${v.id}">${icon('check')}Aprobar las ${v.horas.semanas_por_revisar} pendientes</button>`
                    : ''}
                </div>
              </section>
              <section class="panel flush">${evidenciasTabla(evidencias, { revisar: v.puede.revisar_evidencias })}</section>`
          : ''
      }
      ${subirTerceros ? subidaDocumento(v, catalogos.tipos_documento) : ''}
      <section class="panel">
        <div class="panel-h"><h2>Documentos</h2>${subirTerceros ? html`<button class="btn sec sm" id="nuevo">${icon('upload')}Subir documento</button>` : ''}</div>
      </section>
      <section class="panel flush">${documentosTabla(v.documentos, { revisar: v.puede.revisar_documentos, aprobarEmpresa: role === 'EMPRESA' && v.puede.revisar_evidencias })}</section>
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
      }`,
  );

  root.addEventListener('click', (e) => {
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });

  wireEvidencias(root, refresh);
  wireDocumentos(root, refresh);
  wireSubidaDocumento(root, v.id, refresh);

  const finForm = root.querySelector('#finForm');
  finForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    confirmInline(finForm.querySelector('[type=submit]'), 'finalizar', (si) =>
      withBusy(si, async () => {
        try {
          await api.post(`/docente/vinculaciones/${v.id}/finalizacion`, {
            justificacion: finForm.justificacion.value.trim() || null,
          });
          toast('Práctica cerrada. Avisamos al estudiante y a la empresa.');
          refresh();
        } catch (error) {
          mount(root.querySelector('#finError'), errorBox(error.message));
          showFieldErrors(finForm, error.errors);
        }
      }),
    );
  });
}
