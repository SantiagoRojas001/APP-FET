import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/**
 * Parámetros generales, iguales para todos los programas: promedio mínimo
 * para quedar habilitado y, por ciclo, los semestres en los que se registran
 * los estudiantes y las horas que exige la práctica.
 */
export default async function reglasView({ root, refresh }) {
  const param = await api.get('/admin/parametros');

  mount(
    root,
    html`${pageHeader('Parámetros')}
      <form id="paramForm" novalidate>
        <section class="panel">
          <h2>Promedio mínimo</h2>
          <div class="form-grid">
            <div class="field">
              <label for="promedio_minimo">Promedio para quedar habilitado</label>
              <input class="input" id="promedio_minimo" name="promedio_minimo" inputmode="decimal" value="${String(param.promedio_minimo).replace('.', ',')}" />
            </div>
          </div>
        </section>
        <section class="panel">
          <h2>Por ciclo</h2>
          <div class="lines" id="semestres_habilitados">
            ${param.ciclos.map(
              (c) => html`<div class="line" role="group" aria-label="Ciclo ${c.etiqueta.toLowerCase()}">
                <div><b>${c.etiqueta}</b><span>${c.semestres[0]}.º a ${c.semestres.at(-1)}.º semestre</span></div>
                <div class="actions">
                  <div class="tags" role="group" aria-label="Semestres habilitados del ciclo ${c.etiqueta.toLowerCase()}">
                    ${c.semestres.map(
                      (n) => html`<label class="check"><input type="checkbox" name="semestres" value="${n}" ${param.semestres_habilitados.includes(n) ? 'checked' : ''} />${n}.º</label>`,
                    )}
                  </div>
                  <label class="field">
                    <span class="fine">Horas de práctica</span>
                    <input class="input narrow" type="number" name="horas.${c.valor}" min="40" max="2000" step="1" value="${c.horas}" aria-label="Horas de práctica del ciclo ${c.etiqueta.toLowerCase()}" />
                  </label>
                </div>
              </div>`,
            )}
          </div>
        </section>
        <div class="actions"><button class="btn" type="submit">Guardar parámetros</button></div>
        <div id="paramError"></div>
      </form>`,
  );

  const form = root.querySelector('#paramForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put('/admin/parametros', {
          promedio_minimo: form.promedio_minimo.value.trim(),
          semestres_habilitados: [...form.querySelectorAll('[name=semestres]:checked')].map((c) => Number(c.value)),
          horas: Object.fromEntries(param.ciclos.map((c) => [c.valor, Number(form.elements.namedItem(`horas.${c.valor}`).value)])),
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
