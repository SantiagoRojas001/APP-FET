import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { icon, pageHeader, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';
import { hojaVidaDetalle, hydrateFoto } from '../hoja-vida-detalle.js';
import { hydrateRepositories } from '../repositorio.js';

/**
 * Vista previa de la hoja de vida del estudiante, tal como la ven empresas y
 * docentes. Desde aquí se publica: solo publicada la encuentran las empresas.
 */
export default async function hojaVidaVistaView({ root, navigate, refresh }) {
  const perfil = await api.get('/estudiante/perfil');
  const hv = perfil.hoja_vida;
  const completa = hv.porcentaje === 100;

  const publicacion = () => {
    if (perfil.hoja_vida_habilitada === false) return '';
    if (hv.publicada_en) {
      return html`<section class="callout">
        ${icon('circle-check')}
        <p><b>Tu hoja de vida está publicada.</b> Las empresas con acuerdo vigente la ven y pueden asignarte una cita. Te avisaremos por correo y en la app.</p>
        <button class="btn sec sm" data-publicar="0">Dejar de publicar</button>
      </section>`;
    }
    return html`<section class="callout">
      ${icon(completa ? 'send' : 'alert-circle')}
      <p>
        ${completa
          ? html`<b>Tu hoja de vida está completa.</b> Publícala para que las empresas te encuentren y te asignen citas.`
          : html`<b>Completa tu hoja de vida para publicarla</b> (va al ${hv.porcentaje} %). Faltan: ${hv.secciones.filter((s) => !s.completa).map((s) => s.seccion).join(', ')}.`}
      </p>
      <button class="btn" data-publicar="1" ${completa ? '' : 'disabled'}>${icon('send')}Publicar</button>
    </section>`;
  };

  mount(
    root,
    html`${pageHeader(
        perfil.nombre_completo,
        perfil.hoja_vida_habilitada === false
          ? 'Tu hoja de vida está deshabilitada mientras cursas tu práctica: ya no la ven las empresas y no se puede editar.'
          : `Vista previa de tu hoja de vida: así la ven las empresas y tus docentes. ${perfil.programa.nombre}, ${perfil.semestre_actual}.º semestre.`,
        html`<div class="actions no-print">
          <button class="btn sec" id="pdf">${icon('download')}Descargar PDF (PA-GTH-F-10)</button>
          ${perfil.hoja_vida_habilitada === false ? '' : html`<button class="btn" id="edit">${icon('file-cv')}Editar</button>`}
        </div>`,
      )}
      ${publicacion()}
      ${hojaVidaDetalle(perfil)}`,
  );

  root.querySelector('#edit')?.addEventListener('click', () => navigate('/hoja-vida'));
  root.querySelector('[data-publicar]')?.addEventListener('click', (e) => {
    const publicar = e.currentTarget.dataset.publicar === '1';
    withBusy(e.currentTarget, async () => {
      try {
        await api.patch('/estudiante/hoja-vida/publicacion', { publicada: publicar });
        session.setUser(await api.get('/auth/yo'));
        toast(publicar ? 'Hoja de vida publicada. Las empresas ya pueden verla.' : 'Tu hoja de vida ya no está publicada.');
        refresh();
      } catch (error) {
        toast(error.message);
      }
    });
  });
  root.querySelector('#pdf').addEventListener('click', (e) =>
    withBusy(e.currentTarget, () => api.download('/api/estudiante/hoja-vida.pdf', 'hoja-de-vida.pdf').catch((err) => toast(err.message))),
  );
  hydrateRepositories(root);
  hydrateFoto(root);
}
