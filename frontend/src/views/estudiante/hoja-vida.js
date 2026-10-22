import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, errorBox, icon, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/**
 * Editor de la hoja de vida en formato institucional. Las listas
 * (experiencias, competencias, proyectos) se editan en el navegador y se
 * guardan juntas con PUT /estudiante/perfil.
 */
export default async function hojaVidaView({ root, navigate }) {
  const [perfil, cat] = await Promise.all([api.get('/estudiante/perfil'), api.get('/catalogos')]);
  const hv = perfil.hoja_vida;

  const draft = {
    experiencias: hv.experiencias.map((e) => ({ ...e })),
    competencias: perfil.competencias.map((c) => ({ id: c.id, nivel: c.nivel })),
    proyectos: perfil.proyectos.map((p) => ({ ...p })),
  };

  const levelSelect = (name, value) =>
    html`<select class="input" name="${name}">${cat.niveles_competencia.map(
      (n) => html`<option value="${n.valor}" ${n.valor === value ? 'selected' : ''}>${n.etiqueta}</option>`,
    )}</select>`;

  const experiencia = (e, i) => html`<div class="list-item">
    <div class="list-item-h"><b>Experiencia ${i + 1}</b><button type="button" class="btn danger sm" data-remove="experiencias" data-i="${i}">${icon('trash')}Quitar</button></div>
    <div class="form-grid">
      <div class="field"><label>Cargo</label><input class="input" name="experiencias.${i}.cargo" value="${e.cargo ?? ''}" /></div>
      <div class="field"><label>Organización</label><input class="input" name="experiencias.${i}.organizacion" value="${e.organizacion ?? ''}" /></div>
      <div class="field"><label>Desde</label><input class="input" type="date" name="experiencias.${i}.fecha_inicio" value="${e.fecha_inicio ?? ''}" /></div>
      <div class="field"><label>Hasta (vacío si continúa)</label><input class="input" type="date" name="experiencias.${i}.fecha_fin" value="${e.fecha_fin ?? ''}" /></div>
      <div class="field wide"><label>Funciones y logros</label><textarea class="input" name="experiencias.${i}.descripcion">${e.descripcion ?? ''}</textarea></div>
    </div>
  </div>`;

  const competencia = (c, i) => html`<div class="list-item">
    <div class="form-grid" style="align-items:end">
      <div class="field"><label>Competencia</label><select class="input" name="competencias.${i}.id">
        ${cat.competencias.map((o) => html`<option value="${o.id}" ${o.id === c.id ? 'selected' : ''}>${o.nombre}</option>`)}
      </select></div>
      <div class="field"><label>Nivel</label>${levelSelect(`competencias.${i}.nivel`, c.nivel)}</div>
      <div><button type="button" class="btn danger sm" data-remove="competencias" data-i="${i}">${icon('trash')}Quitar</button></div>
    </div>
  </div>`;

  const proyecto = (p, i) => html`<div class="list-item">
    <div class="list-item-h"><b>Proyecto ${i + 1}</b><button type="button" class="btn danger sm" data-remove="proyectos" data-i="${i}">${icon('trash')}Quitar</button></div>
    <div class="form-grid">
      <div class="field"><label>Título</label><input class="input" name="proyectos.${i}.titulo" value="${p.titulo ?? ''}" /></div>
      <div class="field"><label>Repositorio (https://...)</label><input class="input" type="url" name="proyectos.${i}.url_repositorio" value="${p.url_repositorio ?? ''}" placeholder="https://github.com/usuario/proyecto" /></div>
      <div class="field"><label>Semestre</label><input class="input" type="number" min="1" max="12" name="proyectos.${i}.semestre" value="${p.semestre ?? ''}" /></div>
      <div class="field wide"><label>Descripción</label><textarea class="input" name="proyectos.${i}.descripcion">${p.descripcion ?? ''}</textarea></div>
    </div>
  </div>`;

  const lists = { experiencias: experiencia, competencias: competencia, proyectos: proyecto };
  const blank = {
    experiencias: () => ({}),
    competencias: () => ({ id: cat.competencias.find((c) => !draft.competencias.some((d) => d.id === c.id))?.id, nivel: 'BASICO' }),
    proyectos: () => ({}),
  };

  mount(
    root,
    html`${pageHeader(
        'Hoja de vida',
        'Formato institucional FET. Las empresas la ven junto con tus proyectos y repositorios.',
        html`<button class="btn sec" type="button" id="preview">${icon('file-text')}Vista previa</button>`,
      )}
      <section class="panel">
        <div class="panel-h"><h2>Completa al ${hv.porcentaje} %</h2>${hv.porcentaje === 100 ? badge('ok', 'Completa') : badge('warn', 'Por completar')}</div>
        <div class="meter"><span style="width:${hv.porcentaje}%"></span></div>
        <div class="tags">${hv.secciones.map((s) => html`<span class="tag${s.completa ? '' : ' on'}">${icon(s.completa ? 'check' : 'alert-circle')}${s.seccion}</span>`)}</div>
      </section>
      <form id="hvForm" class="main-in" style="gap:16px" novalidate>
        <section class="panel">
          <h2>Datos personales</h2>
          <dl class="kv">
            <div><dt>Nombre</dt><dd>${perfil.nombre_completo}</dd></div>
            <div><dt>Documento</dt><dd>${perfil.documento}</dd></div>
            <div><dt>Programa</dt><dd>${perfil.programa.nombre}</dd></div>
            <div><dt>Semestre</dt><dd>${perfil.semestre_actual}.º</dd></div>
          </dl>
          <div class="form-grid">
            <div class="field"><label for="telefono">Teléfono</label><input class="input" id="telefono" name="telefono" value="${perfil.telefono ?? ''}" /></div>
            <div class="field" style="grid-column:span 2"><label for="disponibilidad">Disponibilidad</label><input class="input" id="disponibilidad" name="disponibilidad" value="${perfil.disponibilidad ?? ''}" placeholder="Ej.: medio tiempo en las mañanas" /></div>
          </div>
        </section>
        <section class="panel">
          <h2>Perfil y formación</h2>
          <div class="field"><label for="perfil_profesional">Perfil profesional</label><textarea class="input" id="perfil_profesional" name="perfil_profesional">${hv.perfil_profesional ?? ''}</textarea></div>
          <div class="field"><label for="formacion">Formación académica</label><textarea class="input" id="formacion" name="formacion">${hv.formacion ?? ''}</textarea></div>
        </section>
        ${['experiencias', 'competencias', 'proyectos'].map(
          (key) => html`<section class="panel">
            <div class="panel-h">
              <h2>${{ experiencias: 'Experiencia laboral', competencias: 'Competencias', proyectos: 'Repositorio de proyectos' }[key]}</h2>
              <button type="button" class="btn sec sm" data-add="${key}">${icon('plus')}Agregar</button>
            </div>
            <div class="list-edit" data-list="${key}"></div>
          </section>`,
        )}
        <div id="hvError"></div>
        <div class="actions"><button class="btn" type="submit">Guardar hoja de vida</button></div>
      </form>`,
  );

  const form = root.querySelector('#hvForm');

  /** Copia lo escrito en los campos de las listas al borrador antes de redibujarlas. */
  function syncDraft() {
    for (const el of form.querySelectorAll('[name*="."]')) {
      const [list, i, key] = el.name.split('.');
      draft[list][i][key] = key === 'id' ? Number(el.value) : el.value;
    }
  }

  function drawLists() {
    for (const [key, item] of Object.entries(lists)) {
      const el = form.querySelector(`[data-list="${key}"]`);
      mount(el, draft[key].length ? draft[key].map(item) : html`<p class="fine">Sin registros.</p>`);
    }
  }

  form.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    const remove = e.target.closest('[data-remove]');
    if (!add && !remove) return;
    syncDraft();
    if (add) draft[add.dataset.add].push(blank[add.dataset.add]());
    if (remove) draft[remove.dataset.remove].splice(Number(remove.dataset.i), 1);
    drawLists();
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    syncDraft();
    const nullable = (v) => (v === '' || v === undefined ? null : v);
    const payload = {
      telefono: nullable(form.telefono.value.trim()),
      disponibilidad: nullable(form.disponibilidad.value.trim()),
      perfil_profesional: nullable(form.perfil_profesional.value.trim()),
      formacion: nullable(form.formacion.value.trim()),
      experiencias: draft.experiencias.map((x) => ({
        cargo: x.cargo,
        organizacion: x.organizacion,
        fecha_inicio: nullable(x.fecha_inicio),
        fecha_fin: nullable(x.fecha_fin),
        descripcion: nullable(x.descripcion),
      })),
      competencias: draft.competencias,
      proyectos: draft.proyectos.map((x) => ({
        titulo: x.titulo,
        url_repositorio: x.url_repositorio,
        semestre: nullable(x.semestre),
        descripcion: nullable(x.descripcion),
      })),
    };

    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put('/estudiante/perfil', payload);
        toast('Hoja de vida guardada.');
        navigate('/hoja-vida/vista');
      } catch (error) {
        mount(root.querySelector('#hvError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });

  root.querySelector('#preview').addEventListener('click', () => navigate('/hoja-vida/vista'));

  drawLists();
}
