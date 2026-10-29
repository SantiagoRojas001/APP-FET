import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { todayIso } from '../../lib/format.js';
import { errorBox, hoursMeter, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';
import { evidenciasTabla, wireEvidencias } from '../evidencias.js';

/**
 * Reporte semanal de horas con su evidencia. La empresa aprueba cada semana o
 * la devuelve con un comentario; solo las horas aprobadas cuentan.
 */
export default async function evidenciasView({ root, navigate, refresh }) {
  const id = session.user.vinculacion_activa_id;
  const [v, evidencias] = await Promise.all([api.get(`/vinculaciones/${id}`), api.get(`/vinculaciones/${id}/evidencias`)]);
  const devueltas = evidencias.filter((e) => e.estado.valor === 'OBSERVADO').length;

  mount(
    root,
    html`${pageHeader('Horas semanales', 'Cada semana reporta tus horas y lo que hiciste. Cuentan cuando la empresa las aprueba.')}
      <section class="panel">
        ${hoursMeter(v.horas)}
        ${v.horas.semanas_por_revisar ? html`<p class="help">${v.horas.semanas_por_revisar} semana(s) esperan la aprobación de la empresa.</p>` : ''}
        ${devueltas ? html`<p class="note-box">${icon('alert-circle')} La empresa devolvió ${devueltas} semana(s). Corrígelas en la lista para que cuenten.</p>` : ''}
        ${
          v.horas.cumplidas_en
            ? html`<div class="callout">
                ${icon('circle-check')}
                <p>
                  <b>Cumpliste las horas de tu programa.</b> Puedes seguir reportando semanas: tu proceso culmina
                  cuando la empresa responda tu evaluación.
                </p>
                ${v.evaluaciones.mia ? '' : html`<button class="btn sm" id="toEval">Evaluar la empresa</button>`}
              </div>`
            : ''
        }
      </section>
      <section class="panel">
        <h2>Reportar una semana</h2>
        <form id="evForm" class="form-grid" novalidate>
          <div class="field">
            <label for="semana_inicio">Cualquier día de la semana</label
            ><input class="input" type="date" id="semana_inicio" name="semana_inicio" value="${todayIso()}" min="${v.fecha_inicio}" max="${todayIso()}" />
          </div>
          <div class="field">
            <label for="horas">Horas de la semana</label
            ><input class="input" type="number" id="horas" name="horas" min="0.5" max="60" step="0.5" value="40" />
          </div>
          <div class="field">
            <label for="archivo">Soporte (PDF o foto, opcional)</label
            ><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf,image/jpeg,image/png" />
          </div>
          <div class="field wide">
            <label for="actividades">Actividades de la semana</label
            ><textarea class="input" id="actividades" name="actividades" maxlength="2000"></textarea>
          </div>
          <div><button class="btn" type="submit">${icon('upload')}Enviar a la empresa</button></div>
        </form>
        <div id="evError"></div>
      </section>
      <section class="panel flush">${evidenciasTabla(evidencias, { corregir: true, vacio: 'Empieza con tu primera semana de práctica.' })}</section>`,
  );

  root.querySelector('#toEval')?.addEventListener('click', () => navigate(`/vinculacion/${id}/evaluacion`));
  wireEvidencias(root, refresh);

  const form = root.querySelector('#evForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = new FormData(form);
    if (!form.archivo.files.length) data.delete('archivo');
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(`/vinculaciones/${id}/evidencias`, data);
        toast('Semana enviada. La empresa la revisará.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#evError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
