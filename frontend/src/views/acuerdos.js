import { api } from '../api/client.js';
import { html, mount } from '../lib/html.js';
import { archivoAcciones, wireArchivos } from '../lib/archivos.js';
import { formatDate } from '../lib/format.js';
import { badge, confirmInline, emptyState, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../lib/ui.js';
import { session } from '../session.js';

const TABS = [
  ['PENDIENTE', 'Pendientes'],
  ['APROBADO', 'Aprobados'],
  ['RECHAZADO', 'Rechazados'],
  ['', 'Todos'],
];

/**
 * Convenios de las empresas. La administración los aprueba o rechaza y
 * atiende las solicitudes de convenio de las empresas que aún no lo tienen
 * (les envía el convenio para que se registren, o las rechaza); el director
 * de programa los consulta, sin aprobarlos.
 */
export default async function acuerdosView({ root }) {
  const esAdmin = session.role === 'ADMIN';
  const base = esAdmin ? '/admin/acuerdos' : '/director/acuerdos';
  let estado = esAdmin ? 'PENDIENTE' : '';

  mount(
    root,
    html`${pageHeader('Convenios')}
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar convenios">
        ${(esAdmin ? [...TABS, ['SOLICITUDES', 'Solicitudes de empresas']] : TABS).map(([v, l]) => html`<button data-f="${v}" aria-pressed="${estado === v}">${l}</button>`)}
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

  const solicitud = (s) => html`<article class="rowi" style="grid-template-columns:minmax(0,1fr)">
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${s.razon_social}</h3><div class="sub">NIT ${s.nit} · ${s.modalidad_interes.etiqueta} · solicitada el ${formatDate(s.creado_en)}</div></div>
        ${statusBadge(s.estado)}
      </div>
      <dl class="facts">
        <div><dt>Contacto</dt><dd>${s.contacto_nombre} · ${s.contacto_telefono}</dd></div>
        <div><dt>Correo</dt><dd>${s.correo}</dd></div>
        <div><dt>Representante legal</dt><dd>${s.representante_nombre} · ${s.representante_documento}</dd></div>
        <div><dt>Firma</dt><dd>${archivoAcciones(s.url_firma, `firma-${s.nit}.png`)}</dd></div>
        ${s.url_convenio ? html`<div><dt>Convenio enviado</dt><dd>${archivoAcciones(s.url_convenio, `convenio-${s.nit}.pdf`)}</dd></div>` : ''}
      </dl>
      ${s.observacion ? html`<p class="note-box">${s.observacion}</p>` : ''}
      ${s.estado.valor === 'PENDIENTE'
        ? html`<form class="form-grid" data-enviar="${s.id}" novalidate>
              <div class="field wide"><label for="conv${s.id}">Convenio para la empresa (PDF)</label><input class="input" type="file" id="conv${s.id}" name="convenio" accept="application/pdf" /></div>
              <div><button class="btn sm" type="submit">${icon('send')}Enviar convenio</button></div>
            </form>
            <form class="decide" data-rechazar="${s.id}" novalidate>
              <div class="field"><label for="mot${s.id}">Motivo del rechazo</label><input class="input" id="mot${s.id}" name="motivo" /></div>
              <button class="btn danger sm" type="submit">Rechazar solicitud</button>
            </form>`
        : ''}
    </div>
  </article>`;

  async function load() {
    if (estado === 'SOLICITUDES') {
      const solicitudes = await api.get('/admin/solicitudes-convenio');
      return mount(list, solicitudes.length ? solicitudes.map(solicitud) : emptyState('file-text', 'No hay solicitudes de convenio'));
    }
    const items = await api.get(base, { estado });
    mount(
      list,
      items.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Empresa</th><th>Vigencia</th><th>Enviado</th><th>Estado</th></tr></thead>
            <tbody>${items.map(
              (a) => html`<tr>
                <td><b>${a.empresa.razon_social}</b><small>NIT ${a.empresa.nit}</small><small>${archivoAcciones(a.url_archivo, `convenio-${a.empresa.nit}.pdf`)}</small></td>
                <td class="num">${formatDate(a.vigencia_inicio)} a ${formatDate(a.vigencia_fin)}${a.vigente ? html`<small>${badge('ok', 'Vigente')}</small>` : ''}</td>
                <td class="num">${formatDate(a.cargado_en)}</td>
                <td>
                  ${statusBadge(a.estado)}${a.observacion ? html`<small>${a.observacion}</small>` : ''}
                  ${esAdmin && a.estado.valor === 'PENDIENTE' ? revision(a) : ''}
                </td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('file-text', 'No hay convenios con este filtro'),
    );
  }

  wireArchivos(root);
  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (!f) return;
    estado = f.dataset.f;
    root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
    load();
  });

  list.addEventListener('submit', (e) => {
    const enviar = e.target.closest('[data-enviar]');
    if (enviar) {
      e.preventDefault();
      if (!enviar.convenio.files.length) return showFieldErrors(enviar, { convenio: ['Adjunta el convenio en PDF.'] });
      const data = new FormData();
      data.append('convenio', enviar.convenio.files[0]);
      return withBusy(e.submitter, async () => {
        try {
          await api.post(`/admin/solicitudes-convenio/${enviar.dataset.enviar}/convenio`, data);
          toast('Convenio enviado. La empresa lo recibe por correo con el enlace para registrarse.');
          load();
        } catch (error) {
          showFieldErrors(enviar, error.errors) || toast(error.message);
        }
      });
    }
    const rechazar = e.target.closest('[data-rechazar]');
    if (rechazar) {
      e.preventDefault();
      return confirmInline(e.submitter, 'rechazar', (si) =>
        withBusy(si, async () => {
          try {
            await api.post(`/admin/solicitudes-convenio/${rechazar.dataset.rechazar}/rechazo`, { motivo: rechazar.motivo.value.trim() });
            toast('Solicitud rechazada. Le enviamos el motivo a la empresa.');
            load();
          } catch (error) {
            showFieldErrors(rechazar, error.errors) || toast(error.message);
          }
        }),
      );
    }
    const form = e.target.closest('[data-revisar]');
    if (!form) return;
    e.preventDefault();
    const aprobar = e.submitter?.value === '1';
    withBusy(e.submitter, async () => {
      try {
        await api.patch(`/admin/acuerdos/${form.dataset.revisar}/revision`, { aprobar, observacion: form.observacion.value.trim() || null });
        toast(aprobar ? 'Convenio aprobado. La empresa ya puede asignar citas.' : 'Convenio rechazado. Le avisamos a la empresa.');
        load();
      } catch (error) {
        showFieldErrors(form, error.errors) || toast(error.message);
      }
    });
  });

  await load();
}
