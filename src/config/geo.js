// Configuració centralitzada del geo-stack.
// Per defecte utilitza serveis públics d'OSM. Per evitar que les coordenades dels
// treballadors surtin a un tercer, es pot apuntar al geo-stack AUTOALLOTJAT de domi
// (tileserver + Overpass propis) via variables d'entorn Vite. Si el local és fiable
// i està en directe, es fa servir primer; els miralls públics queden com a fallback.

// URL de tiles del mapa (Leaflet). Ex. autoallotjat: https://geo.crtbcn.cat/tiles/{z}/{x}/{y}.png
export const TILE_URL =
  import.meta.env.VITE_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'

export const TILE_ATTRIBUTION =
  import.meta.env.VITE_TILE_ATTRIBUTION || '© <a href="https://openstreetmap.org">OpenStreetMap</a>'

export const TILE_MAX_ZOOM = Number(import.meta.env.VITE_TILE_MAX_ZOOM || 19)

// Endpoint(s) Overpass. Si es configura el local (VITE_OVERPASS_URL), es prova PRIMER;
// els miralls públics queden com a fallback per fiabilitat.
const LOCAL_OVERPASS = (import.meta.env.VITE_OVERPASS_URL || '')
  .split(',')
  .map((s) => s.trim())
  .filter(Boolean)

export const OVERPASS_MIRRORS = [
  ...LOCAL_OVERPASS,
  'https://overpass-api.de/api/interpreter',
  'https://overpass.kumi.systems/api/interpreter',
  'https://overpass.openstreetmap.ru/api/interpreter',
]
