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

    <div class="sf-cards">
      <router-link v-for="c in cuesOrdenades" :key="c.clau" :to="c.enllac"
        class="sf-card" :class="c.n > 0 ? c.urgencia : 'zero'">
        <div class="sf-ico">{{ c.icona }}</div>
        <div class="sf-body">
          <div class="sf-titol">{{ c.titol }} <span class="sf-n">{{ c.n }}</span></div>
          <div class="sf-sub">{{ c.sub }}</div>
          <span v-if="c.n > 0" class="sf-cta">Obrir i treballar ▸</span>
          <span v-else class="sf-sub ok">✓ res pendent</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'
import api from '../services/apiClient'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const carregant = ref(true)
const cues = ref([])
const total = computed(() => cues.value.reduce((s, c) => s + c.n, 0))
const PES = { alta: 0, mitjana: 1, baixa: 2 }
const cuesOrdenades = computed(() => [...cues.value].sort((a, b) => {
  if ((a.n > 0) !== (b.n > 0)) return a.n > 0 ? -1 : 1
  return (PES[a.urgencia] ?? 9) - (PES[b.urgencia] ?? 9)
}))

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
  const [fitxatges, foraZona, absencies, excedencies, suggeriments, conciliacio] = await Promise.all([
    n(db.getWorkLogs(), l => l.status === 'pending'),
    n(db.getWorkLogs(), l => l.hour_status === 'out_of_area' && l.status === 'pending'),
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
  cues.value = [
    { clau: 'fitxatges', titol: 'Fitxatges pendents d\'aprovar', sub: 'registres de jornada a validar', icona: '⏱', n: fitxatges, urgencia: 'mitjana', enllac: '/work-logs' },
    { clau: 'forazona', titol: 'Fitxatges fora de zona', sub: 'marca feta fora del domicili', icona: '📍', n: foraZona, urgencia: 'alta', enllac: '/work-logs' },
    { clau: 'absencies', titol: 'Permisos per aprovar', sub: 'sol·licituds d\'absència pendents', icona: '📋', n: absencies, urgencia: 'mitjana', enllac: '/absences' },
    { clau: 'excedencies', titol: 'Excedències per resoldre', sub: 'sol·licituds pendents', icona: '📄', n: excedencies, urgencia: 'mitjana', enllac: '/excedencies' },
    { clau: 'disciplinari', titol: 'Suggeriments disciplinaris', sub: 'el motor proposa; decideix una persona', icona: '⚖️', n: suggeriments, urgencia: 'alta', enllac: '/disciplinary' },
    { clau: 'conciliacio', titol: 'Comptes domi per conciliar', sub: 'identitats domi ↔ RRHH sense vincular', icona: '🔗', n: conciliacio, urgencia: 'baixa', enllac: '/conciliacio' },
    { clau: 'drets', titol: 'Drets RGPD per respondre', sub: 'sol·licituds amb termini d\'un mes (art. 12.3)', icona: '🔐', n: drets, urgencia: 'alta', enllac: '/privacy' },
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
.sf-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; margin-top: 14px; }
.sf-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #DCE4EE); border-left: 5px solid #cfd8e3; border-radius: 14px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; text-decoration: none; color: inherit; transition: box-shadow .15s; min-height: 56px; }
.sf-card:hover { box-shadow: 0 6px 18px rgba(10,42,74,.10); }
.sf-card.alta { border-left-color: #B3352F; }
.sf-card.mitjana { border-left-color: #D9A400; }
.sf-card.baixa { border-left-color: #0DAF83; }
.sf-card.zero { opacity: .5; }
.sf-ico { font-size: 1.6rem; line-height: 1; flex-shrink: 0; width: 36px; text-align: center; }
.sf-body { flex: 1; min-width: 0; }
.sf-titol { font-weight: 700; font-size: .98rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sf-sub { font-size: .78rem; color: var(--color-text-muted, #7a8aa0); margin-top: 3px; }
.sf-sub.ok { color: #0A7C5E; }
.sf-n { display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 24px; padding: 0 7px; border-radius: 99px; font-size: .86rem; font-weight: 800; color: #fff; background: #B3352F; font-variant-numeric: tabular-nums; }
.sf-card.mitjana .sf-n { background: #D9A400; }
.sf-card.baixa .sf-n { background: #0DAF83; }
.sf-card.zero .sf-n { background: #b6c2d1; }
.sf-cta { font-size: .76rem; color: var(--color-primary, #00806C); font-weight: 700; margin-top: 8px; display: inline-block; }
.sf-buida { background: #e4f5ee; border: 1px solid #b6e0cf; border-radius: 12px; padding: 26px; text-align: center; color: #0A6B50; font-size: 1.05rem; margin-top: 12px; }
@media (max-width: 600px) { .sf-cards { grid-template-columns: 1fr; } }
</style>
