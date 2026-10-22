import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { pageHeader } from '../../lib/ui.js';

export default async function resumenView({ root, navigate }) {
  const r = await api.get('/admin/resumen');

  mount(
    root,
    html`${pageHeader('Resumen', 'Estado general del proceso de prácticas.')}
      <section class="kpis">
        <div class="kpi"><b>${r.estudiantes}</b><span>Estudiantes registrados</span></div>
        <div class="kpi"><b>${r.estudiantes_habilitados}</b><span>Habilitados para práctica</span></div>
        <div class="kpi"><b>${r.vinculaciones_activas}</b><span>Prácticas activas</span></div>
        <div class="kpi"><b>${r.docentes}</b><span>Docentes</span></div>
      </section>
      <section class="callout">
        <span></span>
        <p><b>${r.empresas_validadas} empresas validadas</b> y ${r.empresas_pendientes} pendientes de validación.</p>
        ${r.empresas_pendientes ? html`<button class="btn sm" id="toEmp">Revisar empresas</button>` : ''}
      </section>`,
  );

  root.querySelector('#toEmp')?.addEventListener('click', () => navigate('/empresas'));
}
