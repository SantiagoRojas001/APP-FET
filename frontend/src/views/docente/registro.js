import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import {
  badge,
  confirmInline,
  emptyState,
  errorBox,
  icon,
  modalityTag,
  pageHeader,
  showFieldErrors,
  toast,
  withBusy,
} from '../../lib/ui.js';

const ESTADOS = [
  ['sin_promedio', 'Sin promedio'],
  ['habilitados', 'Habilitados'],
  ['no_habilitados', 'No habilitados'],
  ['', 'Todos'],
];

const decimal = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 2 });

/**
 * Registro de los estudiantes que eligieron pasantía o práctica. Al registrar
 * el promedio se envía el correo con el resultado: si alcanza el mínimo,
 * con su usuario y contraseña; si no, con un mensaje de ánimo.
 */
export default async function registroView({ root }) {
  const cat = await api.get('/catalogos');
  const { promedio_minimo: minimo, semestres_habilitados: semestres } = cat.parametros;
  const filters = { estado: 'sin_promedio', programa_id: '', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader(
        'Registro de estudiantes',
        `Registra a quienes eligieron pasantía o práctica en ${semestres.map((s) => `${s}.º`).join(', ')} semestre. Al registrar su promedio les llega el resultado por correo: con ${decimal.format(minimo)} o más reciben su usuario y contraseña.`,
      )}
      <section class="panel">
        <h2>Registrar estudiante</h2>
        <form id="regForm" class="form-grid" novalidate>
          <div class="field"><label for="nombres">Nombres</label><input class="input" id="nombres" name="nombres" /></div>
          <div class="field"><label for="apellidos">Apellidos</label><input class="input" id="apellidos" name="apellidos" /></div>
          <div class="field"><label for="correo">Correo</label><input class="input" type="email" id="correo" name="correo" placeholder="nombre@${cat.dominio_correo}" /></div>
          <div class="field">
            <label for="programa_id">Programa</label>
            <select class="input" id="programa_id" name="programa_id">${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre}</option>`)}</select>
          </div>
          <div class="field">
            <label for="semestre_actual">Semestre</label>
            <select class="input" id="semestre_actual" name="semestre_actual">${semestres.map((s) => html`<option value="${s}">${s}.º</option>`)}</select>
          </div>
          <div class="field">
            <label for="modalidad_grado">Modalidad de grado</label>
            <select class="input" id="modalidad_grado" name="modalidad_grado">${cat.modalidades.map((m) => html`<option value="${m.valor}">${m.etiqueta}</option>`)}</select>
          </div>
          <div><button class="btn" type="submit">${icon('user-plus')}Registrar</button></div>
        </form>
        <div id="regError"></div>
        <p class="help">Registrar no envía ningún correo: el estudiante recibe el resultado cuando registras su promedio.</p>
      </section>
      <div class="toolbar">
        <div class="seg" role="group" aria-label="Filtrar por estado">
          ${ESTADOS.map(([v, l]) => html`<button data-estado="${v}" aria-pressed="${filters.estado === v}">${l}</button>`)}
        </div>
        <div class="actions">
          <select class="input" id="fPrograma" aria-label="Programa" style="width:auto">
            <option value="">Todos los programas</option>
            ${cat.programas.map((p) => html`<option value="${p.id}">${p.nombre}</option>`)}
          </select>
          <label class="input-ic search">${icon('search')}<input class="input" id="q" type="search" placeholder="Buscar por nombre" aria-label="Buscar por nombre" /></label>
        </div>
      </div>
      <section class="panel flush" id="list"></section>
      <div class="actions" id="pager"></div>`,
  );

  const list = root.querySelector('#list');

  const promedio = (s) => {
    if (s.promedio !== null && s.promedio !== undefined) {
      return html`<b class="num">${decimal.format(s.promedio)}</b>
        <small>${s.habilitado ? badge('ok', 'Habilitado, credenciales enviadas') : badge('neutral', 'No habilitado, mensaje enviado')}</small>`;
    }
    return html`<form class="actions" data-promedio="${s.id}" novalidate>
      <input class="input narrow" name="promedio" inputmode="decimal" placeholder="4,25" aria-label="Promedio de ${s.nombre_completo}" />
      <button class="btn sm" type="submit">Registrar</button>
    </form>`;
  };

  async function load() {
    const res = await api.get('/docente/estudiantes', filters);
    mount(
      list,
      res.data.length
        ? html`<div class="tbl"><table>
            <thead><tr><th>Estudiante</th><th>Programa</th><th>Modalidad</th><th>Promedio</th></tr></thead>
            <tbody>${res.data.map(
              (s) => html`<tr>
                <td><b>${s.nombre_completo}</b><small class="mono">${s.correo}</small></td>
                <td>${s.programa.nombre}<small>${s.semestre_actual}.º semestre, ciclo ${s.ciclo.toLowerCase()}</small></td>
                <td>${s.modalidad_grado ? modalityTag(s.modalidad_grado) : ''}</td>
                <td>${promedio(s)}</td>
              </tr>`,
            )}</tbody></table></div>`
        : emptyState('users', 'No hay estudiantes con este filtro'),
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
    const estado = e.target.closest('[data-estado]');
    if (estado) {
      filters.estado = estado.dataset.estado;
      filters.page = 1;
      root.querySelectorAll('[data-estado]').forEach((b) => b.setAttribute('aria-pressed', String(b === estado)));
      return load();
    }
    const page = e.target.closest('[data-page]');
    if (page) {
      filters.page = Number(page.dataset.page);
      load();
    }
  });

  root.querySelector('#fPrograma').addEventListener('change', (e) => {
    filters.programa_id = e.target.value;
    filters.page = 1;
    load();
  });

  let debounce;
  root.querySelector('#q').addEventListener('input', (e) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      filters.q = e.target.value.trim();
      filters.page = 1;
      load();
    }, 250);
  });

  // El promedio se registra una sola vez y dispara el correo: se confirma en pantalla.
  list.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-promedio]');
    if (!form) return;
    e.preventDefault();
    const valor = form.promedio.value.trim();
    if (!valor) return showFieldErrors(form, { promedio: ['Escribe el promedio.'] });
    confirmInline(
      form.querySelector('[type=submit]'),
      'registrar y enviar el correo',
      (si) =>
        withBusy(si, async () => {
          try {
            const s = await api.patch(`/docente/estudiantes/${form.dataset.promedio}/promedio`, { promedio: valor });
            toast(s.habilitado ? `${s.nombres} cumple el promedio: le enviamos su usuario y contraseña.` : `Le enviamos el resultado a ${s.nombres}.`);
            load();
          } catch (error) {
            showFieldErrors(form, error.errors) || toast(error.message);
          }
        }),
      `Promedio ${valor}. El correo se envía de inmediato y no se puede cambiar.`,
    );
  });

  const form = root.querySelector('#regForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.post('/docente/estudiantes', {
          nombres: form.nombres.value.trim(),
          apellidos: form.apellidos.value.trim(),
          correo: form.correo.value.trim(),
          programa_id: Number(form.programa_id.value),
          semestre_actual: Number(form.semestre_actual.value),
          modalidad_grado: form.modalidad_grado.value,
        });
        toast('Estudiante registrado. Registra su promedio para enviarle el resultado.');
        form.reset();
        mount(root.querySelector('#regError'), '');
        showFieldErrors(form, {});
        load();
      } catch (error) {
        mount(root.querySelector('#regError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  await load();
}
