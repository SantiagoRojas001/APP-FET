import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { pageHeader } from '../lib/ui.js';
import { hojaVidaDetalle } from './hoja-vida-detalle.js';
import { hydrateRepositories } from './repositorio.js';

/** Hoja de vida completa de un estudiante, en solo lectura (empresas, docentes y admin). */
export default async function perfilEstudianteView({ root, params }) {
  const s = await api.get(`/estudiantes/${params[0]}`);

  mount(
    root,
    html`${pageHeader(
        s.nombre_completo,
        `${s.programa.nombre}, ${s.semestre_actual}.º semestre`,
        html`<button class="btn sec" id="back">Volver</button>`,
      )}
      ${hojaVidaDetalle(s)}`,
  );

  root.querySelector('#back').addEventListener('click', () => history.back());
  hydrateRepositories(root);
}
