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
          <div><strong>Estat:</strong> {{ etiqueta('fitxatge', workLog.status) }}</div>
          <div><strong>Segmentat:</strong> {{ workLog.segmented ? 'Sí' : 'No' }}</div>
        </div>
        <div class="effective-banner">
          <span class="effective-label">Temps de treball EFECTIU (només trams aprovats):</span>
          <span class="effective-value">{{ effectiveHoursLabel }}</span>
          <span v-if="workLog.segmented && pendingCount > 0" class="effective-note">
            · {{ pendingCount }} tram(s) pendent(s) de revisió no compten encara
          </span>
        </div>

        <!-- «Corregir hora»: RRHH i admin (p. ex. una sortida oblidada fitxada l'endemà) -->
        <div v-if="potReobrir" class="correccio">
          <button v-if="!correccio.obert" class="btn btn-outline btn-sm" @click="obreCorreccio">🕒 Corregir hora</button>
          <div v-else class="correccio-form">
            <div class="correccio-camps">
              <label>Entrada <input type="datetime-local" v-model="correccio.inici" class="form-input" /></label>
              <label>Sortida <input type="datetime-local" v-model="correccio.fi" class="form-input" /></label>
            </div>
            <textarea v-model="correccio.motiu" rows="2" class="form-input"
              placeholder="Motiu de la correcció (mínim 10 caràcters). Quedarà registrat a la traçabilitat."></textarea>
            <div v-if="correccio.error" class="correccio-error">{{ correccio.error }}</div>
            <div class="text-small text-muted">Les hores i els trams es tornen a calcular, i el fitxatge torna a pendent per validar-lo.</div>
            <div class="correccio-botons">
              <button class="btn btn-primary btn-sm" :disabled="correccio.desant || correccio.motiu.trim().length < 10" @click="desaCorreccio">
                {{ correccio.desant ? 'Desant…' : 'Desar correcció' }}
              </button>
              <button class="btn btn-outline btn-sm" :disabled="correccio.desant" @click="correccio.obert = false">Cancel·lar</button>
            </div>
          </div>
        </div>

        <!-- «Corregir zona»: era en un centre que no tenia assignat, o el GPS va fallar -->
        <div v-if="potReobrir && marquesFora.length" class="correccio">
          <button v-if="!zona.obert" class="btn btn-outline btn-sm" @click="obreZona">📍 Corregir zona</button>
          <div v-else class="correccio-form">
            <div class="text-small"><strong>Quines marques es van fer dins de zona?</strong></div>
            <div class="correccio-camps">
              <label v-for="m in marquesFora" :key="m.clau" class="zona-marca">
                <input type="checkbox" :value="m.clau" v-model="zona.marques" /> {{ m.nom }}
              </label>
            </div>
            <label class="text-small">On era
              <select v-model="zona.centre" class="form-select">
                <option value="">— Sense indicar centre —</option>
                <option v-for="c in zona.centres" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            <textarea v-model="zona.motiu" rows="2" class="form-input"
              placeholder="Motiu (mínim 10 caràcters). P. ex.: era a Viladomat, centre que encara no tenia assignat."></textarea>
            <div v-if="zona.error" class="correccio-error">{{ zona.error }}</div>
            <div class="text-small text-muted">Les marques triades passen a dins de zona i es recalculen els trams, les hores fora de zona i el temps efectiu.</div>
            <div class="correccio-botons">
              <button class="btn btn-primary btn-sm" :disabled="zona.desant || !zona.marques.length || zona.motiu.trim().length < 10" @click="desaZona">
                {{ zona.desant ? 'Desant…' : 'Desar correcció' }}
              </button>
              <button class="btn btn-outline btn-sm" :disabled="zona.desant" @click="zona.obert = false">Cancel·lar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pausa obligatoria -->
      <div v-if="workLog.break_required" class="card break-card">
        <div class="break-cap">
          <h3>☕ Pausa obligatòria</h3>
          <span class="break-estat" :class="'pausa-' + (pausaNoFeta ? 'skipped' : (workLog.break_status || 'pending'))">{{ pausaNoFeta ? '⛔ No feta' : iconaPausa + ' ' + etiqueta('pausa', workLog.break_status || 'pending') }}</span>
        </div>
        <div class="break-linia">
          <div class="break-dada"><span class="break-et">Inici</span><strong>{{ workLog.break_start_time ? horaDe(workLog.break_start_time) : '—' }}</strong></div>
          <div class="break-fletxa">→</div>
          <div class="break-dada"><span class="break-et">Fi</span><strong>{{ workLog.break_end_time ? horaDe(workLog.break_end_time) : (workLog.break_status === 'active' ? 'en curs' : '—') }}</strong></div>
          <div class="break-dada break-durada"><span class="break-et">Durada</span><strong>{{ duradaPausa ?? '—' }}</strong></div>
        </div>
        <div v-if="pausaNoFeta" class="break-avis">
          La jornada es va tancar sense fer la pausa obligatòria: ni la persona la va iniciar ni la va obrir el servidor.
        </div>
        <div v-if="pausaIncoherent" class="break-avis">
          ⚠ L'hora de fi és anterior a la d'inici: aquest registre de pausa no és coherent i cal corregir-lo.
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
              <span class="segment-status" :class="'status-' + seg.status">{{ etiqueta('tram', seg.status) }}</span>
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
                <span :class="seg.home_verification === 'verificat' ? 'tag-ok' : (seg.home_verification === 'fora_radi' ? 'tag-ko' : '')">{{ etiqueta('verificacio', seg.home_verification) }}</span>
                <span v-if="seg.home_distance_m != null"> · {{ seg.home_distance_m }} m (radi {{ seg.home_radius_m ?? '—' }} m)</span>
              </div>
              <!-- Zona i CP en lloc de les coordenades (calculat al navegador, sense cap servei extern);
                   les coordenades queden al títol per a qui ja les podia veure. -->
              <template v-if="coordsVisibles">
                <div>
                  <strong>Posició inici:</strong>
                  <span v-if="posicio(seg, 'inici')" :title="posicio(seg, 'inici').join(', ')">📍 {{ zonaDePosicio(...posicio(seg, 'inici'))?.text || '—' }}</span>
                  <span v-else class="text-muted">Sense marca GPS (canvi de tram)</span>
                </div>
                <div>
                  <strong>Posició fi:</strong>
                  <span v-if="posicio(seg, 'fi')" :title="posicio(seg, 'fi').join(', ')">📍 {{ zonaDePosicio(...posicio(seg, 'fi'))?.text || '—' }}</span>
                  <span v-else class="text-muted">Sense marca GPS (canvi de tram)</span>
                </div>
              </template>
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
            <!-- «Revisar de nou»: RRHH i admin tornen a pendent un tram ja resolt -->
            <div v-if="potReobrir && seg.status !== 'pending'" class="segment-actions">
              <button @click="reobrirTram(seg)" class="btn btn-outline btn-sm" title="Torna el tram a pendent per validar-lo de nou">↺ Revisar de nou</button>
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
            <span class="mod-action">{{ etiqueta('modificacio', mod.action) }}</span>
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
import { confirma, demana } from '../utils/dialegs'
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import db from '../services/db'
import api from '../services/apiClient'
import { formatHM } from '../utils/formatHours'
import { etiqueta } from '../utils/etiquetes'
import { zonaDePosicio } from '../utils/zonaDePosicio'

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

// ── Pausa ── (break_start/end en hora de Madrid sense zona: l'hora es llegeix del text)
const horaDe = (ts) => String(ts).slice(11, 19)
const minutsPausa = computed(() => {
  const a = workLog.value?.break_start_time, b = workLog.value?.break_end_time
  if (!a || !b) return null
  return Math.round((new Date(String(b).replace(' ', 'T')) - new Date(String(a).replace(' ', 'T'))) / 60000)
})
const pausaIncoherent = computed(() => minutsPausa.value !== null && minutsPausa.value < 0)
const duradaPausa = computed(() => {
  const m = minutsPausa.value
  if (m === null || m < 0) return null
  return m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`
})
// Jornada tancada amb la pausa encara «pendent»: ja no es pot fer, no és «pendent de fer».
const pausaNoFeta = computed(() => !!workLog.value?.end_time && !workLog.value?.break_start_time
  && (!workLog.value?.break_status || workLog.value.break_status === 'pending'))
const iconaPausa = computed(() => ({ completed: '✅', active: '⏳', skipped: '⛔' }[workLog.value?.break_status] || '🕒'))

// Les coordenades només arriben si qui mira les pot veure (EIPD §6.3); si no hi són, no es diu res.
const coordsVisibles = computed(() => !!workLog.value && 'start_location_lat' in workLog.value)
// Posició d'inici o fi d'un tram. Si el tram no en té però comença amb l'entrada (o acaba amb la
// sortida), és la del fitxatge: abans els trams dins d'horari es creaven sense cap posició.
const mateixMoment = (a, b) => !!a && !!b && String(a).slice(0, 19) === String(b).slice(0, 19)
function posicio(seg, quina) {
  const w = workLog.value || {}
  if (quina === 'inici') {
    if (seg.start_lat) return [seg.start_lat, seg.start_lng]
    if (mateixMoment(seg.start_time, w.start_time) && w.start_location_lat) return [w.start_location_lat, w.start_location_lng]
  } else {
    if (seg.end_lat) return [seg.end_lat, seg.end_lng]
    if (mateixMoment(seg.end_time, w.end_time) && w.end_location_lat) return [w.end_location_lat, w.end_location_lng]
  }
  return null
}

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
const potReobrir = computed(() => ['admin', 'hr'].includes(authStore.user?.role))
async function reobrirTram(seg) {
  const motiu = await demana(`Per què cal revisar de nou el tram ${seg.segment_number}? (mínim 10 caràcters)\nQuedarà registrat a la traçabilitat.`)
  if (motiu === null) return
  if (motiu.trim().length < 10) return alert("Cal un motiu d'almenys 10 caràcters.")
  try {
    await api.post(`/v1/work-logs/${workLog.value.id}/segments/${seg.id}/reobrir`, { motiu: motiu.trim() })
    await loadData()
  } catch (e) {
    alert(e?.message || "No s'ha pogut reobrir el tram.")
  }
}

// «Corregir hora». Les hores arriben en hora de Madrid sense zona ('2026-10-05T08:00:00.000'):
// el camp datetime-local en vol els 16 primers caràcters i el servidor les vol amb espai.
const correccio = reactive({ obert: false, inici: '', fi: '', motiu: '', desant: false, error: '' })
function obreCorreccio() {
  Object.assign(correccio, {
    obert: true, motiu: '', error: '',
    inici: String(workLog.value?.start_time || '').slice(0, 16),
    fi: String(workLog.value?.end_time || '').slice(0, 16),
  })
}
async function desaCorreccio() {
  correccio.error = ''
  if (!correccio.fi) { correccio.error = "Cal l'hora de sortida."; return }
  correccio.desant = true
  try {
    await api.post(`/v1/work-logs/${workLog.value.id}/corregir-hora`, {
      start_time: correccio.inici.replace('T', ' '),
      end_time: correccio.fi.replace('T', ' '),
      motiu: correccio.motiu.trim(),
    })
    correccio.obert = false
    await loadData()
  } catch (e) {
    correccio.error = e?.message || "No s'ha pogut desar la correcció."
  } finally {
    correccio.desant = false
  }
}

// «Corregir zona». Les marques de pausa arriben com 0/1 o null (sense cast).
const esFora = (v) => v === false || v === 0 || v === '0'
const marquesFora = computed(() => {
  const w = workLog.value
  if (!w || !w.end_time) return []
  return [
    { clau: 'entrada', nom: 'Entrada', fora: esFora(w.start_location_match) },
    { clau: 'pausa', nom: 'Pausa', fora: esFora(w.break_start_location_match) || esFora(w.break_end_location_match) },
    { clau: 'sortida', nom: 'Sortida', fora: esFora(w.end_location_match) },
  ].filter(m => m.fora)
})
const zona = reactive({ obert: false, marques: [], centre: '', centres: [], motiu: '', desant: false, error: '' })
async function obreZona() {
  Object.assign(zona, { obert: true, marques: marquesFora.value.map(m => m.clau), centre: '', motiu: '', error: '' })
  if (!zona.centres.length) {
    try { zona.centres = (await db.getAmbulatoryCenters()) || [] } catch (e) { zona.centres = [] }
  }
}
async function desaZona() {
  zona.error = ''
  zona.desant = true
  try {
    await api.post(`/v1/work-logs/${workLog.value.id}/corregir-zona`, {
      marques: zona.marques, centre_id: zona.centre || null, motiu: zona.motiu.trim(),
    })
    zona.obert = false
    await loadData()
  } catch (e) {
    zona.error = e?.message || "No s'ha pogut desar la correcció."
  } finally {
    zona.desant = false
  }
}

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
.break-cap { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.break-cap h3 { margin: 0; }
.break-estat { font-size: .82rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; background: #eef2f6; color: #475569; }
.break-estat.pausa-completed { background: #d4edda; color: #1e6b34; }
.break-estat.pausa-active { background: #fff3cd; color: #8a6d00; }
.break-estat.pausa-skipped, .break-estat.pausa-pending { background: #fdecea; color: #a12a22; }
.break-linia { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.break-dada { display: flex; flex-direction: column; gap: 2px; min-width: 70px; }
.break-dada strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.break-et { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #7a8aa0; }
.break-fletxa { color: #94a3b8; font-size: 1.1rem; }
.break-durada { margin-left: auto; padding-left: 14px; border-left: 1px solid #e5eaf0; }
.break-avis { margin-top: 12px; padding: 10px 12px; border-radius: 8px; background: #fdecea; border-left: 4px solid #B3352F; color: #8f1f19; font-size: .85rem; font-weight: 600; }
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
.correccio { margin-top: 12px; }
.correccio-camps .zona-marca { display: flex; flex-direction: row; align-items: center; gap: 6px; font-size: 0.9rem; }
.correccio-camps .zona-marca input { width: auto; margin: 0; }
.correccio-form { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; }
.correccio-camps { display: flex; gap: 12px; flex-wrap: wrap; }
.correccio-camps label { display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem; font-weight: 600; }
.correccio-botons { display: flex; gap: 8px; }
.correccio-error { color: #c62828; font-size: 0.85rem; }
.reject-hint { color: #ff9800; font-size: 0.82em; margin-top: -4px; }
.modification-item { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9em; }
.empty { color: #999; padding: 12px; }
.loading, .error { text-align: center; padding: 40px; color: #999; }
</style>
