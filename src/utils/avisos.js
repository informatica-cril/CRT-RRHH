// Avisos dins de l'aplicació (en lloc de la finestra del navegador «… diu»).
//
// avisa('Desat', 'ok' | 'error' | 'info') mostra una targeta a la cantonada. Els avisos curts
// marxen sols; els llargs, els d'error i els que porten una dada per copiar (p. ex. una
// contrasenya temporal) es queden fins que la persona els tanca.
//
// Per no haver de tocar les 50+ crides a alert() repartides per l'app, alert() passa per aquí.
import { reactive } from 'vue'

export const avisos = reactive([])
let seguent = 1

function tipusDe(text) {
  const t = text.toLowerCase()
  if (/^\s*(✅|✓)/.test(text) || /desat|desada|correctament|creat|enviat|actualitzat/.test(t)) return 'ok'
  if (/^\s*(❌|⚠️|⚠)/.test(text) || /error|no s'ha pogut|no es pot|incorrect|cal |falta|massa|no autoritzat/.test(t)) return 'error'
  return 'info'
}

function netejaIcona(text) {
  return text.replace(/^\s*(✅|✓|❌|⚠️|⚠)\s*/, '')
}

export function avisa(text, tipus = null) {
  const brut = String(text ?? '')
  const t = tipus || tipusDe(brut)
  const missatge = netejaIcona(brut)
  // Es queda fins que el tanquen si és llarg, és d'error o porta una dada per copiar.
  const fix = t === 'error' || missatge.length > 140 || missatge.includes('\n') || /contrasenya|codi/i.test(missatge)
  const id = seguent++
  avisos.push({ id, tipus: t, missatge, fix })
  if (!fix) setTimeout(() => tanca(id), 5000)
  return id
}

export function tanca(id) {
  const i = avisos.findIndex(a => a.id === id)
  if (i >= 0) avisos.splice(i, 1)
}

if (typeof window !== 'undefined' && !window.__avisosInstallats) {
  window.__avisosInstallats = true
  window.alert = (m) => { avisa(m) }
}
