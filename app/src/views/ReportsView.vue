<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('reports') }}</h1><p class="page-subtitle">Informes configurables i dades de nòmines per treballador i període</p></div>
      <div class="page-actions" style="flex-wrap:wrap;">
        <select class="form-select" v-model="selectedMonth" style="width:180px;">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <select v-if="authStore.isAdmin" class="form-select" v-model="selectedUser" style="width:200px;">
          <option value="all">Tots els treballadors</option>
          <option v-for="u in workers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid mb-lg">
      <div class="stat-card primary">
        <div class="stat-label">{{ t('worked_hours') }}</div>
        <div class="stat-value">{{ formatH(summary.totalHours) }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('theoretical_hours') }}</div>
        <div class="stat-value">{{ summary.theoreticalHours }}h</div>
      </div>
      <div class="stat-card" style="border-bottom:3px solid var(--color-success);">
        <div class="stat-label" style="color:var(--color-success);">✓ H. Autoritzades</div>
        <div class="stat-value" style="color:var(--color-success);">{{ Number(summary.authorizedHours) >= 0.05 ? summary.authorizedHours + 'h' : '—' }}</div>
      </div>
      <div class="stat-card" style="border-bottom:3px solid var(--color-danger);">
        <div class="stat-label" style="color:var(--color-danger);">✗ H. No autoritzades</div>
        <div class="stat-value" style="color:var(--color-danger);">{{ Number(summary.unauthorizedHours) >= 0.05 ? summary.unauthorizedHours + 'h' : '—' }}</div>
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
                <td><strong>{{ Number(l.total_hours_worked || 0).toFixed(2) }}h</strong></td>
                <td><span class="badge" :class="statusBadge(l.status)">{{ t(l.status) || l.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Main Report Table -->
    <div class="card">
      <div class="card-header" style="flex-wrap:wrap;gap:8px;">
        <h3 class="card-title">{{ t('monthly_report') }}</h3>
        <div style="display:flex;gap:8px;margin-left:auto;">
          <button @click="downloadReport('pdf')"
            style="display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:1.5px solid #dc2626;background:rgba(220,38,38,0.06);color:#dc2626;font-weight:600;font-size:0.8rem;cursor:pointer;transition:all 0.15s;"
            @mouseenter="e=>e.currentTarget.style.background='rgba(220,38,38,0.14)'"
            @mouseleave="e=>e.currentTarget.style.background='rgba(220,38,38,0.06)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
            Descarregar PDF
          </button>
          <button @click="downloadReport('excel')"
            style="display:flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:1.5px solid #16a34a;background:rgba(22,163,74,0.06);color:#16a34a;font-weight:600;font-size:0.8rem;cursor:pointer;transition:all 0.15s;"
            @mouseenter="e=>e.currentTarget.style.background='rgba(22,163,74,0.14)'"
            @mouseleave="e=>e.currentTarget.style.background='rgba(22,163,74,0.06)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            Descarregar Excel
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th v-if="selectedUser === 'all'">Treballador</th>
              <th>Data</th>
              <th>Entrada</th>
              <th>Sortida</th>
              <th>Total h.</th>
              <th style="color:var(--color-success);">✓ Autorit.</th>
              <th style="color:var(--color-danger);">✗ No aut.</th>
              <th>📍 CP</th>
              <th>Estat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in reportLogs" :key="log.id"
              :style="log.status==='rejected' ? 'background:rgba(239,68,68,0.04);' : log.status==='pending' ? 'background:rgba(245,158,11,0.03);' : ''">
              <td v-if="selectedUser === 'all'" style="font-weight:500;">{{ getWorkerName(log.user_id) }}</td>
              <td style="font-size:0.82rem;">{{ formatDate(log.date) }}</td>
              <td style="font-variant-numeric:tabular-nums;">{{ formatTime(log.start_time) }}</td>
              <td style="font-variant-numeric:tabular-nums;color:var(--color-text-secondary);">{{ log.end_time ? formatTime(log.end_time) : '—' }}</td>
              <td><strong>{{ Number(log.total_hours_worked || 0).toFixed(2) }}h</strong></td>
              <td>
                <span v-if="Number(log.extra_hours_authorized||0) >= 0.05" style="color:var(--color-success);font-weight:600;">
                  +{{ Number(log.extra_hours_authorized).toFixed(1) }}h
                </span>
                <span v-else style="color:var(--color-text-muted);">—</span>
              </td>
              <td>
                <span v-if="Number(log.extra_hours_unauthorized||0) >= 0.05" style="color:var(--color-danger);font-weight:600;">
                  {{ Number(log.extra_hours_unauthorized).toFixed(1) }}h
                </span>
                <span v-else style="color:var(--color-text-muted);">—</span>
              </td>
              <td>
                <span v-if="log.location_match === false" style="color:var(--color-danger);font-size:0.78rem;">⚠️ Fora</span>
                <span v-else-if="log.location_match === true" style="color:var(--color-success);font-size:0.82rem;">✓</span>
                <span v-else style="color:var(--color-text-muted);">—</span>
              </td>
              <td>
                <span class="badge" :class="statusBadge(log.status)" style="font-size:0.7rem;">
                  {{ log.status==='approved' ? 'Aprovat' : log.status==='rejected' ? 'Rebutjat' : 'Pendent' }}
                </span>
              </td>
            </tr>
            <tr v-if="reportLogs.length === 0">
              <td :colspan="selectedUser === 'all' ? 9 : 8" style="text-align:center;padding:32px;color:var(--color-text-muted);">Cap registre per aquest període</td>
            </tr>
          </tbody>
          <!-- Totals row -->
          <tfoot v-if="reportLogs.length > 0">
            <tr style="background:var(--color-bg);font-weight:700;border-top:2px solid var(--color-border-light);">
              <td v-if="selectedUser === 'all'"></td>
              <td style="color:var(--color-text-muted);font-size:0.78rem;">{{ reportLogs.length }} jornades</td>
              <td></td><td></td>
              <td style="color:var(--color-primary);">{{ formatH(summary.totalHours) }}</td>
              <td style="color:var(--color-success);">{{ Number(summary.authorizedHours) >= 0.05 ? '+' + formatH(summary.authorizedHours) : '—' }}</td>
              <td style="color:var(--color-danger);">{{ Number(summary.unauthorizedHours) >= 0.05 ? formatH(summary.unauthorizedHours) : '—' }}</td>
              <td></td><td></td>
            </tr>
          </tfoot>
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
const selectedUser = ref(authStore.isAdmin ? 'all' : authStore.userId)
const workers = ref([])
const users = ref([])
const absences = ref([])

async function fetchData() {
  try {
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
    absences.value = await db.getAbsences()
    await workLogStore.loadLogs()
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
    totalHours: Number(logs.reduce((s, l) => s + Number(l.total_hours_worked || 0), 0)).toFixed(1),
    theoreticalHours: (logs.length * 8).toFixed(0),
    authorizedHours: Number(logs.reduce((s, l) => s + Number(l.extra_hours_authorized || 0), 0)).toFixed(1),
    unauthorizedHours: Number(logs.reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0), 0)).toFixed(1),
    absenceDays: absences.value.filter(a => selectedUser.value === 'all' || a.user_id == selectedUser.value).length,
    approvedCount: logs.filter(l => l.status === 'approved').length
  }
})

