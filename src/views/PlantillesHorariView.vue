<template>
  <div class="ph">
    <div class="page-header">
      <div>
        <h1 class="page-title">Plantilles d'horari</h1>
        <p class="page-subtitle">Quines plantilles es fan servir, quines són repetides i quines no fa servir ningú. Ajuntar-les no canvia l'horari de ningú.</p>
      </div>
    </div>

    <div v-if="carregant" class="card text-muted ph-buit">Carregant…</div>
    <template v-else>
      <div class="ph-resum">
        <button type="button" class="ph-xifra" :class="{ actiu: filtre === 'totes' }" @click="filtre = 'totes'">
          <strong>{{ plantilles.length }}</strong><span>plantilles</span>
        </button>
        <button type="button" class="ph-xifra" :class="{ actiu: filtre === 'repetides' }" @click="filtre = 'repetides'">
          <strong>{{ sobrants }}</strong><span>repetides de més</span>
        </button>
        <button type="button" class="ph-xifra" :class="{ actiu: filtre === 'senseus' }" @click="filtre = 'senseus'">
          <strong>{{ senseUs.length }}</strong><span>no les fa servir ningú</span>
        </button>
        <button type="button" class="ph-xifra" :class="{ actiu: filtre === 'revisar' }" @click="filtre = 'revisar'">
          <strong>{{ perRevisar }}</strong><span>amb hores que no quadren o sense dies</span>
        </button>
      </div>

      <div class="card ph-accio-gran" v-if="senseUs.length">
        <div>
          <strong>🧹 {{ senseUs.length }} plantilla{{ senseUs.length === 1 ? '' : 'es' }} que no fa servir ningú</strong>
          <div class="text-small text-muted">Ni persones (actives o de baixa) ni dies del quadrant. Es poden esborrar sense que canviï res.</div>
        </div>
        <button type="button" class="btn btn-danger btn-sm" :disabled="treballant" @click="esborraSenseUs">Esborrar-les</button>
      </div>

      <div v-if="!grupsVisibles.length" class="card text-muted ph-buit">No n'hi ha cap amb aquest filtre. 🎉</div>

      <div v-for="g in grupsVisibles" :key="g.signatura" class="card ph-grup">
        <div class="ph-grup-cap">
          <div>
            <div class="ph-horari">{{ g.resum }}</div>
            <div class="text-small text-muted">{{ formatHM(g.minuts / 60) }} a la setmana · {{ g.persones }} persona{{ g.persones === 1 ? '' : 'es' }}</div>
          </div>
          <div v-if="g.plantilles.length > 1" class="ph-ajunta">
            <span class="text-small">{{ g.plantilles.length }} plantilles fan aquest horari. Deixar-ne només una:</span>
            <select class="form-select" v-model="queda[g.signatura]">
              <option v-for="p in g.plantilles" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <button type="button" class="btn btn-primary btn-sm" :disabled="treballant" @click="ajunta(g)">Ajuntar</button>
          </div>
        </div>
        <table class="ph-taula">
          <tbody>
            <tr v-for="p in g.plantilles" :key="p.id">
              <td class="ph-nom">
                {{ p.name }}
                <div v-if="avisHores(p)" class="ph-avis">⚠ {{ avisHores(p) }}</div>
              </td>
              <td class="ph-us">
                <span v-if="p.persones_actives">{{ p.persones_actives }} activa{{ p.persones_actives === 1 ? '' : 'es' }}</span>
                <span v-if="p.persones_inactives" class="text-muted">{{ p.persones_inactives }} de baixa</span>
                <span v-if="p.dies_quadrant" class="text-muted">{{ p.dies_quadrant }} dies al quadrant</span>
                <span v-if="!usada(p)" class="text-muted">Ningú</span>
              </td>
              <td class="ph-botons">
                <button v-if="avisHores(p) && p.minuts_reals" type="button" class="btn btn-outline btn-sm" :disabled="treballant" @click="corregeixHores(p)"
                  :title="'Posar ' + textHores(p.minuts_reals) + ' a la plantilla, que és el que suma el seu horari'">Posar {{ textHores(p.minuts_reals) }}</button>
                <button v-if="!usada(p)" type="button" class="btn btn-outline btn-sm" :disabled="treballant" @click="esborra(p)">Esborrar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'
