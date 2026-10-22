const fecha = new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
const fechaCorta = new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short' });

/** Las fechas sin hora (AAAA-MM-DD) se leen como fecha local, no UTC. */
const parse = (value) => (/^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value));

export const formatDate = (value) => (value ? fecha.format(parse(value)) : '—');
export const formatShortDate = (value) => (value ? fechaCorta.format(parse(value)) : '—');

export function relativeDays(value) {
  const days = Math.round((Date.now() - parse(value).getTime()) / 86_400_000);
  if (days <= 0) return 'Hoy';
  if (days === 1) return 'Ayer';
  if (days < 7) return `Hace ${days} días`;
  if (days < 14) return 'Hace 1 semana';
  return formatShortDate(value);
}

export const initials = (name = '') =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');

export const todayIso = () => new Date().toLocaleDateString('en-CA');
