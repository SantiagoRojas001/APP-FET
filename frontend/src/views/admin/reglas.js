import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate, todayIso } from '../../lib/format.js';
import { badge, errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

export default async function reglasView({ root, refresh }) {
  const [reglas, cat] = await Promise.all([api.get('/admin/reglas'), api.get('/catalogos')]);
  const today = todayIso();
  const vigente = (r) => r.vigente_desde <= today && (!r.vigente_hasta || r.vigente_hasta >= today);

  mount(
    root,
    html`${pageHeader('Reglas de práctica', 'Semestre desde el cual cada programa autoriza la pasantía o práctica. Una regla nueva cierra la anterior, que queda en el historial.')}
      <section class="panel">
        <h2>Nueva regla</h2>
        <form id="reglaForm" class="form-grid" novalidate>
          <div class="field"><label for="programa_id">Programa</label><select class="input" id="programa_id" name="programa_id">
            ${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre} (${p.total_semestres} sem.)</option>`)}
          </select></div>
          <div class="field"><label for="semestre_minimo">Semestre mínimo</label><input class="input" type="number" min="1" id="semestre_minimo" name="semestre_minimo" /></div>
          <div class="field"><label for="vigente_desde">Vigente desde</label><input class="input" type="date" id="vigente_desde" name="vigente_desde" value="${today}" /></div>
          <div><button class="btn" type="submit">Guardar regla</button></div>
        </form>
        <div id="reglaError"></div>
      </section>
      <section class="panel flush"><div class="tbl"><table>
        <thead><tr><th>Programa</th><th>Semestre mínimo</th><th>Vigencia</th><th>Estado</th></tr></thead>
        <tbody>${reglas.map(
          (r) => html`<tr>
            <td><b>${r.programa.nombre}</b></td>
            <td class="num">${r.semestre_minimo}.º</td>
            <td class="num">${formatDate(r.vigente_desde)} a ${r.vigente_hasta ? formatDate(r.vigente_hasta) : 'hoy'}</td>
            <td>${vigente(r) ? badge('ok', 'Vigente') : r.vigente_desde > today ? badge('info', 'Programada') : badge('neutral', 'Histórica')}</td>
          </tr>`,
        )}</tbody></table></div></section>`,
  );

  const form = root.querySelector('#reglaForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/admin/reglas', {
          programa_id: Number(form.programa_id.value),
          semestre_minimo: Number(form.semestre_minimo.value) || null,
          vigente_desde: form.vigente_desde.value,
        });
        toast('Regla guardada.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#reglaError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
