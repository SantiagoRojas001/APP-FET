import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { todayIso } from '../../lib/format.js';
import { errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';
import { documentosTabla, wireDocumentos } from '../documentos.js';

/**
 * Entregas de la pasantía activa: el estudiante sube su ARL, bitácora,
 * control de asistencia e informes, y ve, descarga y sigue la revisión
 * docente de cada uno.
 */
export default async function entregasView({ root, refresh }) {
  const id = session.user.vinculacion_activa_id;
  const [v, cat] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get('/catalogos')]);
  const sinArl = !v.documentos.some((d) => d.tipo.valor === 'ARL');
  const observados = v.documentos.filter((d) => d.estado_revision.valor === 'OBSERVADO').length;

  mount(
    root,
    html`${pageHeader('Entregas', '', v.puede.subir_documento ? html`<button class="btn" id="nuevo">${icon('upload')}Subir documento</button>` : '')}
      ${sinArl ? html`<p class="note-box">${icon('alert-circle')} Sube tu certificado de ARL: es obligatorio para iniciar la práctica.</p>` : ''}
      ${observados ? html`<p class="note-box">${icon('alert-circle')} Tienes ${observados} documento(s) con observaciones del docente. Corrígelos y súbelos de nuevo.</p>` : ''}
      <section class="panel" id="subir" hidden>
        <h2>Subir documento</h2>
        <form id="upForm" class="form-grid" novalidate>
          <div class="field">
            <label for="tipo">Tipo</label
            ><select class="input" id="tipo" name="tipo">
              ${cat.tipos_documento.map((t) => html`<option value="${t.valor}" ${sinArl && t.valor === 'ARL' ? 'selected' : ''}>${t.etiqueta}</option>`)}
            </select>
          </div>
          <div class="field">
            <label for="periodo_inicio">Desde</label
            ><input class="input" type="date" id="periodo_inicio" name="periodo_inicio" min="${v.fecha_inicio}" max="${todayIso()}" />
          </div>
          <div class="field">
            <label for="periodo_fin">Hasta</label
            ><input class="input" type="date" id="periodo_fin" name="periodo_fin" />
          </div>
          <div class="field wide">
            <label for="archivo">Archivo PDF (máx. 10 MB)</label
            ><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" />
          </div>
          <div class="actions">
            <button class="btn" type="submit">${icon('upload')}Enviar a revisión</button>
            <button class="btn sec" type="button" id="cancelar">Cancelar</button>
          </div>
        </form>
        <div id="upError"></div>
      </section>
      <section class="panel flush">${documentosTabla(v.documentos, { vacio: 'Empieza por tu certificado de ARL.' })}</section>`,
  );

  wireDocumentos(root, refresh);

  const subir = root.querySelector('#subir');
  const mostrar = (visible) => {
    subir.hidden = !visible;
    if (visible) subir.querySelector('select').focus();
  };
  root.querySelector('#nuevo')?.addEventListener('click', () => mostrar(subir.hidden));
  root.querySelector('#cancelar').addEventListener('click', () => mostrar(false));

  const form = root.querySelector('#upForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${id}/documentos`, new FormData(form));
        toast('Documento enviado a revisión.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#upError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
