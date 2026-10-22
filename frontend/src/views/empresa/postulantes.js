import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, initials } from '../../lib/format.js';
import { emptyState, icon, modalityTag, pageHeader, statusBadge, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

function row(p) {
  const s = p.estudiante;
  return html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(s.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${s.nombre_completo}</h3><div class="sub">${s.programa.nombre}, ${s.semestre_actual}.º semestre</div></div>
        ${p.estado.valor === 'APROBADA' ? statusBadge({ valor: 'ENVIADA', etiqueta: 'Por decidir' }) : statusBadge(p.estado)}
      </div>
      <dl class="facts">
        <div><dt>Vacante</dt><dd>${p.vacante.titulo}</dd></div>
        <div><dt>Postulación</dt><dd>${formatDate(p.fecha)}</dd></div>
        <div><dt>Hoja de vida</dt><dd>${s.hoja_vida?.porcentaje ?? 0} %</dd></div>
      </dl>
      <div class="tags">
        ${modalityTag(p.vacante.modalidad, true)}
        ${s.competencias?.map((c) => html`<span class="tag">${c.nombre}</span>`)}
      </div>
      ${s.proyectos?.length
        ? html`<div class="repos">${s.proyectos.map(
            (r) => html`<article class="repo"><div class="nm">${r.titulo}</div>${r.descripcion ? html`<p>${r.descripcion}</p>` : ''}
              <div class="ft"><a href="${r.url_repositorio}" target="_blank" rel="noopener noreferrer">${icon('brand-github')}Ver repositorio</a></div></article>`,
          )}</div>`
        : ''}
      <div class="actions">
        <a class="btn sec sm" href="#/estudiantes/${s.id}">${icon('file-cv')}Hoja de vida completa</a>
        ${p.estado.valor === 'APROBADA'
          ? html`<button class="btn good sm" data-decide="ACEPTADA" data-id="${p.id}">${icon('user-check')}Aceptar</button>
              <button class="btn danger sm" data-decide="DESCARTADA" data-id="${p.id}">Descartar</button>`
          : ''}
      </div>
    </div>
  </article>`;
}

export default async function postulantesView({ root, refresh }) {
  const items = await api.get('/empresa/postulaciones');

  mount(
    root,
    html`${pageHeader('Postulantes', `${session.user.nombre}. Solo ves postulaciones que un docente de la FET ya aprobó.`)}
      <section class="panel flush">
        ${items.length ? items.map(row) : emptyState('inbox', 'Aún no hay postulantes aprobados', 'Aparecerán aquí cuando un docente apruebe una postulación a tus vacantes.')}
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-decide]');
    if (!btn) return;
    const accept = btn.dataset.decide === 'ACEPTADA';
    if (!accept && !confirm('¿Descartar esta postulación?')) return;
    withBusy(btn, async () => {
      try {
        await api.patch(`/empresa/postulaciones/${btn.dataset.id}`, { decision: btn.dataset.decide });
        toast(accept ? 'Estudiante aceptado. La práctica quedó registrada.' : 'Postulación descartada.');
        refresh();
      } catch (error) {
        toast(error.message);
      }
    });
  });
}
