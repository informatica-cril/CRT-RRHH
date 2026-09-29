<template>
  <div>
    <div class="page-header" v-if="activeTab !== 'menu'">
      <div>
        <h1 class="page-title">{{ t('dashboard_title') }}</h1>
        <p class="page-subtitle">{{ t('dashboard_subtitle') }}</p>
      </div>
      <div class="page-actions" v-if="authStore.isAdmin">
        <button class="btn btn-primary" @click="$router.push('/reports')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          {{ t('reports') }}
        </button>
      </div>
    </div>
    <div class="page-header" v-else>
      <div>
        <h1 class="page-title">Menú administrador</h1>
        <p class="page-subtitle">Accediu a totes les eines de gestió</p>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="stats-grid" v-if="activeTab !== 'menu'">
      <div class="stat-card primary" v-if="authStore.isAdmin">
        <div class="stat-label">{{ t('total_employees') }}</div>
        <div class="stat-value">{{ totalEmployees }}</div>
        <div class="stat-trend">↗ {{ t('active_workdays') }}</div>
      </div>
      <div class="stat-card" :class="{ primary: !authStore.isAdmin }">
        <div class="stat-label">{{ t('hours_today') }}</div>
        <div class="stat-value">{{ hoursToday }}</div>
        <div class="stat-trend" v-if="workLogStore.isWorking">⏱ {{ t('workday_active') }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('hours_week') }}</div>
        <div class="stat-value">{{ hoursWeek }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ t('hours_month') }}</div>
        <div class="stat-value">{{ hoursMonth }}</div>
      </div>
      <div class="stat-card" v-if="authStore.isAdmin">
        <div class="stat-label">{{ t('pending_requests') }}</div>
        <div class="stat-value">{{ pendingRequests }}</div>
      </div>
    </div>

    <div class="dashboard-grid" v-if="activeTab !== 'menu'">
      <!-- Left Column: Weekly Chart + Activity -->
      <div>
        <!-- Weekly Chart (Desktop/Tablet) -->
        <div class="card hide-on-mobile" id="weekly-chart">
          <div class="card-header">
            <h3 class="card-title">{{ t('weekly_summary') }}</h3>
            <span class="text-small text-muted">Hores treballades · setmana actual</span>
          </div>
          <div style="position:relative;padding-top:28px;">
            <!-- Reference line at 8h -->
            <div style="position:absolute;left:0;right:0;bottom:28px;pointer-events:none;z-index:1;">
              <div :style="{ position:'absolute', bottom: refLine8h + 'px', left:'8px', right:'8px', borderTop:'1.5px dashed rgba(100,116,139,0.25)' }">
                <span style="position:absolute;right:0;top:-14px;font-size:0.62rem;color:var(--color-text-muted);background:white;padding:0 3px;">8h</span>
              </div>
            </div>
            <!-- Bars — overflow visible so hour labels show above bars -->
            <div class="chart-bars" style="overflow:visible;height:180px;">
              <div class="chart-bar-group" v-for="(day, index) in weekDays" :key="index">
                <div style="position:relative;width:100%;display:flex;justify-content:center;">
                  <!-- Hour label above bar -->
                  <span v-if="day.hours > 0"
                    style="position:absolute;bottom:100%;margin-bottom:3px;font-size:0.68rem;font-weight:700;white-space:nowrap;line-height:1;"
                    :style="{ color: index === todayIndex ? 'var(--color-primary)' : '#475569' }">
                    {{ formatH(day.hours) }}
                  </span>
                  <div class="chart-bar"
                    :class="{ highlight: index === todayIndex, 'bar-empty': day.hours === 0 }"
                    :style="{
                      height: day.height + 'px',
                      width: '100%',
                      background: index === todayIndex
                        ? 'linear-gradient(180deg, #60a5fa, #1d4ed8)'
                        : day.hours >= 8
                          ? 'linear-gradient(180deg, #4ade80, #16a34a)'
                          : day.hours > 0
                            ? 'linear-gradient(180deg, #94a3b8, #475569)'
                            : 'rgba(226,232,240,0.4)'
                    }">
                  </div>
                </div>
                <span class="chart-bar-label"
                  :style="{ fontWeight: index === todayIndex ? '700' : '400', color: index === todayIndex ? 'var(--color-primary)' : 'var(--color-text-muted)', marginTop: '6px' }">
                  {{ day.label }}
                </span>
              </div>
            </div>
          </div>
          <!-- Legend -->
          <div style="display:flex;gap:16px;margin-top:8px;font-size:0.7rem;color:var(--color-text-muted);">
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:10px;height:10px;border-radius:3px;background:linear-gradient(180deg,#22c55e,#16a34a);"></div> ≥8h</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:10px;height:10px;border-radius:3px;background:linear-gradient(180deg,#3b82f6,#1d4ed8);"></div> Avui</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:10px;height:10px;border-radius:3px;background:linear-gradient(180deg,#64748b,#475569);"></div> &lt;8h</div>
          </div>
        </div>

        <!-- Weekly Summary List (Mobile) -->
        <div class="card show-on-mobile" id="weekly-summary-mobile">
          <div class="card-header">
            <h3 class="card-title">{{ t('weekly_summary') }}</h3>
          </div>
          <div class="mobile-summary-list">
            <div v-for="(day, index) in weekDays" :key="index" class="mobile-summary-item" 
              :style="index === todayIndex ? 'border-left: 3px solid var(--color-accent)' : ''">
              <span class="mobile-summary-day">{{ day.fullLabel || day.label }}</span>
              <span class="mobile-summary-value">{{ day.hours }}h</span>
            </div>
          </div>
        </div>

        <!-- Admin Analytics Chart (Desktop/Tablet) -->
        <div v-if="authStore.isAdmin" class="card mt-lg hide-on-mobile" id="admin-analytics-chart">
          <div class="card-header">
            <h3 class="card-title">📊 Analítica Mensual</h3>
            <div class="text-small text-muted">Evolució d'Hores Extres i Absències (6 mesos)</div>
          </div>
          <div style="height: 250px; position: relative; width: 100%; min-width: 0;">
            <Bar v-if="analyticsChartData" :data="analyticsChartData" :options="chartOptions" />
            <div v-else class="empty-state">Carregant dades...</div>
          </div>
        </div>

        <!-- Admin: Recent Activity -->
        <div v-if="authStore.isAdmin" class="card mt-lg" id="admin-activity">
          <div class="card-header" style="flex-wrap:wrap;gap:8px;">
            <h3 class="card-title">{{ t('recent_activity') }}</h3>
            <div style="display:flex;align-items:center;gap:8px;margin-left:auto;">
              <!-- Status filter -->
              <select v-model="activityFilter" style="font-size:0.78rem;padding:4px 8px;border-radius:8px;border:1px solid var(--color-border-light);background:var(--color-bg);color:var(--color-text-secondary);cursor:pointer;">
                <option value="all">Tots</option>
                <option value="active">En actiu</option>
                <option value="pending">Pendents</option>
                <option value="approved">Aprovats</option>
              </select>
              <button class="btn btn-outline btn-sm" @click="$router.push('/work-logs')" style="font-size:0.75rem;padding:4px 10px;">
                Veure tots →
              </button>
            </div>
          </div>

          <!-- Who's working now -->
          <div v-if="activeWorkers.length > 0" style="background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.2);border-radius:10px;padding:10px 14px;margin-bottom:14px;">
            <div style="font-size:0.72rem;font-weight:700;color:var(--color-success);letter-spacing:0.05em;margin-bottom:8px;text-transform:uppercase;">
              ● En actiu ara — {{ activeWorkers.length }} treballador{{ activeWorkers.length !== 1 ? 's' : '' }}
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <div v-for="w in activeWorkers" :key="w.id"
                style="display:flex;align-items:center;gap:5px;background:white;border:1px solid rgba(34,197,94,0.3);border-radius:20px;padding:3px 10px 3px 5px;font-size:0.78rem;">
                <div style="width:22px;height:22px;border-radius:50%;background:var(--color-success);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.6rem;flex-shrink:0;">
                  {{ initials(w.name) }}
                </div>
                <span style="font-weight:600;">{{ w.name.split(' ')[0] }}</span>
                <span style="color:var(--color-text-muted);font-size:0.7rem;">{{ formatTime(w.log.start_time) }}</span>
              </div>
            </div>
          </div>

          <!-- Timeline grouped by date -->
          <div v-if="activityGroups.length === 0" class="empty-state" style="padding:24px 0;">
            <p style="color:var(--color-text-muted);">Cap registre recent</p>
          </div>

          <div v-for="group in activityGroups" :key="group.label">
            <div style="font-size:0.7rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.06em;padding:10px 0 6px;border-bottom:1px solid var(--color-border-light);margin-bottom:2px;">
              {{ group.label }}
            </div>
            <div v-for="log in group.logs" :key="log.id"
              style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(0,0,0,0.04);">
              <!-- Avatar initials -->
              <div :style="{
                width:'32px',height:'32px',borderRadius:'50%',flexShrink:0,
                background: log.status === 'approved' ? 'var(--color-success)' : log.status === 'rejected' ? 'var(--color-danger)' : log.start_time && !log.end_time ? 'var(--color-success)' : 'var(--color-primary)',
                color:'white',display:'flex',alignItems:'center',justifyContent:'center',
                fontWeight:'700',fontSize:'0.65rem'
              }">
                {{ initials(getUserName(log.user_id)) }}
              </div>
              <!-- Info -->
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:0.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  {{ getUserName(log.user_id) }}
                </div>
                <div style="font-size:0.72rem;color:var(--color-text-muted);">
                  {{ formatTime(log.start_time) }}
                  <span v-if="log.end_time"> → {{ formatTime(log.end_time) }}</span>
                  <span v-else style="color:var(--color-success);font-weight:600;"> → en curs</span>
                  <span v-if="Number(log.total_hours_worked) > 0" style="margin-left:6px;font-weight:600;color:var(--color-text);">
                    {{ formatH(log.total_hours_worked) }}
                  </span>
                  <span v-if="Number(log.hours_out_of_area) > 0" style="margin-left:4px;color:var(--color-warning);" title="Hores fora de zona">
                    · ⚠️ {{ formatH(log.hours_out_of_area) }} fora
                  </span>
                </div>
              </div>
              <!-- Status badge -->
              <span class="badge" :class="log.start_time && !log.end_time ? 'badge-success' : statusBadge(log.status)"
                style="font-size:0.65rem;padding:2px 7px;white-space:nowrap;flex-shrink:0;">
                {{ log.start_time && !log.end_time ? '● Actiu' : t(log.status) || log.status }}
              </span>
            </div>
          </div>

          <div v-if="hasMoreActivity" style="text-align:center;padding-top:10px;">
            <button class="btn btn-outline btn-sm" @click="activityDaysLimit += 4; activityLimit += 10" style="font-size:0.75rem;">
              Mostrar més dies →
            </button>
          </div>
        </div>

        <!-- Worker: Quick link to full dashboard -->
        <div v-if="authStore.isWorker" class="card mt-lg">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
              <h3 class="card-title">Fitxatge i Calendari</h3>
              <p class="text-small text-muted">Sol·licitud de permisos, calendari laboral i fitxatge</p>
            </div>
            <button class="btn btn-accent" @click="$router.push('/worker')">Obrir →</button>
          </div>
        </div>
      </div>

      <!-- Right Column: Time Tracker + Pending -->
      <div>
        <!-- Time Tracker Widget -->
        <div class="time-tracker" id="time-tracker" v-if="authStore.user?.job_profile !== 'Gerencia'">
          <div class="tracker-label">{{ t('time_tracker') }}</div>
          <div class="tracker-time">{{ workLogStore.formattedElapsed }}</div>
          <div class="tracker-buttons" v-if="workLogStore.isWorking">
            <button class="tracker-btn stop" @click="endWorkday" :title="t('end_workday')">⏹</button>
          </div>
          <div v-else style="font-size: 0.85rem; opacity: 0.7; margin-top: 8px;">
            {{ t('workday_not_started') }}
          </div>
        </div>

        <!-- Pending Approvals (Admin) -->
        <div v-if="authStore.isAdmin && pendingLogs.length > 0" class="card mt-lg" id="pending-approvals">
          <div class="card-header">
            <h3 class="card-title">{{ t('pending_approvals') }}</h3>
            <span class="badge badge-pending">{{ pendingLogs.length }}</span>
          </div>
          <div v-for="log in pendingLogs.slice(0, 5)" :key="log.id"
            style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--color-border-light);">
            <div>
              <div style="font-weight: 500; font-size: 0.87rem;">{{ getUserName(log.user_id) }}</div>
              <div style="font-size: 0.78rem; color: var(--color-text-secondary);">{{ formatDate(log.date) }} · {{ formatH(log.total_hours_worked) }}</div>
            </div>
            <div style="display: flex; gap: 4px;">
              <button class="btn btn-success btn-sm" @click="approveLog(log.id)">✓</button>
              <button class="btn btn-danger btn-sm" @click="rejectLogModal(log.id)">✗</button>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="card mt-lg">
          <div class="card-header">
            <h3 class="card-title">{{ t('extra_hours') }}</h3>
          </div>
          <div style="text-align: center; padding: 16px 0;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--color-warning);">
              {{ totalExtraHours }}
            </div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 4px;">
              {{ t('monthly_summary') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Admin: Worker Map by CP ═══ -->
    <div v-if="authStore.isAdmin && activeTab !== 'menu'" class="card mt-lg" id="worker-map-card">
      <div class="card-header" style="margin-bottom:0;">
        <h3 class="card-title">🗺️ Mapa de treballadors per zona</h3>
        <div class="text-small text-muted">{{ cpZoneSummary.length }} zones · {{ totalZoneWorkers }} treballadors assignats</div>
      </div>

      <!-- Zones principals (≥2 treballadors diferents) -->
      <div v-if="majorZones.length" style="margin:14px 0 8px;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-text-muted);margin-bottom:8px;">Zones principals</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <div v-for="zone in majorZones" :key="zone.cp"
            style="display:flex;align-items:center;gap:6px;background:var(--color-bg);border:1px solid var(--color-border-light);border-radius:20px;padding:5px 12px;font-size:0.78rem;max-width:420px;">
            <span :style="{ display:'inline-block', width:'10px', height:'10px', borderRadius:'50%', flexShrink:0, background: zone.color }"></span>
            <strong style="white-space:nowrap;">{{ zone.cp === 'TOTS_BCN' ? '🗺️ Tots BCN' : zone.cp }}</strong>
            <span class="text-muted">—</span>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ zone.workers.map(w => w.name.split(' ')[0]).join(', ') }}</span>
            <span style="color:var(--color-text-muted);font-size:0.7rem;white-space:nowrap;margin-left:2px;">· {{ zone.annualHours }}h</span>
          </div>
        </div>
      </div>

      <!-- Zones secundàries (1 treballador) — col·lapsables -->
      <div v-if="minorZones.length" style="margin-bottom:10px;">
        <button @click="showMinorZones = !showMinorZones"
          style="display:flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-text-muted);padding:6px 0;">
          <span>{{ showMinorZones ? '▲' : '▼' }}</span>
          Zones individuals ({{ minorZones.length }})
        </button>
        <div v-if="showMinorZones" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:4px;">
          <div v-for="zone in minorZones" :key="zone.cp"
            style="display:flex;align-items:center;gap:5px;font-size:0.72rem;padding:3px 6px;border-radius:6px;background:var(--color-bg);">
            <span :style="{ display:'inline-block', width:'8px', height:'8px', borderRadius:'50%', flexShrink:0, background: zone.color }"></span>
            <strong>{{ zone.cp }}</strong>
            <span class="text-muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">{{ zone.workers.map(w => w.name.split(' ')[0]).join(', ') }}</span>
            <span style="color:var(--color-text-muted);flex-shrink:0;">{{ zone.annualHours }}h</span>
          </div>
        </div>
      </div>

      <!-- Map container -->
      <div id="workers-leaflet-map" style="height:420px;border-radius:12px;overflow:hidden;border:1px solid var(--color-border-light);"></div>
    </div>

    <!-- ═══ Admin Menu Grid ═══ -->
    <div v-if="activeTab === 'menu'" class="menu-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:16px;margin-top:8px;padding-bottom:100px;">
      <div class="card stat-card primary" @click="$router.push('/absences')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">📅</span>
        <div style="font-weight:700;font-size:1rem;">Absències</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/employees')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">👥</span>
        <div style="font-weight:700;font-size:1rem;">Treballadors</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/documents')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">📄</span>
        <div style="font-weight:700;font-size:1rem;">Documentació</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/excedencies')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">⏳</span>
        <div style="font-weight:700;font-size:1rem;">Excedències</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/reports')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">📊</span>
        <div style="font-weight:700;font-size:1rem;">Informes</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/payrolls-admin')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">💰</span>
        <div style="font-weight:700;font-size:1rem;">Nòmines</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/chat-admin')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">💬</span>
        <div style="font-weight:700;font-size:1rem;">Xat (Super)</div>
      </div>
      <div class="card stat-card primary" @click="$router.push('/audit')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;">
        <span style="font-size:2.5rem;">🛡️</span>
        <div style="font-weight:700;font-size:1rem;">Auditoria</div>
      </div>
      <div class="card stat-card" @click="$router.push('/permisos-config')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(0,0,0,0.05);">
        <span style="font-size:2.5rem;">⚙️</span>
        <div style="font-weight:700;font-size:1rem;">Config Permisos</div>
      </div>
      <div class="card stat-card" @click="$router.push('/auth-codes')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(0,0,0,0.05);">
        <span style="font-size:2.5rem;">🔐</span>
        <div style="font-weight:700;font-size:1rem;">Codis Auth</div>
      </div>
      <div class="card stat-card" @click="$router.push('/settings')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(0,0,0,0.05);">
        <span style="font-size:2.5rem;">👤</span>
        <div style="font-weight:700;font-size:1rem;">Configuració</div>
      </div>
      <div class="card stat-card" @click="handleLogout" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(239,68,68,0.1);color:var(--color-danger);">
        <span style="font-size:2.5rem;">🚪</span>
        <div style="font-weight:700;font-size:1rem;">Tancar Sessió</div>
      </div>
    </div>

    <div class="rdl-notice">{{ t('rdl_notice') }} · {{ t('rdl_retention') }}</div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay" @click.self="showRejectModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ t('reject') }}</h3>
          <button class="icon-btn" @click="showRejectModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('rejection_reason') }}</label>
          <textarea class="form-textarea" v-model="rejectReason"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRejectModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-danger" @click="confirmReject">{{ t('reject') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
