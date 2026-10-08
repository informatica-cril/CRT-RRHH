import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Canvis de diverses claus en el mateix moment: s'ajunten en un sol router.replace
// (si no, cada un partiria de la query antiga i s'esborrarien entre ells).
let pendent = null

function escriu(router, clau, valor) {
  pendent = pendent || { ...router.currentRoute.value.query }
  if (valor === undefined) delete pendent[clau]
  else pendent[clau] = valor
  Promise.resolve().then(() => {
    if (!pendent) return
    const query = pendent
    pendent = null
    router.replace({ query }).catch(() => {})
  })
}

/** Ids de persona: a la URL són text, però els selectors els comparen com a número. */
export const idOText = (v) => (/^\d+$/.test(v) ? Number(v) : v)

/**
 * Desa a la URL (?clau=valor) un estat de la pantalla — mes, filtre, pestanya, persona —
 * perquè en recarregar (F5) o obrir l'enllaç es torni exactament al mateix lloc.
 * El valor inicial de la pantalla no s'escriu a la URL. Cal cridar-lo al setup, just
 * després de crear la ref i abans de carregar dades.
 */
export function useEstatUrl(clau, estat, llegeix = (v) => v) {
  const route = useRoute()
  const router = useRouter()
  const perDefecte = estat.value

  const desat = route.query[clau]
  if (typeof desat === 'string' && desat !== '') estat.value = llegeix(desat)

  watch(estat, (v) => {
    const buit = v === perDefecte || v === '' || v === null || v === undefined
    escriu(router, clau, buit ? undefined : String(v))
  })
}
