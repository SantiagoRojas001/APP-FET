import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { icon, pageHeader, toast, withBusy } from '../../lib/ui.js';
import { hojaVidaDetalle, hydrateFoto } from '../hoja-vida-detalle.js';
import { hydrateRepositories } from '../repositorio.js';

/**
 * Vista previa de la hoja de vida del estudiante, tal como la ven empresas y
 * docentes. Al guardarla completa se envía sola a revisión docente: solo
 * aprobada la ven las empresas. Si el docente la devuelve, se ven sus
 * recomendaciones.
 */
export default async function hojaVidaVistaView({ root, navigate }) {
  const perfil = await api.get('/estudiante/perfil');
  const hv = perfil.hoja_vida;
  const faltan = hv.secciones.filter((s) => !s.completa).map((s) => s.seccion).join(', ');

  const revision = () => {
    if (perfil.hoja_vida_habilitada === false) return '';
    switch (hv.estado?.valor) {
      case 'EN_REVISION':
        return html`<section class="callout">
          ${icon('clock-hour-4')}
          <p><b>Tu hoja de vida está en revisión docente.</b> Te avisaremos cuando la aprueben o si hay algo por corregir. Mientras tanto no puedes editarla.</p>
        </section>`;
      case 'APROBADA':
        return html`<section class="callout">
          ${icon('circle-check')}
          <p><b>Tu docente aprobó tu hoja de vida.</b> Ya la ven las empresas: cuando alguna te asigne una cita, te avisaremos. Si la editas, deberás enviarla de nuevo a revisión.</p>
        </section>`;
      case 'CON_OBSERVACIONES':
        return html`<section class="callout">
          ${icon('alert-circle')}
          <p><b>Tu docente te dejó recomendaciones:</b> ${hv.observaciones}<br />Corrígela y guárdala: volverá a revisión.</p>
        </section>`;
      default:
        return html`<section class="callout">
          ${icon('alert-circle')}
          <p><b>Tu hoja de vida va al ${hv.porcentaje} %.</b>${faltan ? ` Faltan: ${faltan}.` : ''} Al guardarla completa se enviará a revisión.</p>
        </section>`;
    }
  };

  mount(
    root,
    html`${pageHeader(
        perfil.nombre_completo,
        perfil.hoja_vida_habilitada === false
          ? 'Tu hoja de vida está deshabilitada mientras cursas tu práctica: ya no la ven las empresas y no se puede editar.'
          : `${perfil.programa.nombre}, ${perfil.semestre_actual}.º semestre.`,
        html`<div class="actions no-print">
          <button class="btn sec" id="pdf">${icon('download')}Descargar PDF (PA-GTH-F-10)</button>
          ${perfil.hoja_vida_habilitada === false || hv.estado?.valor === 'EN_REVISION' ? '' : html`<button class="btn" id="edit">${icon('file-cv')}Editar</button>`}
        </div>`,
      )}
      ${revision()}
      ${hojaVidaDetalle(perfil)}`,
  );

  root.querySelector('#edit')?.addEventListener('click', () => navigate('/hoja-vida'));
  root.querySelector('#pdf').addEventListener('click', (e) =>
    withBusy(e.currentTarget, () => api.download('/api/estudiante/hoja-vida.pdf', 'hoja-de-vida.pdf').catch((err) => toast(err.message))),
  );
  hydrateRepositories(root);
  hydrateFoto(root);
}
