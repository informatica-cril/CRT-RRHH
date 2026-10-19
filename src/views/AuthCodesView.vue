<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">Hores de més</h1><p class="page-subtitle">Les peticions per autoritzar i les hores de més autoritzades a cada persona. S'apliquen soles als seus fitxatges d'aquells dies: no cal cap codi.</p></div>
      <div class="page-actions" v-if="authStore.isStaff">
        <button class="btn btn-primary" @click="openCreateModal">+ Autoritzar hores</button>
      </div>
    </div>

    <!-- Peticions pendents: les demana la persona quan es passa de l'hora, o les força coordinació des de domi. -->
    <div v-if="authStore.isStaff && pendents.length" class="card mb-lg hm-pendents">
      <h3 class="card-title">⚠ {{ pendents.length }} petici{{ pendents.length === 1 ? 'ó' : 'ons' }} per autoritzar</h3>
      <p class="text-small text-muted" style="margin:4px 0 10px;">Si s'autoritza i la jornada ja està tancada, les hores s'hi apliquen soles.</p>
      <div v-for="p in pendents" :key="p.id" class="hm-peticio">
        <div class="hm-peticio-info">
          <strong>{{ p.user?.name || getUserName(p.user_id) }}</strong>
          <span class="badge badge-warning">{{ p.hours }} h</span>
          <span class="text-small text-muted">{{ formatDate(p.period_from) }}<template v-if="p.period_to && p.period_to !== p.period_from"> → {{ formatDate(p.period_to) }}</template>
            · {{ p.origen === 'treballador' ? 'la demana la persona' : 'forçada per ' + p.requested_by }}</span>
          <div v-if="p.reason" class="text-small text-muted">{{ p.reason }}</div>
        </div>
        <div class="hm-peticio-acc">
          <button class="btn btn-success btn-sm" :disabled="p._busy" @click="autoritzar(p)">✔ Autoritzar</button>
          <button class="btn btn-outline btn-sm" :disabled="p._busy" @click="denegar(p)">✖ Denegar</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Persona</th>
              <th>Tipus</th>
              <th>Concepte</th>
              <th>Hores</th>
              <th>Consum (registre)</th>
              <th>S'esgota</th>
              <th>Validesa</th>
              <th>{{ t('status') }}</th>
              <th v-if="authStore.isStaff">{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="code in codes" :key="code.id">
              <td><strong>{{ code.user_id ? getUserName(code.user_id) : 'Qualsevol (codi antic)' }}</strong><div class="text-small text-muted">Ref. {{ code.code }}</div></td>
              <td>
                <span class="badge" :class="code.type === 'extraordinaria' ? 'badge-warning' : 'badge-info'" style="white-space:nowrap;">
                  {{ code.type === 'extraordinaria' ? '⚡ Extra 1,25×' : '✋ Compl. 1,00×' }}
                </span>
              </td>
              <td>{{ code.concept || '—' }}</td>
              <td><strong>{{ code.authorized_hours }}h</strong></td>
              <td style="min-width:130px;">
                <template v-if="code.consum">
                  <div style="display:flex;align-items:center;gap:6px;">
                    <div style="flex:1;height:8px;border-radius:4px;background:var(--color-bg);overflow:hidden;min-width:50px;">
                      <div :style="{width: Math.min(100, code.consum.pct||0)+'%', height:'100%',
                                    background: (code.consum.pct||0) >= 100 ? '#dc3545' : (code.consum.pct||0) >= 85 ? '#D9A400' : '#0DAF83'}"></div>
                    </div>
                    <span class="text-small" style="white-space:nowrap;"><b>{{ code.consum.usat_h }}h</b> · {{ code.consum.pct ?? 0 }}%</span>
                  </div>
                </template>
              </td>
              <td class="text-small">
                <template v-if="code.consum?.esgota === 'esgotat'"><span style="color:#dc3545;font-weight:700;">Esgotat</span></template>
                <template v-else-if="code.consum?.esgota">
                  {{ formatDate(code.consum.esgota) }}
                  <div v-if="code.consum.dins_validesa === false" style="color:#0A7C5E;">fora de la validesa: caduca abans</div>
                  <div v-else style="color:#96600F;">al ritme actual del registre</div>
                </template>
                <template v-else><span class="text-muted">sense consum imputat</span></template>
              </td>
              <td><span class="text-small">{{ formatDate(code.valid_from) }} → {{ formatDate(code.valid_to) }}</span></td>
              <td>
                <span class="badge" :class="codeBadge(code)">{{ codeStatusText(code) }}</span>
              </td>
              <td v-if="authStore.isStaff">
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <button v-if="!code.revoked && !code.used" class="btn btn-outline btn-sm" @click="renovarCode(code)">↻ Renovar</button>
                  <button v-if="!code.revoked && !code.used" class="btn btn-danger btn-sm" @click="revokeCode(code)">⊘ Anul·lar</button>
                  <span v-if="code.notification_sent" class="badge badge-info" style="font-size:0.65rem;">📧 Enviat</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header">
          <h3 class="modal-title">Autoritzar hores de més</h3>
          <button class="icon-btn" @click="showModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Concepte *</label>
          <input class="form-input" v-model="form.concept" placeholder="Ex: Projecte urgent client A" />
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Hores autoritzades *</label>
            <input class="form-input" type="number" v-model.number="form.authorized_hours" min="0.5" max="2000" step="0.5"
              :style="capExceeded ? 'border-color:var(--color-danger);' : ''" />
          </div>
          <div class="form-group">
            <label class="form-label">Persona *</label>
            <SelectorPersona v-model="form.user_id" :persones="workers" style="width:100%;" />
          </div>
        </div>

        <!-- Tipus d'hora: la jornada completa NOMÉS pot fer extraordinàries (art. 12.4.c ET) -->
        <div class="form-group">
          <label class="form-label">Tipus d'hora *</label>
          <select class="form-select" v-model="form.type">
            <option value="complementaria">✋ Complementàries (només per a qui treballa a temps parcial)</option>
            <option value="extraordinaria">⚡ Extraordinàries (com a màxim 80 h l'any)</option>
          </select>
        </div>

        <!-- Límit legal: 30% de la jornada bàsica contractada (art. 12.5 ET) -->
        <div v-if="form.user_id" class="text-small" style="margin:-4px 0 8px;padding:8px 12px;border-radius:8px;"
          :style="capExceeded ? 'background:rgba(239,68,68,0.08);color:var(--color-danger);' : 'background:var(--color-bg);color:var(--color-text-secondary);'">
          <!-- Sense base de càlcul no s'autoritzen hores: es diu QUÈ falta -->
          <template v-if="capInfo && capInfo.calculable === false">
            <strong>⛔ Topall no calculable.</strong> {{ capInfo.motiu }}
          </template>
          <template v-else-if="capInfo && capInfo.jornada_completa && form.type === 'complementaria'">
            <strong>⛔ Jornada completa: no pot fer hores complementàries</strong> (art. 12.4.c ET).
            Canvia el tipus a hores EXTRAORDINÀRIES (màxim 80 h/any).
          </template>
          <template v-else-if="capInfo && form.type === 'extraordinaria'">
            <strong>Hores extraordinàries: topall de 80 h l'any</strong> (art. 20.2 del conveni XII),
            retribuïdes a 1,25×. El topall del 30% de complementàries no hi aplica.
          </template>
          <template v-else-if="capInfo">
            <strong>Màxim de complementàries de l'any ({{ capInfo.year }}): {{ capInfo.cap }}h</strong>
            <span> — 30% de {{ capInfo.annualBasic }}h de jornada bàsica anual</span>
            · ja autoritzades l'any <strong>{{ capInfo.already }}h</strong> · disponibles <strong>{{ capInfo.remaining }}h</strong>
            <div style="margin-top:6px;">
              <button type="button" class="btn btn-accent btn-sm" @click="useFullCap" :disabled="!(capInfo.remaining > 0)">
                ⚡ Donar d'alta el PACTE signat amb tot l'any ({{ capInfo.remaining }}h)
              </button>
              <span class="text-small text-muted" style="margin-left:6px;">Normalment no cal: el codi es crea sol quan es dona d'alta la persona. Fes-lo servir només si li falta.</span>
            </div>
            <!-- Seguiment mensual del consum anual -->
            <div style="margin-top:6px;font-weight:600;">Seguiment mensual {{ capInfo.year }}:</div>
            <div style="display:flex;gap:3px;margin-top:3px;flex-wrap:wrap;">
              <div v-for="(h, i) in capInfo.monthly" :key="i" :title="monthNames[i] + ': ' + h + 'h autoritzades'" :style="monthCellStyle(h, i)">
                <div style="font-size:0.6rem;opacity:0.7;">{{ monthNames[i] }}</div>
                <div style="font-weight:700;">{{ h > 0 ? h : '·' }}</div>
              </div>
            </div>
            <div v-if="capExceeded" style="font-weight:700;margin-top:6px;">⚠️ Passa del màxim de l'any (el 30 % de la seva jornada). Posa-hi menys hores o anul·la algun codi anterior.</div>
          </template>
          <template v-else>Calculant el màxim de l'any…</template>
        </div>
        <div v-else class="text-small text-muted" style="margin:-4px 0 8px;">
          Tria la persona: les hores s'apliquen soles als seus fitxatges d'aquells dies.
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Franja horària inici</label>
            <input class="form-input" type="time" v-model="form.time_slot_start" min="06:00" max="22:00"
              :style="franjaInvalid ? 'border-color:var(--color-danger);' : ''" />
          </div>
          <div class="form-group">
            <label class="form-label">Franja horària fi</label>
            <input class="form-input" type="time" v-model="form.time_slot_end" min="06:00" max="22:00"
              :style="franjaInvalid ? 'border-color:var(--color-danger);' : ''" />
          </div>
        </div>
        <div class="text-small" style="margin:-4px 0 8px;" :style="franjaInvalid ? 'color:var(--color-danger);font-weight:600;' : 'color:var(--color-text-secondary);'">
          <template v-if="franjaInvalid">⚠️ Franja no permesa. Ha d'estar entre les 06:00 i les 22:00 (no s'admet la franja nocturna 22:00–06:00).</template>
          <template v-else>Franja flexible entre 06:00 i 22:00. No s'admet la franja nocturna (22:00–06:00).</template>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Vàlid des de</label>
            <input class="form-input" type="date" v-model="form.valid_from" />
          </div>
          <div class="form-group">
            <label class="form-label">Vàlid fins a</label>
            <input class="form-input" type="date" v-model="form.valid_to" />
          </div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;">
          <input type="checkbox" v-model="form.send_notification" style="width:16px;height:16px;" />
          <span>Enviar notificació per email al destinatari</span>
        </label>
        <div v-if="createError" class="text-small" style="color:var(--color-danger);margin-top:8px;">{{ createError }}</div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="createCode" :disabled="!form.user_id || !form.concept || !form.authorized_hours || capExceeded || franjaInvalid">Autoritzar</button>
        </div>
      </div>
    </div>

    <!-- Renew Modal -->
    <div v-if="showRenewModal" class="modal-overlay" @click.self="showRenewModal = false">
      <div class="modal" style="max-width:440px;">
        <div class="modal-header">
          <h3 class="modal-title">↻ Renovar el codi {{ renewTarget?.code }}</h3>
        </div>
        <p class="text-small text-muted">Es genera un codi NOU per a {{ renewTarget?.user_id ? getUserName(renewTarget.user_id) : 'qualsevol' }}
          ({{ renewTarget?.type === 'extraordinaria' ? 'extraordinàries 1,25×' : 'complementàries' }}) i el vell queda revocat.
          El topall anual es recomprova.</p>
        <div class="form-group">
          <label>Hores autoritzades</label>
          <input type="number" v-model.number="renewForm.hours" min="0.25" step="0.25" />
        </div>
        <div class="form-group" style="display:flex;gap:10px;">
          <div style="flex:1;"><label>Vàlid des de</label><input type="date" v-model="renewForm.valid_from" /></div>
          <div style="flex:1;"><label>Fins a</label><input type="date" v-model="renewForm.valid_to" /></div>
        </div>
        <p v-if="renewError" class="text-small" style="color:#dc3545;">{{ renewError }}</p>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRenewModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" :disabled="!renewForm.hours || !renewForm.valid_from || !renewForm.valid_to || renewBusy"
                  @click="confirmRenew">Renovar i emetre el codi nou</button>
        </div>
      </div>
    </div>

    <!-- Revoke Confirm Modal -->
    <div v-if="showRevokeModal" class="modal-overlay" @click.self="showRevokeModal = false">
      <div class="modal" style="max-width:400px;">
        <div class="modal-header">
          <h3 class="modal-title">⚠️ Anul·lar el codi</h3>
        </div>
        <p>Segur que vols anul·lar el codi <strong>{{ revokeTarget?.code }}</strong>?</p>
        <p class="text-small text-muted mt-sm">Aquesta acció és immediata i no es pot desfer.</p>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRevokeModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-danger" @click="confirmRevoke">Sí, anul·lar-lo ara</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import SelectorPersona from '../components/SelectorPersona.vue'
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { avisa } from '../utils/avisos'
import { demana } from '../utils/dialegs'
import { i18n } from '../i18n'

