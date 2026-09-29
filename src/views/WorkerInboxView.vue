<template>
  <div class="safata">
    <div class="sf-head">
      <div>
        <h1 class="page-title">📥 La meva safata</h1>
        <p class="page-subtitle">Tot el que et reclama una acció: firmes, avisos i notificacions. Cada targeta t'hi porta.</p>
      </div>
      <span class="sf-tot"><b>{{ total }}</b> pendents</span>
    </div>

    <div v-if="carregant" class="sf-buida">Calculant els pendents…</div>
    <div v-else-if="total === 0" class="sf-buida ok">✓ No tens res pendent. Tot en ordre.</div>

    <div class="sf-cards">
      <router-link v-for="c in cuesOrdenades" :key="c.clau" :to="c.enllac"
        class="sf-card" :class="c.n > 0 ? c.urgencia : 'zero'">
        <div class="sf-ico">{{ c.icona }}</div>
        <div class="sf-body">
          <div class="sf-titol">{{ c.titol }} <span class="sf-n">{{ c.n }}</span></div>
          <div class="sf-sub">{{ c.sub }}</div>
          <span v-if="c.n > 0" class="sf-cta">Obrir ▸</span>
          <span v-else class="sf-sub ok">✓ res pendent</span>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const carregant = ref(true)
const cues = ref([])
const audienciaLink = ref('/worker/history')
const total = computed(() => cues.value.reduce((s, c) => s + c.n, 0))
const PES = { alta: 0, mitjana: 1, baixa: 2 }
const cuesOrdenades = computed(() => [...cues.value].sort((a, b) => {
  if ((a.n > 0) !== (b.n > 0)) return a.n > 0 ? -1 : 1
  return (PES[a.urgencia] ?? 9) - (PES[b.urgencia] ?? 9)
}))

async function arr(promise) {
  try { const r = await promise; return Array.isArray(r) ? r : (r?.data ?? []) } catch (e) { return [] }
}

onMounted(async () => {
  const uid = authStore.userId
  const [alertes, docs, nomines, discip] = await Promise.all([
    arr(db.getPendingAlerts(uid)),
    arr(db.getDocumentsForUser(uid)),
    arr(db.getWorkerPayrolls(uid)),
    arr(db.getMyDisciplinaryNotifications()),
  ])

  // Audiència prèvia: si hi ha una alerta amb el tram, guardem l'enllaç directe al detall
  const audi = alertes.find(a => ['audiencia', 'segment_rejected'].includes(a.type) && a.work_log_id)
  if (audi) audienciaLink.value = `/work-logs/${audi.work_log_id}/detail`
  const nAudi = alertes.filter(a => ['audiencia', 'segment_rejected'].includes(a.type)).length
  // Les resolucions de RRHH (absències, permisos, hores) tenen targeta pròpia: no són
  // avisos del fitxatge i barrejar-les hi amagava la notícia.
  const nResolucions = alertes.filter(a => a.type === 'resolucio_rrhh').length
  const nAvisos = alertes.filter(a => !['audiencia', 'segment_rejected', 'resolucio_rrhh'].includes(a.type)).length
  const nDocs = docs.filter(d => (d.requires_signature || d.pending_signature) && !d.signed_at && !d.signed).length
  const nNomines = nomines.filter(n => !n.signed_at && !n.viewed_signed && !n.signed).length
  const nDiscip = discip.filter(d => !d.acknowledged_at && !d.acknowledged).length

  cues.value = [
    { clau: 'audiencia', titol: 'Audiència prèvia oberta', sub: 'un tram del teu fitxatge; pots presentar la teva explicació', icona: '🗣️', n: nAudi, urgencia: 'alta', enllac: audienciaLink.value },
    { clau: 'disciplinari', titol: 'Notificació a acusar', sub: 'comunicació que has de donar per rebuda', icona: '⚖️', n: nDiscip, urgencia: 'alta', enllac: '/worker/compliance' },
    { clau: 'resolucions', titol: 'Sol·licituds resoltes', sub: 'absències, permisos o hores que RRHH ha resolt', icona: '📬', n: nResolucions, urgencia: 'mitjana', enllac: '/absences' },
    { clau: 'firmes', titol: 'Documents per signar', sub: 'documents pendents de la teva firma', icona: '✍️', n: nDocs, urgencia: 'mitjana', enllac: '/worker/documents' },
    { clau: 'nomines', titol: 'Nòmines per signar recepció', sub: 'confirma que les has rebut', icona: '💶', n: nNomines, urgencia: 'mitjana', enllac: '/worker/payrolls' },
    { clau: 'avisos', titol: 'Avisos del fitxatge', sub: 'entrades/sortides o pauses per revisar', icona: '⏰', n: nAvisos, urgencia: 'baixa', enllac: '/worker' },
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
.sf-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; margin-top: 14px; }
.sf-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #DCE4EE); border-left: 5px solid #cfd8e3; border-radius: 14px; padding: 16px; display: flex; gap: 14px; align-items: flex-start; text-decoration: none; color: inherit; transition: box-shadow .15s; min-height: 60px; }
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
