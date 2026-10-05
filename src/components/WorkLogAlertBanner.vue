<template>
  <div v-if="alerts.length > 0" class="alert-banner">
    <div v-for="alert in alerts" :key="alert.id" class="alert-item" :class="'alert-' + alert.type">
      <span class="alert-icon">{{ iconFor(alert.type) }}</span>
      <span class="alert-message">{{ alert.message || defaultMessage(alert.type) }}</span>
      <router-link v-if="['audiencia','segment_rejected'].includes(alert.type) && alert.work_log_id"
        :to="`/work-logs/${alert.work_log_id}/detail`" class="alert-cta">Obrir el detall i respondre ▸</router-link>
      <button @click="dismiss(alert)" class="alert-dismiss">✕</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import db from '../services/db'

const authStore = useAuthStore()
const alerts = ref([])

onMounted(async () => {
  await loadAlerts()
  // Poll cada 60 segundos
  setInterval(loadAlerts, 60000)
})

async function loadAlerts() {
  if (!authStore.userId) return
  try {
    alerts.value = await db.getPendingAlerts(authStore.userId)
  } catch (e) {
    // Silencioso
  }
}

async function dismiss(alert) {
  try {
    await db.dismissAlert(alert.id)
    alerts.value = alerts.value.filter(a => a.id !== alert.id)
  } catch (e) { console.error(e) }
}

function iconFor(type) {
  const icons = {
    no_clock_in: '⏰', no_clock_out: '🚪', break_required: '☕', audiencia: '🗣️',
    segment_rejected: '⚠️', out_of_zone: '📍', resolucio_rrhh: '📬'
  }
  return icons[type] || '🔔'
}

function defaultMessage(type) {
  const msgs = {
    no_clock_in: 'No has fitxat l\'entrada avui.',
    no_clock_out: 'Tens un fitxatge sense tancar.',
    break_required: 'Has de fer la pausa obligatòria.',
    audiencia: 'Tens un marcatge pendent de revisió: pots presentar la teva explicació des del detall del fichatge.',
    segment_rejected: 'Un tram del teu fitxatge ha estat rebutjat.',
    out_of_zone: 'S\'ha detectat fitxatge fora de zona.',
    resolucio_rrhh: 'Tens una sol·licitud resolta.'
  }
  return msgs[type] || 'Tens una alerta pendent.'
}
</script>

<style scoped>
.alert-banner {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
}
.alert-item {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; color: white; font-size: 0.9em;
  animation: slideDown 0.3s ease;
}
.alert-no_clock_in, .alert-no_clock_out { background: #f44336; }
.alert-break_required { background: #ff9800; }
.alert-audiencia { background: #3f51b5; }
.alert-segment_rejected { background: #9c27b0; }
.alert-out_of_zone { background: #e91e63; }
.alert-resolucio_rrhh { background: #00806C; }
.alert-icon { font-size: 1.2em; }
.alert-message { flex: 1; }
.alert-cta {
  font-weight: 700;
  font-size: 0.82rem;
  white-space: nowrap;
  text-decoration: underline;
  color: inherit;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
}
.alert-dismiss {
  background: rgba(255,255,255,0.2); border: none; color: white;
  width: 24px; height: 24px; border-radius: 50%; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
</style>