// import { db, BARCELONA_HOLIDAYS_2026 } from '../services/db'
import { db } from '../services/db'
import { getCurrentPosition, BARCELONA_POSTAL_CODES, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { getPolygon, getGeoJsonBounds } from '../services/geoPolygonService'
import { i18n } from '../i18n'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const router = useRouter()
const route = useRoute()
const t = (key) => i18n.t(key)

const props = defineProps({ initialTab: { type: String, default: '' } })
const activeTab = computed(() => props.initialTab || (route.name === 'admin-menu' ? 'menu' : 'tauler'))

const showRejectModal = ref(false)
const rejectReason = ref('')
const rejectLogId = ref(null)

const totalEmployees = ref(0)
const users = ref([])

async function fetchStats() {
  try {
    const [allUsers] = await Promise.all([
      db.getUsers(),
      workLogStore.loadLogs()
    ])
    users.value = allUsers
    totalEmployees.value = allUsers.filter(u => u.role === 'worker' && u.active).length
  } catch (e) {
    console.error(e)
  }
}

function formatH(hoursDecimal) {
  const total = Number(hoursDecimal) || 0
  const h = Math.floor(total)
  const m = Math.round((total - h) * 60)
  if (h === 0) return m + 'min'
  if (m === 0) return h + 'h'
  return h + 'h ' + m + 'min'
}

const hoursToday = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 10) === today && (!uid || l.user_id === uid))
    .reduce((s, l) => {
      let h = Number(l.total_hours_worked || 0)
      if (!l.end_time && l.start_time) {
        const start = new Date(l.start_time)
        h += Math.max(0, (new Date() - start) / 3600000)
      }
      return s + h
    }, 0))
})