const workerAvg = computed(() => { const l = reportLogs.value; return l.length ? Number(l.reduce((s, l) => s + Number(l.total_hours_worked || 0), 0) / l.length).toFixed(1) : '0' })
const workerAbsences = computed(() => absences.value.filter(a => a.user_id == selectedUser.value).length)

function getWorkerPostalCode(userId) { return users.value.find(u => u.id === userId)?.postal_code_assigned || '—' }
function getWorkerName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { weekday: 'short', day: '2-digit', month: 'short' }) }
function formatTime(d) { if (!d) return '—'; return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Madrid' }) }
function formatH(hoursDecimal) {
  const total = Number(hoursDecimal) || 0
  const h = Math.floor(total); const m = Math.round((total - h) * 60)
  if (h === 0) return m + 'min'
  if (m === 0) return h + 'h'
  return h + 'h ' + m + 'min'
}
function statusBadge(s) { return { pending: 'badge-pending', approved: 'badge-success', rejected: 'badge-danger' }[s] || 'badge-primary' }

const monthLabel = computed(() => {
  const [y, m] = selectedMonth.value.split('-')
  return new Date(y, m - 1, 1).toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' })
})

function downloadReport(type) {
  const logs = reportLogs.value
  const workerName = selectedUser.value === 'all' ? 'Tots els treballadors' : getWorkerName(selectedUser.value)
  const period = monthLabel.value

  if (type === 'excel') {
    const headers = selectedUser.value === 'all'
      ? ['Treballador', 'Data', 'Entrada', 'Sortida', 'Total h.', 'H. Autorit.', 'H. No aut.', 'Estat']
      : ['Data', 'Entrada', 'Sortida', 'Total h.', 'H. Autorit.', 'H. No aut.', 'Estat']

    const rows = logs.map(l => {
      const base = [
        formatDate(l.date),
        formatTime(l.start_time),
        l.end_time ? formatTime(l.end_time) : '—',
        Number(l.total_hours_worked || 0).toFixed(2),
        Number(l.extra_hours_authorized || 0).toFixed(2),
        Number(l.extra_hours_unauthorized || 0).toFixed(2),
        l.status || '—'
      ]
      return selectedUser.value === 'all' ? [`"${getWorkerName(l.user_id)}"`, ...base] : base
    })

    const totals = selectedUser.value === 'all'
      ? ['TOTAL', '', '', '', summary.value.totalHours, summary.value.authorizedHours, summary.value.unauthorizedHours, '']
      : ['TOTAL', '', '', summary.value.totalHours, summary.value.authorizedHours, summary.value.unauthorizedHours, '']

    const outOfCpSection = selectedUser.value !== 'all' && outOfCpLogs.value.length > 0 ? [
      '',
      `"⚠️ Fitxatges fora del codi postal assignat (${outOfCpLogs.value.length})"`,
      ['Data', 'CP Assignat', 'Entrada', 'Sortida', 'Hores', 'Estat'].join(','),
      ...outOfCpLogs.value.map(l => [
        formatDate(l.date),
        l.assigned_postal_code || getWorkerPostalCode(l.user_id) || '—',
        formatTime(l.start_time),
        l.end_time ? formatTime(l.end_time) : '—',
        Number(l.total_hours_worked || 0).toFixed(2),
        l.status || '—'
      ].join(','))
    ] : []

    const csv = [
      `"Informe CRT RRHH — ${period} — ${workerName}"`,
      '',
      headers.join(','),
      ...rows.map(r => r.join(',')),
      '',
      totals.join(','),
      ...outOfCpSection
    ].join('\n')

    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `informe_${selectedMonth.value}_${selectedUser.value === 'all' ? 'tots' : workerName.replace(/\s+/g, '_')}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  if (type === 'pdf') {
    const statusLabel = { approved: 'Aprovat', pending: 'Pendent', rejected: 'Rebutjat' }
    const showWorker = selectedUser.value === 'all'

    const fmtH = (v) => {
      const total = Number(v) || 0
      if (total < 0.05) return '—'
      const h = Math.floor(total); const m = Math.round((total - h) * 60)
      if (h === 0) return m + 'min'
      if (m === 0) return h + 'h'
      return h + 'h ' + m + 'min'
    }

    const rows = logs.map(l => `
      <tr style="${l.status==='rejected'?'background:#fff5f5;':l.status==='pending'?'background:#fffbeb;':''}">
        ${showWorker ? `<td>${getWorkerName(l.user_id)}</td>` : ''}
        <td>${formatDate(l.date)}</td>
        <td style="font-variant-numeric:tabular-nums;">${formatTime(l.start_time)}</td>
        <td style="font-variant-numeric:tabular-nums;color:#64748b;">${l.end_time ? formatTime(l.end_time) : '—'}</td>
        <td style="font-weight:700;">${fmtH(l.total_hours_worked)}</td>
        <td style="color:#16a34a;">${Number(l.extra_hours_authorized || 0) >= 0.05 ? '+' + fmtH(l.extra_hours_authorized) : '—'}</td>
        <td style="color:#dc2626;">${fmtH(l.extra_hours_unauthorized)}</td>
        <td><span style="padding:2px 8px;border-radius:4px;font-size:0.73rem;font-weight:600;background:${l.status==='approved'?'#dcfce7':l.status==='rejected'?'#fee2e2':'#fef9c3'};color:${l.status==='approved'?'#166534':l.status==='rejected'?'#991b1b':'#854d0e'};">${statusLabel[l.status] || l.status}</span></td>
      </tr>`).join('')

    const totalHours = fmtH(summary.value.totalHours)
    const authH = Number(summary.value.authorizedHours) >= 0.05 ? '+' + fmtH(summary.value.authorizedHours) : '—'
    const unauthH = fmtH(summary.value.unauthorizedHours)

    const workerIndividual = !showWorker ? `
  <div style="margin-bottom:18px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
    <div style="font-weight:700;color:#00806C;margin-bottom:10px;font-size:12px;">Informe individual: ${workerName}</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Mitjana diària</div>
        <div style="font-size:14px;font-weight:700;">${workerAvg.value}h</div>
      </div>
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Dies treballats</div>
        <div style="font-size:14px;font-weight:700;">${logs.length}</div>
      </div>
      <div style="flex:1;min-width:100px;background:#f0fdf4;padding:8px 12px;border-radius:6px;border:1px solid #16a34a;">
        <div style="font-size:8px;color:#16a34a;text-transform:uppercase;">✓ H. Autoritzades</div>
        <div style="font-size:14px;font-weight:700;color:#16a34a;">${authH}</div>
      </div>
      <div style="flex:1;min-width:100px;background:#fff5f5;padding:8px 12px;border-radius:6px;border:1px solid #dc2626;">
        <div style="font-size:8px;color:#dc2626;text-transform:uppercase;">✗ No autoritzades</div>
        <div style="font-size:14px;font-weight:700;color:#dc2626;">${unauthH}</div>
      </div>
      <div style="flex:1;min-width:100px;background:white;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <div style="font-size:8px;color:#64748b;text-transform:uppercase;">Permisos usats</div>
        <div style="font-size:14px;font-weight:700;">${workerAbsences.value}</div>
      </div>
      ${outOfCpLogs.value.length > 0 ? `<div style="flex:1;min-width:100px;background:#fffbeb;padding:8px 12px;border-radius:6px;border:1px solid #f59e0b;">
        <div style="font-size:8px;color:#d97706;text-transform:uppercase;">⚠️ Fora CP</div>
        <div style="font-size:14px;font-weight:700;color:#d97706;">${outOfCpLogs.value.length}</div>
      </div>` : ''}
    </div>
  </div>` : ''

    const totalsRow = logs.length > 0 ? `
  <tfoot><tr style="background:#f1f5f9;font-weight:700;border-top:2px solid #00806C;">
    ${showWorker ? '<td></td>' : ''}
    <td style="color:#64748b;font-size:9px;">${logs.length} jornades</td>
    <td></td><td></td>
    <td style="color:#00806C;">${totalHours}</td>
    <td style="color:#16a34a;">${authH}</td>
    <td style="color:#dc2626;">${unauthH}</td>
    <td></td>
  </tr></tfoot>` : ''

    const outOfCpTable = !showWorker && outOfCpLogs.value.length > 0 ? `
  <div style="margin-top:20px;margin-bottom:6px;">
    <div style="font-weight:700;color:#d97706;font-size:11px;margin-bottom:8px;border-left:3px solid #f59e0b;padding-left:8px;">⚠️ Fitxatges fora del codi postal assignat (${outOfCpLogs.value.length})</div>
    <table>
      <thead><tr style="background:#92400e;">
        <th>Data</th><th>CP Assignat</th><th>Entrada</th><th>Sortida</th><th>Hores</th><th>Estat</th>
      </tr></thead>
      <tbody>${outOfCpLogs.value.map(l => `
        <tr style="background:#fffbeb;">
          <td>${formatDate(l.date)}</td>
          <td><span style="background:#fef9c3;color:#854d0e;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:600;">${l.assigned_postal_code || getWorkerPostalCode(l.user_id)}</span></td>
          <td style="font-variant-numeric:tabular-nums;">${formatTime(l.start_time)}</td>
          <td style="font-variant-numeric:tabular-nums;color:#64748b;">${l.end_time ? formatTime(l.end_time) : '—'}</td>
          <td style="font-weight:700;">${Number(l.total_hours_worked || 0).toFixed(2)}h</td>
          <td><span style="padding:2px 6px;border-radius:3px;font-size:9px;font-weight:600;background:${l.status==='approved'?'#dcfce7':l.status==='rejected'?'#fee2e2':'#fef9c3'};color:${l.status==='approved'?'#166534':l.status==='rejected'?'#991b1b':'#854d0e'};">${statusLabel[l.status] || l.status}</span></td>
        </tr>`).join('')}
      </tbody>
    </table>
  </div>` : ''

    const html = `<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <title>Informe ${period} — ${workerName}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 24px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 2px solid #00806C; padding-bottom: 12px; }
    .header h1 { font-size: 17px; color: #00806C; }
    .header p { font-size: 11px; color: #64748b; margin-top: 2px; }
    .summary { display: flex; gap: 10px; margin-bottom: 16px; }
    .summary-card { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; }
    .summary-card .label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
    .summary-card .value { font-size: 15px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #00806C; color: white; padding: 7px 8px; text-align: left; font-size: 10px; }
    td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    .footer { margin-top: 16px; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; display:flex; justify-content:space-between; }
    @media print { body { padding: 10px; } @page { margin: 1.2cm; size: A4 landscape; } }
  </style>
</head>
<body>
  <div class="header">
    <div>
      <h1>Informe mensual · CRT RRHH</h1>
      <p>${period} · ${workerName}</p>
    </div>
    <div style="text-align:right;font-size:10px;color:#64748b;">
      Generat: ${new Date().toLocaleDateString('ca-ES', { day: '2-digit', month: 'long', year: 'numeric' })}
    </div>
  </div>

  <div class="summary">
    <div class="summary-card"><div class="label">Hores treballades</div><div class="value">${totalHours}</div></div>
    <div class="summary-card"><div class="label">Hores teòriques</div><div class="value">${summary.value.theoreticalHours}h</div></div>
    <div class="summary-card" style="border-color:#16a34a;"><div class="label" style="color:#16a34a;">✓ H. Autoritzades</div><div class="value" style="color:#16a34a;">${authH}</div></div>
    <div class="summary-card" style="border-color:#dc2626;"><div class="label" style="color:#dc2626;">✗ H. No autoritzades</div><div class="value" style="color:#dc2626;">${unauthH}</div></div>
    <div class="summary-card"><div class="label">Absències</div><div class="value">${summary.value.absenceDays}</div></div>
  </div>

  ${workerIndividual}

  ${outOfCpTable}

  <table>
    <thead><tr>
      ${showWorker ? '<th>Treballador</th>' : ''}
      <th>Data</th><th>Entrada</th><th>Sortida</th><th>Total h.</th>
      <th style="color:#86efac;">✓ Autorit.</th><th style="color:#fca5a5;">✗ No aut.</th><th>Estat</th>
    </tr></thead>
    <tbody>${rows}</tbody>
    ${totalsRow}
  </table>

  <div class="footer">
    <span>RDL 8/2019 Art. 34.9 ET · RGPD (UE) 2016/679 · LOPDGDD LO 3/2018 · Retenció mínima 4 anys</span>
    <span>CRT — Document confidencial</span>
  </div>
  <script>window.onload = () => { window.print(); }<\/script>
</body>
</html>`

    const win = window.open('', '_blank')
    win.document.write(html)
    win.document.close()
  }
}
</script>