console.log('[AuthCodesView] Compilando componente...')

const authStore = useAuthStore()
const t = (key) => i18n.t(key)

const codes = ref([])
const showModal = ref(false)
const showRevokeModal = ref(false)
const revokeTarget = ref(null)
const showRenewModal = ref(false)
const renewTarget = ref(null)
const renewBusy = ref(false)
const renewError = ref('')
const renewForm = reactive({ hours: null, valid_from: '', valid_to: '' })

function renovarCode(code) {
  renewTarget.value = code
  renewForm.hours = Number(code.authorized_hours)
  renewForm.valid_from = new Date().toISOString().slice(0, 10)
  renewForm.valid_to = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)
  renewError.value = ''
  showRenewModal.value = true
}
async function confirmRenew() {
  if (!renewTarget.value) return
  renewBusy.value = true
  renewError.value = ''
  try {
    const r = await db.renewAuthCode(renewTarget.value.id, { ...renewForm })
    auditLog(authStore.userId, 'RENEW_AUTH_CODE', 'authorization_code', renewTarget.value.id,
             `Renovat ${renewTarget.value.code} → ${r?.code || ''} (${renewForm.hours}h)`)
    showRenewModal.value = false
    codes.value = await db.getAuthCodes()
  } catch (e) {
    renewError.value = e?.response?.data?.error || e?.message || 'No s\'ha pogut renovar.'
  } finally { renewBusy.value = false }
}
const workers = ref([])
const users = ref([])
const capInfo = ref(null)
const createError = ref('')