const hoursWeek = computed(() => {
  const now = new Date(); const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1); weekStart.setHours(0,0,0,0)
  const uid = authStore.isAdmin ? null : authStore.userId
  const ws = weekStart.toISOString().split('T')[0]
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 10) >= ws && (!uid || l.user_id === uid))
    .reduce((s, l) => s + Number(l.total_hours_worked || 0), 0))
})

const hoursMonth = computed(() => {
  const month = new Date().toISOString().slice(0, 7)
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 7) === month && (!uid || l.user_id === uid))
    .reduce((s, l) => s + Number(l.total_hours_worked || 0), 0))
})

const pendingRequests = ref(0)

async function fetchPendingCount() {
  const absences = await db.getAbsences()
  const pendingAbs = absences.filter(a => a.approved === null).length
  pendingRequests.value = workLogStore.logs.filter(l => l.status === 'pending').length + pendingAbs
}

const pendingLogs = computed(() => workLogStore.logs.filter(l => l.status === 'pending').sort((a, b) => new Date(b.date) - new Date(a.date)))

// ── Activity feed ──────────────────────────────────────────────────────────
const activityFilter = ref('all')
const activityLimit = ref(10)
const activityDaysLimit = ref(3)

const activeWorkers = computed(() => {
  const active = workLogStore.logs.filter(l => l.start_time && !l.end_time)
  return active.map(log => ({
    id: log.user_id,
    name: getUserName(log.user_id),
    log
  })).filter((w, i, arr) => arr.findIndex(x => x.id === w.id) === i)
})

