import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { icon, pageHeader } from '../../lib/ui.js';
import { hojaVidaDetalle } from '../hoja-vida-detalle.js';
import { hydrateRepositories } from '../repositorio.js';

/** Vista previa de la hoja de vida del estudiante, tal como la ven empresas y docentes. */
export default async function hojaVidaVistaView({ root, navigate }) {
  const perfil = await api.get('/estudiante/perfil');

  mount(
    root,
    html`${pageHeader(
        perfil.nombre_completo,
        `Vista previa de tu hoja de vida: así la ven las empresas y tus docentes. ${perfil.programa.nombre}, ${perfil.semestre_actual}.º semestre.`,
        html`<div class="actions no-print">
          <button class="btn sec" id="print">${icon('download')}Imprimir o guardar PDF</button>
          <button class="btn" id="edit">${icon('file-cv')}Editar</button>
        </div>`,
      )}
      ${hojaVidaDetalle(perfil)}`,
  );

  root.querySelector('#edit').addEventListener('click', () => navigate('/hoja-vida'));
  root.querySelector('#print').addEventListener('click', () => window.print());
  hydrateRepositories(root);
}
