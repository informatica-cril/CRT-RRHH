// Després de canviar els codis postals o els municipis d'una persona (domiciliària), torna a
// comprovar els seus fitxatges pendents que havien sortit fora de zona. Els polígons dels CP
// només els té el navegador: aquí es fa la mateixa comprovació que en fitxar (checkUserLocation)
// i el servidor només aplica el resultat, i només si millora (fora → dins).
import api from '../services/apiClient'
import { db } from '../services/db'
import { checkUserLocation } from '../services/geolocation'

/** Retorna quants fitxatges han quedat dins de zona. */
export async function reavaluaZonaPersona(userId) {
  const logs = await api.get(`/v1/work-logs/fora-zona/${userId}`)
  if (!Array.isArray(logs) || logs.length === 0) return 0

  const user = await db.getUser(userId)
  const assignment = await db.getActiveCpAssignment(userId).catch(() => null)
  const dins = async (p) => (p ? !!(await checkUserLocation(user, assignment, p.lat, p.lng))?.valid : null)

  const resultats = []
  for (const l of logs) {
    const r = { work_log_id: l.id, inici_dins: await dins(l.inici), fi_dins: await dins(l.fi) }
    if (r.inici_dins || r.fi_dins) resultats.push(r)
  }
  if (resultats.length === 0) return 0

  const res = await api.post('/v1/work-logs/reavalua-zona', { user_id: userId, resultats })
  return res?.fitxatges_reavaluats || 0
}

export function textReavaluats(n) {
  return n > 0
    ? `\n\n${n} fitxatge${n === 1 ? '' : 's'} pendent${n === 1 ? '' : 's'} que sortia${n === 1 ? '' : 'n'} fora de zona ara queda${n === 1 ? '' : 'en'} dins amb la zona nova.`
    : ''
}