const filteredActivityLogs = computed(() => {
  let logs = [...workLogStore.logs]
  if (activityFilter.value === 'active') logs = logs.filter(l => l.start_time && !l.end_time)
  else if (activityFilter.value === 'pending') logs = logs.filter(l => l.status === 'pending')
  else if (activityFilter.value === 'approved') logs = logs.filter(l => l.status === 'approved')
  return logs.sort((a, b) => {
    if ((b.date || '') !== (a.date || '')) return (b.date || '').localeCompare(a.date || '')
    return (b.id || 0) - (a.id || 0)
  })
})

const totalActivityLogs = computed(() => filteredActivityLogs.value.length)
const hasMoreActivity = computed(() => activityDaysLimit.value < 30)

const activityGroups = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0]
  const cutoff = new Date(Date.now() - activityDaysLimit.value * 86400000).toISOString().split('T')[0]
  const inWindow = filteredActivityLogs.value.filter(l => (l.date || '').substring(0, 10) >= cutoff)
  const limited = inWindow.slice(0, activityLimit.value)
  const groups = []
  const seen = {}
  for (const log of limited) {
    const d = (log.date || '').substring(0, 10)
    let label = d === today ? 'Avui' : d === yesterday ? 'Ahir' : formatDate(d)
    if (!seen[d]) { seen[d] = true; groups.push({ label, logs: [] }) }
    groups[groups.length - 1].logs.push(log)
  }
  return groups
})

