import { html } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { badge, icon } from '../lib/ui.js';
import { repositorySlot } from './repositorio.js';

const NIVELES = { BASICO: 'básico', INTERMEDIO: 'intermedio', AVANZADO: 'avanzado' };

/**
 * Hoja de vida completa en solo lectura. La usan la vista previa del
 * estudiante y el perfil que consultan empresas y docentes, así los dos ven
 * exactamente lo mismo. Después de montarla, llama a `hydrateRepositories`
 * para cargar la vista previa de GitHub de cada proyecto.
 */
export function hojaVidaDetalle(s) {
  const hv = s.hoja_vida;

  return html`<section class="panel">
      <div class="panel-h">
        <h2>Datos</h2>
        ${s.habilitado_para_practica ? badge('ok', 'Habilitado para práctica') : badge('neutral', 'Aún no habilitado')}
      </div>
      <dl class="kv">
        <div><dt>Correo</dt><dd>${s.correo}</dd></div>
        <div><dt>Teléfono</dt><dd>${s.telefono ?? '—'}</dd></div>
        <div><dt>Código</dt><dd class="mono">${s.codigo}</dd></div>
        <div><dt>Disponibilidad</dt><dd>${s.disponibilidad ?? '—'}</dd></div>
        <div><dt>Hoja de vida</dt><dd>${hv.porcentaje} % completa</dd></div>
      </dl>
    </section>
    <section class="panel">
      <h2>Perfil profesional</h2>
      <p class="muted">${hv.perfil_profesional ?? 'Sin registrar.'}</p>
      <h2>Formación académica</h2>
      <p class="muted">${hv.formacion ?? 'Sin registrar.'}</p>
    </section>
    <section class="panel">
      <h2>Experiencia laboral</h2>
      ${hv.experiencias?.length
        ? html`<div class="lines">${hv.experiencias.map(
            (e) => html`<div class="line"><div><b>${e.cargo}, ${e.organizacion}</b><span>${formatDate(e.fecha_inicio)} a ${e.fecha_fin ? formatDate(e.fecha_fin) : 'la fecha'}${e.descripcion ? `. ${e.descripcion}` : ''}</span></div></div>`,
          )}</div>`
        : html`<p class="fine">Sin experiencia registrada.</p>`}
    </section>
    <section class="panel">
      <h2>Competencias</h2>
      <div class="tags">${s.competencias.length ? s.competencias.map((c) => html`<span class="tag">${c.nombre} · ${NIVELES[c.nivel] ?? c.nivel}</span>`) : html`<p class="fine">Sin competencias registradas.</p>`}</div>
    </section>
    <section class="panel">
      <h2>Repositorio de proyectos</h2>
      ${s.proyectos.length
        ? s.proyectos.map(
            (p) => html`<article class="project">
              <div class="project-h">
                <div><b>${p.titulo}</b>${p.semestre ? html` <span class="fine">· ${p.semestre}.º semestre</span>` : ''}</div>
                <a class="fine" href="${p.url_repositorio}" target="_blank" rel="noopener noreferrer">${icon('external-link')} Abrir en GitHub</a>
              </div>
              ${p.descripcion ? html`<p class="muted">${p.descripcion}</p>` : ''}
              ${repositorySlot(p)}
            </article>`,
          )
        : html`<p class="fine">Sin proyectos registrados.</p>`}
    </section>`;
}
