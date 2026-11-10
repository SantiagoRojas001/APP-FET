import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, formatDateTime, initials } from '../../lib/format.js';
import { padDeFirma } from '../../lib/firma.js';
import { confirmInline, emptyState, errorBox, icon, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';
import { hydrateFoto } from '../hoja-vida-detalle.js';

/**
 * Cartas de presentación: estudiantes del programa que una empresa aceptó
 * después de su cita. El director las firma con su nombre, documento y
 * firma (se registran una vez). Al enviar la carta a la empresa empieza la
 * práctica y la hoja de vida del estudiante se deshabilita.
 */
export default async function cartasView({ root, refresh, navigate }) {
  const [citas, firmante] = await Promise.all([api.get('/director/cartas'), api.get('/director/firma')]);
  const pendientes = citas.filter((c) => c.estado.valor === 'ACEPTADO');
  const enviadas = citas.filter((c) => c.estado.valor === 'PRESENTADO');

  const row = (c) => html`<article class="rowi" style="grid-template-columns:44px minmax(0,1fr)">
    <div class="av lg">${initials(c.estudiante.nombre_completo)}</div>
    <div class="rowi-main">
      <div class="panel-h">
        <div><h3>${c.estudiante.nombre_completo}</h3><div class="sub">${c.estudiante.programa.nombre}, ${c.estudiante.semestre_actual}.º semestre</div></div>
        ${statusBadge(c.estado)}
      </div>
      <dl class="facts">
        <div><dt>Empresa</dt><dd>${c.empresa.razon_social}</dd></div>
        ${c.vacante ? html`<div><dt>Vacante</dt><dd>${c.vacante.titulo}</dd></div>` : ''}
        <div><dt>Cita</dt><dd>${formatDateTime(c.fecha_hora)}</dd></div>
        <div><dt>Inicio</dt><dd>${formatDate(c.fecha_inicio_practica)}</dd></div>
        <div><dt>Tutor (aprobó la hoja de vida)</dt><dd>${c.tutor ?? 'No registra'}</dd></div>
      </dl>
      <div class="actions">
        <button class="btn sec sm" data-file="${c.url_carta}">${icon('download')}Ver carta</button>
        <a class="btn sec sm" href="#/estudiantes/${c.estudiante.id}">${icon('file-cv')}Hoja de vida</a>
        ${c.estado.valor === 'ACEPTADO'
          ? html`<button class="btn sm" data-enviar="${c.id}" ${firmante.completa ? '' : html`disabled title="Registra tu firma primero"`}>${icon('send')}Enviar carta a la empresa</button>`
          : c.vinculacion_id
            ? html`<button class="btn sec sm" data-go="/vinculacion/${c.vinculacion_id}">Ver práctica</button>`
            : ''}
      </div>
    </div>
  </article>`;

  mount(
    root,
    html`${pageHeader('Cartas de presentación')}
      <section class="panel">
        <div class="panel-h"><h2>Tu firma en las cartas</h2>${firmante.completa ? html`<button class="btn sec sm" id="editarFirma">${icon('pencil')}Cambiar</button>` : ''}</div>
        ${firmante.completa
          ? html`<div class="firma-actual">
              <img class="firma-img" data-foto="${firmante.url_firma}" alt="Tu firma" />
              <div><b>${firmante.nombre}</b><div class="fine">C.C. ${firmante.documento}</div></div>
            </div>`
          : html`<p class="note-box">${icon('alert-circle')} Para enviar cartas registra tu nombre, tu documento de identidad y tu firma.</p>`}
        <form id="firmaForm" class="form-grid" ${firmante.completa ? 'hidden' : ''} novalidate>
          <div class="field"><label for="nombre">Nombre completo</label><input class="input" id="nombre" name="nombre" value="${firmante.nombre ?? ''}" /></div>
          <div class="field"><label for="documento">Documento de identidad</label><input class="input" id="documento" name="documento" inputmode="numeric" value="${firmante.documento ?? ''}" /></div>
          <div class="field wide">
            <label for="firmaPad">Firma${firmante.url_firma ? ' (déjala en blanco para conservar la actual)' : ''}</label>
            <canvas class="firma-pad" id="firmaPad" aria-label="Recuadro para dibujar tu firma"></canvas>
            <input type="hidden" name="firma" />
          </div>
          <div class="actions"><button class="btn" type="submit">Guardar</button><button class="btn sec" type="button" id="firmaBorrar">Borrar firma</button></div>
          <div id="firmaError" class="wide"></div>
        </form>
      </section>
      <section class="panel"><h2>Por enviar</h2></section>
      <section class="panel flush">${pendientes.length ? pendientes.map(row) : emptyState('send', 'No hay cartas por enviar')}</section>
      ${enviadas.length
        ? html`<section class="panel"><h2>Enviadas</h2></section>
            <section class="panel flush">${enviadas.map(row)}</section>`
        : ''}`,
  );

  root.addEventListener('click', (e) => {
    const file = e.target.closest('[data-file]');
    if (file) return api.open(file.dataset.file).catch((err) => toast(err.message));
    const go = e.target.closest('[data-go]');
    if (go) return navigate(go.dataset.go);
    const enviar = e.target.closest('[data-enviar]');
    if (enviar) {
      confirmInline(
        enviar,
        'enviar la carta',
        (si) =>
          withBusy(si, async () => {
            try {
              await api.post(`/director/cartas/${enviar.dataset.enviar}`);
              toast('Carta enviada a la empresa. La práctica quedó registrada.');
              refresh();
            } catch (error) {
              toast(error.message);
            }
          }),
        'Con la carta empieza la práctica y se cierran los demás procesos del estudiante.',
      );
    }
  });

  hydrateFoto(root);

  // Firma del director: el recuadro se prepara cuando el formulario está visible.
  const firmaForm = root.querySelector('#firmaForm');
  let pad = firmaForm.hidden ? null : padDeFirma(root.querySelector('#firmaPad'));
  root.querySelector('#editarFirma')?.addEventListener('click', () => {
    firmaForm.hidden = false;
    pad ??= padDeFirma(root.querySelector('#firmaPad'));
  });
  root.querySelector('#firmaBorrar').addEventListener('click', () => pad?.limpiar());
  firmaForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (pad.vacia() && !firmante.url_firma) return showFieldErrors(firmaForm, { firma: ['Dibuja tu firma.'] });
    withBusy(firmaForm.querySelector('[type=submit]'), async () => {
      const data = new FormData();
      data.append('nombre', firmaForm.nombre.value.trim());
      data.append('documento', firmaForm.documento.value.trim());
      if (!pad.vacia()) data.append('firma', await pad.png(), 'firma.png');
      try {
        await api.post('/director/firma', data);
        toast('Firma guardada. Ya puedes enviar cartas.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#firmaError'), errorBox(error.message));
        showFieldErrors(firmaForm, error.errors);
      }
    });
  });
}