function initials(name = '') {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const totalExtraHours = computed(() => {
  const month = new Date().toISOString().slice(0, 7)
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 7) === month && (!uid || l.user_id === uid))
    .reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0) + Number(l.extra_hours_authorized || 0), 0))
})

const todayIndex = computed(() => { const d = new Date().getDay(); return d === 0 ? 6 : d - 1 })

const CHART_MAX_HEIGHT = 170
const CHART_REFERENCE_HOURS = 8

const weekDays = computed(() => {
  const dayNames = ['Dl', 'Dt', 'Dc', 'Dj', 'Dv', 'Ds', 'Dg']
  const now = new Date(); const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1); weekStart.setHours(0,0,0,0)
  const uid = authStore.isAdmin ? null : authStore.userId
  const ws = weekStart.toISOString().split('T')[0]
  const weekLogs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 10) >= ws && (!uid || l.user_id === uid))
  const dayHours = dayNames.map((label, i) => {
    const dayLogs = weekLogs.filter(l => { const d = new Date(l.date.substring(0, 10)).getDay(); return (d === 0 ? 6 : d - 1) === i })
    return { label, hours: Math.round(dayLogs.reduce((s, l) => s + Number(l.total_hours_worked || 0), 0) * 100) / 100 }
  })
  const maxHours = Math.max(...dayHours.map(d => d.hours), CHART_REFERENCE_HOURS)
  return dayHours.map(d => ({ ...d, height: Math.max(d.hours > 0 ? 8 : 3, (d.hours / maxHours) * CHART_MAX_HEIGHT) }))
})

// Pixel position of the 8h reference line inside the chart
const refLine8h = computed(() => {
  const maxHours = Math.max(...weekDays.value.map(d => d.hours), CHART_REFERENCE_HOURS)
  return Math.round((CHART_REFERENCE_HOURS / maxHours) * CHART_MAX_HEIGHT)
})

// ── Admin Analytics Chart Data ──────────────────────────────────────────
const analyticsChartData = ref(null)

