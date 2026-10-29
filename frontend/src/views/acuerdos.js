import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { badge, emptyState, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';
import { session } from '../session.js';

const TABS = [
  ['PENDIENTE', 'Pendientes'],
  ['APROBADO', 'Aprobados'],
  ['RECHAZADO', 'Rechazados'],
  ['', 'Todos'],
];

/**
 * Acuerdos de las empresas. La administración los aprueba o rechaza; el
 * director de programa los consulta, sin aprobarlos.
 */
export default async function acuerdosView({ root }) {
  const esAdmin = session.role === 'ADMIN';
  const base = esAdmin ? '/admin/acuerdos' : '/director/acuerdos';
  let estado = esAdmin ? 'PENDIENTE' : '';

  mount(
    root,
    html`${pageHeader(
        'Acuerdos',
        esAdmin
          ? 'Acuerdos que suben las empresas. Sin uno aprobado y vigente, la empresa no puede asignar citas.'
          : 'Acuerdos de las empresas con la FET, existentes y pendientes de aprobación. Los aprueba la administración.',
      )}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar acuerdos">
        ${TABS.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${estado === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>`,
  );
  const list = root.querySelector('#list');

  const revision = (a) =>
    html`<form class="decide" data-revisar="${a.id}" novalidate>
      <div class="field"><label for="obs${a.id}">Observación</label><input class="input" id="obs${a.id}" name="observacion" placeholder="Obligatoria si lo rechazas" /></div>
      <button class="btn good sm" type="submit" value="1">${icon('check')}Aprobar</button>
      <button class="btn danger sm" type="submit" value="0">Rechazar</button>
    </form>`;

  async function load() {
    const items = await api.get(base, { estado });
    mount(
      list,
      items.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Empresa</th><th>Vigencia</th><th>Enviado</th><th>Estado</th></tr></thead>
            <tbody>${items.map(
              (a) => html`<tr>
                <td><b>${a.empresa.razon_social}</b><small>NIT ${a.empresa.nit}</small><small><button class="link" data-file="${a.url_archivo}">${icon('download')}Ver PDF</button></small></td>
                <td class="num">${formatDate(a.vigencia_inicio)} a ${formatDate(a.vigencia_fin)}${a.vigente ? html`<small>${badge('ok', 'Vigente')}</small>` : ''}</td>
                <td class="num">${formatDate(a.cargado_en)}</td>
                <td>
                  ${statusBadge(a.estado)}${a.observacion ? html`<small>${a.observacion}</small>` : ''}
                  ${esAdmin && a.estado.valor === 'PENDIENTE' ? revision(a) : ''}
                </td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('file-text', 'No hay acuerdos con este filtro'),
    );
  }

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) return api.open(file.dataset.file).catch((err) => toast(err.message));
    const f = e.target.closest('[data-f]');
    if (!f) return;
    estado = f.dataset.f;
    root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
    load();
  });

  list.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-revisar]');
    if (!form) return;
    e.preventDefault();
    const aprobar = e.submitter?.value === '1';
    withBusy(e.submitter, async () => {
      try {
        await api.patch(`/admin/acuerdos/${form.dataset.revisar}/revision`, { aprobar, observacion: form.observacion.value.trim() || null });
        toast(aprobar ? 'Acuerdo aprobado. La empresa ya puede asignar citas.' : 'Acuerdo rechazado. Le avisamos a la empresa.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
