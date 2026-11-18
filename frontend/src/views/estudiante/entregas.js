import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { archivoAcciones } from '../../lib/archivos.js';
import { formatDate } from '../../lib/format.js';
import { icon, pageHeader } from '../../lib/ui.js';
import { session } from '../../session.js';
import { documentosTabla, subidaDocumento, wireDocumentos, wireSubidaDocumento } from '../documentos.js';

/**
 * Entregas de la pasantía activa: el estudiante sube su ARL y sus documentos,
 * sigue la revisión docente y descarga los materiales de su docente.
 */
export default async function entregasView({ root, refresh }) {
  const id = session.user.vinculacion_activa_id;
  const [v, cat, materiales] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get('/catalogos'), api.get('/estudiante/materiales')]);
  const sinArl = !v.documentos.some((d) => d.tipo.valor === 'ARL');
  const observados = v.documentos.filter((d) => d.estado_revision.valor === 'OBSERVADO').length;

  mount(
    root,
    html`${pageHeader('Entregas', '', v.puede.subir_documento ? html`<button class="btn" id="nuevo">${icon('upload')}Subir documento</button>` : '')}
      ${sinArl ? html`<p class="note-box">${icon('alert-circle')} Sube tu certificado de ARL: sin él no puedes reportar horas.</p>` : ''}
      ${observados ? html`<p class="note-box">${icon('alert-circle')} Tienes ${observados} documento(s) con observaciones del docente.</p>` : ''}
      ${subidaDocumento(v, cat.tipos_documento, sinArl ? 'ARL' : '')}
      <section class="panel flush">${documentosTabla(v.documentos, { vacio: 'Empieza por tu certificado de ARL.' })}</section>
      ${materiales.length
        ? html`<section class="panel">
            <h2>Materiales de tu docente</h2>
            <div class="lines">
              ${materiales.map((m) => html`<div class="line"><div><b>${m.titulo}</b><span>${formatDate(m.creado_en)}</span></div>${archivoAcciones(m.url_descarga, `${m.titulo}.pdf`)}</div>`)}
            </div>
          </section>`
        : ''}`,
  );

  wireDocumentos(root, refresh);
  wireSubidaDocumento(root, id, refresh);
}
