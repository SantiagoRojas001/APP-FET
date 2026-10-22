import { session } from '../../session.js';
import seguimientoView from '../seguimiento.js';

/** Documentos de seguimiento de la pasantía activa del estudiante. */
export default function seguimientoPropioView(ctx) {
  return seguimientoView({ ...ctx, params: [String(session.user.vinculacion_activa_id)] });
}
