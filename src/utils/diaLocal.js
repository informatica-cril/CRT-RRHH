// Dia (YYYY-MM-DD) d'una data que ve de l'API.
// Laravel serialitza els camps amb cast `date` en UTC («2026-10-29T23:00:00.000000Z» és
// el 30 a Madrid): tallar el text dona el dia anterior. Si porta hora, es passa a hora local.
export function diaLocal(d) {
  if (!d) return ''
  const s = String(d)
  if (s.length <= 10) return s
  const dt = new Date(s)
  if (isNaN(dt)) return s.slice(0, 10)
  const p = (n) => String(n).padStart(2, '0')
  return `${dt.getFullYear()}-${p(dt.getMonth() + 1)}-${p(dt.getDate())}`
}
