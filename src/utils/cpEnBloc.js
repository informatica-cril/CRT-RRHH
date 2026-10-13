// Assignació de codis postals en bloc: una llista enganxada («Nom Cognom 08004-08014 (Sants)»)
// es converteix en files { text, nom, cps } i cada fila es casa amb una persona de la plantilla.

const PARTICULES = new Set(['de', 'del', 'la', 'las', 'los', 'el', 'i', 'y', 'dr', 'dra', 'doctor', 'doctora'])

const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const mots = (s) => norm(s).split(/[^a-zñç]+/).filter(m => m.length > 1 && !PARTICULES.has(m))

/** Una línia per persona. El nom és el text abans del primer codi postal o parèntesi. */
export function llegeixLlista(text) {
  return String(text || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean).map(linia => {
    const cps = [...new Set(linia.match(/\b0[0-9]{4}\b/g) || [])].sort()
    const nom = linia.split(/[(\d]/)[0].replace(/[-–,:]+\s*$/, '').trim()
    return { text: linia, nom, cps }
  }).filter(f => f.nom && f.cps.length)
}

/**
 * Persones que encaixen amb el nom: com a mínim dos mots del nom de la persona apareixen a la
 * línia (també enganxats: «Han ido» ↔ «Hanido»). Primer les de més mots en comú i, a igualtat,
 * les de domiciliària.
 */
function puntuats(nom, persones) {
  const m = mots(nom)
  const linia = new Set([...m, ...m.slice(1).map((x, i) => m[i] + x)])
  return persones
    .map(p => ({ p, punts: mots(p.name).filter(x => linia.has(x)).length, domi: domi(p) }))
    .filter(c => c.punts >= 2)
    .sort((a, b) => (b.punts - a.punts) || (b.domi - a.domi))
}

const domi = (p) => (String(p.work_type || '').startsWith('DOMICILIARIA') ? 1 : 0)

export function candidats(nom, persones) {
  return puntuats(nom, persones).map(c => c.p)
}

/** La persona triada automàticament: només si cap altra no és igual de bona. */
export function triaAutomatica(nom, persones) {
  const [a, b] = puntuats(nom, persones)
  if (!a) return null
  return b && b.punts === a.punts && b.domi === a.domi ? null : a.p
}