// Límit del 30% de la jornada bàsica: es carrega del backend en canviar treballador/data
async function loadCap() {
  capInfo.value = null
  if (!form.user_id || !form.valid_from) return
  try {
    capInfo.value = await db.getComplementaryCap(form.user_id, form.valid_from)
  } catch (e) {
    console.error('[AuthCodes] cap', e); capInfo.value = null
  }
}

// El nou codi supera el tope ANUAL del 30%? (ja autoritzat l'any + hores d'aquest codi > cap)
const capExceeded = computed(() => {
  // El 30% és el topall de les COMPLEMENTÀRIES; les extraordinàries tenen el seu (80 h/any).
  if (form.type === 'extraordinaria') return false
  if (!form.user_id || !capInfo.value) return false
  // Sense topall calculable o amb jornada completa no s'emeten complementàries.
  if (capInfo.value.calculable === false || capInfo.value.jornada_completa) return true
  if (!capInfo.value.cap) return false
  return (Number(capInfo.value.already) + Number(form.authorized_hours || 0)) > Number(capInfo.value.cap) + 0.01
})

// Franja horària: només diürna 06:00–22:00 (no nocturna)
function toMin(t) {
  if (!t) return null
  const [h, m] = String(t).split(':').map(Number)
  return h * 60 + m
}
const franjaInvalid = computed(() => {
  const s = toMin(form.time_slot_start), e = toMin(form.time_slot_end)
  if (s == null || e == null) return false
  return e <= s || s < 360 || e > 1320
})

