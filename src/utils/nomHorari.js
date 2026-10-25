// Nom automàtic d'una plantilla d'horari a partir dels seus dies (RRHH, 19-10-2026): així no hi ha
// noms inventats ni dues maneres d'escriure el mateix horari. El mateix criteri que
// WorkSchedule::nomAutomatic() al servidor.
//   «30h · Dl-Dv 08:00-14:00»
//   «26h 50min · Dl, Dc, Dv 08:00-14:00 / Dm, Dj 12:00-18:00»
//   «40h · Dl-Dv 09:00-14:00 i 15:00-18:00»
// Les hores s'escriuen com al total de sota (formatHM), no en decimals: 33h 45min, no 33,75 h.

import { formatHM } from './formatHours'

const NOMS = ['Dg', 'Dl', 'Dm', 'Dc', 'Dj', 'Dv', 'Ds']
const ORDRE = [1, 2, 3, 4, 5, 6, 0]
const hhmm = h => {
  const [a, b] = String(h).split(':').map(Number)
  return `${String(a || 0).padStart(2, '0')}:${String(b || 0).padStart(2, '0')}`
}
const minuts = h => { const [a, b] = h.split(':').map(Number); return a * 60 + b }

export function nomHorari(dies) {
  // Trams actius per dia, ordenats i ajuntant els que es trepitgen.
  const per = {}
  for (const d of dies || []) {
    if (!d || d.active === false || !d.start || !d.end) continue
    const [a, b] = [hhmm(d.start), hhmm(d.end)]
    if (b > a) (per[Number(d.day)] ||= []).push([a, b])
  }
  let total = 0
  const perText = {}
  for (const dia of ORDRE) {
    if (!per[dia]) continue
    const junts = []
    for (const [a, b] of per[dia].sort()) {
      const u = junts[junts.length - 1]
      if (u && a <= u[1]) u[1] = b > u[1] ? b : u[1]
      else junts.push([a, b])
    }
    total += junts.reduce((s, [a, b]) => s + minuts(b) - minuts(a), 0)
    ;(perText[junts.map(([a, b]) => `${a}-${b}`).join(' i ')] ||= []).push(dia)
  }
  const etiquetaDies = llista => {
    const pos = llista.map(d => ORDRE.indexOf(d))
    const seguits = llista.length > 2 && pos.every((p, i) => i === 0 || p === pos[i - 1] + 1)
    return seguits ? `${NOMS[llista[0]]}-${NOMS[llista[llista.length - 1]]}` : llista.map(d => NOMS[d]).join(', ')
  }
  const parts = Object.entries(perText).map(([text, llista]) => `${etiquetaDies(llista)} ${text}`)
  if (!parts.length) return ''
  return `${formatHM(total / 60)} · ${parts.join(' / ')}`.slice(0, 250)
}
