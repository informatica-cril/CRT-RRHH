// Tradueix una posició a «CP 08015 · Sant Antoni» per ensenyar-la a RRHH en lloc de les coordenades.
// Tot es calcula aquí, amb les dades de zones que ja té l'app: la posició no s'envia a cap servei
// extern de geocodificació (EIPD: minimització i cap tercer).
import { BARCELONA_POSTAL_CODES, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { getCachedPolygon, pointInGeoJson } from '../services/geoPolygonService'

function distanciaM(lat1, lng1, lat2, lng2) {
  const R = 6371000
  const rad = (g) => (g * Math.PI) / 180
  const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(a))
}

// Municipis de l'àrea metropolitana que no són ni CP de Barcelona ni del Vallès: sense ells, una
// marca a Cornellà o a l'Hospitalet només podia dir «fora de les zones». Centre aproximat + CP principal.
const AREA_METROPOLITANA = [
  ['L\'Hospitalet de Llobregat', '08901', 41.3597, 2.0997], ['Cornellà de Llobregat', '08940', 41.3570, 2.0700],
  ['Sant Joan Despí', '08970', 41.3676, 2.0574], ['Esplugues de Llobregat', '08950', 41.3766, 2.0880],
  ['Sant Just Desvern', '08960', 41.3836, 2.0723], ['Sant Feliu de Llobregat', '08980', 41.3814, 2.0453],
  ['El Prat de Llobregat', '08820', 41.3264, 2.0950], ['Sant Boi de Llobregat', '08830', 41.3436, 2.0367],
  ['Molins de Rei', '08750', 41.4140, 2.0160], ['Viladecans', '08840', 41.3140, 2.0140],
  ['Gavà', '08850', 41.3050, 2.0010], ['Castelldefels', '08860', 41.2800, 1.9767],
  ['Badalona', '08911', 41.4500, 2.2474], ['Santa Coloma de Gramenet', '08921', 41.4515, 2.2080],
  ['Sant Adrià de Besòs', '08930', 41.4306, 2.2186],
].map(([nom, cp, lat, lng]) => ({ clau: `AMB_${cp}`, nom, sub: `CP ${cp}`, lat, lng, radius: 2500 }))

const ZONES = [
  ...Object.entries(BARCELONA_POSTAL_CODES).map(([cp, z]) => ({ clau: cp, nom: `CP ${cp}`, sub: z.zone, ...z })),
  ...Object.entries(VALLES_MUNICIPALITIES).map(([clau, z]) => ({ clau, nom: z.name, sub: 'Vallès', ...z })),
  ...AREA_METROPOLITANA,
]

/**
 * @returns {{ text: string, exacte: boolean } | null}
 *  - dins d'un polígon conegut (els ja consultats per l'app): exacte
 *  - si no, el CP o municipi més proper, marcat com a aproximat
 *  - massa lluny de tot: es diu a quina distància queda la zona més propera
 */
export function zonaDePosicio(lat, lng) {
  const la = Number(lat), ln = Number(lng)
  if (!Number.isFinite(la) || !Number.isFinite(ln)) return null

  for (const z of ZONES) {
    const pol = getCachedPolygon(z.clau)
    if (pol && pointInGeoJson(la, ln, pol)) return { text: `${z.nom} · ${z.sub}`, exacte: true }
  }

  let millor = null
  for (const z of ZONES) {
    const d = distanciaM(la, ln, z.lat, z.lng)
    if (!millor || d < millor.d) millor = { z, d }
  }
  if (!millor) return null
  const { z, d } = millor
  if (d <= (z.radius || 2000) * 1.5) return { text: `${z.nom} · ${z.sub} (aprox.)`, exacte: false }
  const km = (d / 1000).toFixed(1).replace('.', ',')
  return { text: `Fora de les zones conegudes (a ${km} km de ${z.nom})`, exacte: false }
}
