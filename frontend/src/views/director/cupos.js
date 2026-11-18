import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { errorBox, pageHeader, showFieldErrors, toast, withBusy } from '../../lib/ui.js';

const CICLOS = [
  ['TECNICO', 'tecnico', 'Técnico'],
  ['TECNOLOGO', 'tecnologo', 'Tecnólogo'],
];

/** Cupos de pasantía del programa en el periodo actual, por ciclo. */
export default async function cuposView({ root, refresh }) {
  const cupos = await api.get('/director/cupos');

  mount(
    root,
    html`${pageHeader('Cupos de pasantía', `Periodo ${cupos.periodo}`)}
      <form id="cuposForm" novalidate>
        <section class="panel">
          <div class="form-grid">
            ${CICLOS.map(
              ([ciclo, campo, nombre]) => html`<div class="field">
                <label for="${campo}">${nombre}</label>
                <input class="input" id="${campo}" name="${campo}" type="number" min="0" max="500" step="1" value="${cupos[ciclo].limite}" />
                <small class="fine">${cupos[ciclo].usados} usados, ${cupos[ciclo].disponibles} disponibles</small>
              </div>`,
            )}
          </div>
        </section>
        <div class="actions"><button class="btn" type="submit">Guardar cupos</button></div>
        <div id="cuposError"></div>
      </form>`,
  );

  const form = root.querySelector('#cuposForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    withBusy(form.querySelector('[type=submit]'), async () => {
      try {
        await api.put('/director/cupos', { tecnico: Number(form.tecnico.value), tecnologo: Number(form.tecnologo.value) });
        toast('Cupos guardados.');
        refresh();
      } catch (error) {
        mount(root.querySelector('#cuposError'), errorBox(error.message));
        showFieldErrors(form, error.errors);
      }
    });
  });
}
