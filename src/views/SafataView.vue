<template>
  <div class="safata">
    <div class="sf-head">
      <div>
        <h1 class="page-title">📥 Safata de pendents</h1>
        <p class="page-subtitle">Tot el que espera una decisió teva, per urgència. Cada targeta obre la pantalla on es resol.</p>
      </div>
      <span class="sf-tot"><b>{{ total }}</b> pendents</span>
    </div>

    <div v-if="carregant" class="sf-buida">Calculant els pendents…</div>
    <div v-else-if="total === 0" class="sf-buida ok">✓ Res pendent. Bona feina.</div>

    <!-- Només el que té feina; el que és al dia queda en una línia discreta a sota. -->
    <div v-if="!carregant && actives.length" class="sf-cards">
      <router-link v-for="c in actives" :key="c.clau" :to="c.enllac"
        class="sf-card" :class="c.urgencia" @click="c.clau === 'sensesortida' && baixaALaLlista()">
        <div class="sf-ico">{{ c.icona }}</div>
        <div class="sf-body">
          <div class="sf-sub sf-urg">{{ URGENCIA[c.urgencia] }}</div>
          <div class="sf-titol">{{ c.titol }}</div>
          <div class="sf-sub">{{ c.sub }}</div>
          <span class="sf-cta">{{ c.clau === 'sensesortida' ? 'Veure la llista' : 'Obrir i resoldre' }} →</span>
        </div>
        <span class="sf-n">{{ c.n }}</span>
      </router-link>
    </div>

    <div v-if="!carregant && alDia.length && actives.length" class="sf-aldia">
      <span class="sf-aldia-t">✓ Al dia:</span>
      <router-link v-for="c in alDia" :key="c.clau" :to="c.enllac" class="sf-xip">{{ c.icona }} {{ c.titol }}</router-link>
    </div>

    <JornadesSenseSortida />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'
import api from '../services/apiClient'
import { useAuthStore } from '../stores/auth'
import JornadesSenseSortida from '../components/JornadesSenseSortida.vue'