const monthNames = ['Gen', 'Feb', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Des']
const selectedMonthIdx = computed(() => form.valid_from ? new Date(form.valid_from).getMonth() : -1)
function monthCellStyle(h, i) {
  const isCurrent = i === selectedMonthIdx.value
  return `min-width:30px;text-align:center;padding:2px 3px;border-radius:4px;`
    + `border:1px solid ${isCurrent ? 'var(--color-primary)' : 'var(--color-border-light)'};`
    + `background:${h > 0 ? 'rgba(245,158,11,0.15)' : 'transparent'};`
}

// Prepara el codi d'autorització amb TOT el 30% anual disponible (validesa tot l'any
// i franja diürna completa 06:00–22:00)
function useFullCap() {
  if (!capInfo.value || capInfo.value.remaining <= 0) return
  const yr = capInfo.value.year
  form.authorized_hours = capInfo.value.remaining
  form.valid_from = `${yr}-01-01`
  form.valid_to = `${yr}-12-31`
  form.time_slot_start = '06:00'
  form.time_slot_end = '22:00'
  if (!form.concept) form.concept = `Pacte de complementàries signat (art. 12.5) — alta anual ${yr}`
}

async function fetchData() {
  try {
    codes.value = await db.getAuthCodes()
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => { fetchData(); loadPendents() })

// ── Peticions pendents (abans només a «Control d'hores») ──
const pendents = ref([])
async function loadPendents() {
  if (!authStore.isStaff) return
  try {
    const r = await db.getOvertimeRequests('pending')
    pendents.value = (Array.isArray(r) ? r : (r?.data ?? [])).map(p => ({ ...p, _busy: false }))
  } catch (e) { pendents.value = [] }
}
async function autoritzar(p) {
  p._busy = true
  try {
    const r = await db.authorizeOvertime(p.id)
    avisa(r?.aplicades ? `✅ Autoritzades i aplicades ${r.aplicades} h al fitxatge.` : '✅ Autoritzades.')
    await Promise.all([loadPendents(), fetchData()])
  } catch (e) { p._busy = false; avisa(e?.message || "No s'ha pogut autoritzar.", 'error') }
}
async function denegar(p) {
  // El motiu és obligatori: qui rep la denegació ha de saber per què.
  const reason = await demana('Motiu de la denegació (obligatori):', { frases: 'denegacio' })
  if (reason === null) return
  if (!reason.trim()) { avisa('Cal escriure el motiu de la denegació.', 'error'); return }
  p._busy = true
  try { await db.denyOvertime(p.id, reason); await loadPendents() }
  catch (e) { p._busy = false; avisa(e?.message || "No s'ha pogut denegar.", 'error') }
}

const now = new Date()
const form = reactive({
  concept: '', authorized_hours: 4, user_id: null, type: 'complementaria',
  time_slot_start: '16:00', time_slot_end: '20:00',
  valid_from: now.toISOString().split('T')[0],
  valid_to: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0],
  send_notification: true
})

// Recalcular el tope quan canvia el treballador o la data (form ja està declarat)
watch(() => [form.user_id, form.valid_from], loadCap)

function openCreateModal() {
  Object.assign(form, { concept: '', authorized_hours: 4, user_id: null, type: 'complementaria', time_slot_start: '16:00', time_slot_end: '20:00', valid_from: now.toISOString().split('T')[0], valid_to: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0], send_notification: true })
  capInfo.value = null
  createError.value = ''
  showModal.value = true
}

