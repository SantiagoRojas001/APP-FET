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

const ESTADO_FILA = {
  nuevo: ['ok', 'Se creará'],
  revisar: ['warn', 'Revisar'],
  omitido: ['neutral', 'Omitido'],
  registrado: ['neutral', 'Ya registrado'],
};

/**
 * Registro de estudiantes con el listado de modalidades de grado (PDF, Excel
 * o CSV). La app se queda con quienes eligieron pasantía o práctica en un
 * semestre habilitado; el docente revisa la vista previa, corrige lo que haga
 * falta y confirma. Al registrar el promedio se envía el correo con el
 * resultado: si alcanza el mínimo, con su usuario y contraseña; si no, con un
 * mensaje de ánimo. Si el listado trae el promedio, el correo sale al confirmar.
 */
export default async function registroView({ root }) {
  const cat = await api.get('/catalogos');
  const { promedio_minimo: minimo, semestres_habilitados: semestres } = cat.parametros;
  const filters = { estado: 'sin_promedio', programa_id: '', q: '', page: 1 };

  mount(
    root,
    html`${pageHeader(
        'Registro de estudiantes',
        `Carga el listado de modalidades de grado: se registran quienes eligieron pasantía o práctica en ${semestres.map((s) => `${s}.º`).join(', ')} semestre. Al registrar su promedio les llega el resultado por correo: con ${decimal.format(minimo)} o más reciben su usuario y contraseña para el primer ingreso.`,
      )}
      <section class="panel">
        <h2>Cargar listado de modalidades de grado</h2>
        <form id="impForm" class="form-grid" novalidate>
          <div class="field wide">
            <label for="archivo">Listado en PDF, Excel (.xlsx) o CSV</label>
            <input class="input" type="file" id="archivo" name="archivo" accept="application/pdf,.xlsx,.csv" />
            <span class="help">Debe traer el nombre, el correo, el programa, el ciclo (o el semestre) y la modalidad de grado de cada estudiante. Si trae el promedio, el correo con el resultado sale al confirmar.</span>
          </div>
          <div><button class="btn" type="submit">${icon('upload')}Leer listado</button></div>
        </form>
        <div id="impError"></div>
      </section>
      <div id="preview"></div>
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

  // ---------------------------------------------------------- Importación
  const preview = root.querySelector('#preview');
  let filas = [];

  const opcionesSemestre = (actual) => [
    html`<option value="" ${actual ? '' : 'selected'}>Semestre</option>`,
    ...semestres.map((n) => html`<option value="${n}" ${n === actual ? 'selected' : ''}>${n}.º</option>`),
  ];
  const opcionesPrograma = (actual) => [
    html`<option value="" ${actual ? '' : 'selected'}>Elige el programa</option>`,
    ...cat.programas.map((p) => html`<option value="${p.id}" ${p.id === actual ? 'selected' : ''}>${p.nombre}</option>`),
  ];

  const editable = (f, i) => html`<tr data-fila="${i}">
    <td><input type="checkbox" name="incluir" ${f.estado === 'nuevo' ? 'checked' : ''} aria-label="Incluir fila ${f.fila}" /></td>
    <td>
      <input class="input" name="nombres" value="${f.nombres ?? ''}" aria-label="Nombres" placeholder="Nombres" />
      <input class="input" name="apellidos" value="${f.apellidos ?? ''}" aria-label="Apellidos" placeholder="Apellidos" />
    </td>
    <td><input class="input" type="email" name="correo" value="${f.correo ?? ''}" aria-label="Correo" /></td>
    <td><select class="input" name="programa_id" aria-label="Programa">${opcionesPrograma(f.programa_id)}</select>${f.ciclo ? html`<small>Ciclo ${f.ciclo.toLowerCase()}</small>` : ''}</td>
    <td><select class="input" name="semestre_actual" aria-label="Semestre">${opcionesSemestre(f.semestre_actual)}</select></td>
    <td><select class="input" name="modalidad_grado" aria-label="Modalidad">${cat.modalidades.map((m) => html`<option value="${m.valor}" ${m.valor === f.modalidad_grado ? 'selected' : ''}>${m.etiqueta}</option>`)}</select>${f.promedio !== null ? html`<small>Promedio ${decimal.format(f.promedio)}</small>` : ''}</td>
    <td>${badge(...ESTADO_FILA[f.estado])}${f.motivo ? html`<small>${f.motivo}</small>` : ''}</td>
  </tr>`;

  function drawPreview(resultado = null) {
    const candidatas = filas.map((f, i) => [f, i]).filter(([f]) => ['nuevo', 'revisar'].includes(f.estado));
    const fuera = filas.filter((f) => ['omitido', 'registrado'].includes(f.estado));
    mount(
      preview,
      html`<section class="panel">
          <div class="panel-h">
            <h2>Vista previa</h2>
            <div class="tags">
              ${badge('ok', `${filas.filter((f) => f.estado === 'nuevo').length} listos`)}
              ${badge('warn', `${filas.filter((f) => f.estado === 'revisar').length} por revisar`)}
              ${badge('neutral', `${fuera.length} fuera del registro`)}
            </div>
          </div>
          <p class="help">Se crean las cuentas de las filas marcadas. Corrige las que dicen «Revisar» y márcalas para incluirlas.</p>
          ${resultado
            ? html`<p class="note-box">
                ${icon('circle-check')} Se crearon ${resultado.creados} cuentas.
                ${resultado.con_correo ? `${resultado.con_correo} recibieron el resultado por correo. ` : ''}${resultado.pendientes_de_promedio ? `${resultado.pendientes_de_promedio} esperan el promedio para recibir su correo.` : ''}
                ${resultado.errores.length ? html`<br />No se pudieron crear ${resultado.errores.length}: ${resultado.errores.map((e) => `fila ${e.fila} (${e.correo ?? 'sin correo'}): ${e.error}`).join('; ')}` : ''}
              </p>`
            : ''}
        </section>
        ${candidatas.length
          ? html`<section class="panel flush"><div class="tbl"><table>
              <thead><tr><th><span class="fine">Crear</span></th><th>Estudiante</th><th>Correo</th><th>Programa</th><th>Semestre</th><th>Modalidad</th><th>Estado</th></tr></thead>
              <tbody id="candidatas">${candidatas.map(([f, i]) => editable(f, i))}</tbody>
            </table></div></section>
            <div class="actions"><button class="btn" id="confirmar">${icon('user-plus')}Crear cuentas</button><button class="btn sec" id="descartar">Descartar listado</button></div>`
          : ''}
        ${fuera.length
          ? html`<section class="panel"><h2>Fuera del registro</h2>
              <div class="lines">${fuera.map((f) => html`<div class="line"><div><b>${[f.nombres, f.apellidos].filter(Boolean).join(' ') || f.correo}</b><span>${f.motivo}</span></div>${badge(...ESTADO_FILA[f.estado])}</div>`)}</div>
            </section>`
          : ''}`,
    );
  }

  const impForm = root.querySelector('#impForm');
  impForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!impForm.archivo.files.length) return showFieldErrors(impForm, { archivo: ['Elige el archivo del listado.'] });
    withBusy(impForm.querySelector('[type=submit]'), async () => {
      try {
        const data = new FormData();
        data.append('archivo', impForm.archivo.files[0]);
        ({ filas } = await api.post('/docente/importaciones', data));
        mount(root.querySelector('#impError'), '');
        showFieldErrors(impForm, {});
        drawPreview();
      } catch (error) {
        mount(root.querySelector('#impError'), errorBox(error.message));
        showFieldErrors(impForm, error.errors);
      }
    });
  });

  preview.addEventListener('click', (e) => {
    if (e.target.closest('#descartar')) {
      filas = [];
      impForm.reset();
      return mount(preview, '');
    }
    const confirmar = e.target.closest('#confirmar');
    if (!confirmar) return;
    const elegidas = [...preview.querySelectorAll('#candidatas tr')]
      .filter((tr) => tr.querySelector('[name=incluir]').checked)
      .map((tr) => {
        const f = filas[Number(tr.dataset.fila)];
        const valor = (name) => tr.querySelector(`[name=${name}]`).value.trim();
        return {
          ...f,
          nombres: valor('nombres'),
          apellidos: valor('apellidos'),
          correo: valor('correo').toLowerCase(),
          programa_id: Number(valor('programa_id')) || null,
          semestre_actual: Number(valor('semestre_actual')) || null,
          modalidad_grado: valor('modalidad_grado'),
        };
      });
    if (!elegidas.length) return toast('Marca al menos un estudiante.');
    withBusy(confirmar, async () => {
      try {
        const resultado = await api.post('/docente/importaciones/confirmacion', { filas: elegidas });
        const fallidas = new Set(resultado.errores.map((e) => e.fila));
        // Las filas creadas salen de la vista previa; las que fallaron quedan para corregirlas.
        filas = filas.map((f) => (elegidas.some((x) => x.fila === f.fila) && !fallidas.has(f.fila) ? { ...f, estado: 'registrado', motivo: 'Cuenta creada.' } : f));
        drawPreview(resultado);
        toast(`Se crearon ${resultado.creados} cuentas.`);
        load();
      } catch (error) {
        toast(error.message);
      }
    });
  });

  await load();
}
