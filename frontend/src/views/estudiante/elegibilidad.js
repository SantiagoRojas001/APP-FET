import { api } from '../../api/client.js';
import { html, mount } from '../../lib/html.js';
import { badge, modalityTag, pageHeader } from '../../lib/ui.js';

export default async function elegibilidadView({ root }) {
  const e = await api.get('/estudiante/elegibilidad');

  const status = (ok) => (ok === null ? badge('neutral', 'En cada postulación') : ok ? badge('ok', 'Cumple') : badge('bad', 'No cumple'));
  const rows = [
    [
      'Semestre habilitado para práctica',
      e.semestre_minimo ? `${e.programa}: desde ${e.semestre_minimo}.º semestre. Estás en ${e.semestre_actual}.º.` : `${e.programa} no tiene regla vigente.`,
      e.habilitado_para_practica,
    ],
    ['Hoja de vida', `Completa al ${e.hoja_vida_porcentaje} %. Las empresas la ven junto a tus proyectos.`, e.hoja_vida_porcentaje === 100],
    ['Sin vinculación activa', 'Solo puedes postularte si no tienes una práctica o contrato en curso.', !e.vinculacion_activa_id],
    ['Aprobación docente', 'Tu docente verifica los requisitos de cada postulación.', null],
  ];

  mount(
    root,
    html`${pageHeader(
        'Elegibilidad',
        e.habilitado_para_practica
          ? 'Puedes postularte a pasantías y prácticas profesionales.'
          : 'Todavía no puedes postularte. Revisa los requisitos abajo.',
      )}
      <section class="panel">
        <h2>Modalidades a las que puedes aplicar</h2>
        ${e.modalidades_permitidas.length
          ? html`<div class="tags">${e.modalidades_permitidas.map((m) => modalityTag(m, true))}</div>`
          : html`<p class="fine">Ninguna por ahora. Podrás postularte cuando cumplas el semestre mínimo de tu programa.</p>`}
      </section>
      <section class="panel"><div class="lines">
        ${rows.map(([t, d, ok]) => html`<div class="line"><div><b>${t}</b><span>${d}</span></div>${status(ok)}</div>`)}
      </div></section>`,
  );
}
