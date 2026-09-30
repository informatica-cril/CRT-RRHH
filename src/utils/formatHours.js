// Converteix hores en decimal ("37.5") a "Xh Ym", perquè un decimal d'hores
// no es llegeix d'un cop d'ull (37,5h no diu res fins que fas el càlcul mental).
export function formatHM(hoursDecimal) {
  const totalMinutes = Math.round(Number(hoursDecimal || 0) * 60)
  const h = Math.floor(totalMinutes / 60)
  const m = totalMinutes % 60
  if (h === 0 && m === 0) return '0min'
  if (h === 0) return `${m}min`
  if (m === 0) return `${h}h`
  return `${h}h ${m}min`
}
