import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, todayIso } from '../../lib/format.js';
import { emptyState, errorBox, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

/**
 * Convenio de la empresa con la FET. Lo aprueba la administración;
 * sin uno aprobado y vigente no se pueden asignar citas.
 */
export default async function acuerdoView({ root, refresh }) {
  const convenios = await api.get('/empresa/acuerdos');
  const vigente = convenios.find((a) => a.vigente);
  const pendiente = convenios.some((a) => a.estado.valor === 'PENDIENTE');

  mount(
    root,
    html`${pageHeader('Convenio con la FET')}
      <section class="callout">
        ${icon(vigente ? 'circle-check' : 'alert-circle')}
        <p>
          ${vigente
            ? html`<b>Tienes un convenio vigente</b> hasta el ${formatDate(vigente.vigencia_fin)}.`
            : pendiente
              ? html`<b>Tu convenio está en revisión.</b> Te avisaremos cuando la FET lo apruebe.`
              : html`<b>Aún no tienes un convenio vigente.</b> Súbelo para poder asignar citas.`}
        </p>
      </section>
      ${pendiente
        ? ''
        : html`<section class="panel">
            <h2>${vigente ? 'Subir renovación' : 'Subir convenio'}</h2>
            <form id="acForm" class="form-grid" novalidate>
              <div class="field"><label for="vigencia_inicio">Vigente desde</label><input class="input" type="date" id="vigencia_inicio" name="vigencia_inicio" value="${todayIso()}" /></div>
              <div class="field"><label for="vigencia_fin">Vigente hasta</label><input class="input" type="date" id="vigencia_fin" name="vigencia_fin" min="${todayIso()}" /></div>
              <div class="field"><label for="archivo">Convenio en PDF (máx. 10 MB)</label><input class="input" type="file" id="archivo" name="archivo" accept="application/pdf" /></div>
              <div><button class="btn" type="submit">${icon('upload')}Enviar a la FET</button></div>
            </form>
            <div id="acError"></div>
          </section>`}
      <section class="panel"><h2>Historial</h2></section>
      <section class="panel flush">
        ${convenios.length
          ? html`<div class="tbl"><table>
              <thead><tr><th>Vigencia</th><th>Enviado</th><th>Estado</th></tr></thead>
              <tbody>${convenios.map(
                (a) => html`<tr>
                  <td class="num">${formatDate(a.vigencia_inicio)} a ${formatDate(a.vigencia_fin)}<small><button class="link" data-file="${a.url_archivo}">${icon('download')}Ver PDF</button></small></td>
                  <td class="num">${formatDate(a.cargado_en)}</td>
                  <td>${statusBadge(a.estado)}${a.observacion ? html`<small>${a.observacion}</small>` : ''}</td>
                </tr>`,
              )}</tbody></table></div>`
          : emptyState('file-text', 'Aún no has enviado convenios')}
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) api.open(file.dataset.file).catch((err) => toast(err.message));
  });

  const form = root.querySelector('#acForm');
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/empresa/acuerdos', new FormData(form));
        toast('Convenio enviado. La FET lo revisará.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#acError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