async function fetchAnalyticsData() {
  const labels = []
  const dataExt = []
  const dataAbs = []
  
  const allAbsences = await db.getAbsences()
  
  for (let i = 5; i >= 0; i--) {
     const d = new Date()
     d.setMonth(d.getMonth() - i)
     const mStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
     const labelStr = d.toLocaleDateString('ca-ES', { month: 'short', year: '2-digit' })
     labels.push(labelStr)
     
     // Extra hours
     const totalExt = workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === mStr).reduce((s, l) => s + Number(l.extra_hours_authorized || 0) + Number(l.extra_hours_unauthorized || 0), 0)
     dataExt.push(Number(totalExt).toFixed(1))
     
     // Absences
     const totalAbs = allAbsences.filter(a => a.start_date && a.start_date.substring(0, 7) === mStr).length
     dataAbs.push(totalAbs)
  }
  
  analyticsChartData.value = {
    labels,
    datasets: [
      {
        label: 'Desviació Horària (+h)',
        backgroundColor: '#f59e0b',
        borderRadius: 4,
        data: dataExt
      },
      {
        label: 'Ausències i Permisos (dies)',
        backgroundColor: '#ef4444',
        borderRadius: 4,
        data: dataAbs
      }
    ]
  }
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { 
    legend: { 
      position: 'bottom', 
      labels: { 
        usePointStyle: true, 
        boxWidth: 8, 
        padding: 10,
        font: { size: 10, family: 'Outfit, sans-serif' } 
      } 
    } 
  },
  scales: {
    y: { 
      beginAtZero: true, 
      grid: { color: 'rgba(0,0,0,0.05)' },
      ticks: { font: { size: 9 } }
    },
    x: { 
      grid: { display: false },
      ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 }
    }
  }
}

// ── CP Zone Summary for map pills and markers ──────────────────────────
const ZONE_COLORS = ['#3b82f6','#f97316','#10b981','#8b5cf6','#ef4444','#f59e0b','#06b6d4','#ec4899','#84cc16','#6366f1']
const cpZoneSummary = ref([])
const showMinorZones = ref(false)

const majorZones = computed(() => cpZoneSummary.value
  .filter(z => z.cp === 'TOTS_BCN' || z.workers.length >= 2)
  .sort((a, b) => { if (a.cp === 'TOTS_BCN') return -1; if (b.cp === 'TOTS_BCN') return 1; return b.workers.length - a.workers.length })
)
const minorZones = computed(() => cpZoneSummary.value.filter(z => z.cp !== 'TOTS_BCN' && z.workers.length < 2).sort((a, b) => a.cp.localeCompare(b.cp)))
const totalZoneWorkers = computed(() => {
  const ids = new Set()
  cpZoneSummary.value.forEach(z => z.workers.forEach(w => ids.add(w.id)))
  return ids.size
})

async function fetchCpZoneSummary() {
  const bulk = await db.getUsersBulk().catch(() => ({ users: [], work_schedules: [], zone_assignments: [] }))
  const scheduleMap = Object.fromEntries(bulk.work_schedules.map(s => [s.id, s]))
  const workers = bulk.users.filter(u => u.role === 'worker' && u.active)
  const zoneMap = {}
  
  const BCN_THRESHOLD = 30 // workers with >= 30 BCN CPs show grouped as "Tots BCN"

  const addToZone = (cp, w, annualHours) => {
    if (!zoneMap[cp]) {
      const isMuni = !!VALLES_MUNICIPALITIES[cp]
      const zoneName = isMuni ? VALLES_MUNICIPALITIES[cp].name : (BARCELONA_POSTAL_CODES[cp]?.zone || cp)
      zoneMap[cp] = { cp, workers: [], annualHours: 0, zone: zoneName, color: ZONE_COLORS[Object.keys(zoneMap).length % ZONE_COLORS.length] }
    }
    if (!zoneMap[cp].workers.find(e => e.id === w.id)) zoneMap[cp].workers.push(w)
    zoneMap[cp].annualHours += annualHours
  }

  workers.forEach(w => {
    const activeZone = bulk.zone_assignments.find(z => z.users.some(u => u.id === w.id))
    const sched = scheduleMap[w.work_schedule_id] || null

    const codes = activeZone?.postal_codes || (w.postal_code_assigned && w.work_type !== 'DOMICILIARIA_VALLES' ? [w.postal_code_assigned] : [])
    const munis = activeZone?.municipalities || []

    const zonesToPlot = [...codes]
    if (w.work_type === 'DOMICILIARIA_VALLES') zonesToPlot.push(...munis)

    const weeklyHours = sched ? parseFloat(sched.total_hours_weekly) : 40
    const annualHours = Math.round(weeklyHours * 46.5)

    const bcnCodes = codes.filter(c => c.startsWith('08'))
    if (bcnCodes.length >= BCN_THRESHOLD) {
      // Group under a single "Tots BCN" entry
      if (!zoneMap['TOTS_BCN']) {
        zoneMap['TOTS_BCN'] = { cp: 'TOTS_BCN', workers: [], annualHours: 0, zone: 'Tots els CP de Barcelona', color: '#3b82f6' }
      }
      if (!zoneMap['TOTS_BCN'].workers.find(e => e.id === w.id)) zoneMap['TOTS_BCN'].workers.push(w)
      zoneMap['TOTS_BCN'].annualHours += annualHours
      // Still show non-BCN zones (e.g. Vallès municipalities) individually
      zonesToPlot.filter(cp => !cp.startsWith('08')).forEach(cp => addToZone(cp, w, annualHours))
    } else {
      zonesToPlot.forEach(cp => addToZone(cp, w, annualHours))
    }
  })
  cpZoneSummary.value = Object.values(zoneMap)
}

