import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import { badge, errorBox, emptyState, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

const ROLES = [
  ['', 'Todos'],
  ['ESTUDIANTE', 'Estudiantes'],
  ['EMPRESA', 'Empresas'],
  ['DOCENTE', 'Docentes'],
  ['ADMIN', 'Administradores'],
];

export default async function usuariosView({ root }) {
  const filters = { rol: '', page: 1 };

  mount(
    root,
    html`${pageHeader('Usuarios', 'Cuentas con acceso a APP FET. Las cuentas de docentes y administradores se crean aquí.')}
      <section class="panel">
        <h2>Crear cuenta de docente o administrador</h2>
        <form id="userForm" class="form-grid" novalidate>
          <div class="field"><label for="correo">Correo institucional</label><input class="input" type="email" id="correo" name="correo" /></div>
          <div class="field"><label for="password">Contraseña inicial</label><input class="input" type="password" id="password" name="password" autocomplete="new-password" /></div>
          <div class="field"><label for="rol">Rol</label><select class="input" id="rol" name="rol"><option value="DOCENTE">Docente</option><option value="ADMIN">Administrador</option></select></div>
          <div><button class="btn" type="submit">Crear cuenta</button></div>
        </form>
        <div id="userError"></div>
      </section>
      <div class="toolbar"><div class="seg" role="group" aria-label="Filtrar por rol">
        ${ROLES.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${filters.rol === v}">${l}</button>`)}
      </div></div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  async function load() {
    const res = await api.get('/admin/usuarios', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creado</th><th>Acceso</th></tr></thead>
            <tbody>${res.data.map(
              (u) => html`<tr>
                <td><b>${u.nombre}</b>${u.estudiante ? html`<small>${u.estudiante.programa?.nombre}, ${u.estudiante.semestre_actual}.º</small>` : ''}</td>
                <td class="mono">${u.correo}</td>
                <td>${u.rol.etiqueta}</td>
                <td class="num">${formatDate(u.creado_en)}</td>
                <td>${u.id === session.user.id
                  ? badge('info', 'Tu cuenta')
                  : html`<button class="btn sm ${u.activo ? 'sec' : 'good'}" data-toggle="${u.id}" data-activo="${u.activo ? '0' : '1'}">${u.activo ? 'Desactivar' : 'Activar'}</button>`}</td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('users', 'No hay usuarios con este filtro'),
    );
    const { current_page: page, last_page: last } = res.meta;
    mount(
      root.querySelector('#pager'),
      last > 1
        ? html`<button class="btn sec sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Anterior</button>
            <span class="fine">Página ${page} de ${last}</span>
            <button class="btn sec sm" data-page="${page + 1}" ${page >= last ? 'disabled' : ''}>Siguiente</button>`
        : '',
    );
  }

  root.addEventListener('click', (e) => {
    const f = e.target.closest('[data-f]');
    if (f) {
      filters.rol = f.dataset.f;
      filters.page = 1;
      root.querySelectorAll('[data-f]').forEach((b) => b.setAttribute('aria-pressed', String(b === f)));
      return load();
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      return load();
    }
    const toggle = e.target.closest('[data-toggle]');
    if (toggle) {
      withBusy(toggle, async () => {
        const activo = toggle.dataset.activo === '1';
        await api.patch(`/admin/usuarios/${toggle.dataset.toggle}`, { activo });
        toast(activo ? 'Cuenta activada.' : 'Cuenta desactivada y sesiones cerradas.');
        load();
      });
    }
  });

  const form = root.querySelector('#userForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/admin/usuarios', { correo: form.correo.value.trim(), password: form.password.value, rol: form.rol.value });
        toast('Cuenta creada.');
        form.reset();
        mount(root.querySelector('#userError'), '');
        load();
      } catch (error) {
        mount(root.querySelector('#userError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  await load();
}
