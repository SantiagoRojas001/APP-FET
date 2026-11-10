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
import { documentosTabla, wireDocumentos } from './documentos.js';
import { evidenciasTabla, wireEvidencias } from './evidencias.js';

/**
 * Seguimiento de una vinculación: horas semanales, documentos, evaluaciones
 * y cierre. La empresa aprueba las horas con acceso a todo lo que envió el
 * estudiante (soportes, documentos y hoja de vida) y, al final, lo evalúa:
 * con eso la práctica culmina. El docente revisa los documentos y puede
 * finalizarla antes si acumula reportes. El estudiante sube sus documentos en
 * Entregas. Los permisos los decide la API (`v.puede`).
 */
export default async function seguimientoView({ root, params, navigate, refresh }) {
  const id = params[0];
  const role = session.role;
  const esFet = role === 'DOCENTE' || role === 'ADMIN';
  const [v, evidencias, reportes] = await Promise.all([
    api.get(`/vinculaciones/${id}`),
    role === 'ESTUDIANTE' ? [] : api.get(`/vinculaciones/${id}/evidencias`),
    esFet ? api.get(`/vinculaciones/${id}/reportes`) : [],
  ]);
  const activa = v.estado.valor === 'ACTIVA';
  const sinArl = activa && !v.documentos.some((d) => d.tipo.valor === 'ARL');

  const cierreEmpresa = () => {
    if (role !== 'EMPRESA' || !activa) return '';
    const faltaHoras = !v.horas.cumplidas_en;
    return html`<section class="panel">
      <h2>Cierre de la práctica</h2>
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
          ${role === 'ESTUDIANTE' ? '' : html`<a class="btn sec sm" href="#/estudiantes/${v.estudiante.id}">${icon('file-cv')}Hoja de vida</a>`}
          ${activa && role !== 'ADMIN' ? html`<button class="btn sec sm" data-go="/reportar?vinculacion=${v.id}">${icon('flag')}Reportar un problema</button>` : ''}
        </div>
      </section>
      ${sinArl ? html`<p class="note-box">${icon('alert-circle')} ${role === 'ESTUDIANTE' ? 'Sube tu certificado de ARL: es obligatorio para iniciar la práctica.' : 'El practicante aún no sube su certificado de ARL.'}</p>` : ''}
      ${cierreEmpresa()}${cierreDocente()}
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
      <section class="panel"><h2>Documentos</h2></section>
      <section class="panel flush">${documentosTabla(v.documentos, { revisar: v.puede.revisar_documentos })}</section>
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

  const finForm = root.querySelector('#finForm');
  finForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    confirmInline(finForm.querySelector('[type=submit]'), 'finalizar', (si) =>
      withBusy(si, async () => {
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
      }),
    );
  });
}
