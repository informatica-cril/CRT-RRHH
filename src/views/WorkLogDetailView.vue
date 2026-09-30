<template>
  <div class="work-log-detail">
    <div class="header">
      <button @click="$router.back()" class="back-btn">← Tornar</button>
      <h2>Detall de Fichatge</h2>
    </div>

    <div v-if="loading" class="loading">Carregant...</div>
    <div v-else-if="!workLog" class="error">No s'ha trobat el fichatge</div>
    <div v-else class="content">
      <!-- Info general del fichaje -->
      <div class="card info-card">
        <h3>Informació General</h3>
        <div class="info-grid">
          <div><strong>Treballador:</strong> {{ workLog.user?.name }}</div>
          <div><strong>Data:</strong> {{ formatDate(workLog.date) }}</div>
          <div><strong>Inici:</strong> {{ formatDateTime(workLog.start_time) }}</div>
          <div><strong>Fi:</strong> {{ workLog.end_time ? formatDateTime(workLog.end_time) : 'En curs' }}</div>
          <div><strong>Hores totals (brutes):</strong> {{ formatHM(workLog.total_hours_worked) }}</div>
          <div><strong>Minuts complementaris:</strong> {{ workLog.complementary_minutes || 0 }} min</div>
          <div><strong>Estat:</strong> {{ workLog.status }}</div>
          <div><strong>Segmentat:</strong> {{ workLog.segmented ? 'Sí' : 'No' }}</div>
        </div>
        <div class="effective-banner">
          <span class="effective-label">Temps de treball EFECTIU (només trams aprovats):</span>
          <span class="effective-value">{{ effectiveHoursLabel }}</span>
          <span v-if="workLog.segmented && pendingCount > 0" class="effective-note">
            · {{ pendingCount }} tram(s) pendent(s) de revisió no compten encara
          </span>
        </div>
      </div>

      <!-- Pausa obligatoria -->
      <div v-if="workLog.break_required" class="card break-card">
        <h3>Pausa Obligatòria</h3>
        <div class="info-grid">
          <div><strong>Estat:</strong> {{ workLog.break_status }}</div>
          <div v-if="workLog.break_start_time"><strong>Inici:</strong> {{ formatDateTime(workLog.break_start_time) }}</div>
          <div v-if="workLog.break_end_time"><strong>Fi:</strong> {{ formatDateTime(workLog.break_end_time) }}</div>
        </div>
      </div>

      <!-- Tramos segmentados -->
      <div class="card segments-card">
        <h3>Trams Segmentats ({{ segments.length }})</h3>
        <div v-if="segments.length === 0" class="empty">No hi ha trams segmentats</div>
        <div v-else class="segments-list">
          <div v-for="seg in segments" :key="seg.id" class="segment-item" :class="segmentClass(seg)">
            <div class="segment-header">
              <span class="segment-number">Tram {{ seg.segment_number }}</span>
              <span class="segment-status" :class="'status-' + seg.status">{{ seg.status }}</span>
            </div>
            <div class="segment-details">
              <div><strong>Inici:</strong> {{ formatDateTime(seg.start_time) }}</div>
              <div><strong>Fi:</strong> {{ formatDateTime(seg.end_time) }}</div>
              <div><strong>Duració:</strong> {{ seg.duration_minutes }} min</div>
              <div>
                <strong>Zona:</strong>
                <span :class="seg.in_zone ? 'tag-ok' : 'tag-ko'">{{ seg.in_zone ? 'Dins zona' : 'Fora zona' }}</span>
              </div>
              <div>
                <strong>Horari:</strong>
                <span :class="seg.in_schedule ? 'tag-ok' : 'tag-ko'">{{ seg.in_schedule ? 'Dins horari' : 'Fora horari' }}</span>
              </div>
              <!-- Transparència simètrica (C5 EIPD): el treballador veu la distància
                   mesurada i el radi aplicat en cada marcatge -->
              <div v-if="seg.home_verification">
                <strong>Domicili:</strong>
                <span :class="seg.home_verification === 'verificat' ? 'tag-ok' : (seg.home_verification === 'fora_radi' ? 'tag-ko' : '')">{{ seg.home_verification }}</span>
                <span v-if="seg.home_distance_m != null"> · {{ seg.home_distance_m }} m (radi {{ seg.home_radius_m ?? '—' }} m)</span>
              </div>
              <div v-if="seg.start_lat">
                <strong>Posició inici:</strong> {{ seg.start_lat }}, {{ seg.start_lng }}
              </div>
              <div v-if="seg.end_lat">
                <strong>Posició fi:</strong> {{ seg.end_lat }}, {{ seg.end_lng }}
              </div>
              <div v-if="seg.rejection_reason" class="rejection">
                <strong>Motiu rebuig:</strong> {{ seg.rejection_reason }}
              </div>
            </div>
            <!-- Audiència prèvia (EIPD C6) -->
            <div v-if="seg.audiencia_requested_at" class="audiencia-box">
              <div class="text-small">
                🗣️ <strong>Audiència prèvia</strong> oberta el {{ formatDateTime(seg.audiencia_requested_at) }}
                · termini d'al·legacions: <strong>{{ formatDate(seg.audiencia_deadline) }}</strong>
              </div>
              <div v-if="seg.allegation" class="text-small" style="margin-top:6px;">
                <strong>Al·legació del treballador</strong> ({{ formatDateTime(seg.allegation_at) }}):
                <em>«{{ seg.allegation }}»</em>
              </div>
              <div v-else class="text-small text-muted" style="margin-top:4px;">Sense al·legació presentada encara.</div>
            </div>
            <!-- Al·legació del TITULAR (treballador) sobre tram pendent -->
            <div v-if="esTitular && seg.status === 'pending'" class="allegation-form">
              <textarea v-model="allegationText[seg.id]" rows="2" class="form-input"
                placeholder="La teva explicació (p. ex. error del GPS a l'interior, canvi de domicili del pacient...)"></textarea>
              <button class="btn-approve" @click="sendAllegation(seg)"
                :disabled="(allegationText[seg.id] || '').trim().length < 20">Presentar al·legació (mín. 20 caràcters)</button>
            </div>
            <!-- Acciones para coordinator/admin -->
            <div v-if="canManage && seg.status === 'pending'" class="segment-actions">
              <button @click="approveSegment(seg)" class="btn-approve">Aprovar</button>
              <div class="reject-form">
                <select v-model="rejectFault[seg.id]" class="form-input">
                  <option value="">— Qualificar el fet segons el conveni (art. 64) —</option>
                  <optgroup v-for="g in faultsByGrau" :key="g.grau" :label="g.label">
                    <option v-for="ft in g.items" :key="ft.clau" :value="ft.clau">{{ ft.base_conveni }} · {{ ft.descripcio }}</option>
                  </optgroup>
                </select>
                <textarea v-model="rejectReason[seg.id]" rows="2" class="form-input" placeholder="Motiu del rebuig (mínim 5 caràcters)"></textarea>
                <div v-if="(rejectReason[seg.id] || '').trim().length > 0 && (rejectReason[seg.id] || '').trim().length < 5"
                  class="reject-hint">Falten {{ 5 - (rejectReason[seg.id] || '').trim().length }} caràcters més al motiu.</div>
                <button @click="rejectSegment(seg)" class="btn-reject"
                  :disabled="!rejectFault[seg.id] || (rejectReason[seg.id] || '').trim().length < 5">Rebutjar i qualificar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Trazabilidad -->
      <div class="card modifications-card">
        <h3>Traçabilitat ({{ modifications.length }})</h3>
        <div v-if="modifications.length === 0" class="empty">No hi ha modificacions registrades</div>
        <div v-else class="modifications-list">
          <div v-for="mod in modifications" :key="mod.id" class="modification-item">
            <span class="mod-action">{{ mod.action }}</span>
            <span class="mod-user">{{ mod.user?.name }}</span>
            <span class="mod-date">{{ formatDateTime(mod.created_at) }}</span>
            <span v-if="mod.comment" class="mod-comment">{{ mod.comment }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import db from '../services/db'
import { formatHM } from '../utils/formatHours'

const route = useRoute()
const authStore = useAuthStore()
const workLog = ref(null)
const segments = ref([])
const modifications = ref([])
const loading = ref(true)
const faultTypes = ref([])
const rejectFault = ref({})
const rejectReason = ref({})

const canManage = computed(() => ['admin', 'hr', 'coordinator'].includes(authStore.user?.role))

// Catàleg de faltes del conveni (art. 64) agrupat per gravetat, per qualificar el rebuig.
const GRAU_ORDRE = { lleu: 1, menys_greu: 2, greu: 3, molt_greu: 4 }
const GRAU_LABEL = { lleu: 'Lleus', menys_greu: 'Menys greus', greu: 'Greus', molt_greu: 'Molt greus' }
const faultsByGrau = computed(() => {
  const g = {}
  for (const ft of faultTypes.value) (g[ft.grau_base] = g[ft.grau_base] || []).push(ft)
  return Object.keys(g).sort((a, b) => (GRAU_ORDRE[a] || 9) - (GRAU_ORDRE[b] || 9))
    .map(k => ({ grau: k, label: GRAU_LABEL[k] || k, items: g[k] }))
})

// Tiempo efectivo: si está segmentado, solo suma tramos aprobados (reactivo en vivo).
// Si no, usa el valor calculado en backend (effective_hours) o las horas trabajadas.
const effectiveHours = computed(() => {
  if (!workLog.value) return '0.00'
  if (workLog.value.segmented) {
    const mins = segments.value
      .filter(s => s.status === 'approved')
      .reduce((sum, s) => sum + (Number(s.duration_minutes) || 0), 0)
    return (mins / 60).toFixed(2)
  }
  const val = workLog.value.effective_hours ?? workLog.value.hours_worked ?? workLog.value.total_hours_worked ?? 0
  return Number(val).toFixed(2)
})

const effectiveHoursLabel = computed(() => formatHM(effectiveHours.value))

const pendingCount = computed(() =>
  segments.value.filter(s => s.status === 'pending').length
)

async function loadData() {
  const id = route.params.id
  try {
    const detail = await db.getWorkLogDetail(id)
    workLog.value = detail
    segments.value = detail.segments || []
    modifications.value = detail.modifications || []
    if (canManage.value) {
      try { faultTypes.value = (await db.disciplinaryFaultTypes()) || [] } catch (e) { /* catàleg opcional */ }
    }
  } catch (e) {
    console.error('Error loading work log detail:', e)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ca-ES')
}

function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ca-ES', { dateStyle: 'short', timeStyle: 'medium' })
}

function segmentClass(seg) {
  if (seg.status === 'approved') return 'segment-approved'
  if (seg.status === 'rejected') return 'segment-rejected'
  if (!seg.in_zone || !seg.in_schedule) return 'segment-warning'
  return ''
}

async function approveSegment(seg) {
  try {
    await db.approveWorkLogSegment(route.params.id, seg.id)
    seg.status = 'approved'
  } catch (e) { console.error(e) }
}

async function rejectSegment(seg) {
  const faultClau = rejectFault.value[seg.id]
  const reason = (rejectReason.value[seg.id] || '').trim()
  if (!faultClau) { alert('Cal qualificar el fet segons el conveni (art. 64) abans de rebutjar.'); return }
  if (reason.length < 5) { alert('Cal indicar el motiu del rebuig.'); return }
  try {
    await db.rejectWorkLogSegment(route.params.id, seg.id, reason, faultClau)
    await loadData()   // refresca estat + temps efectiu; l'evidència queda a la safata de RRHH
  } catch (e) {
    // Audiència prèvia (EIPD C6): el servidor pot denegar el rebuig i obrir audiència —
    // el missatge (422) s'ha de veure, no silenciar-se
    const msg = e?.message || e?.response?.data?.message || 'Error'
    alert(msg)
    await loadData()
  }
}

// ── Audiència prèvia: al·legació del titular ──
const esTitular = computed(() => authStore.user?.id === workLog.value?.user_id)
const allegationText = ref({})

async function sendAllegation(seg) {
  const text = (allegationText.value[seg.id] || '').trim()
  if (!text) return
  try {
    await db.sendSegmentAllegation(route.params.id, seg.id, text)
    allegationText.value[seg.id] = ''
    await loadData()
  } catch (e) {
    alert(e?.response?.data?.message || 'No s\'ha pogut enviar l\'al·legació')
  }
}
</script>

<style scoped>
.work-log-detail { padding: 20px; max-width: 900px; margin: 0 auto; }
.header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.back-btn { padding: 8px 16px; border: none; background: #e0e0e0; border-radius: 6px; cursor: pointer; }
.card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.effective-banner { margin-top: 14px; padding: 12px 16px; background: #eef7ee; border: 1px solid #4caf50; border-radius: 8px; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.effective-label { font-weight: 600; color: #2e5d2e; }
.effective-value { font-size: 1.4em; font-weight: 700; color: #2e7d32; }
.effective-note { font-size: 0.85em; color: #8a6d00; }
.segment-item { border: 1px solid #e0e0e0; border-radius: 8px; padding: 12px; margin-bottom: 8px; }
.segment-approved { border-color: #4caf50; }
.segment-rejected { border-color: #f44336; }
.segment-warning { border-color: #ff9800; }
.tag-ok { color: #4caf50; font-weight: bold; }
.tag-ko { color: #f44336; font-weight: bold; }
.segment-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
.segment-status { padding: 2px 8px; border-radius: 4px; font-size: 0.85em; }
.status-pending { background: #fff3cd; }
.status-approved { background: #d4edda; }
.status-rejected { background: #f8d7da; }
.audiencia-box { background: #fff8e6; border-left: 3px solid #f0a500; border-radius: 6px; padding: 10px 12px; margin-top: 8px; }
.allegation-form { display: flex; gap: 8px; margin-top: 8px; align-items: flex-start; }
.allegation-form textarea { flex: 1; }
.segment-actions { display: flex; gap: 8px; margin-top: 8px; }
.btn-approve { background: #4caf50; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
.btn-reject { background: #f44336; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
.reject-hint { color: #ff9800; font-size: 0.82em; margin-top: -4px; }
.modification-item { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9em; }
.empty { color: #999; padding: 12px; }
.loading, .error { text-align: center; padding: 40px; color: #999; }
</style>
