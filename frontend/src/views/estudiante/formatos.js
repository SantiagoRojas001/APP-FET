import { session } from '../../session.js';
import formatosView from '../formatos.js';

/** Formatos de la pasantía activa del estudiante. */
export default function formatosEstudianteView(ctx) {
  return formatosView({ ...ctx, params: [session.user.vinculacion_activa_id] });
}