// La llista de jornades sense sortida ja és a sota de les targetes: la targeta només hi baixa.
function baixaALaLlista() {
  setTimeout(() => document.getElementById('sense-sortida')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
}

const authStore = useAuthStore()
const carregant = ref(true)
const cues = ref([])
const total = computed(() => cues.value.reduce((s, c) => s + c.n, 0))
const PES = { alta: 0, mitjana: 1, baixa: 2 }
const URGENCIA = { alta: 'Urgent', mitjana: 'Aquesta setmana', baixa: 'Quan es pugui' }
const actives = computed(() => cues.value.filter(c => c.n > 0)
  .sort((a, b) => (PES[a.urgencia] ?? 9) - (PES[b.urgencia] ?? 9) || b.n - a.n))
const alDia = computed(() => cues.value.filter(c => c.n === 0))

// Compta amb tancament segur: si una cua peta, val 0 i la resta segueix.
async function n(promise, filtre) {
  try {
    const r = await promise
    const arr = Array.isArray(r) ? r : (r?.data ?? [])
    return filtre ? arr.filter(filtre).length : arr.length
  } catch (e) { return 0 }
}

onMounted(async () => {
  const esAdmin = authStore.user?.role === 'admin'
  // Fitxatges: recompte al servidor. Descarregar-los per comptar-los només en portava 500 i, amb
  // l'històric importat, deixava fora els pendents de mesos anteriors.
  const resum = await api.get('/v1/safata/resum').catch(() => null)
  const nCua = (clau) => (resum?.cues || []).find(c => c.clau === clau)?.n || 0
  const fitxatges = nCua('fitxatges')
  const foraZona = nCua('forazona')
  const senseSortida = nCua('sensesortida')
  const [absencies, excedencies, suggeriments, conciliacio] = await Promise.all([
    n(db.getAbsences(), a => a.approved === null || a.approved === undefined),
    n(db.getExcedencias(), e => e.status === 'pending'),
    n(db.disciplinarySuggestions()),
    esAdmin || authStore.user?.role === 'hr' ? n(db.getConciliacio()) : Promise.resolve(0),
  ])
  // Drets RGPD: només els veu qui els pot resoldre. Urgència ALTA perquè el termini de l'art. 12.3
  // (un mes) corre des que la persona la presenta, no des que algú se n'assabenta.
  const drets = (esAdmin || authStore.user?.role === 'hr')
    ? await n(api.get('/v1/privacy/requests?pendents=1'))
    : 0
  const comite = (esAdmin || authStore.user?.role === 'hr')
    ? await n(api.get('/v1/comite/hores?estat=pendent'))
    : 0
  cues.value = [
    { clau: 'fitxatges', titol: 'Fitxatges pendents d\'aprovar', sub: 'registres de jornada a validar', icona: '⏱', n: fitxatges, urgencia: 'mitjana', enllac: '/work-logs?vista=pendents' },
    { clau: 'forazona', titol: 'Fitxatges fora de zona', sub: 'marca feta fora de la zona o el centre assignat', icona: '📍', n: foraZona, urgencia: 'alta', enllac: '/work-logs?vista=forazona' },
    { clau: 'sensesortida', titol: 'Jornades sense sortida', sub: "fitxatges d'altres dies sense sortida: llista a sota", icona: '🚪', n: senseSortida, urgencia: 'alta', enllac: '/safata#sense-sortida' },
    { clau: 'absencies', titol: 'Permisos per aprovar', sub: 'sol·licituds d\'absència pendents', icona: '📋', n: absencies, urgencia: 'mitjana', enllac: '/absences' },
    { clau: 'excedencies', titol: 'Excedències per resoldre', sub: 'sol·licituds pendents', icona: '📄', n: excedencies, urgencia: 'mitjana', enllac: '/excedencies' },
    { clau: 'disciplinari', titol: 'Suggeriments disciplinaris', sub: 'el motor proposa; decideix una persona', icona: '⚖️', n: suggeriments, urgencia: 'alta', enllac: '/disciplinary' },
    { clau: 'conciliacio', titol: 'Comptes domi per conciliar', sub: 'identitats domi ↔ RRHH sense vincular', icona: '🔗', n: conciliacio, urgencia: 'baixa', enllac: '/conciliacio' },
    { clau: 'drets', titol: 'Drets RGPD per respondre', sub: 'sol·licituds amb termini d\'un mes (art. 12.3)', icona: '🔐', n: drets, urgencia: 'alta', enllac: '/privacy' },
    { clau: 'comite', titol: 'Hores de comitè per validar', sub: 'crèdit horari de la representació (art. 68 ET)', icona: '🤝', n: comite, urgencia: 'mitjana', enllac: '/comite' },
  ]
  carregant.value = false
})
</script>

<style scoped>
.safata { padding: 4px 0; }
.sf-head { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; }
.sf-head > div { flex: 1; }
.sf-tot { background: var(--color-navy, #0A2A4A); color: #fff; border-radius: 12px; padding: 10px 18px; font-weight: 800; white-space: nowrap; }
.sf-tot b { font-size: 1.5rem; margin-right: 6px; font-variant-numeric: tabular-nums; }
.sf-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; margin-top: 16px; }
.sf-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #DCE4EE); border-top: 4px solid #cfd8e3; border-radius: 14px; padding: 16px 18px; display: flex; gap: 14px; align-items: flex-start; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; height: 100%; box-sizing: border-box; }
.sf-card:hover { box-shadow: 0 8px 22px rgba(10,42,74,.12); transform: translateY(-1px); }
.sf-card.alta { border-top-color: #B3352F; }
.sf-card.mitjana { border-top-color: #D9A400; }
.sf-card.baixa { border-top-color: #0DAF83; }
.sf-ico { font-size: 1.5rem; line-height: 1; flex-shrink: 0; width: 32px; text-align: center; margin-top: 2px; }
.sf-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.sf-urg { text-transform: uppercase; letter-spacing: .04em; font-weight: 700; font-size: .68rem; margin: 0 0 2px; }
.sf-card.alta .sf-urg { color: #B3352F; }
.sf-card.mitjana .sf-urg { color: #9A7400; }
.sf-card.baixa .sf-urg { color: #0A7C5E; }
.sf-titol { font-weight: 700; font-size: 1rem; line-height: 1.3; }
.sf-sub { font-size: .8rem; color: var(--color-text-muted, #7a8aa0); margin-top: 3px; }
.sf-n { flex-shrink: 0; font-size: 1.9rem; font-weight: 800; line-height: 1; color: #B3352F; font-variant-numeric: tabular-nums; }
.sf-card.mitjana .sf-n { color: #9A7400; }
.sf-card.baixa .sf-n { color: #0A7C5E; }
.sf-cta { font-size: .8rem; color: var(--color-primary, #00806C); font-weight: 700; margin-top: auto; padding-top: 10px; }
.sf-aldia { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 16px; padding: 10px 14px; background: var(--color-surface, #fff); border: 1px dashed var(--color-border, #DCE4EE); border-radius: 12px; }
.sf-aldia-t { font-size: .82rem; font-weight: 700; color: #0A7C5E; margin-right: 4px; }
.sf-xip { font-size: .78rem; color: var(--color-text-muted, #5f6f86); text-decoration: none; padding: 3px 10px; border-radius: 99px; background: var(--color-bg, #f3f6fa); }
.sf-xip:hover { color: var(--color-primary, #00806C); }
.sf-buida { background: #e4f5ee; border: 1px solid #b6e0cf; border-radius: 12px; padding: 26px; text-align: center; color: #0A6B50; font-size: 1.05rem; margin-top: 12px; }
@media (max-width: 600px) { .sf-cards { grid-template-columns: 1fr; } }
</style>
