import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { icon, pageHeader, toast, withBusy } from '../../lib/ui.js';
import { hojaVidaDetalle, hydrateFoto } from '../hoja-vida-detalle.js';
import { hydrateRepositories } from '../repositorio.js';

/** Vista previa de la hoja de vida del estudiante, tal como la ven empresas y docentes. */
export default async function hojaVidaVistaView({ root, navigate }) {
  const perfil = await api.get('/estudiante/perfil');

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
      ${hojaVidaDetalle(perfil)}`,
  );

  root.querySelector('#edit')?.addEventListener('click', () => navigate('/hoja-vida'));
  root.querySelector('#pdf').addEventListener('click', (e) =>
    withBusy(e.currentTarget, () => api.download('/api/estudiante/hoja-vida.pdf', 'hoja-de-vida.pdf').catch((err) => toast(err.message))),
  );
  hydrateRepositories(root);
  hydrateFoto(root);
}