// ── Leaflet Map initialization ─────────────────────────────────────────────
// Centroidal coordinates used only for placing the label marker
const CP_COORDS = {
  '08001': [41.3800, 2.1710], '08002': [41.3850, 2.1766], '08003': [41.3861, 2.1860],
  '08004': [41.3740, 2.1600], '08005': [41.3960, 2.2020], '08006': [41.4020, 2.1540],
  '08007': [41.3920, 2.1540], '08008': [41.3880, 2.1580], '08009': [41.3945, 2.1620],
  '08010': [41.3965, 2.1710], '08011': [41.3810, 2.1490], '08012': [41.4030, 2.1610],
  '08013': [41.3990, 2.1800], '08014': [41.3680, 2.1390], '08015': [41.3740, 2.1540],
  '08016': [41.4275, 2.1900], '08017': [41.4150, 2.1390], '08018': [41.3960, 2.1980],
  '08019': [41.4070, 2.2100], '08020': [41.4150, 2.2120], '08021': [41.3940, 2.1340],
  '08022': [41.4220, 2.1490], '08023': [41.4320, 2.1700], '08024': [41.4110, 2.1720],
  '08025': [41.4050, 2.1740], '08026': [41.4110, 2.1840], '08027': [41.4200, 2.1990],
  '08028': [41.3480, 2.1200], '08029': [41.3870, 2.1490], '08030': [41.4220, 2.2070],
  '08031': [41.4360, 2.1850], '08032': [41.4330, 2.1980], '08033': [41.4300, 2.2100],
  '08034': [41.3870, 2.1180], '08035': [41.4130, 2.1290], '08036': [41.3890, 2.1390],
  '08037': [41.3980, 2.1680], '08038': [41.3580, 2.1730], '08039': [41.3820, 2.1880],
  '08040': [41.3520, 2.1570], '08041': [41.4440, 2.2020], '08042': [41.4620, 2.2360]
}

function loadLeaflet() {
  return new Promise((resolve) => {
    if (window.L) return resolve()
    const link = document.createElement('link')
    link.rel = 'stylesheet'; link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    script.onload = resolve; document.head.appendChild(script)
  })
}

