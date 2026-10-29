import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

/** La administración corrige los datos académicos y personales de un estudiante. */
export default async function estudianteAdminView({ root, params, navigate }) {
  const [s, cat] = await Promise.all([api.get(`/estudiantes/${params[0]}`), api.get('/catalogos')]);

  mount(
    root,
    html`${pageHeader(s.nombre_completo, s.correo, html`<button class="btn sec" id="back">Volver</button>`)}
      <section class="panel">
        <h2>Datos del estudiante</h2>
        <form id="form" class="form-grid" novalidate>
          <div class="field">
            <label for="nombres">Nombres</label
            ><input class="input" id="nombres" name="nombres" value="${s.nombres}" />
          </div>
          <div class="field">
            <label for="apellidos">Apellidos</label
            ><input class="input" id="apellidos" name="apellidos" value="${s.apellidos}" />
          </div>
          <div class="field">
            <label for="programa_id">Programa</label
            ><select class="input" id="programa_id" name="programa_id">
              ${cat.programas.map((p) => html`<option value="${p.id}" ${p.id === s.programa.id ? 'selected' : ''}>${p.nombre}</option>`)}
            </select>
          </div>
          <div class="field">
            <label for="semestre_actual">Semestre actual</label
            ><input
              class="input"
              type="number"
              min="1"
              id="semestre_actual"
              name="semestre_actual"
              value="${s.semestre_actual}"
            />
          </div>
          <div class="field">
            <label for="codigo">Código estudiantil</label
            ><input class="input" id="codigo" name="codigo" value="${s.codigo}" />
          </div>
          <div class="field">
            <label for="documento">Documento de identidad</label
            ><input class="input" id="documento" name="documento" value="${s.documento ?? ''}" />
          </div>
          <div class="field">
            <label for="telefono">Teléfono</label
            ><input class="input" id="telefono" name="telefono" value="${s.telefono ?? ''}" />
          </div>
          <div class="field">
            <label class="check"
              ><input type="checkbox" name="es_egresado" ${s.es_egresado ? 'checked' : ''} /> Es
              egresado</label
            >
          </div>
          <div><button class="btn" type="submit">Guardar cambios</button></div>
        </form>
        <div id="error"></div>
      </section>`,
  );

  root.querySelector('#back').addEventListener('click', () => navigate('/usuarios'));

  const form = root.querySelector('#form');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.patch(`/admin/estudiantes/${s.id}`, {
          nombres: form.nombres.value.trim(),
          apellidos: form.apellidos.value.trim(),
          programa_id: Number(form.programa_id.value),
          semestre_actual: Number(form.semestre_actual.value) || null,
          codigo: form.codigo.value.trim(),
          documento: form.documento.value.trim(),
          telefono: form.telefono.value.trim() || null,
          es_egresado: form.es_egresado.checked,
        });
        toast('Datos actualizados.');
        navigate('/usuarios');
      } catch (error) {
        mount(root.querySelector('#error'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
