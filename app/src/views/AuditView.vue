<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('audit_logs') }}</h1>
        <p class="page-subtitle">Registre d'auditoria · RDL 8/2019 · RGPD Art. 32 · LOPDGDD</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-outline" @click="exportCSV">⬇ Exportar CSV</button>
      </div>
    </div>

    <!-- Legal notice -->
    <div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:0.78rem;color:var(--color-text-secondary);display:flex;gap:10px;align-items:flex-start;">
      <span style="font-size:1rem;flex-shrink:0;">⚖️</span>
      <span>
        <strong>Obligació legal:</strong> El <strong>RDL 8/2019</strong> obliga a conservar els registres horaris <strong>4 anys</strong> i a garantir l'accés a la Inspecció de Treball.
        El <strong>RGPD Art. 32</strong> i la <strong>LOPDGDD</strong> exigeixen registrar qui accedeix i modifica dades personals.
        Aquest registre no es pot modificar ni eliminar.
      </span>
    </div>

    <!-- Summary stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
      <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:1.6rem;font-weight:700;color:var(--color-primary);">{{ statsToday }}</div>
        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">Accions avui</div>
      </div>
      <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:1.6rem;font-weight:700;color:var(--color-warning);">{{ statsLoginFailed }}</div>
        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">Intents fallits (7d)</div>
      </div>
      <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:1.6rem;font-weight:700;color:var(--color-success);">{{ statsApprovals }}</div>
        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">Aprovacions (7d)</div>
      </div>
      <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:1.6rem;font-weight:700;">{{ logs.length }}</div>
        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">Total registres</div>
      </div>
      <div class="card" style="padding:14px;text-align:center;">
        <div style="font-size:1rem;font-weight:700;color:var(--color-text-muted);">{{ retentionUntil }}</div>
        <div style="font-size:0.72rem;color:var(--color-text-muted);margin-top:2px;">Retenció fins (4 anys)</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card" style="padding:14px;margin-bottom:12px;">
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:0.72rem;font-weight:600;color:var(--color-text-muted);">DES DE</label>
          <input type="date" class="form-input" style="width:150px;font-size:0.82rem;" v-model="filterFrom" />
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:0.72rem;font-weight:600;color:var(--color-text-muted);">FINS A</label>
          <input type="date" class="form-input" style="width:150px;font-size:0.82rem;" v-model="filterTo" />
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:0.72rem;font-weight:600;color:var(--color-text-muted);">TREBALLADOR</label>
          <select class="form-input" style="width:180px;font-size:0.82rem;" v-model="filterUser">
            <option value="">Tots</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:0.72rem;font-weight:600;color:var(--color-text-muted);">CATEGORIA</label>
          <select class="form-input" style="width:180px;font-size:0.82rem;" v-model="filterCategory">
            <option value="">Totes</option>
            <option value="security">Seguretat (login/logout)</option>
            <option value="worktime">Registre horari</option>
            <option value="document">Documents i nòmines</option>
            <option value="absence">Absències i permisos</option>
            <option value="admin">Administració</option>
            <option value="data">Dades personals</option>
          </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:0.72rem;font-weight:600;color:var(--color-text-muted);">CERCA</label>
          <input class="form-input" style="width:180px;font-size:0.82rem;" v-model="searchQuery" placeholder="Acció, detalls, IP..." />
        </div>
        <button class="btn btn-outline btn-sm" @click="clearFilters" style="font-size:0.78rem;align-self:flex-end;">✕ Netejar</button>
      </div>
      <div style="margin-top:8px;font-size:0.72rem;color:var(--color-text-muted);">
        Mostrant {{ filteredLogs.length }} de {{ logs.length }} registres
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width:150px;">Data / Hora</th>
              <th style="width:160px;">Usuari</th>
              <th style="width:120px;">Categoria</th>
              <th style="width:180px;">Acció</th>
              <th>Detalls</th>
              <th style="width:120px;">IP</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in paginatedLogs" :key="log.id" :style="rowStyle(log.action)">
              <td style="font-size:0.78rem;white-space:nowrap;">
                <div style="font-weight:600;">{{ formatDate(log.created_at) }}</div>
                <div style="color:var(--color-text-muted);">{{ formatTime(log.created_at) }}</div>
              </td>
              <td style="font-size:0.82rem;">
                <div style="font-weight:600;">{{ getUserName(log.user_id) }}</div>
                <div v-if="getUserRole(log.user_id)" style="font-size:0.7rem;color:var(--color-text-muted);">{{ getUserRole(log.user_id) }}</div>
              </td>
              <td>
                <span :class="['badge', categoryBadge(log.action)]" style="font-size:0.65rem;">
                  {{ categoryLabel(log.action) }}
                </span>
              </td>
              <td>
                <span :class="['badge', actionBadge(log.action)]" style="font-size:0.7rem;">
                  {{ formatAction(log.action) }}
                </span>
              </td>
              <td style="font-size:0.78rem;max-width:300px;">
                <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                  {{ log.description || log.details || '—' }}
                </span>
              </td>
              <td>
                <code style="font-size:0.72rem;background:var(--color-bg);padding:2px 5px;border-radius:4px;">{{ log.ip_address || '—' }}</code>
              </td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td colspan="6" style="text-align:center;padding:40px;color:var(--color-text-muted);">
                Cap registre amb els filtres actuals
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0 0;border-top:1px solid var(--color-border-light);">
        <span style="font-size:0.78rem;color:var(--color-text-muted);">Pàgina {{ currentPage }} de {{ totalPages }}</span>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-outline btn-sm" :disabled="currentPage === 1" @click="currentPage--">← Anterior</button>
          <button class="btn btn-outline btn-sm" :disabled="currentPage === totalPages" @click="currentPage++">Següent →</button>
        </div>
      </div>
    </div>

    <!-- Legal footer -->
    <div style="margin-top:16px;padding:12px 16px;background:var(--color-bg);border-radius:8px;font-size:0.72rem;color:var(--color-text-muted);line-height:1.6;">
      <strong>Base legal:</strong>
      RDL 8/2019 (Art. 34.9 ET) · Retenció mínima 4 anys ·
      RGPD (UE) 2016/679 Art. 32 · LOPDGDD LO 3/2018 ·
      Aquest registre és inalterble i accessible a la Inspecció de Treball i Seguretat Social.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { i18n } from '../i18n'

