import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { emptyState, hoursMeter, icon, modalityTag, pageHeader, statusBadge } from '../../lib/ui.js';
import { session } from '../../session.js';
import { hydrateRepositories, repositorySlot } from '../repositorio.js';

const count = (docs, estado) => docs.filter((d) => d.estado_revision.valor === estado).length;

/**
 * Inicio del entorno de pasantía: resumen de la práctica activa, estado de
 * los documentos y vista previa del repositorio. Las horas y las entregas
 * tienen su propia sección en el menú.
 */
export default async function pasantiaView({ root, navigate }) {
  const [v, perfil] = await Promise.all([
    api.get(`/vinculaciones/${session.user.vinculacion_activa_id}`),
    api.get('/estudiante/perfil'),
  ]);
  const docs = v.documentos;

  mount(
    root,
    html`${pageHeader(v.vacante?.titulo ?? v.tipo.etiqueta, `${v.empresa.razon_social}, desde el ${formatDate(v.fecha_inicio)}.`)}
      <section class="panel">
        ${hoursMeter(v.horas)}
        ${v.horas.cumplidas_en
          ? html`<p class="note-box">${icon('circle-check')} Cumpliste las horas de tu programa. Tu proceso culmina cuando la empresa responda tu evaluación.</p>`
          : ''}
        ${docs.some((d) => d.tipo.valor === 'ARL')
          ? ''
          : html`<p class="note-box">${icon('alert-circle')} Sube tu certificado de ARL en Entregas: es obligatorio para iniciar la práctica.</p>`}
      </section>
      <section class="kpis">
        <div class="kpi"><b>${docs.length}</b><span>Documentos entregados</span></div>
        <div class="kpi"><b>${count(docs, 'APROBADO')}</b><span>Aprobados</span></div>
        <div class="kpi"><b>${count(docs, 'PENDIENTE')}</b><span>Por revisar</span></div>
        <div class="kpi"><b>${count(docs, 'OBSERVADO')}</b><span>Con observaciones</span></div>
      </section>
      <div class="split">
        <section class="panel">
          <div class="panel-h"><h2>Datos de la pasantía</h2>${statusBadge(v.estado)}</div>
          <div class="tags">${modalityTag(v.tipo, true)}</div>
          <dl class="kv">
            <div><dt>Empresa</dt><dd>${v.empresa.razon_social}</dd></div>
            <div><dt>Contacto en la empresa</dt><dd>${v.empresa.contacto_nombre ?? 'No registra'}</dd></div>
            <div><dt>Docente tutor</dt><dd>${v.tutor?.correo ?? 'Sin asignar'}</dd></div>
            <div><dt>Semestre al iniciar</dt><dd>${v.semestre_al_vincular}.º</dd></div>
          </dl>
          ${count(docs, 'OBSERVADO')
            ? html`<p class="note-box">${icon('alert-circle')} Tienes documentos con observaciones. Revísalos en Entregas.</p>`
            : ''}
        </section>
        <section class="panel">
          <h2>Mi repositorio</h2>
          ${perfil.proyectos.length
            ? repositorySlot(perfil.proyectos[0])
            : emptyState('brand-github', 'Aún no vinculas un repositorio', 'Agrégalo en tu hoja de vida.', html`<button class="btn sec sm" id="toHv">Ir a hoja de vida</button>`)}
        </section>
      </div>`,
  );

  root.querySelector('#toHv')?.addEventListener('click', () => navigate('/hoja-vida'));
  hydrateRepositories(root);
}
