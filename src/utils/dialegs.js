// Finestres de confirmació i de text pròpies de l'app (en lloc de confirm() i prompt() del navegador).
//
//   if (!(await confirma('Eliminar aquest tipus de permís?'))) return
//   const motiu = await demana('Motiu de la denegació', { obligatori: true })   // null si es cancel·la
//
// Com que són asíncrones, s'han de cridar amb await (la funció ha de ser async).
import { reactive } from 'vue'

export const dialeg = reactive({
  obert: false,
  tipus: 'confirma',       // 'confirma' | 'demana'
  missatge: '',
  perill: false,           // botó vermell per a accions que no es poden desfer
  boto: 'Acceptar',
  valor: '',
  obligatori: false,
  minim: 0,
  placeholder: '',
  frases: '',              // clau de src/utils/frasesRapides.js (frases ràpides)
  resol: null,
})

// Paraules que indiquen una acció delicada: el botó surt en vermell.
const PERILL = /eliminar|esborrar|desactivar|retirar|revocar|cancel·lar|arxivar|renunciar|definitiv/i

function obre(opcions) {
  if (dialeg.resol) dialeg.resol(dialeg.tipus === 'confirma' ? false : null)
  return new Promise((resol) => Object.assign(dialeg, opcions, { obert: true, resol }))
}

export function confirma(missatge, { boto = null, perill = null } = {}) {
  const m = String(missatge ?? '')
  const delicat = perill ?? PERILL.test(m)
  return obre({ tipus: 'confirma', missatge: m, perill: delicat, boto: boto || (delicat ? 'Sí, continuar' : 'Acceptar'),
    valor: '', obligatori: false, minim: 0, placeholder: '', frases: '' })
}

export function demana(missatge, { obligatori = null, minim = 0, placeholder = '', valor = '', frases = '' } = {}) {
  const m = String(missatge ?? '')
  // Si el text diu «obligatori» o demana un mínim de caràcters, no es pot deixar buit.
  const mMin = /mínim\s+(\d+)\s+caràcters/i.exec(m)
  const min = minim || (mMin ? Number(mMin[1]) : 0)
  const oblig = obligatori ?? (/obligatori/i.test(m) || min > 0)
  return obre({ tipus: 'demana', missatge: m, perill: false, boto: 'Acceptar', valor, obligatori: oblig,
    minim: min || (oblig ? 1 : 0), placeholder: placeholder || (oblig ? 'Escriu-ho aquí' : 'Opcional'), frases })
}

export function respon(acceptat) {
  const r = dialeg.resol
  dialeg.obert = false
  dialeg.resol = null
  if (!r) return
  if (dialeg.tipus === 'confirma') r(!!acceptat)
  else r(acceptat ? dialeg.valor : null)
}
