import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, pageHeader } from '../../lib/ui.js';

/**
 * Panel de administración: primero lo que está por atender, luego las cifras
 * del periodo actual y el estado de cada programa.
 */
export default async function resumenView({ root, navigate }) {
  const r = await api.get('/admin/resumen');
  const p = r.pendientes;

  const pendientes = [
    [p.empresas_por_validar, 'Empresas por validar', '/empresas'],
    [p.convenios_por_aprobar, 'Convenios por aprobar', '/convenios'],
    [p.reportes_abiertos, 'Reportes sin resolver', '/reportes'],
  ];

  mount(
    root,
    html`${pageHeader('Resumen', `Periodo ${r.periodo}`)}
      <section class="panel">
        <h2>Por atender</h2>
        <div class="lines">
          ${pendientes.map(
            ([n, texto, ruta]) => html`<div class="line">
              <div><b class="num">${n}</b><span>${texto}</span></div>
              ${n === 0 ? badge('ok', 'Al día') : html`<button class="btn sm" data-go="${ruta}">Revisar</button>`}
            </div>`,
          )}
        </div>
      </section>
      <section class="kpis">
        <div class="kpi"><b>${r.cifras.estudiantes_habilitados}</b><span>Estudiantes habilitados</span></div>
        <div class="kpi"><b>${r.cifras.practicas_activas}</b><span>Prácticas activas</span></div>
        <div class="kpi"><b>${r.cifras.practicas_del_periodo}</b><span>Prácticas iniciadas en ${r.periodo}</span></div>
        <div class="kpi"><b>${r.cifras.empresas_con_convenio}</b><span>Empresas con convenio vigente</span></div>
      </section>
      <section class="panel"><h2>Por programa</h2></section>
      <section class="panel flush">
        <div class="tbl"><table>
          <thead><tr><th>Programa</th><th>Habilitados</th><th>Hojas de vida aprobadas</th><th>En práctica</th></tr></thead>
          <tbody>${r.programas.map(
            (f) => html`<tr>
              <td><b>${f.nombre}</b></td>
              <td class="num">${f.habilitados}</td>
              <td class="num">${f.hojas_aprobadas}</td>
              <td class="num">${f.en_practica}</td>
            </tr>`,
          )}</tbody>
        </table></div>
      </section>`,
  );

  root.addEventListener('click', (e) => {
    const go = e.target.closest('[data-go]');
    if (go) navigate(go.dataset.go);
  });
}
