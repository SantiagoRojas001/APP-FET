import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';

const decimal = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 2 });

const ESTADO_FILA = {
  nuevo: ['ok', 'Se registrará'],
  revisar: ['warn', 'Revisar'],
  no_habilitado: ['bad', 'No habilitado'],
  omitido: ['neutral', 'Omitido'],
  registrado: ['neutral', 'Ya registrado'],
  avisado: ['neutral', 'Correo enviado'],
};

/**
 * Carga del listado de modalidades de grado (PDF, Excel o CSV). La app se
 * queda con quienes eligieron pasantía o práctica en el programa del docente;
 * en la vista previa se corrige lo que haga falta y al confirmar se registran
 * los habilitados. A quien cursa un semestre no habilitado (o trae un promedio
 * inferior al mínimo) no se le registra: recibe un correo con el motivo.
 */
export default async function registroView({ root }) {
  const cat = await api.get('/catalogos');
  const { semestres_habilitados: semestres } = cat.parametros;
  const propio = session.user.programa;
  const programas = propio ? cat.programas.filter((p) => p.id === propio.id) : cat.programas;

  mount(
    root,
    html`${pageHeader('Cargar listado')}
      <section class="panel">
        <form id="impForm" class="form-grid" novalidate>
          <div class="field wide">
            <label for="archivo">Listado de modalidades de grado (PDF, Excel o CSV)</label>
            <input class="input" type="file" id="archivo" name="archivo" accept="application/pdf,.xlsx,.csv" />
          </div>
          <div><button class="btn" type="submit">${icon('upload')}Leer listado</button></div>
        </form>
        <div id="impError"></div>
      </section>
      <div id="preview"></div>`,
  );

  const preview = root.querySelector('#preview');
  let filas = [];

  const opcionesSemestre = (actual) => [
    html`<option value="" ${actual ? '' : 'selected'}>Semestre</option>`,
    ...semestres.map((n) => html`<option value="${n}" ${n === actual ? 'selected' : ''}>${n}.º</option>`),
  ];
  const opcionesPrograma = (actual) => [
    html`<option value="" ${actual ? '' : 'selected'}>Elige el programa</option>`,
    ...programas.map((p) => html`<option value="${p.id}" ${p.id === actual ? 'selected' : ''}>${p.nombre}</option>`),
  ];
  const nombre = (f) => [f.nombres, f.apellidos].filter(Boolean).join(' ') || f.correo;

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
    const noHabilitados = filas.map((f, i) => [f, i]).filter(([f]) => f.estado === 'no_habilitado');
    const fuera = filas.filter((f) => ['omitido', 'registrado', 'avisado'].includes(f.estado));
    const contar = (estado) => filas.filter((f) => f.estado === estado).length;
    mount(
      preview,
      html`<section class="panel">
          <div class="panel-h">
            <h2>Vista previa</h2>
            <div class="tags">
              ${badge('ok', `${contar('nuevo')} listos`)}
              ${contar('revisar') ? badge('warn', `${contar('revisar')} por revisar`) : ''}
              ${noHabilitados.length ? badge('bad', `${noHabilitados.length} no habilitados`) : ''}
              ${fuera.length ? badge('neutral', `${fuera.length} fuera del registro`) : ''}
            </div>
          </div>
          ${resultado
            ? html`<p class="note-box">
                ${icon('circle-check')} Se registraron ${resultado.creados} estudiantes.
                ${resultado.con_correo ? `${resultado.con_correo} recibieron su usuario y contraseña. ` : ''}${resultado.pendientes_de_promedio ? `${resultado.pendientes_de_promedio} esperan el promedio. ` : ''}${resultado.no_habilitados ? `${resultado.no_habilitados} recibieron el correo con el motivo por el que no quedan habilitados.` : ''}
                ${resultado.errores.length ? html`<br />No se pudieron procesar ${resultado.errores.length}: ${resultado.errores.map((e) => `fila ${e.fila} (${e.correo ?? 'sin correo'}): ${e.error}`).join('; ')}` : ''}
              </p>`
            : ''}
        </section>
        ${candidatas.length
          ? html`<section class="panel flush"><div class="tbl"><table>
              <thead><tr><th><span class="fine">Registrar</span></th><th>Estudiante</th><th>Correo</th><th>Programa</th><th>Semestre</th><th>Modalidad</th><th>Estado</th></tr></thead>
              <tbody id="candidatas">${candidatas.map(([f, i]) => editable(f, i))}</tbody>
            </table></div></section>`
          : ''}
        ${noHabilitados.length
          ? html`<section class="panel">
              <h2>No habilitados</h2>
              <div class="lines" id="noHabilitados">${noHabilitados.map(
                ([f, i]) => html`<label class="line" data-fila="${i}">
                  <div><b>${nombre(f)}</b><span>${f.correo} · ${f.motivo}</span></div>
                  <span class="check"><input type="checkbox" name="avisar" checked />Enviar correo con el motivo</span>
                </label>`,
              )}</div>
            </section>`
          : ''}
        ${candidatas.length || noHabilitados.length
          ? html`<div class="actions"><button class="btn" id="confirmar">${icon('user-plus')}Confirmar registro</button><button class="btn sec" id="descartar">Descartar listado</button></div>`
          : ''}
        ${fuera.length
          ? html`<section class="panel"><h2>Fuera del registro</h2>
              <div class="lines">${fuera.map((f) => html`<div class="line"><div><b>${nombre(f)}</b><span>${f.motivo}</span></div>${badge(...ESTADO_FILA[f.estado])}</div>`)}</div>
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
    const avisos = [...preview.querySelectorAll('#noHabilitados [data-fila]')]
      .filter((l) => l.querySelector('[name=avisar]').checked)
      .map((l) => filas[Number(l.dataset.fila)]);
    if (!elegidas.length && !avisos.length) return toast('Marca al menos un estudiante.');

    withBusy(confirmar, async () => {
      try {
        const resultado = await api.post('/docente/importaciones/confirmacion', { filas: elegidas, no_habilitados: avisos });
        const fallidas = new Set(resultado.errores.map((e) => e.fila));
        const listo = (f, lista, estado, motivo) => (lista.some((x) => x.fila === f.fila) && !fallidas.has(f.fila) ? { ...f, estado, motivo } : f);
        // Las filas procesadas salen de la vista previa; las que fallaron quedan para corregirlas.
        filas = filas.map((f) => listo(listo(f, elegidas, 'registrado', 'Registrado.'), avisos, 'avisado', 'Se le envió el motivo por correo.'));
        drawPreview(resultado);
        toast(`Se registraron ${resultado.creados} estudiantes.`);
      } catch (error) {
        toast(error.message);
      }
    });
  });
}
