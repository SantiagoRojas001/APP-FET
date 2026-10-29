import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { formatDate } from '../../lib/format.js';
import {
  badge,
  errorBox,
  emptyState,
  icon,
  pageHeader,
  showFieldErrors,
  toast,
  withBusy,
} from '../../lib/ui.js';
import { session } from '../../session.js';

const ROLES = [
  ['', 'Todos'],
  ['ESTUDIANTE', 'Estudiantes'],
  ['EMPRESA', 'Empresas'],
  ['DOCENTE', 'Docentes'],
  ['ADMIN', 'Administradores'],
];

/**
 * Cuentas de APP FET. Solo la administración las crea: cada persona recibe
 * una invitación por correo para activar la suya.
 */
export default async function usuariosView({ root, navigate }) {
  const filters = { rol: '', q: '', page: 1 };
  const cat = await api.get('/catalogos');
  const dominio = `@${cat.dominio_correo}`;

  mount(
    root,
    html`${pageHeader('Usuarios', `Estudiantes y docentes usan su correo ${dominio}. Cada cuenta nueva recibe una invitación para definir su contraseña. Las empresas se invitan desde Empresas.`)}
      <section class="panel">
        <h2>Crear estudiante</h2>
        <form id="estForm" class="form-grid" novalidate>
          <div class="field">
            <label for="e-correo">Correo institucional</label
            ><input class="input" type="email" id="e-correo" name="correo" placeholder="nombre${dominio}" />
          </div>
          <div class="field">
            <label for="e-nombres">Nombres</label><input class="input" id="e-nombres" name="nombres" />
          </div>
          <div class="field">
            <label for="e-apellidos">Apellidos</label
            ><input class="input" id="e-apellidos" name="apellidos" />
          </div>
          <div class="field">
            <label for="e-programa">Programa</label
            ><select class="input" id="e-programa" name="programa_id">
              ${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre}</option>`)}
            </select>
          </div>
          <div class="field">
            <label for="e-semestre">Semestre actual</label
            ><input class="input" type="number" min="1" id="e-semestre" name="semestre_actual" />
          </div>
          <div class="field">
            <label for="e-codigo">Código estudiantil</label
            ><input class="input" id="e-codigo" name="codigo" />
          </div>
          <div class="field">
            <label for="e-documento">Documento de identidad</label
            ><input class="input" id="e-documento" name="documento" />
          </div>
          <div class="field">
            <label for="e-telefono">Teléfono (opcional)</label
            ><input class="input" id="e-telefono" name="telefono" />
          </div>
          <div><button class="btn" type="submit">${icon('user-plus')}Crear e invitar</button></div>
        </form>
        <div id="estError"></div>
      </section>
      <section class="panel">
        <h2>Crear docente o administrador</h2>
        <form id="userForm" class="form-grid" novalidate>
          <div class="field">
            <label for="correo">Correo institucional</label
            ><input class="input" type="email" id="correo" name="correo" placeholder="nombre${dominio}" />
          </div>
          <div class="field">
            <label for="rol">Rol</label
            ><select class="input" id="rol" name="rol">
              <option value="DOCENTE">Docente</option>
              <option value="ADMIN">Administrador</option>
            </select>
          </div>
          <div><button class="btn" type="submit">${icon('user-plus')}Crear e invitar</button></div>
        </form>
        <div id="userError"></div>
      </section>
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por rol">
          ${ROLES.map(([v, l]) => html`<button data-f="${v}" aria-pressed="${filters.rol === v}">${l}</button>`)}
        </div>
        <input class="input search" type="search" id="q" placeholder="Buscar por correo" />
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const acceso = (u) => {
    if (u.id === session.user.id) return badge('info', 'Tu cuenta');
    return html`<div class="actions">
      ${u.pendiente_activacion ? html`<button class="btn sec sm" data-invite="${u.id}">${icon('send')}Reenviar invitación</button>` : ''}
      <button
        class="btn sm ${u.activo ? 'sec' : 'good'}"
        data-toggle="${u.id}"
        data-activo="${u.activo ? '0' : '1'}"
      >
        ${u.activo ? 'Desactivar' : 'Activar'}
      </button>
      ${u.estudiante ? html`<button class="btn sec sm" data-edit="${u.estudiante.id}">${icon('pencil')}Editar</button>` : ''}
    </div>`;
  };

  async function load() {
    const res = await api.get('/admin/usuarios', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Correo</th>
                  <th>Rol</th>
                  <th>Creado</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                ${res.data.map(
                  (u) =>
                    html`<tr>
                      <td>
                        <b>${u.nombre}</b
                        >${u.estudiante ? html`<small>${u.estudiante.programa?.nombre}, ${u.estudiante.semestre_actual}.º</small>` : ''}
                      </td>
                      <td class="mono">${u.correo}</td>
                      <td>${u.rol.etiqueta}</td>
                      <td class="num">${formatDate(u.creado_en)}</td>
                      <td>
                        ${!u.activo ? badge('neutral', 'Desactivada') : u.pendiente_activacion ? badge('warn', 'Invitación pendiente') : badge('ok', 'Activa')}
                      </td>
                      <td>${acceso(u)}</td>
                    </tr>`,
                )}
              </tbody>
            </table>
          </div>`
        : emptyState('users', 'No hay usuarios con este filtro'),
    );
    const { current_page: page, last_page: last } = res.meta;
    mount(
      root.querySelector('#pager'),
      last > 1
        ? html`<button class="btn sec sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>
              Anterior
            </button>
            <span class="fine">Página ${page} de ${last}</span>
            <button class="btn sec sm" data-page="${page + 1}" ${page >= last ? 'disabled' : ''}>
              Siguiente
            </button>`
        : '',
    );
  }

  let timer;
  root.querySelector('#q').addEventListener('input', (e) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      filters.q = e.target.value.trim();
      filters.page = 1;
      load();
    }, 300);
  });

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
    const edit = e.target.closest('[data-edit]');
    if (edit) return navigate(`/usuarios/estudiante/${edit.dataset.edit}`);
    const invite = e.target.closest('[data-invite]');
    if (invite) {
      return withBusy(invite, async () => {
        try {
          await api.post(`/admin/usuarios/${invite.dataset.invite}/invitacion`);
          toast('Invitación enviada de nuevo.');
        } catch (error) {
          toast(error.message);
        }
      });
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

  const submit = (form, errorId, path, body, done) =>
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post(path, body());
        toast(done);
        form.reset();
        mount(root.querySelector(errorId), '');
        load();
      } catch (error) {
        mount(root.querySelector(errorId), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });

  const estForm = root.querySelector('#estForm');
  estForm.addEventListener('submit', (e) => {
    e.preventDefault();
    submit(
      estForm,
      '#estError',
      '/admin/estudiantes',
      () => ({
        correo: estForm.correo.value.trim(),
        nombres: estForm.nombres.value.trim(),
        apellidos: estForm.apellidos.value.trim(),
        programa_id: Number(estForm.programa_id.value),
        semestre_actual: Number(estForm.semestre_actual.value) || null,
        codigo: estForm.codigo.value.trim(),
        documento: estForm.documento.value.trim(),
        telefono: estForm.telefono.value.trim() || null,
      }),
      'Estudiante creado. Le enviamos la invitación a su correo.',
    );
  });

  const userForm = root.querySelector('#userForm');
  userForm.addEventListener('submit', (e) => {
    e.preventDefault();
    submit(
      userForm,
      '#userError',
      '/admin/usuarios',
      () => ({ correo: userForm.correo.value.trim(), rol: userForm.rol.value }),
      'Cuenta creada. Le enviamos la invitación a su correo.',
    );
  });

  await load();
}