// Shared: build a Leaflet map with CP zone polygons + markers
// zones = array of { cp, color, workers (may be empty for worker map), annualHours, zone }
async function buildLeafletZoneMap(containerId, zones, mapOptions = {}) {
  await loadLeaflet()
  await nextTick()
  const container = document.getElementById(containerId)
  if (!container) return null
  // Destroy existing map instance if any
  if (container._leaflet_id) {
    try {
      // Find and remove the existing Leaflet instance attached to this container
      const existingMap = window._adminLeafletMapInstance
      if (existingMap) { existingMap.remove(); window._adminLeafletMapInstance = null }
    } catch {}
    container._leaflet_id = null
    container.innerHTML = ''
  }
  const L = window.L
  const map = L.map(container, { zoomControl: true, ...mapOptions }).setView([41.3880, 2.1690], 13)
  window._adminLeafletMapInstance = map
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 19
  }).addTo(map)

  const bounds = []

  for (const zone of zones) {
    const workerNames = zone.workers?.map(w => `<strong>${w.name}</strong>`).join('<br>') || ''
    const workerCount = zone.workers?.length || 0
    const popupContent = `
      <div style="min-width:160px;">
        <div style="font-size:1rem;font-weight:700;color:${zone.color};margin-bottom:6px;">📍 ${zone.cp}</div>
        <div style="font-size:0.82rem;color:#555;margin-bottom:4px;">${zone.zone}</div>
        ${workerNames ? `<div style="font-size:0.85rem;margin-bottom:6px;">${workerNames}</div>` : ''}
        <div style="font-size:0.78rem;color:#888;border-top:1px solid #eee;padding-top:4px;margin-top:4px;">
          ${workerCount ? `👥 ${workerCount} treballador${workerCount > 1 ? 's' : ''}<br>` : ''}
          ${zone.annualHours ? `🕐 ${zone.annualHours}h/any (teòriques)` : '🗺️ Zona assignada'}
        </div>
      </div>`

    // Fetch FULL boundary polygon via geoPolygonService (Overpass + localStorage cache)
    const geometry = await getPolygon(zone.cp)
    if (geometry) {
      const layer = L.geoJSON(geometry, {
        style: {
          color: zone.color, weight: 2.5, opacity: 0.95,
          fillColor: zone.color, fillOpacity: 0.15
        }
      }).addTo(map).bindPopup(popupContent, { maxWidth: 260 })
      bounds.push(layer.getBounds())
    } else {
      // Fallback: draw a visible circle if polygon data unavailable
      console.warn(`[AdminMap] No polygon for ${zone.cp}, using circle fallback`)
      const muniCoords = VALLES_MUNICIPALITIES[zone.cp] ? [VALLES_MUNICIPALITIES[zone.cp].lat, VALLES_MUNICIPALITIES[zone.cp].lng] : null
      const coords = CP_COORDS[zone.cp] || muniCoords
      if (coords) {
        const radius = VALLES_MUNICIPALITIES[zone.cp]?.radius || 800
        const circle = L.circle(coords, {
          radius: radius, color: zone.color, weight: 2, opacity: 0.8,
          fillColor: zone.color, fillOpacity: 0.12, dashArray: '6,4'
        }).addTo(map).bindPopup(popupContent, { maxWidth: 260 })
        bounds.push(circle.getBounds())
      }
    }

    // Label marker at centroid (visual only, NOT the zone boundary)
    const muniCoords = VALLES_MUNICIPALITIES[zone.cp] ? [VALLES_MUNICIPALITIES[zone.cp].lat, VALLES_MUNICIPALITIES[zone.cp].lng] : null
    const coords = CP_COORDS[zone.cp] || muniCoords || [41.3880, 2.1690]
    const initials = !zone.workers?.length
      ? zone.cp.slice(-2)
      : zone.workers.length > 1
        ? `+${zone.workers.length}`
        : zone.workers[0]?.name.split(' ').map(n => n[0]).join('').substring(0, 2)

    L.marker(coords, {
      icon: L.divIcon({
        html: `<div style="background:${zone.color};color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.7rem;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);">${initials}</div>`,
        iconSize: [30, 30], iconAnchor: [15, 15], className: ''
      })
    }).addTo(map).bindPopup(popupContent, { maxWidth: 260 })
  }

  // Fit map to all zone bounds
  if (bounds.length > 0) {
    const combined = bounds.reduce((acc, b) => acc.extend(b), bounds[0])
    map.fitBounds(combined, { padding: [20, 20] })
  }

  // ── Worker real-location markers ──────────────────────────────────────────
  const allWorkers = users.value.filter(u => u.role === 'worker' && u.active)
  for (const worker of allWorkers) {
    const logs = workLogStore.logs
      .filter(l => l.user_id === worker.id && (l.start_lat || l.end_lat))
      .sort((a, b) => new Date(b.date) - new Date(a.date))
    if (!logs.length) continue
    const latest = logs[0]
    const lat = latest.end_lat || latest.start_lat
    const lng = latest.end_lng || latest.start_lng
    if (!lat || !lng) continue

    const initials = worker.name.split(' ').map(n => n[0]).join('').substring(0, 2)
    const isWorking = workLogStore.logs.some(l => l.user_id === worker.id && l.start_time && !l.end_time)
    const dotColor = isWorking ? '#22c55e' : '#94a3b8'
    const popupHtml = `<div style="min-width:150px;">
      <div style="font-weight:700;font-size:0.95rem;margin-bottom:4px;">${worker.name}</div>
      <div style="font-size:0.8rem;color:#555;margin-bottom:6px;">${latest.date}</div>
      <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${dotColor};"></span>
        ${isWorking ? "Treballant ara" : "Ultima posicio registrada"}
      </div>
      <div style="font-size:0.72rem;color:#888;margin-top:4px;">📍 ${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}</div>
    </div>`

    L.marker([lat, lng], {
      icon: L.divIcon({
        html: `<div style="background:#1e293b;color:#fff;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.65rem;border:3px solid ${dotColor};box-shadow:0 2px 10px rgba(0,0,0,0.4);">${initials}</div>`,
        iconSize: [34, 34], iconAnchor: [17, 17], className: ''
      }),
      zIndexOffset: 1000
    }).addTo(map).bindPopup(popupHtml, { maxWidth: 220 })
  }

  return map
}

async function initMap() {
  if (!authStore.isAdmin) return
  await buildLeafletZoneMap('workers-leaflet-map', cpZoneSummary.value)
}


onMounted(async () => {
  await fetchStats()
  await fetchPendingCount()
  if (authStore.isAdmin) {
    await fetchCpZoneSummary()
    await fetchAnalyticsData()
    await nextTick()
    setTimeout(initMap, 400)
  }
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

async function approveLog(id) { await workLogStore.approveLog(id); await fetchStats(); await fetchPendingCount(); await fetchAnalyticsData() }
function rejectLogModal(id) { rejectLogId.value = id; rejectReason.value = ''; showRejectModal.value = true }
async function confirmReject() { if (rejectLogId.value && rejectReason.value) { await workLogStore.rejectLog(rejectLogId.value, rejectReason.value); showRejectModal.value = false; await fetchStats(); await fetchPendingCount(); await fetchAnalyticsData() } }

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || 'Desconegut' 
}
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Madrid' }) }
function statusBadge(s) { return { pending: 'badge-pending', approved: 'badge-success', rejected: 'badge-danger' }[s] || 'badge-primary' }
</script>