async function createCode() {
  createError.value = ''
  // Bloqueig client (el backend també ho valida amb 422)
  if (capExceeded.value) {
    createError.value = 'Les hores superen el 30% de la jornada bàsica contractada.'
    return
  }
  if (franjaInvalid.value) {
    createError.value = 'La franja horària ha d\'estar entre les 06:00 i les 22:00 (no s\'admet la nocturna).'
    return
  }
  const arr = new Uint8Array(9)
  crypto.getRandomValues(arr)
  const code = Array.from(arr, b => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[b % 32]).join('').replace(/(.{4})/g, '$1-').slice(0, 14)
  try {
    const creat = await db.addAuthCode({
      code, concept: form.concept, authorized_hours: form.authorized_hours,
      type: form.type,
      time_slot_start: form.time_slot_start, time_slot_end: form.time_slot_end,
      generated_by: authStore.userId, user_id: form.user_id,
      valid_from: new Date(form.valid_from).toISOString(),
      valid_to: new Date(form.valid_to + 'T23:59:59').toISOString(),
      used: false, revoked: false,
      notification_sent: form.send_notification
    })
    auditLog(authStore.userId, 'GENERATE_AUTH_CODE', 'authorization_code', null, `Codi: ${code}, Concepte: ${form.concept}, ${form.authorized_hours}h`)
    if (form.send_notification && form.user_id) {
      const user = users.value.find(u => u.id === form.user_id)
      auditLog(authStore.userId, 'SEND_NOTIFICATION', 'authorization_code', null, `Email enviat a ${user?.email}: ${code}`)
    }
    await fetchData()
    showModal.value = false
    avisa(creat?.aplicades > 0
      ? `✅ Hores autoritzades. Ja s'han aplicat ${creat.aplicades} h a fitxatges tancats d'aquells dies.`
      : "✅ Hores autoritzades. S'aplicaran soles quan fitxi la sortida aquells dies.")
  } catch (e) {
    console.error(e)
    if (e && e.status === 422) {
      const errs = e.errors || {}
      // El motiu del rebuig pot ser el topall, el tipus d'hora (jornada completa)
      // o una fitxa sense quadre horari: es mostra el missatge sencer del servidor.
      createError.value = e.message || errs.authorized_hours?.[0] || errs.type?.[0] || errs.user_id?.[0]
        || 'Les hores superen el 30% de la jornada bàsica contractada.'
      await loadCap()
    } else {
      createError.value = 'No s\'ha pogut crear el codi. Torna-ho a provar.'
    }
  }
}

function revokeCode(code) { revokeTarget.value = code; showRevokeModal.value = true }

async function confirmRevoke() {
  if (revokeTarget.value) {
    try {
      await db.revokeAuthCode(revokeTarget.value.id)
      auditLog(authStore.userId, 'REVOKE_AUTH_CODE', 'authorization_code', revokeTarget.value.id, `Revocat: ${revokeTarget.value.code}`)
      await fetchData()
      showRevokeModal.value = false
    } catch (e) { console.error(e) }
  }
}

function getUserName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function codeBadge(c) { return c.revoked ? 'badge-danger' : c.used ? 'badge-warning' : isExpired(c) ? 'badge-warning' : 'badge-success' }
function codeStatusText(c) { return c.revoked ? 'Revocat' : c.used ? 'Gastada' : isExpired(c) ? t('code_expired') : 'Actiu' }
function isExpired(c) { return new Date() > new Date(c.valid_to) }
</script>

<style scoped>
.hm-pendents { border-left: 4px solid #D9A400; }
.hm-peticio { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid var(--color-border-light, #eef2f7); }
.hm-peticio-info { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.hm-peticio-info > div { flex-basis: 100%; }
.hm-peticio-acc { display: flex; gap: 6px; }
</style>
