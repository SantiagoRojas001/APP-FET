import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { todayIso } from '../../lib/format.js';
import { badge, emptyState, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';
import { session } from '../../session.js';
import { padDeFirma } from '../../lib/firma.js';
import { recortarRostro } from '../../lib/rostro.js';
import { hydrateFoto } from '../hoja-vida-detalle.js';

/**
 * Editor de la hoja de vida en el formato institucional PA-GTH-F-10:
 * datos personales y foto, formación académica, cursos, experiencia laboral,
 * experiencia investigativa y referencias. Además, el perfil, las
 * competencias y los repositorios que ven las empresas en APP FET. Las listas
 * se editan en el navegador y se guardan juntas con PUT /estudiante/perfil.
 * Cuando el docente autoriza la práctica, la hoja de vida se deshabilita.
 */
export default async function hojaVidaView({ root, navigate, refresh }) {
  const [perfil, cat] = await Promise.all([api.get('/estudiante/perfil'), api.get('/catalogos')]);
  const hv = perfil.hoja_vida;
  const dp = hv.datos_personales;

  if (perfil.hoja_vida_habilitada === false) {
    mount(
      root,
      html`${pageHeader('Hoja de vida')}
        <section class="panel">
          ${emptyState(
            'lock',
            'Tu hoja de vida está deshabilitada',
            'La FET autorizó tu práctica: tu hoja de vida dejó de circular entre las empresas y no se puede editar mientras la práctica esté activa.',
            html`<button class="btn sec" id="preview">${icon('file-text')}Ver mi hoja de vida</button>`,
          )}
        </section>`,
    );
    root.querySelector('#preview').addEventListener('click', () => navigate('/hoja-vida/vista'));
    return;
  }

  if (hv.estado?.valor === 'EN_REVISION') {
    mount(
      root,
      html`${pageHeader('Hoja de vida')}
        <section class="panel">
          ${emptyState(
            'clock-hour-4',
            'Tu hoja de vida está en revisión docente',
            'Podrás editarla cuando tu docente responda. Te avisaremos por correo y en la app.',
            html`<button class="btn sec" id="preview">${icon('file-text')}Ver mi hoja de vida</button>`,
          )}
        </section>`,
    );
    root.querySelector('#preview').addEventListener('click', () => navigate('/hoja-vida/vista'));
    return;
  }

  const opciones = (lista, vacia = true) => [...(vacia ? [['', 'Sin especificar']] : []), ...lista.map((o) => [o, o])];
  const enumOpciones = (lista) => lista.map((o) => [o.valor, o.etiqueta]);

  /** Campos de cada lista: [clave, etiqueta, tipo, opciones, ancho]. */
  const LISTAS = {
    formaciones: {
      titulo: 'Títulos (maestría, especialización, pregrado, tecnólogo o técnico)',
      item: 'Título',
      nuevo: () => ({ nivel: 'TECNICO', pais_ciudad: 'Colombia, Neiva' }),
      campos: [
        ['nivel', 'Nivel', 'select', enumOpciones(cat.niveles_formacion)],
        ['titulo', 'Título obtenido'],
        ['institucion', 'Institución'],
        ['pais_ciudad', 'País y ciudad'],
        ['fecha_grado', 'Fecha de grado (vacía si está en curso)', 'date'],
        ['modalidad', 'Modalidad de estudio', 'select', opciones(cat.hoja_vida.modalidad_estudio)],
      ],
    },
    cursos: {
      titulo: 'Diplomados, cursos, seminarios y actualizaciones',
      item: 'Curso',
      nuevo: () => ({ modalidad: 'Virtual' }),
      campos: [
        ['tema', 'Tema', 'text', null, true],
        ['duracion_horas', 'Duración (horas)', 'number'],
        ['fecha', 'Fecha', 'month'],
        ['modalidad', 'Modalidad', 'select', opciones(cat.hoja_vida.modalidad_estudio, false)],
        ['institucion', 'Institución', 'text', null, true],
      ],
    },
    experiencias: {
      titulo: '3. Experiencia laboral',
      item: 'Experiencia',
      nuevo: () => ({}),
      campos: [
        ['organizacion', 'Institución o empresa'],
        ['cargo', 'Nombre del cargo'],
        ['dedicacion', 'Dedicación', 'select', opciones(cat.hoja_vida.dedicacion)],
        ['direccion', 'Dirección'],
        ['telefono', 'Teléfonos'],
        ['jefe_inmediato', 'Nombre del jefe inmediato'],
        ['fecha_inicio', 'Fecha de inicio', 'date'],
        ['fecha_fin', 'Fecha de terminación (vacía si continúa)', 'date'],
        ['descripcion', 'Funciones', 'textarea', null, true],
      ],
    },
    investigaciones: {
      titulo: '4. Experiencia académica investigativa',
      item: 'Actividad',
      nuevo: () => ({ tipo: 'GRUPO' }),
      campos: [
        ['tipo', 'Apartado', 'select', enumOpciones(cat.tipos_investigacion)],
        ['periodo', 'Tiempo de vinculación o fecha (ej.: 2024-1 a 2025-2)'],
        ['descripcion', 'Nombre del grupo, proyecto o trabajo y una breve descripción', 'textarea', null, true],
      ],
    },
    referencias: {
      titulo: '5. Referencias (al menos una familiar y una personal)',
      item: 'Referencia',
      // La nueva referencia es del tipo que falta: el formato pide una familiar y una personal.
      nuevo: () => ({ tipo: draft.referencias.some((x) => x.tipo === 'FAMILIAR') ? 'PERSONAL' : 'FAMILIAR' }),
      campos: [
        ['tipo', 'Tipo', 'select', enumOpciones(cat.tipos_referencia)],
        ['nombre', 'Nombre completo'],
        ['ocupacion', 'Profesión u ocupación'],
        ['parentesco', 'Parentesco (solo familiar)'],
        ['telefono', 'Teléfono'],
      ],
    },
    competencias: {
      titulo: 'Competencias',
      item: 'Competencia',
      nuevo: () => ({ id: cat.competencias.find((c) => !draft.competencias.some((d) => d.id === c.id))?.id, nivel: 'BASICO' }),
      campos: [
        ['id', 'Competencia', 'select', cat.competencias.map((c) => [c.id, c.nombre])],
        ['nivel', 'Nivel', 'select', enumOpciones(cat.niveles_competencia)],
      ],
    },
    proyectos: {
      titulo: 'Repositorio de proyectos',
      item: 'Proyecto',
      nuevo: () => ({}),
      campos: [
        ['titulo', 'Título'],
        ['url_repositorio', 'Repositorio (https://...)', 'url'],
        ['semestre', 'Semestre', 'number'],
        ['descripcion', 'Descripción', 'textarea', null, true],
      ],
    },
  };

  const draft = {
    formaciones: (hv.formaciones ?? []).map((f) => ({ ...f, nivel: f.nivel.valor })),
    cursos: (hv.cursos ?? []).map((c) => ({ ...c })),
    experiencias: (hv.experiencias ?? []).map((e) => ({ ...e })),
    investigaciones: (hv.investigaciones ?? []).map((i) => ({ ...i, tipo: i.tipo.valor })),
    referencias: (hv.referencias ?? []).map((r) => ({ ...r, tipo: r.tipo.valor })),
    competencias: perfil.competencias.map((c) => ({ id: c.id, nivel: c.nivel })),
    proyectos: perfil.proyectos.map((p) => ({ ...p })),
  };

  const campo = (lista, i, [clave, etiqueta, tipo = 'text', opts, ancho], valor) => {
    const name = `${lista}.${i}.${clave}`;
    const v = valor ?? '';
    const control =
      tipo === 'select'
        ? html`<select class="input" name="${name}">${opts.map(([val, et]) => html`<option value="${val}" ${String(val) === String(v) ? 'selected' : ''}>${et}</option>`)}</select>`
        : tipo === 'textarea'
          ? html`<textarea class="input" name="${name}">${v}</textarea>`
          : html`<input class="input" type="${tipo}" name="${name}" value="${v}" ${tipo === 'number' ? html`min="1"` : ''} ${tipo === 'date' || tipo === 'month' ? html`max="${tipo === 'month' ? todayIso().slice(0, 7) : todayIso()}"` : ''} />`;
    return html`<div class="field${ancho ? ' wide' : ''}"><label>${etiqueta}</label>${control}</div>`;
  };

  const item = (lista) => (valores, i) =>
    html`<div class="list-item">
      <div class="list-item-h">
        <b>${LISTAS[lista].item} ${i + 1}</b><button type="button" class="btn danger sm" data-remove="${lista}" data-i="${i}">${icon('trash')}Quitar</button>
      </div>
      <div class="form-grid">${LISTAS[lista].campos.map((c) => campo(lista, i, c, valores[c[0]]))}</div>
    </div>`;

  const seccionLista = (lista) =>
    html`<div class="panel-h">
        <h2>${LISTAS[lista].titulo}</h2>
        <button type="button" class="btn sec sm" data-add="${lista}">${icon('plus')}Agregar</button>
      </div>
      <div class="list-edit" data-list="${lista}"></div>`;

  const input = (name, label, value, type = 'text', extra = '') =>
    html`<div class="field"><label for="${name}">${label}</label><input class="input" id="${name}" name="${name}" type="${type}" value="${value ?? ''}" ${extra} /></div>`;

  const select = (name, label, value, opts) =>
    html`<div class="field"><label for="${name}">${label}</label><select class="input" id="${name}" name="${name}">${opts.map(([v, et]) => html`<option value="${v}" ${v === (value ?? '') ? 'selected' : ''}>${et}</option>`)}</select></div>`;

  mount(
    root,
    html`${pageHeader(
        'Hoja de vida',
        '',
        html`<button class="btn sec" type="button" id="preview">${icon('file-text')}Vista previa</button>`,
      )}
      ${hv.estado?.valor === 'CON_OBSERVACIONES'
        ? html`<p class="note-box">${icon('alert-circle')} Recomendaciones de tu docente: ${hv.observaciones}</p>`
        : hv.estado?.valor === 'APROBADA'
          ? html`<p class="note-box">${icon('circle-check')} Tu hoja de vida está aprobada. Si guardas cambios, deberás enviarla de nuevo a revisión y las empresas no la verán hasta que se apruebe.</p>`
          : ''}
      <section class="panel">
        <div class="panel-h"><h2>Completa al ${hv.porcentaje} %</h2>${hv.porcentaje === 100 ? badge('ok', 'Completa') : badge('warn', 'Por completar')}</div>
        <div class="meter"><span style="width:${hv.porcentaje}%"></span></div>
        <div class="tags">${hv.secciones.map((s) => html`<span class="tag${s.completa ? '' : ' on'}">${icon(s.completa ? 'check' : 'alert-circle')}${s.seccion}</span>`)}</div>
      </section>
      <form id="hvForm" style="display:flex;flex-direction:column;gap:16px" novalidate>
        <section class="panel">
          <h2>1. Datos personales</h2>
          <div class="hv-id">
            <div>
              <dl class="kv">
                <div><dt>Nombre</dt><dd>${perfil.nombre_completo}</dd></div>
                <div><dt>Documento de identidad</dt><dd>${perfil.documento}</dd></div>
                <div><dt>E-mail</dt><dd>${perfil.correo}</dd></div>
                <div><dt>Programa</dt><dd>${perfil.programa.nombre}, ${perfil.semestre_actual}.º semestre</dd></div>
              </dl>
            </div>
            <div style="flex:0 0 auto;display:flex;flex-direction:column;gap:8px;align-items:center">
              ${hv.url_foto ? html`<img class="hv-foto" data-foto="${hv.url_foto}" alt="Tu foto" />` : html`<div class="hv-foto">Sin foto</div>`}
              <label class="btn sec sm">${icon('upload')}${hv.url_foto ? 'Cambiar foto' : 'Subir foto'}<input type="file" id="foto" accept="image/jpeg,image/png" hidden /></label>
            </div>
          </div>
          <div class="form-grid">
            ${input('fecha_nacimiento', 'Fecha de nacimiento', dp.fecha_nacimiento, 'date', html`max="${todayIso()}"`)}
            ${input('lugar_nacimiento', 'Lugar de nacimiento', dp.lugar_nacimiento, 'text', html`placeholder="Ciudad, departamento"`)}
            ${select('estado_civil', 'Estado civil', dp.estado_civil, opciones(cat.hoja_vida.estado_civil))}
            ${input('lugar_residencia', 'Lugar de residencia', dp.lugar_residencia)}
            ${input('direccion', 'Dirección', dp.direccion)}
            ${input('telefono', 'Teléfonos', perfil.telefono, 'tel')}
            ${select('rh', 'R-H', dp.rh, opciones(cat.hoja_vida.rh))}
            ${input('contacto_emergencia_nombre', 'Contacto de emergencia', dp.contacto_emergencia_nombre)}
            ${input('contacto_emergencia_telefono', 'Teléfono del contacto de emergencia', dp.contacto_emergencia_telefono, 'tel')}
          </div>
        </section>
        <section class="panel">
          <h2>2. Formación académica</h2>
          ${seccionLista('formaciones')}
          ${seccionLista('cursos')}
        </section>
        <section class="panel">${seccionLista('experiencias')}</section>
        <section class="panel">${seccionLista('investigaciones')}</section>
        <section class="panel">${seccionLista('referencias')}</section>
        <section class="panel">
          <h2>Firma</h2>
          <div class="firma-actual">
            ${hv.url_firma ? html`<img class="firma-img" data-foto="${hv.url_firma}" alt="Tu firma" />` : html`<p class="fine">Aún no registras tu firma.</p>`}
            <button type="button" class="btn sec sm" id="firmar">${icon('pencil')}${hv.url_firma ? 'Cambiar firma' : 'Dibujar firma'}</button>
          </div>
          <div class="firma-editor" id="firmaEditor" hidden>
            <canvas class="firma-pad" id="firmaPad" aria-label="Recuadro para dibujar tu firma"></canvas>
            <div class="actions">
              <button type="button" class="btn sm" id="firmaGuardar">Guardar firma</button>
              <button type="button" class="btn sec sm" id="firmaLimpiar">Borrar</button>
              <button type="button" class="btn sec sm" id="firmaCancelar">Cancelar</button>
            </div>
          </div>
        </section>
        <section class="panel">
          <h2>Perfil para las empresas</h2>
          <div class="form-grid">
            <div class="field wide"><label for="perfil_profesional">Perfil profesional</label><textarea class="input" id="perfil_profesional" name="perfil_profesional">${hv.perfil_profesional ?? ''}</textarea></div>
            <div class="field wide"><label for="disponibilidad">Disponibilidad</label><input class="input" id="disponibilidad" name="disponibilidad" value="${perfil.disponibilidad ?? ''}" placeholder="Ej.: medio tiempo en las mañanas" /></div>
          </div>
          ${seccionLista('competencias')}
          ${seccionLista('proyectos')}
        </section>
        <div id="hvError"></div>
        <div class="actions"><button class="btn" type="submit">Guardar hoja de vida</button></div>
      </form>`,
  );

  const form = root.querySelector('#hvForm');

  /** El anillo del perfil (encabezado) muestra el avance de la hoja de vida: se refresca al guardar. */
  const actualizarPerfil = () =>
    api
      .get('/auth/yo')
      .then((u) => session.setUser(u))
      .catch(() => {});

  /** Copia lo escrito en los campos de las listas al borrador antes de redibujarlas. */
  function syncDraft() {
    for (const el of form.querySelectorAll('[name*="."]')) {
      const [lista, i, clave] = el.name.split('.');
      draft[lista][i][clave] = clave === 'id' ? Number(el.value) : el.value;
    }
  }

  function drawLists() {
    for (const lista of Object.keys(LISTAS)) {
      const el = form.querySelector(`[data-list="${lista}"]`);
      mount(el, draft[lista].length ? draft[lista].map(item(lista)) : html`<p class="fine">No registra.</p>`);
    }
    avisoReferencias();
  }

  /** Avisa qué tipo de referencia falta, antes de guardar. */
  function avisoReferencias() {
    const tipos = new Set(draft.referencias.map((x) => x.tipo));
    const faltan = [['FAMILIAR', 'una familiar'], ['PERSONAL', 'una personal']].filter(([t]) => !tipos.has(t)).map(([, l]) => l);
    form.querySelector('[data-ref-aviso]')?.remove();
    if (!faltan.length) return;
    const aviso = document.createElement('p');
    aviso.className = 'note-box';
    aviso.dataset.refAviso = '';
    aviso.textContent = `Te falta ${faltan.join(' y ')} referencia${faltan.length > 1 ? 's' : ''}. Cambia el tipo de una existente o agrega otra.`;
    form.querySelector('[data-list="referencias"]').after(aviso);
  }

  form.addEventListener('change', (e) => {
    if (!e.target.name?.startsWith('referencias.')) return;
    syncDraft();
    avisoReferencias();
  });

  form.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    const remove = e.target.closest('[data-remove]');
    if (!add && !remove) return;
    syncDraft();
    if (add) draft[add.dataset.add].push(LISTAS[add.dataset.add].nuevo());
    if (remove) draft[remove.dataset.remove].splice(Number(remove.dataset.i), 1);
    drawLists();
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    syncDraft();
    const nulo = (v) => (v === '' || v === undefined || v === null ? null : typeof v === 'string' ? v.trim() || null : v);
    const limpiar = (lista) =>
      draft[lista].map((fila) =>
        Object.fromEntries(LISTAS[lista].campos.map(([clave, , tipo]) => [clave, tipo === 'number' && nulo(fila[clave]) !== null ? Number(fila[clave]) : nulo(fila[clave])])),
      );
    const valor = (name) => nulo(form[name].value);

    const payload = {
      telefono: valor('telefono'),
      disponibilidad: valor('disponibilidad'),
      perfil_profesional: valor('perfil_profesional'),
      fecha_nacimiento: valor('fecha_nacimiento'),
      lugar_nacimiento: valor('lugar_nacimiento'),
      estado_civil: valor('estado_civil'),
      lugar_residencia: valor('lugar_residencia'),
      direccion: valor('direccion'),
      rh: valor('rh'),
      contacto_emergencia_nombre: valor('contacto_emergencia_nombre'),
      contacto_emergencia_telefono: valor('contacto_emergencia_telefono'),
      formaciones: limpiar('formaciones'),
      cursos: limpiar('cursos'),
      experiencias: limpiar('experiencias'),
      investigaciones: limpiar('investigaciones'),
      referencias: limpiar('referencias'),
      competencias: draft.competencias,
      proyectos: limpiar('proyectos'),
    };

    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put('/estudiante/perfil', payload);
        await actualizarPerfil();
        toast('Hoja de vida guardada.');
        navigate('/hoja-vida/vista');
      } catch (error) {
        mount(root.querySelector('#hvError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  // La foto debe tener un rostro: se revisa y se recorta en el navegador antes de subirla.
  root.querySelector('#foto').addEventListener('change', async (e) => {
    const archivo = e.target.files[0];
    e.target.value = '';
    if (!archivo) return;
    const boton = e.target.closest('label');
    boton.classList.add('busy');
    const data = new FormData();
    try {
      data.append('foto', await recortarRostro(archivo), 'foto.jpg');
      await api.post('/estudiante/foto', data);
      await actualizarPerfil();
      toast('Foto actualizada.');
      refresh();
    } catch (error) {
      toast(error.errors?.foto?.[0] ?? error.message);
    } finally {
      boton.classList.remove('busy');
    }
  });

  // Firma: se dibuja en el recuadro (no se sube un archivo) y la API comprueba que sea un trazo.
  const editor = root.querySelector('#firmaEditor');
  let pad = null;
  root.querySelector('#firmar').addEventListener('click', () => {
    editor.hidden = false;
    pad ??= padDeFirma(root.querySelector('#firmaPad'));
  });
  root.querySelector('#firmaLimpiar').addEventListener('click', () => pad.limpiar());
  root.querySelector('#firmaCancelar').addEventListener('click', () => {
    pad.limpiar();
    editor.hidden = true;
  });
  root.querySelector('#firmaGuardar').addEventListener('click', (e) => {
    if (pad.vacia()) return toast('Dibuja tu firma en el recuadro.');
    withBusy(e.currentTarget, async () => {
      const data = new FormData();
      data.append('firma', await pad.png(), 'firma.png');
      try {
        await api.post('/estudiante/firma', data);
        toast('Firma guardada.');
        refresh();
      } catch (error) {
        toast(error.errors?.firma?.[0] ?? error.message);
      }
    });
  });

  root.querySelector('#preview').addEventListener('click', () => navigate('/hoja-vida/vista'));

  drawLists();
  hydrateFoto(root);
}