import { confirma } from '../utils/dialegs'
import { avisa } from '../utils/avisos'
import { formatHM } from '../utils/formatHours'
import { useEstatUrl } from '../composables/useEstatUrl'

const plantilles = ref([])
const carregant = ref(true)
const treballant = ref(false)
const filtre = ref('totes')
useEstatUrl('filtre', filtre)
const queda = ref({})

async function carrega() {
  try {
    plantilles.value = await api.get('/v1/plantilles-horari')
  } catch (e) {
    avisa(e?.message || "No s'han pogut carregar les plantilles.", 'error')
  } finally {
    carregant.value = false
  }
}
onMounted(carrega)

const usada = p => p.persones_actives + p.persones_inactives + p.dies_quadrant > 0
const textHores = min => formatHM(min / 60)
// Les hores que diu la plantilla no són les que suma el seu horari (p. ex. «35 h» amb un horari de 25 h).
function avisHores(p) {
  if (!p.signatura) return 'No té cap dia amb horari: qui la fa servir no té horari de veritat.'
  if (Math.abs(p.total_hours_weekly * 60 - p.minuts_reals) >= 15) {
    return `Diu ${String(p.total_hours_weekly).replace('.', ',')} h però l'horari suma ${textHores(p.minuts_reals)}.`
  }
  return ''
}

// ── Resum llegible de l'horari: «Dl-Dv 08:00–14:00», «Dl, Dc, Dv 08:00–14:00 · Dm, Dj 12:00–18:00» ──
const NOMS = ['Dg', 'Dl', 'Dm', 'Dc', 'Dj', 'Dv', 'Ds']
const ORDRE = [1, 2, 3, 4, 5, 6, 0]
function resum(trams) {
  const perText = {}
  for (const dia of ORDRE) {
    const t = trams[dia]
    if (!t) continue
    const text = t.map(([a, b]) => `${a}–${b}`).join(' i ')
    ;(perText[text] ||= []).push(dia)
  }
  const dies = llista => {
    const pos = llista.map(d => ORDRE.indexOf(d))
    const seguits = llista.length > 2 && pos.every((p, i) => i === 0 || p === pos[i - 1] + 1)
    return seguits ? `${NOMS[llista[0]]}-${NOMS[llista[llista.length - 1]]}` : llista.map(d => NOMS[d]).join(', ')
  }
  return Object.entries(perText).map(([text, ll]) => `${dies(ll)} ${text}`).join(' · ') || 'Sense cap dia amb horari'
}

const grups = computed(() => {
  const g = {}
  for (const p of plantilles.value) (g[p.signatura || `buida-${p.id}`] ||= []).push(p)
  return Object.entries(g).map(([signatura, ps]) => {
    // La que es queda per defecte: la que fa servir més gent (i, si empaten, la més antiga).
    const ordenades = [...ps].sort((a, b) => (b.persones_actives + b.persones_inactives) - (a.persones_actives + a.persones_inactives) || a.id - b.id)
    if (!queda.value[signatura]) queda.value[signatura] = ordenades[0].id
    return {
      signatura,
      plantilles: ordenades,
      resum: resum(ps[0].trams),
      minuts: ps[0].minuts_reals,
      persones: ps.reduce((s, p) => s + p.persones_actives, 0),
    }
  }).sort((a, b) => b.persones - a.persones || b.plantilles.length - a.plantilles.length)
})
const senseUs = computed(() => plantilles.value.filter(p => !usada(p)))
const sobrants = computed(() => grups.value.filter(g => !g.signatura.startsWith('buida-')).reduce((s, g) => s + g.plantilles.length - 1, 0))
const perRevisar = computed(() => plantilles.value.filter(p => avisHores(p)).length)
const grupsVisibles = computed(() => {
  if (filtre.value === 'repetides') return grups.value.filter(g => g.plantilles.length > 1 && !g.signatura.startsWith('buida-'))
  if (filtre.value === 'senseus') return grups.value.filter(g => g.plantilles.some(p => !usada(p)))
  if (filtre.value === 'revisar') return grups.value.filter(g => g.plantilles.some(p => avisHores(p)))
  return grups.value
})