const authStore = useAuthStore()

const t = (key) => i18n.t(key)
const searchQuery = ref('')
const filterFrom = ref('')
const filterTo = ref('')
const filterUser = ref('')
const filterCategory = ref('')
const currentPage = ref(1)
const PAGE_SIZE = 50

const logs = ref([])
const users = ref([])

async function fetchData() {
  try {
    [logs.value, users.value] = await Promise.all([db.getAuditLogs(), db.getUsers()])
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

// ── Category mapping ─────────────────────────────────────────────────────────
const ACTION_CATEGORIES = {
  security: ['LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'PASSWORD_RESET', 'CHANGE_PASSWORD', 'SESSION_EXPIRED'],
  worktime: ['CLOCK_IN', 'CLOCK_OUT', 'APPROVE_LOG', 'REJECT_LOG', 'MODIFY_WORK_LOG', 'APPROVE_GPS_REVIEW', 'REJECT_GPS_REVIEW', 'GPS_OUT_OF_AREA'],
  document: ['SIGN_DOCUMENT', 'SIGN_URGENT_DOCUMENT', 'UPLOAD_DOCUMENT', 'VIEW_DOCUMENT', 'DELETE_DOCUMENT', 'UPLOAD_PAYROLL', 'VIEW_PAYROLL', 'SIGN_PAYROLL'],
  absence: ['CREATE_ABSENCE', 'APPROVE_ABSENCE', 'REJECT_ABSENCE', 'CREATE_EXCEDENCIA', 'APPROVE_EXCEDENCIA'],
  admin: ['CREATE_USER', 'MODIFY_USER', 'DEACTIVATE_USER', 'DELETE_USER', 'EXPORT_DATA', 'START_ONBOARDING', 'COMPLETE_ONBOARDING'],
  data: ['ACCEPT_GEO_CONSENT', 'VIEW_PAYROLL', 'ACCESS_PERSONAL_DATA']
}

function getCategory(action) {
  for (const [cat, actions] of Object.entries(ACTION_CATEGORIES)) {
    if (actions.includes(action)) return cat
  }
  return 'other'
}

function categoryLabel(action) {
  const labels = { security: 'Seguretat', worktime: 'Horari', document: 'Documents', absence: 'Absències', admin: 'Admin', data: 'Dades', other: 'Altre' }
  return labels[getCategory(action)] || 'Altre'
}

function categoryBadge(action) {
  const badges = { security: 'badge-danger', worktime: 'badge-primary', document: 'badge-success', absence: 'badge-pending', admin: 'badge-primary', data: 'badge-warning', other: '' }
  return badges[getCategory(action)] || ''
}

function actionBadge(action) {
  if (['LOGIN_FAILED', 'REJECT_LOG', 'REJECT_ABSENCE', 'DEACTIVATE_USER', 'DELETE_USER'].includes(action)) return 'badge-danger'
  if (['APPROVE_LOG', 'APPROVE_ABSENCE', 'CLOCK_OUT', 'SIGN_DOCUMENT', 'SIGN_PAYROLL'].includes(action)) return 'badge-success'
  if (['LOGIN', 'CLOCK_IN', 'CREATE_ABSENCE'].includes(action)) return 'badge-primary'
  return 'badge-pending'
}

function rowStyle(action) {
  if (['LOGIN_FAILED', 'DEACTIVATE_USER', 'DELETE_USER'].includes(action)) return 'background:rgba(239,68,68,0.04);'
  if (action === 'LOGIN') return 'background:rgba(59,130,246,0.03);'
  return ''
}

const ACTION_LABELS = {
  LOGIN: 'Inici de sessió', LOGOUT: 'Tancament de sessió', LOGIN_FAILED: 'Intent fallit',
  PASSWORD_RESET: 'Reset contrasenya', CHANGE_PASSWORD: 'Canvi contrasenya',
  CLOCK_IN: 'Entrada', CLOCK_OUT: 'Sortida', APPROVE_LOG: 'Aprovació jornada',
  REJECT_LOG: 'Rebuig jornada', MODIFY_WORK_LOG: 'Modificació jornada',
  APPROVE_GPS_REVIEW: 'Aprovació GPS', REJECT_GPS_REVIEW: 'Rebuig GPS',
  SIGN_DOCUMENT: 'Firma document', SIGN_URGENT_DOCUMENT: 'Firma urgent',
  VIEW_PAYROLL: 'Visualització nòmina', SIGN_PAYROLL: 'Firma nòmina',
  CREATE_ABSENCE: 'Sol·licitud permís', APPROVE_ABSENCE: 'Aprovació permís',
  REJECT_ABSENCE: 'Rebuig permís', ACCEPT_GEO_CONSENT: 'Consentiment GPS',
  CREATE_USER: 'Alta treballador', MODIFY_USER: 'Modificació treballador',
  DEACTIVATE_USER: 'Baixa treballador', EXPORT_DATA: 'Exportació dades',
  GPS_OUT_OF_AREA: 'Fora de zona GPS'
}

function formatAction(action) { return ACTION_LABELS[action] || action }

// ── Filters ──────────────────────────────────────────────────────────────────
const filteredLogs = computed(() => {
  let result = [...logs.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))

  if (filterFrom.value) result = result.filter(l => l.created_at?.substring(0, 10) >= filterFrom.value)
  if (filterTo.value) result = result.filter(l => l.created_at?.substring(0, 10) <= filterTo.value)
  if (filterUser.value) { const uid = Number(filterUser.value); result = result.filter(l => Number(l.user_id) === uid) }
  if (filterCategory.value) result = result.filter(l => getCategory(l.action) === filterCategory.value)
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(l =>
      l.action?.toLowerCase().includes(q) ||
      (l.description || l.details || '').toLowerCase().includes(q) ||
      getUserName(l.user_id).toLowerCase().includes(q) ||
      (l.ip_address || '').includes(q)
    )
  }
  return result
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredLogs.value.length / PAGE_SIZE)))
const paginatedLogs = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE
  return filteredLogs.value.slice(start, start + PAGE_SIZE)
})

