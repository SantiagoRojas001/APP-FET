import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { empresaData, empresaFields } from './empresa-form.js';

/** La administración corrige los datos de una empresa. */
export default async function empresaAdminView({ root, params, navigate }) {
  const empresas = await api.get('/admin/empresas');
  const c = empresas.find((e) => String(e.id) === params[0]);
  if (!c) return navigate('/empresas');

  mount(
    root,
    html`${pageHeader(c.razon_social, c.correo, html`<button class="btn sec" id="back">Volver</button>`)}
      <section class="panel">
        <h2>Datos de la empresa</h2>
        <form id="form" class="form-grid" novalidate>
          ${empresaFields(c)}
          <div><button class="btn" type="submit">Guardar cambios</button></div>
        </form>
        <div id="error"></div>
      </section>`,
  );

  root.querySelector('#back').addEventListener('click', () => navigate('/empresas'));

  const form = root.querySelector('#form');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.patch(`/admin/empresas/${c.id}`, empresaData(form));
        toast('Datos actualizados.');
        navigate('/empresas');
      } catch (error) {
        mount(root.querySelector('#error'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
