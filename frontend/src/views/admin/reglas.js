import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/**
 * Parámetros del aplicativo: promedio mínimo para quedar habilitado,
 * semestres en los que se registran los estudiantes y horas por programa.
 */
export default async function reglasView({ root, refresh }) {
  const [param, cat] = await Promise.all([api.get('/admin/parametros'), api.get('/catalogos')]);
  const semestres = Array.from({ length: 10 }, (_, i) => i + 1);

  mount(
    root,
    html`${pageHeader('Parámetros', 'Requisitos para que un estudiante quede habilitado y horas que exige cada programa.')}
      <section class="panel">
        <h2>Habilitación para pasantía o práctica</h2>
        <p class="muted">
          El docente registra a los estudiantes de los semestres marcados que eligieron pasantía o práctica. Al
          registrar el promedio, quienes alcanzan el mínimo reciben su usuario y contraseña.
        </p>
        <form id="paramForm" class="form-grid" novalidate>
          <div class="field">
            <label for="promedio_minimo">Promedio mínimo</label>
            <input class="input" id="promedio_minimo" name="promedio_minimo" inputmode="decimal" value="${String(param.promedio_minimo).replace('.', ',')}" />
          </div>
          <fieldset class="field wide" style="border:0;padding:0;margin:0">
            <legend class="sub-h">Semestres habilitados</legend>
            <div class="tags" id="semestres_habilitados">
              ${semestres.map(
                (n) => html`<label class="check"><input type="checkbox" name="semestres" value="${n}" ${param.semestres_habilitados.includes(n) ? 'checked' : ''} />${n}.º</label>`,
              )}
            </div>
          </fieldset>
          <div><button class="btn" type="submit">Guardar parámetros</button></div>
        </form>
        <div id="paramError"></div>
      </section>
      <section class="panel">
        <h2>Horas de práctica por programa</h2>
        <p class="muted">La práctica se da por cumplida cuando las horas semanales aprobadas por la empresa alcanzan este total.</p>
        <div class="lines">${cat.programas.map(
          (p) => html`<form class="line" data-horas="${p.id}" novalidate>
            <div><b>${p.nombre}</b><span>${p.total_semestres} semestres</span></div>
            <div class="actions">
              <input class="input narrow" type="number" name="horas_requeridas" min="40" max="2000" step="1" value="${p.horas_requeridas}" aria-label="Horas requeridas de ${p.nombre}" />
              <button class="btn sec sm" type="submit">Guardar</button>
            </div>
          </form>`,
        )}</div>
      </section>`,
  );

  root.querySelectorAll('[data-horas]').forEach((f) =>
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      withBusy(f.querySelector('[type=submit]'), async () => {
        try {
          await api.patch(`/admin/programas/${f.dataset.horas}`, { horas_requeridas: Number(f.horas_requeridas.value) });
          toast('Horas actualizadas.');
        } catch (error) {
          showFieldErrors(f, error.errors) || toast(error.message);
        }
      });
    }),
  );

  const form = root.querySelector('#paramForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put('/admin/parametros', {
          promedio_minimo: form.promedio_minimo.value.trim(),
          semestres_habilitados: [...form.querySelectorAll('[name=semestres]:checked')].map((c) => Number(c.value)),
        });
        toast('Parámetros guardados.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#paramError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
