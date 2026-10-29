import { api } from '../api/client.js';
import { html } from '../lib/html.js';
import { formatDate } from '../lib/format.js';
import { badge, icon } from '../lib/ui.js';
import { repositorySlot } from './repositorio.js';

const NIVELES = { BASICO: 'básico', INTERMEDIO: 'intermedio', AVANZADO: 'avanzado' };
const SUPERIOR = ['MAESTRIA', 'ESPECIALIZACION', 'PREGRADO'];
const mes = new Intl.DateTimeFormat('es-CO', { month: '2-digit', year: 'numeric' });
const noRegistra = html`<p class="fine">No registra.</p>`;

const titulo = (f) =>
  html`<div class="line">
    <div>
      <b>${f.nivel.etiqueta}: ${f.titulo}</b>
      <span>${f.institucion}, ${f.pais_ciudad}. ${f.fecha_grado ? `Grado: ${formatDate(f.fecha_grado)}` : 'En curso'}${f.modalidad ? `. ${f.modalidad}` : ''}</span>
    </div>
  </div>`;

const grupoPor = (lista, clave) =>
  lista.reduce((grupos, x) => ({ ...grupos, [x[clave].valor]: [...(grupos[x[clave].valor] ?? []), x] }), {});

/**
 * Hoja de vida completa en solo lectura, con los apartados del formato
 * PA-GTH-F-10 y lo que ven las empresas en APP FET (perfil, competencias y
 * repositorios). La usan la vista previa del estudiante y el perfil que
 * consultan empresas y docentes. Después de montarla, llama a
 * `hydrateRepositories` y `hydrateFoto`.
 */