function clearFilters() {
  searchQuery.value = ''; filterFrom.value = ''; filterTo.value = ''
  filterUser.value = ''; filterCategory.value = ''; currentPage.value = 1
}

// Reset to page 1 whenever any filter changes
watch([filterFrom, filterTo, filterUser, filterCategory, searchQuery], () => { currentPage.value = 1 })

// ── Stats ─────────────────────────────────────────────────────────────────────
// Reactive date strings so stats stay correct if the tab is open past midnight
const statsToday = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return logs.value.filter(l => l.created_at?.substring(0, 10) === today).length
})
const statsLoginFailed = computed(() => {
  const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]
  return logs.value.filter(l => l.action === 'LOGIN_FAILED' && l.created_at?.substring(0, 10) >= weekAgo).length
})
const statsApprovals = computed(() => {
  const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]
  return logs.value.filter(l => ['APPROVE_LOG', 'APPROVE_ABSENCE'].includes(l.action) && l.created_at?.substring(0, 10) >= weekAgo).length
})
const retentionUntil = computed(() => {
  const d = new Date(); d.setFullYear(d.getFullYear() + 4)
  return d.toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' })
})
const today = computed(() => new Date().toISOString().split('T')[0])

// ── Helpers ───────────────────────────────────────────────────────────────────
function getUserName(id) { return users.value.find(u => u.id === id)?.name || 'Sistema' }
function getUserRole(id) {
  const r = users.value.find(u => u.id === id)?.role
  return r === 'admin' ? 'Administrador' : r === 'worker' ? 'Treballador' : ''
}
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }

// ── CSV Export ────────────────────────────────────────────────────────────────
function exportCSV() {
  const headers = ['ID', 'Data', 'Hora', 'Usuari', 'Rol', 'Categoria', 'Accio', 'Detalls', 'IP', 'User-Agent']
  const rows = filteredLogs.value.map(l => [
    l.id,
    formatDate(l.created_at),
    formatTime(l.created_at),
    `"${getUserName(l.user_id)}"`,
    getUserRole(l.user_id),
    categoryLabel(l.action),
    l.action,
    `"${(l.description || l.details || '').replace(/"/g, '""')}"`,
    l.ip_address || '',
    `"${(l.user_agent || '').replace(/"/g, '""')}"`
  ])
  const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `auditoria_crt_${today.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
  auditLog(authStore.userId, 'EXPORT_DATA', 'audit_log', null,
    `Exportació CSV auditoria: ${filteredLogs.value.length} registres (filtres: ${filterFrom.value || 'inici'} → ${filterTo.value || 'avui'})`)
}
</script>
