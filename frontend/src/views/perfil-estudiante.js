import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { icon, pageHeader, toast, withBusy } from '../lib/ui.js';
import { hojaVidaDetalle, hydrateFoto } from './hoja-vida-detalle.js';
import { hydrateRepositories } from './repositorio.js';

/** Hoja de vida completa de un estudiante, en solo lectura (empresas, docentes y admin). */
export default async function perfilEstudianteView({ root, params }) {
  const s = await api.get(`/estudiantes/${params[0]}`);

  mount(
    root,
    html`${pageHeader(
        s.nombre_completo,
        `${s.programa.nombre}, ${s.semestre_actual}.º semestre`,
        html`<div class="actions">
          <button class="btn sec" id="pdf">${icon('download')}Hoja de vida en PDF</button>
          <button class="btn sec" id="back">Volver</button>
        </div>`,
      )}
      ${hojaVidaDetalle(s)}`,
  );

  root.querySelector('#back').addEventListener('click', () => history.back());
  root.querySelector('#pdf').addEventListener('click', (e) =>
    withBusy(e.currentTarget, () => api.download(`/api/estudiantes/${s.id}/hoja-vida.pdf`, `hoja-de-vida-${s.codigo}.pdf`).catch((err) => toast(err.message))),
  );
  hydrateRepositories(root);
  hydrateFoto(root);
}