export function hojaVidaDetalle(s) {
  const hv = s.hoja_vida;
  const dp = hv.datos_personales;
  const formaciones = hv.formaciones ?? [];
  const investigaciones = grupoPor(hv.investigaciones ?? [], 'tipo');
  const referencias = hv.referencias ?? [];
  const dato = (v) => v ?? 'No registra';

  return html`<section class="panel">
      <div class="panel-h">
        <h2>1. Datos personales</h2>
        ${s.habilitado_para_practica ? badge('ok', 'Habilitado para práctica') : badge('neutral', 'Aún no habilitado')}
      </div>
      <div class="hv-id">
        ${hv.url_foto ? html`<img class="hv-foto" data-foto="${hv.url_foto}" alt="Foto de ${s.nombre_completo}" />` : html`<div class="hv-foto">Sin foto</div>`}
        <div>
          <dl class="kv">
            ${s.documento ? html`<div><dt>Documento</dt><dd>${s.documento}</dd></div>` : ''}
            <div><dt>Fecha de nacimiento</dt><dd>${dp.fecha_nacimiento ? formatDate(dp.fecha_nacimiento) : 'No registra'}</dd></div>
            <div><dt>Lugar de nacimiento</dt><dd>${dato(dp.lugar_nacimiento)}</dd></div>
            <div><dt>Estado civil</dt><dd>${dato(dp.estado_civil)}</dd></div>
            <div><dt>Residencia</dt><dd>${dato(dp.lugar_residencia)}</dd></div>
            <div><dt>Dirección</dt><dd>${dato(dp.direccion)}</dd></div>
            <div><dt>Teléfonos</dt><dd>${dato(s.telefono)}</dd></div>
            <div><dt>E-mail</dt><dd>${s.correo}</dd></div>
            <div><dt>R-H</dt><dd>${dato(dp.rh)}</dd></div>
            <div><dt>Contacto de emergencia</dt><dd>${dp.contacto_emergencia_nombre ? `${dp.contacto_emergencia_nombre}, ${dp.contacto_emergencia_telefono}` : 'No registra'}</dd></div>
            <div><dt>Código</dt><dd class="mono">${s.codigo}</dd></div>
            <div><dt>Disponibilidad</dt><dd>${dato(s.disponibilidad)}</dd></div>
          </dl>
        </div>
      </div>
    </section>
    <section class="panel">
      <h2>2. Formación académica</h2>
      <p class="hv-sub">Educación superior</p>
      <div class="lines">
        <div class="line">
          <div>
            <b>Pregrado: ${s.programa.nombre}</b>
            <span>Fundación Escuela Tecnológica de Neiva, Colombia, Neiva. ${s.es_egresado ? 'Egresado(a)' : `En curso (semestre ${s.semestre_actual})`}</span>
          </div>
        </div>
        ${formaciones.filter((f) => SUPERIOR.includes(f.nivel.valor)).map(titulo)}
      </div>
      <p class="hv-sub">Técnica y tecnológica</p>
      ${formaciones.some((f) => !SUPERIOR.includes(f.nivel.valor))
        ? html`<div class="lines">${formaciones.filter((f) => !SUPERIOR.includes(f.nivel.valor)).map(titulo)}</div>`
        : noRegistra}
      <p class="hv-sub">Diplomados, cursos, seminarios y actualizaciones</p>
      ${hv.cursos?.length
        ? html`<div class="lines">${hv.cursos.map(
            (c) => html`<div class="line"><div><b>${c.tema}</b><span>${c.institucion} · ${c.duracion_horas} horas · ${mes.format(new Date(`${c.fecha}-15`))} · ${c.modalidad}</span></div></div>`,
          )}</div>`
        : noRegistra}
    </section>
    <section class="panel">
      <h2>3. Experiencia laboral</h2>
      ${hv.experiencias?.length
        ? html`<div class="lines">${hv.experiencias.map(
            (e) => html`<div class="line"><div>
              <b>${e.cargo}, ${e.organizacion}</b>
              <span>${formatDate(e.fecha_inicio)} a ${e.fecha_fin ? formatDate(e.fecha_fin) : 'la fecha'}${e.dedicacion ? ` · ${e.dedicacion}` : ''}${e.jefe_inmediato ? ` · Jefe inmediato: ${e.jefe_inmediato}` : ''}${e.telefono ? ` · Tel. ${e.telefono}` : ''}</span>
              ${e.descripcion ? html`<span>Funciones: ${e.descripcion}</span>` : ''}
            </div></div>`,
          )}</div>`
        : noRegistra}
    </section>
    <section class="panel">
      <h2>4. Experiencia académica investigativa</h2>
      ${Object.keys(investigaciones).length
        ? html`<div class="lines">${Object.values(investigaciones).map(
            (grupo) => html`<div class="line"><div>
              <b>${grupo[0].tipo.etiqueta}</b>
              ${grupo.map((i) => html`<span>${i.descripcion}${i.periodo ? ` (${i.periodo})` : ''}</span>`)}
            </div></div>`,
          )}</div>`
        : noRegistra}
    </section>
    <section class="panel">
      <h2>5. Referencias</h2>
      ${referencias.length
        ? html`<div class="lines">${referencias.map(
            (r) => html`<div class="line"><div>
              <b>${r.nombre}</b>
              <span>${r.tipo.etiqueta}${r.parentesco ? ` (${r.parentesco})` : ''} · ${r.ocupacion} · Tel. ${r.telefono}</span>
            </div></div>`,
          )}</div>`
        : noRegistra}
    </section>
    <section class="panel">
      <h2>Perfil profesional</h2>
      <p class="muted">${hv.perfil_profesional ?? 'Sin registrar.'}</p>
      <h2>Competencias</h2>
      <div class="tags">${s.competencias.length ? s.competencias.map((c) => html`<span class="tag">${c.nombre} · ${NIVELES[c.nivel] ?? c.nivel}</span>`) : html`<p class="fine">Sin competencias registradas.</p>`}</div>
    </section>
    <section class="panel">
      <h2>Repositorio de proyectos</h2>
      ${s.proyectos.length
        ? s.proyectos.map(
            (p) => html`<article class="project">
              <div class="project-h">
                <div><b>${p.titulo}</b>${p.semestre ? html` <span class="fine">· ${p.semestre}.º semestre</span>` : ''}</div>
                <a class="fine" href="${p.url_repositorio}" target="_blank" rel="noopener noreferrer">${icon('external-link')} Abrir en GitHub</a>
              </div>
              ${p.descripcion ? html`<p class="muted">${p.descripcion}</p>` : ''}
              ${repositorySlot(p)}
            </article>`,
          )
        : html`<p class="fine">Sin proyectos registrados.</p>`}
    </section>`;
}

/** Carga con el token las fotos `<img data-foto>` (la API no las sirve sin autenticación). */
export function hydrateFoto(root) {
  root.querySelectorAll('img[data-foto]').forEach((img) =>
    api
      .blobUrl(img.dataset.foto)
      .then((url) => (img.src = url))
      .catch(() => img.replaceWith(Object.assign(document.createElement('div'), { className: 'hv-foto', textContent: 'Sin foto' }))),
  );
}
