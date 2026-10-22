import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { emptyState, errorBox, icon, modalityTag, pageHeader, showFieldErrors, statusBadge, toast, withBusy } from '../../lib/ui.js';

const count = (v, ...estados) => estados.reduce((n, e) => n + (v.postulaciones_por_estado?.[e] ?? 0), 0);

export default async function vacantesEmpresaView({ root, refresh }) {
  const [items, cat] = await Promise.all([api.get('/empresa/vacantes'), api.get('/catalogos')]);

  mount(
    root,
    html`${pageHeader('Mis vacantes', 'Publica ofertas de pasantía o práctica profesional.')}
      <section class="panel">
        <h2>Publicar vacante</h2>
        <form id="vacForm" class="form-grid" novalidate>
          <div class="field" style="grid-column:span 2"><label for="titulo">Cargo</label><input class="input" id="titulo" name="titulo" placeholder="Ej.: Pasante de desarrollo web" /></div>
          <div class="field"><label for="modalidad">Modalidad</label><select class="input" id="modalidad" name="modalidad">
            ${cat.modalidades.map((m) => html`<option value="${m.valor}">${m.etiqueta}</option>`)}
          </select></div>
          <div class="field wide"><label for="descripcion">Descripción</label><textarea class="input" id="descripcion" name="descripcion" placeholder="Funciones, ciudad, jornada, duración y apoyo económico."></textarea></div>
          <div><button class="btn" type="submit">${icon('briefcase')}Publicar</button></div>
        </form>
        <div id="vacError"></div>
      </section>
      <section class="panel flush">
        ${items.length
          ? html`<div class="tbl"><table>
              <thead><tr><th>Vacante</th><th>Modalidad</th><th>Postulaciones</th><th>Estado</th><th><span class="fine">Acción</span></th></tr></thead>
              <tbody>${items.map(
                (v) => html`<tr>
                  <td><b>${v.titulo}</b><small>Publicada el ${formatDate(v.publicada_en)}</small></td>
                  <td>${modalityTag(v.modalidad)}</td>
                  <td class="num">${count(v, 'APROBADA', 'ACEPTADA', 'DESCARTADA')} aprobadas por docente<small>${count(v, 'ENVIADA')} en revisión docente</small></td>
                  <td>${statusBadge(v.estado)}</td>
                  <td><button class="btn sec sm" data-toggle="${v.id}" data-estado="${v.estado.valor === 'ABIERTA' ? 'CERRADA' : 'ABIERTA'}">${v.estado.valor === 'ABIERTA' ? 'Cerrar' : 'Reabrir'}</button></td>
                </tr>`,
              )}</tbody></table></div>`
          : emptyState('briefcase', 'Aún no has publicado vacantes')}
      </section>`,
  );

  const form = root.querySelector('#vacForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/empresa/vacantes', {
          titulo: form.titulo.value.trim(),
          modalidad: form.modalidad.value,
          descripcion: form.descripcion.value.trim() || null,
        });
        toast('Vacante publicada.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#vacError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle]');
    if (!btn) return;
    withBusy(btn, async () => {
      await api.patch(`/empresa/vacantes/${btn.dataset.toggle}`, { estado: btn.dataset.estado });
      toast(btn.dataset.estado === 'CERRADA' ? 'Vacante cerrada.' : 'Vacante reabierta.');
      refresh();
    });
  });
}
