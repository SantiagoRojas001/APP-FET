import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { emptyState, icon, pageHeader, toast, withBusy } from '../../lib/ui.js';

const num = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
const dias = (v) => (v === null ? '—' : `${num.format(v)} días`);
const pct = (v) => (v === null ? '—' : `${num.format(v)} %`);

/**
 * Cifras de las prácticas para acreditación y registro calificado, por
 * periodo académico, exportables a Excel.
 */
export default async function informesView({ root }) {
  const periodos = await api.get('/admin/periodos');
  const filters = { periodo_id: periodos.find((p) => !p.cerrado_en)?.id ?? '' };

  mount(
    root,
    html`${pageHeader(
        'Informes',
        'Prácticas por programa, empresas con más practicantes, tasas de selección y autorización y tiempo promedio de cada etapa.',
        html`<button class="btn" id="export">${icon('file-spreadsheet')}Exportar a Excel</button>`,
      )}
      <div class="toolbar">
        <div class="field">
          <label for="periodo">Periodo</label
          ><select class="input" id="periodo">
            <option value="">Todos los periodos</option>
            ${periodos.map((p) => html`<option value="${p.id}" ${p.id === filters.periodo_id ? 'selected' : ''}>${p.nombre}</option>`)}
          </select>
        </div>
      </div>
      <div id="body"></div>`,
  );

  const body = root.querySelector('#body');

  async function load() {
    const r = await api.get('/admin/informes', filters);
    const p = r.postulaciones;
    const t = r.tiempos_promedio_dias;

    mount(
      body,
      html`<section class="kpis">
          <div class="kpi"><b>${p.total}</b><span>Postulaciones</span></div>
          <div class="kpi"><b>${pct(p.tasa_seleccion_empresa)}</b><span>Seleccionadas por la empresa</span></div>
          <div class="kpi"><b>${pct(p.tasa_autorizacion_docente)}</b><span>Autorizadas por el docente</span></div>
          <div class="kpi">
            <b>${r.practicantes_por_programa.reduce((s, f) => s + f.total, 0)}</b><span>Prácticas</span>
          </div>
        </section>
        <section class="panel">
          <h2>Tiempo promedio de cada etapa</h2>
          <div class="lines">
            <div class="line">
              <div>
                <b>Decisión de la empresa</b
                ><span>Desde que el estudiante se postula hasta que la empresa selecciona o descarta</span>
              </div>
              <b>${dias(t.decision_empresa)}</b>
            </div>
            <div class="line">
              <div>
                <b>Autorización del docente</b
                ><span>Desde la selección de la empresa hasta que el docente autoriza</span>
              </div>
              <b>${dias(t.autorizacion_docente)}</b>
            </div>
            <div class="line">
              <div>
                <b>Completar las horas</b
                ><span>Desde el inicio de la práctica hasta cumplir las horas del programa</span>
              </div>
              <b>${dias(t.cumplir_horas)}</b>
            </div>
            <div class="line">
              <div><b>Duración de la práctica</b><span>Prácticas ya cerradas</span></div>
              <b>${dias(t.duracion_practica)}</b>
            </div>
          </div>
        </section>
        <section class="panel"><h2>Practicantes por programa</h2></section>
        <section class="panel flush">
          ${
            r.practicantes_por_programa.length
              ? html`<div class="tbl">
                  <table>
                    <thead>
                      <tr>
                        <th>Programa</th>
                        <th>En curso</th>
                        <th>Finalizadas</th>
                        <th>Terminadas antes</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${r.practicantes_por_programa.map(
                        (f) =>
                          html`<tr>
                            <td><b>${f.programa}</b></td>
                            <td class="num">${f.activas}</td>
                            <td class="num">${f.finalizadas}</td>
                            <td class="num">${f.terminadas}</td>
                            <td class="num"><b>${f.total}</b></td>
                          </tr>`,
                      )}
                    </tbody>
                  </table>
                </div>`
              : emptyState('chart-bar', 'No hay prácticas en este periodo')
          }
        </section>
        <section class="panel"><h2>Empresas con más practicantes</h2></section>
        <section class="panel flush">
          ${
            r.empresas_con_mas_practicantes.length
              ? html`<div class="tbl">
                  <table>
                    <thead>
                      <tr>
                        <th>Empresa</th>
                        <th>Practicantes</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${r.empresas_con_mas_practicantes.map(
                        (f) =>
                          html`<tr>
                            <td>${f.empresa}</td>
                            <td class="num">${f.practicantes}</td>
                          </tr>`,
                      )}
                    </tbody>
                  </table>
                </div>`
              : emptyState('building', 'Sin datos')
          }
        </section>
        <section class="panel">
          <h2>Postulaciones</h2>
          <dl class="kv">
            <div>
              <dt>Seleccionadas por la empresa</dt>
              <dd>${p.seleccionadas_por_empresa}</dd>
            </div>
            <div>
              <dt>Descartadas por la empresa</dt>
              <dd>${p.descartadas_por_empresa}</dd>
            </div>
            <div>
              <dt>Autorizadas por el docente</dt>
              <dd>${p.autorizadas_por_docente}</dd>
            </div>
            <div>
              <dt>No autorizadas por el docente</dt>
              <dd>${p.no_autorizadas_por_docente}</dd>
            </div>
          </dl>
        </section>`,
    );
  }

  root.querySelector('#periodo').addEventListener('change', (e) => {
    filters.periodo_id = e.target.value ? Number(e.target.value) : '';
    load();
  });

  root.querySelector('#export').addEventListener('click', (e) =>
    withBusy(e.currentTarget, async () => {
      const nombre = periodos.find((p) => p.id === filters.periodo_id)?.nombre ?? 'todos';
      const query = filters.periodo_id ? `?periodo_id=${filters.periodo_id}` : '';
      await api
        .download(`/api/admin/informes/exportar${query}`, `informe-practicas-${nombre}.xlsx`)
        .catch((err) => toast(err.message));
    }),
  );

  await load();
}
