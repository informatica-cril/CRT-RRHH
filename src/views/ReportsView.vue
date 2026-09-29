<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('reports') }}</h1><p class="page-subtitle">Informes configurables i dades de nòmines per treballador i període</p></div>
      <div class="page-actions" style="flex-wrap:wrap;">
        <select class="form-select" v-model="selectedMonth" style="width:180px;">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <select v-if="authStore.isStaff" class="form-select" v-model="selectedUser" style="width:200px;">
          <option value="all">Tots els treballadors</option>
          <option v-for="u in workers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid mb-lg">
      <div class="stat-card primary">
        <div class="stat-label">{{ t('worked_hours') }}</div>
        <div class="stat-value">{{ summary.totalHours }}h</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('theoretical_hours') }}</div>
        <div class="stat-value">{{ summary.theoreticalHours }}h</div>
      </div>
      <div class="stat-card" style="border-bottom:3px solid var(--color-success);">
        <div class="stat-label" style="color:var(--color-success);">✓ H. Autoritzades</div>
        <div class="stat-value" style="color:var(--color-success);">{{ summary.authorizedHours }}h</div>
      </div>
      <div class="stat-card" style="border-bottom:3px solid var(--color-danger);">
        <div class="stat-label" style="color:var(--color-danger);">✗ H. No autoritzades</div>
        <div class="stat-value" style="color:var(--color-danger);">{{ summary.unauthorizedHours }}h</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('absences') }}</div>
        <div class="stat-value">{{ summary.absenceDays }}</div>
      </div>
    </div>

    <!-- Worker Individual Report -->
    <div v-if="selectedUser !== 'all'" class="card mb-lg">
      <div class="card-header">
        <h3 class="card-title">Informe individual: {{ getWorkerName(selectedUser) }}</h3>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
        <div style="background:var(--color-bg);padding:12px;border-radius:8px;">
          <div class="text-small text-muted">Mitjana diària</div>
          <div style="font-size:1.3rem;font-weight:700;">{{ workerAvg }}h</div>
        </div>
        <div style="background:var(--color-bg);padding:12px;border-radius:8px;">
          <div class="text-small text-muted">Dies treballats</div>
          <div style="font-size:1.3rem;font-weight:700;">{{ reportLogs.length }}</div>
        </div>
        <div style="background:rgba(76,175,80,0.08);padding:12px;border-radius:8px;border-bottom:3px solid var(--color-success);">
          <div class="text-small" style="color:var(--color-success);">✓ Hores autoritzades</div>
          <div style="font-size:1.3rem;font-weight:700;color:var(--color-success);">{{ summary.authorizedHours }}h</div>
        </div>
        <div style="background:rgba(239,68,68,0.08);padding:12px;border-radius:8px;border-bottom:3px solid var(--color-danger);">
          <div class="text-small" style="color:var(--color-danger);">✗ No autoritzades</div>
          <div style="font-size:1.3rem;font-weight:700;color:var(--color-danger);">{{ summary.unauthorizedHours }}h</div>
        </div>
        <div style="background:var(--color-bg);padding:12px;border-radius:8px;">
          <div class="text-small text-muted">Permisos usats</div>
          <div style="font-size:1.3rem;font-weight:700;">{{ workerAbsences }}</div>
        </div>
        <div style="background:rgba(239,68,68,0.06);padding:12px;border-radius:8px;border-bottom:3px solid var(--color-warning);">
          <div class="text-small" style="color:var(--color-warning);">📍 Fitxatges fora de CP</div>
          <div style="font-size:1.3rem;font-weight:700;color:var(--color-warning);">{{ outOfCpLogs.length }}</div>
        </div>
      </div>

      <!-- Out-of-postal-code section -->
      <div v-if="outOfCpLogs.length > 0" style="border-top:1px solid var(--color-border-light);padding-top:16px;">
        <h4 style="color:var(--color-warning);margin-bottom:12px;">⚠️ Fitxatges fora del codi postal assignat</h4>
        <div class="table-container" style="max-height:220px;overflow-y:auto;">
          <table>
            <thead><tr><th>Data</th><th>CP Assignat</th><th>Inici</th><th>Fi</th><th>Hores</th><th>Estat</th></tr></thead>
            <tbody>
              <tr v-for="l in outOfCpLogs" :key="l.id" style="background:rgba(239,68,68,0.03);">
                <td>{{ formatDate(l.date) }}</td>
                <td><span class="badge badge-warning" style="font-size:0.7rem;">{{ l.assigned_postal_code || getWorkerPostalCode(l.user_id) }}</span></td>
                <td class="text-small">{{ formatTime(l.start_time) }}</td>
                <td class="text-small">{{ l.end_time ? formatTime(l.end_time) : '—' }}</td>
                <td><strong>{{ Number((l.effective_hours ?? l.total_hours_worked) || 0).toFixed(2) }}h</strong></td>
                <td><span class="badge" :class="statusBadge(l.status)">{{ t(l.status) || l.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Informe mensual per fisioterapeuta (admin) -->
    <div v-if="authStore.isStaff" class="card mb-lg" id="fisio-monthly-report">
      <div class="card-header">
        <div>
          <h3 class="card-title">🖨️ Informe mensual — Fisioterapeutes</h3>
          <div class="text-small text-muted">{{ selectedMonthLabel }} · efectiu, autoritzat, no autoritzat, bàsic, fora de zona i desviació</div>
        </div>
        <button class="btn btn-primary btn-sm" @click="printFisioReport">🖨️ Imprimir</button>
      </div>
      <div class="table-container">
        <table>
          <thead><tr>
            <th style="text-align:left;">Fisioterapeuta</th>
            <th>Dies</th><th>Bàsic</th><th>Compl. (codi)</th><th>Autoritzat</th>
            <th style="color:var(--color-primary);">Efectiu</th>
            <th style="color:var(--color-danger);">No aut.</th>
            <th style="color:var(--color-warning);">Fora zona</th>
            <th>Desviació</th>
          </tr></thead>
          <tbody>
            <tr v-for="r in fisioMonthlyRows" :key="r.id" :style="r.dies === 0 ? 'opacity:0.5;' : ''">
              <td style="text-align:left;font-weight:600;">{{ r.name }}</td>
              <td>{{ r.dies }}</td>
              <td>{{ r.basic.toFixed(1) }}h</td>
              <td>{{ r.compl.toFixed(1) }}h</td>
              <td style="font-weight:700;">{{ r.authorized.toFixed(1) }}h</td>
              <td style="font-weight:700;color:var(--color-primary);">{{ r.effective.toFixed(1) }}h</td>
              <td :style="r.unauth > 0.01 ? 'color:var(--color-danger);font-weight:700;background:rgba(239,68,68,0.06);' : 'color:#cbd5e1;'">{{ r.unauth > 0.01 ? r.unauth.toFixed(1)+'h' : '—' }}</td>
              <td :style="r.outZone > 0.01 ? 'color:var(--color-warning);font-weight:700;background:rgba(245,158,11,0.08);' : 'color:#cbd5e1;'">{{ r.outZone > 0.01 ? r.outZone.toFixed(1)+'h' : '—' }}</td>
              <td :style="devStyle(r.deviation)">{{ devText(r.deviation) }}</td>
            </tr>
            <tr v-if="fisioMonthlyRows.length === 0"><td colspan="9" class="text-center text-muted" style="padding:24px;">Cap fisioterapeuta actiu</td></tr>
          </tbody>
        </table>
      </div>
      <div class="text-small text-muted" style="margin-top:8px;">
        <strong>Desviació</strong> = efectiu − autoritzat. <span style="color:var(--color-danger);">▲ excés</span> (per sobre de l'autoritzat) · <span style="color:var(--color-warning);">▼ dèficit</span> (per sota).
      </div>
    </div>

    <!-- Main Report Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">{{ t('monthly_report') }}</h3>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-outline btn-sm" @click="downloadReport('pdf')">📄 {{ t('download_pdf') }}</button>
          <button class="btn btn-outline btn-sm" @click="downloadReport('excel')">📊 {{ t('download_excel') }}</button>
        </div>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-if="selectedUser === 'all'">Treballador</th>
              <th>{{ t('date') }}</th>
              <th>{{ t('start_time') }}</th>
              <th>{{ t('end_time') }}</th>
              <th>Total h.</th>
              <th style="color:var(--color-success);">✓ Autorit.</th>
              <th style="color:var(--color-danger);">✗ No aut.</th>
              <th>📍 CP</th>
              <th>{{ t('status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in reportLogs" :key="log.id">
              <td v-if="selectedUser === 'all'">{{ getWorkerName(log.user_id) }}</td>
              <td>{{ formatDate(log.date) }}</td>
              <td>{{ formatTime(log.start_time) }}</td>
              <td>{{ log.end_time ? formatTime(log.end_time) : '—' }}</td>
              <td><strong>{{ Number((log.effective_hours ?? log.total_hours_worked) || 0).toFixed(2) }}h</strong></td>
              <td>
                <span v-if="Number(log.extra_hours_authorized||0) > 0" style="color:var(--color-success);font-weight:600;">
                  +{{ Number(log.extra_hours_authorized||0).toFixed(1) }}h
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="Number(log.extra_hours_unauthorized||0) > 0" style="color:var(--color-danger);font-weight:600;">
                  {{ Number(log.extra_hours_unauthorized||0).toFixed(1) }}h
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="log.location_match === false" style="color:var(--color-danger);font-size:0.8rem;">⚠️ Fora</span>
                <span v-else-if="log.location_match === true" style="color:var(--color-success);font-size:0.8rem;">✓</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td><span class="badge" :class="statusBadge(log.status)">{{ t(log.status) || log.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rdl-notice">{{ t('rdl_notice') }} · {{ t('rdl_retention') }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const t = (key) => i18n.t(key)

const now = new Date()
const selectedMonth = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const selectedUser = ref(authStore.isStaff ? 'all' : authStore.userId)
const workers = ref([])
const users = ref([])
const absences = ref([])
const workSchedules = ref([])
const authCodes = ref([])
const holidaySet = ref(new Set())

async function fetchData() {
  try {
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
    absences.value = await db.getAbsences()
    await workLogStore.loadLogs()
    // Dades per a l'informe mensual per fisioterapeuta (temps autoritzat)
    if (authStore.isStaff) {
      const bulk = await db.getUsersBulk().catch(() => ({ work_schedules: [] }))
      workSchedules.value = bulk.work_schedules || []
      authCodes.value = await db.getAuthCodes().catch(() => [])
      const holidays = await db.getHolidays().catch(() => [])
      holidaySet.value = new Set((holidays || []).map(h => String(h.date).substring(0, 10)))
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const months = computed(() => {
  const result = []
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    result.push({ value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`, label: d.toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' }) })
  }
  return result
})

const reportLogs = computed(() => {
  let logs = workLogStore.logs.filter(l => l.date?.startsWith(selectedMonth.value))
  if (selectedUser.value !== 'all') logs = logs.filter(l => l.user_id == selectedUser.value)
  if (authStore.isWorker) logs = logs.filter(l => l.user_id === authStore.userId)
  return logs.sort((a, b) => new Date(a.date) - new Date(b.date))
})

// Logs from selected worker that are outside their assigned postal code
const outOfCpLogs = computed(() => {
  if (selectedUser.value === 'all') return []
  return reportLogs.value.filter(l => l.location_match === false)
})

const summary = computed(() => {
  const logs = reportLogs.value
  return {
    totalHours: Number(logs.reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0)).toFixed(1),
    theoreticalHours: (logs.length * 8).toFixed(0),
    authorizedHours: Number(logs.reduce((s, l) => s + Number(l.extra_hours_authorized || 0), 0)).toFixed(1),
    unauthorizedHours: Number(logs.reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0), 0)).toFixed(1),
    absenceDays: absences.value.filter(a => selectedUser.value === 'all' || a.user_id == selectedUser.value).length,
    approvedCount: logs.filter(l => l.status === 'approved').length
  }
})

const workerAvg = computed(() => { const l = reportLogs.value; return l.length ? Number(l.reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0) / l.length).toFixed(1) : '0' })
const workerAbsences = computed(() => absences.value.filter(a => a.user_id == selectedUser.value).length)

// ── Informe mensual per fisioterapeuta ──────────────────────────────────
function scheduleDayHours(schedule, jsDay) {
  // Un dia és laborable si té start/end i no està explícitament desactivat.
  // (Alguns horaris no inclouen el camp 'active'; s'assumeix actiu si té horari.)
  const cfg = schedule?.days?.find(d => (d.day === jsDay || d.day_num === jsDay))
  if (!cfg || cfg.active === false || !cfg.start || !cfg.end) return 0
  const [sh, sm] = String(cfg.start).split(':').map(Number)
  const [eh, em] = String(cfg.end).split(':').map(Number)
  return Math.max(0, (eh * 60 + em - sh * 60 - sm) / 60)
}

const selectedMonthLabel = computed(() =>
  (months.value.find(m => m.value === selectedMonth.value)?.label) || selectedMonth.value
)

// Una fila per fisioterapeuta actiu amb els agregats del mes seleccionat
const fisioMonthlyRows = computed(() => {
  const ym = selectedMonth.value
  const [y, m] = ym.split('-').map(Number)
  const days = new Date(y, m, 0).getDate()
  const monthStart = new Date(y, m - 1, 1)
  const monthEnd = new Date(y, m - 1, days, 23, 59, 59)

  const fisios = users.value
    .filter(u => u.role === 'worker' && u.active && u.job_profile === 'Fisioterapeuta')
    .sort((a, b) => a.name.localeCompare(b.name))

  return fisios.map(w => {
    const logs = workLogStore.logs.filter(l => l.user_id === w.id && l.date && l.date.substring(0, 7) === ym)
    const workedDates = new Set(logs.map(l => l.date.substring(0, 10)))
    const sched = workSchedules.value.find(s => s.id === w.work_schedule_id) || null

    let basic = 0
    if (sched) {
      workedDates.forEach(dateStr => {
        if (holidaySet.value.has(dateStr)) return
        basic += scheduleDayHours(sched, new Date(dateStr + 'T00:00:00').getDay())
      })
    }

    const compl = authCodes.value
      .filter(c => !c.revoked && Number(c.user_id) === Number(w.id))
      .filter(c => {
        const vf = c.valid_from ? new Date(c.valid_from) : null
        const vt = c.valid_to ? new Date(c.valid_to) : null
        return vf && vt && vf <= monthEnd && vt >= monthStart
      })
      .reduce((s, c) => s + Number(c.authorized_hours || 0), 0)

    const effective = logs.reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0)
    const unauth = logs.reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0), 0)
    const outZone = logs.reduce((s, l) => s + Number(l.hours_out_of_area || 0), 0)
    const authorized = basic + compl
    const deviation = effective - authorized

    return {
      id: w.id, name: w.name, dies: workedDates.size,
      basic, compl, authorized, effective, unauth, outZone, deviation
    }
  })
})

const hasFisioActivity = computed(() => fisioMonthlyRows.value.some(r => r.dies > 0))

function devText(d) {
  const v = Number(d || 0).toFixed(1)
  if (d > 0.25) return `▲ +${v}h`
  if (d < -0.25) return `▼ ${v}h`
  return `${v}h`
}
function devStyle(d) {
  if (d > 0.25) return 'color:var(--color-danger);font-weight:700;background:rgba(239,68,68,0.06);'
  if (d < -0.25) return 'color:var(--color-warning);font-weight:700;background:rgba(245,158,11,0.08);'
  return 'color:var(--color-success);'
}

// Impressió de l'informe (via iframe aïllat, sense bloqueig de popups)
function printFisioReport() {
  const rows = fisioMonthlyRows.value
  const f1 = (n) => Number(n || 0).toFixed(1)
  const devCell = (d) => {
    const v = f1(d)
    if (d > 0.25) return `<td class="dev over">▲ +${v}h</td>`
    if (d < -0.25) return `<td class="dev under">▼ ${v}h</td>`
    return `<td class="dev ok">${v}h</td>`
  }
  const body = rows.map(r => `
    <tr class="${r.dies === 0 ? 'inactive' : ''}">
      <td class="name">${r.name}</td>
      <td>${r.dies}</td>
      <td>${f1(r.basic)}h</td>
      <td>${f1(r.compl)}h</td>
      <td class="auth">${f1(r.authorized)}h</td>
      <td class="eff">${f1(r.effective)}h</td>
      <td class="${r.unauth > 0.01 ? 'bad' : 'muted'}">${r.unauth > 0.01 ? f1(r.unauth) + 'h' : '—'}</td>
      <td class="${r.outZone > 0.01 ? 'warn' : 'muted'}">${r.outZone > 0.01 ? f1(r.outZone) + 'h' : '—'}</td>
      ${devCell(r.deviation)}
    </tr>`).join('')

  const html = `<!doctype html><html lang="ca"><head><meta charset="utf-8"><title>Informe mensual fisioterapeutes — ${selectedMonthLabel.value}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #1e293b; margin: 24px; }
    h1 { font-size: 18px; margin: 0 0 2px; color: #1e293b; }
    .sub { color: #64748b; font-size: 12px; margin-bottom: 4px; }
    .legend { font-size: 10px; color: #64748b; margin: 8px 0 12px; }
    .legend b.over { color: #dc2626; } .legend b.under { color: #d97706; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; }
    th, td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: right; }
    th { background: #f1f5f9; font-weight: 700; text-align: right; }
    th:first-child, td.name { text-align: left; }
    td.name { font-weight: 600; }
    td.auth { font-weight: 700; }
    td.eff { font-weight: 700; color: #1d4ed8; }
    td.dev.over { color: #dc2626; font-weight: 700; background: #fef2f2; }
    td.dev.under { color: #d97706; font-weight: 700; background: #fffbeb; }
    td.dev.ok { color: #16a34a; }
    td.bad { color: #dc2626; font-weight: 700; background: #fef2f2; }
    td.warn { color: #d97706; font-weight: 700; background: #fffbeb; }
    td.muted { color: #cbd5e1; }
    tr.inactive td { color: #94a3b8; background: #fafafa; }
    .foot { margin-top: 12px; font-size: 9px; color: #94a3b8; }
    @media print { body { margin: 0; } @page { margin: 14mm; size: A4 landscape; } }
  </style></head><body>
    <h1>Informe mensual de jornada — Fisioterapeutes</h1>
    <div class="sub">${selectedMonthLabel.value} · CRT · Generat el ${new Date().toLocaleDateString('ca-ES')}</div>
    <div class="legend">Temps efectiu = només trams aprovats · Autoritzat = bàsic (conveni pels dies treballats, exclou festius) + complementàries amb codi · Desviació = efectiu − autoritzat (<b class="over">▲ excés</b> / <b class="under">▼ dèficit</b>)</div>
    <table>
      <thead><tr>
        <th>Fisioterapeuta</th><th>Dies</th><th>Bàsic</th><th>Compl. (codi)</th><th>Autoritzat</th><th>Efectiu</th><th>No autoritzat</th><th>Fora zona</th><th>Desviació</th>
      </tr></thead>
      <tbody>${body}</tbody>
    </table>
    <div class="foot">Document intern · dades personals (RGPD) · conservació 4 anys (art. 34.9 ET). ${rows.length} fisioterapeutes.</div>
  </body></html>`

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'; iframe.style.right = '0'; iframe.style.bottom = '0'
  iframe.style.width = '0'; iframe.style.height = '0'; iframe.style.border = '0'
  document.body.appendChild(iframe)
  const doc = iframe.contentWindow.document
  doc.open(); doc.write(html); doc.close()
  setTimeout(() => {
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    setTimeout(() => document.body.removeChild(iframe), 1000)
  }, 300)
}

function getWorkerPostalCode(userId) { return users.value.find(u => u.id === userId)?.postal_code_assigned || '—' }
// ... code ...
function getWorkerName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { weekday: 'short', day: '2-digit', month: 'short' }) }
function formatTime(d) { if (!d) return '—'; return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }
function statusBadge(s) { return { pending: 'badge-pending', approved: 'badge-success', rejected: 'badge-danger' }[s] || 'badge-primary' }
</script>
