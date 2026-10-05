// Resum d'una llista de codis postals per ensenyar-la compacta: amb tots els CP de Barcelona eren
// 42 xips desordenats que ocupaven mitja pantalla.
import { BARCELONA_POSTAL_CODES } from '../services/geolocation'

const TOTS_BCN = Object.keys(BARCELONA_POSTAL_CODES)

/**
 * @returns {{ totaBcn: boolean, visibles: string[], resta: number, tots: string[], total: number }}
 *  totaBcn: té tots els CP de Barcelona (s'ensenya «Tota Barcelona» en lloc de la llista)
 *  visibles/resta: els primers `max` ordenats i quants en queden
 */
export function resumCps(codis, max = 4) {
  const tots = [...new Set(codis || [])].sort()
  const totaBcn = TOTS_BCN.length > 0 && TOTS_BCN.every(cp => tots.includes(cp))
  return { totaBcn, visibles: tots.slice(0, max), resta: Math.max(0, tots.length - max), tots, total: tots.length }
}