async function fes(peticio, missatge) {
  treballant.value = true
  try {
    const r = await peticio()
    avisa(missatge(r))
    await carrega()
  } catch (e) {
    avisa(e?.message || "No s'ha pogut fer.", 'error')
  } finally {
    treballant.value = false
  }
}
async function esborraSenseUs() {
  const n = senseUs.value.length
  if (!await confirma(`Esborrar les ${n} plantilles que no fa servir ningú? No canvia l'horari de cap persona.`)) return
  fes(() => api.post('/v1/plantilles-horari/esborra-sense-us', {}), r => `✅ ${r.esborrades} plantilles esborrades.`)
}
async function esborra(p) {
  if (!await confirma(`Esborrar la plantilla «${p.name}»? No la fa servir ningú.`)) return
  fes(() => api.delete(`/v1/plantilles-horari/${p.id}`), () => '✅ Plantilla esborrada.')
}
async function ajunta(g) {
  const desti = g.plantilles.find(p => p.id === queda.value[g.signatura])
  const altres = g.plantilles.filter(p => p.id !== desti.id)
  const gent = altres.reduce((s, p) => s + p.persones_actives + p.persones_inactives, 0)
  if (!await confirma(`Deixar només «${desti.name}»? ${gent} persona${gent === 1 ? '' : 'es'} passaran a aquesta plantilla (mateix horari) i s'esborraran les altres ${altres.length}.`)) return
  fes(() => api.post(`/v1/plantilles-horari/${desti.id}/ajunta`, { ids: altres.map(p => p.id) }),
    r => `✅ Ajuntades: ${r.ajuntades} plantilles menys.`)
}
async function corregeixHores(p) {
  const h = Math.round(p.minuts_reals / 60 * 100) / 100
  if (!await confirma(`Posar ${textHores(p.minuts_reals)} a «${p.name}»? L'horari no canvia; només el número d'hores de la plantilla.`)) return
  fes(() => api.put(`/v1/work-schedules/${p.id}`, { total_hours_weekly: h }), () => '✅ Hores corregides.')
}
</script>

<style scoped>
.ph-buit { padding: 28px; text-align: center; }
.ph-resum { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 12px; }
.ph-xifra { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px 14px; border-radius: 12px; cursor: pointer; text-align: left;
  border: 1px solid var(--color-border, #e2e8f0); background: var(--color-surface, #fff); color: var(--color-text-secondary, #475569); font-size: .8rem; }
.ph-xifra strong { font-size: 1.5rem; color: var(--color-text, #1e293b); }
.ph-xifra.actiu { border-color: var(--color-primary, #094E8C); box-shadow: inset 0 0 0 1px var(--color-primary, #094E8C); }
.ph-accio-gran { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 14px; margin-bottom: 12px; background: #FFF7E6; border: 1px solid #F5C77E; }
.ph-grup { padding: 12px 14px; margin-bottom: 10px; }
.ph-grup-cap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
.ph-horari { font-weight: 700; font-size: .95rem; font-variant-numeric: tabular-nums; }
.ph-ajunta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; max-width: 100%; }
.ph-ajunta select { max-width: 280px; }
.ph-taula { width: 100%; border-collapse: collapse; font-size: .84rem; }
.ph-taula td { padding: 6px 4px; border-top: 1px solid var(--color-border-light, #eef2f7); vertical-align: top; }
.ph-nom { width: 50%; word-break: break-word; }
.ph-avis { margin-top: 2px; font-size: .74rem; color: #b45309; }
.ph-us { display: flex; flex-wrap: wrap; gap: 8px; }
.ph-botons { text-align: right; white-space: nowrap; }
@media (max-width: 640px) {
  .ph-nom { width: auto; }
  .ph-botons { white-space: normal; }
}
</style>
