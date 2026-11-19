import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/**
 * Parámetros generales, iguales para todos los programas: promedio mínimo
 * para quedar habilitado, horas mínimas de la práctica y, por ciclo, los
 * semestres en los que se registran los estudiantes.
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
          <h2>Horas de práctica</h2>
          <div class="form-grid">
            <div class="field">
              <label for="horas">Horas mínimas, iguales para todos</label>
              <input class="input" id="horas" name="horas" type="number" min="40" max="2000" step="1" value="${param.horas}" />
            </div>
          </div>
        </section>
        <section class="panel">
          <h2>Semestres habilitados por ciclo</h2>
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
                </div>
              </div>`,
            )}
          </div>
        </section>
        <div class="actions"><button class="btn" type="submit">Guardar parámetros</button></div>
        <div id="paramError"></div>
      </form>
      <section class="panel">
        <h2>Datos de la carta por programa</h2>
        <div class="lines">
          ${param.programas.map(
            (p) => html`<form class="line" data-programa="${p.id}" novalidate>
              <div><b>${p.nombre}</b></div>
              <div class="actions">
                <input class="input narrow" name="snies" value="${p.snies ?? ''}" placeholder="SNIES" aria-label="SNIES de ${p.nombre}" />
                <input class="input" name="resolucion" value="${p.resolucion ?? ''}" placeholder="Resolución" aria-label="Resolución de ${p.nombre}" />
                <button class="btn sec sm" type="submit">Guardar</button>
              </div>
            </form>`,
          )}
        </div>
      </section>`,
  );

  root.querySelectorAll('[data-programa]').forEach((f) =>
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      withBusy(e.submitter, async () => {
        try {
          await api.put(`/admin/programas/${f.dataset.programa}`, { snies: f.snies.value.trim() || null, resolucion: f.resolucion.value.trim() || null });
          toast('Programa actualizado.');
        } catch (error) {
          toast(error.message);
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
          horas: Number(form.horas.value),
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
