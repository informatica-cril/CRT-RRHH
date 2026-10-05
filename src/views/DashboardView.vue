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
          </div>
          <div class="chart-bars">
            <div class="chart-bar-group" v-for="(day, index) in weekDays" :key="index">
              <div class="chart-bar" :class="{ highlight: index === todayIndex }"
                :style="{ height: day.height + 'px' }"></div>
              <span class="chart-bar-label">{{ day.label }}</span>
            </div>
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
              <span class="mobile-summary-value">{{ formatHM(day.hours) }}</span>
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
          <div class="card-header">
            <h3 class="card-title">{{ t('recent_activity') }}</h3>
          </div>
          
          <div v-if="recentLogs.length === 0" class="empty-state">
            <p>{{ t('loading') }}</p>
          </div>
          
          <template v-else>
            <!-- Desktop Table View -->
            <div class="table-responsive hide-on-mobile">
              <table>
                <thead>
                  <tr>
                    <th>{{ t('employee') }}</th>
                    <th>{{ t('date') }}</th>
                    <th>{{ t('start_time') }}</th>
                    <th>{{ t('end_time') }}</th>
                    <th>{{ t('worked_hours') }}</th>
                    <th>{{ t('status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in recentLogs" :key="log.id" class="act-fila" @click="$router.push(`/work-logs/${log.id}/detail`)" title="Obrir el detall del fitxatge">
                    <td><strong class="act-nom">{{ nomBonic(getUserName(log.user_id)) }}</strong></td>
                    <td>{{ formatDate(log.date) }}</td>
                    <td>{{ formatTime(log.start_time) }}</td>
                    <td>{{ log.end_time ? formatTime(log.end_time) : '—' }}</td>
                    <td>
                      <span v-if="!log.end_time" class="act-encurs">● En curs</span>
                      <span v-else>{{ formatHM(log.effective_hours ?? log.total_hours_worked) }}</span>
                    </td>
                    <td>
                      <span v-if="!log.end_time" class="text-muted text-small">—</span>
                      <span v-else class="badge" :class="statusBadge(log.status)">{{ t(log.status) || log.status }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile Card List View -->
            <div class="mobile-activity-list show-on-mobile">
              <div v-for="log in recentLogs" :key="log.id" class="mobile-log-card" @click="$router.push(`/work-logs/${log.id}/detail`)">
                <div class="mobile-log-header">
                  <div class="mobile-log-name">{{ nomBonic(getUserName(log.user_id)) }}</div>
                  <span v-if="!log.end_time" class="act-encurs">● En curs</span>
                  <span v-else class="badge" :class="statusBadge(log.status)">{{ t(log.status) || log.status }}</span>
                </div>
                <div class="mobile-log-date">{{ formatDate(log.date) }}</div>
                <div class="mobile-log-body mt-sm">
                  <div class="mobile-log-item">
                    <span class="mobile-log-label">Inici/Fi</span>
                    <span>{{ formatTime(log.start_time) }} – {{ log.end_time ? formatTime(log.end_time) : 'en curs' }}</span>
                  </div>
                  <div class="mobile-log-item">
                    <span class="mobile-log-label">Hores</span>
                    <span style="font-weight:700;">{{ log.end_time ? formatHM(log.effective_hours ?? log.total_hours_worked) : '—' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
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
              <div style="font-size: 0.78rem; color: var(--color-text-secondary);">{{ formatDate(log.date) }} · {{ formatHM(log.effective_hours ?? log.total_hours_worked) }}</div>
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

    <!-- ═══ Admin: Anàlisi per treballador ═══ -->
    <div v-if="authStore.isAdmin && activeTab !== 'menu'" class="card mt-lg" id="worker-analysis-card">
      <div class="card-header" style="flex-wrap:wrap;gap:10px;">
        <div>
          <h3 class="card-title">📈 Anàlisi per treballador</h3>
          <div class="text-small text-muted">Autoritzat = hores de conveni pels dies treballats (calendari laboral, exclou festius) + complementàries amb codi · desviació diària</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <select class="form-select" v-model.number="selectedWorkerId">
            <option v-for="w in analysisWorkers" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
          <select class="form-select" v-model="analysisMonth">
            <option v-for="m in analysisMonths" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
      </div>

      <div v-if="!selectedWorkerId" class="empty-state">Seleccioneu un treballador</div>
      <template v-else>
        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(115px,1fr));gap:10px;margin:8px 0 16px;">
          <div class="stat-card" style="padding:10px;">
            <div class="stat-label">Treballat</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--color-primary);">{{ formatHM(monthlyAnalysis.worked) }}</div>
          </div>
          <div class="stat-card" style="padding:10px;">
            <div class="stat-label">Bàsic contractat</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ formatHM(monthlyAnalysis.basic) }}</div>
          </div>
          <div class="stat-card" style="padding:10px;">
            <div class="stat-label">Complement. autoritzades</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--color-warning);">{{ formatHM(monthlyAnalysis.complementary) }}</div>
          </div>
          <div class="stat-card" style="padding:10px;">
            <div class="stat-label">Màxim autoritzat</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ formatHM(monthlyAnalysis.max) }}</div>
          </div>
          <div class="stat-card" style="padding:10px;" v-if="monthlyAnalysis.overflow > 0.01">
            <div class="stat-label" style="color:var(--color-danger);">Excés no autoritzat</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--color-danger);">+{{ formatHM(monthlyAnalysis.overflow) }}</div>
          </div>
        </div>

        <div class="worker-analysis-grid">
          <div>
            <div class="text-small text-muted" style="margin-bottom:6px;font-weight:600;">Progressió mensual · treballat vs màxim autoritzat</div>
            <div style="height:190px;position:relative;">
              <Bar :data="monthlyChartData" :options="monthlyChartOptions" />
            </div>
          </div>
          <div>
            <div class="text-small text-muted" style="margin-bottom:6px;font-weight:600;">Desviació diària · treballat vs previst (horari)</div>
            <div style="height:190px;position:relative;">
              <Bar :data="dailyChartData" :options="dailyChartOptions" />
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ═══ Admin: Radi de verificació — informe del pilot (EIPD §6.1.bis) ═══ -->
    <div v-if="authStore.isAdmin && activeTab !== 'menu' && pilotRadi" class="card mt-lg" id="pilot-radi-card">
      <div class="card-header" style="margin-bottom:0;">
        <h3 class="card-title">📐 Radi de verificació — informe del pilot</h3>
        <div class="text-small text-muted">Evidència empírica per fixar el radi mínim (EIPD §6.1.bis) · {{ pilotRadi.from }} → {{ pilotRadi.to }}</div>
      </div>
      <div v-if="pilotRadi.n_hitos === 0 && pilotRadi.n_visites === 0" class="text-small text-muted" style="padding:14px 0;">
        El pilot encara no ha generat dades. Quan hi hagi hitos reals, aquí apareixerà el radi aconsellat.
      </div>
      <template v-else>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0;">
          <div style="flex:1;min-width:180px;background:linear-gradient(135deg,#00806C,#3B82F6);color:#fff;border-radius:12px;padding:16px;text-align:center;">
            <div style="font-size:0.75rem;opacity:.85;">RADI ACONSELLAT A FIXAR</div>
            <div style="font-size:2rem;font-weight:800;">{{ pilotRadi.radi_recomanat_m ?? '—' }} m</div>
            <div style="font-size:0.72rem;opacity:.8;">base {{ pilotRadi.radi_base_m }} m + p95 de la precisió GPS real</div>
          </div>
          <div v-if="pilotRadi.precisio" style="flex:1;min-width:180px;background:var(--color-bg);border:1px solid var(--color-border-light);border-radius:12px;padding:16px;">
            <div style="font-size:0.75rem;color:var(--color-text-secondary);">Precisió GPS (n={{ pilotRadi.n_hitos }})</div>
            <div style="font-size:0.9rem;margin-top:6px;">p50 <b>{{ pilotRadi.precisio.p50 }} m</b> · p90 <b>{{ pilotRadi.precisio.p90 }} m</b> · p95 <b>{{ pilotRadi.precisio.p95 }} m</b> · màx <b>{{ pilotRadi.precisio.max }} m</b></div>
          </div>
          <div v-if="pilotRadi.fora_radi" style="flex:1;min-width:180px;background:var(--color-bg);border:1px solid var(--color-border-light);border-radius:12px;padding:16px;">
            <div style="font-size:0.75rem;color:var(--color-text-secondary);">Visites (n={{ pilotRadi.n_visites }})</div>
            <div style="font-size:0.9rem;margin-top:6px;">fora de radi: <b>{{ pilotRadi.fora_radi.n }}</b> ({{ pilotRadi.fora_radi.pct }}%)</div>
            <div v-if="pilotRadi.fora_radi.zona_grisa > 0" style="font-size:0.8rem;color:var(--color-warning);margin-top:4px;">⚠ {{ pilotRadi.fora_radi.zona_grisa }} en zona grisa (possibles falsos negatius)</div>
          </div>
        </div>
        <div class="text-small text-muted">Després del període de prova, el DPD fixa el radi definitiu amb aquesta evidència (mínim necessari, sense falsos negatius sistemàtics).</div>
      </template>
    </div>

    <!-- ═══ Admin: Worker Map by CP ═══ -->
    <div v-if="authStore.isAdmin && activeTab !== 'menu'" class="card mt-lg" id="worker-map-card">
      <div class="card-header" style="margin-bottom:0;">
        <h3 class="card-title">🗺️ Mapa de treballadors per zona</h3>
        <div class="text-small text-muted">Zones assignades actuals · hores teòriques anuals per CP</div>
      </div>
      <!-- Mapa a l'esquerra i, a la dreta, una fila per zona que es desplega amb les persones:
           abans eren píndoles amb tots els noms seguits i, amb moltes zones, no es llegia res. -->
      <div class="zm-layout">
        <div id="workers-leaflet-map" class="zm-mapa"></div>
        <div class="zm-panell">
          <input v-model="cercaZona" type="search" class="form-input zm-cerca" placeholder="🔍 Cerca una persona o un CP…" />
          <div class="zm-resum">{{ zonesVisibles.length }} zones · {{ personesAmbZona }} persones</div>
          <div v-if="!zonesVisibles.length" class="zm-buit">Cap zona coincideix amb «{{ cercaZona }}»</div>
          <div class="zm-llista">
            <div v-for="zone in zonesVisibles" :key="zone.cp" class="zm-zona" :class="{ oberta: zonaOberta(zone.cp) }">
              <button type="button" class="zm-fila" @click="clicaZona(zone.cp)">
                <span class="zm-punt" :style="{ background: zone.color }"></span>
                <span class="zm-nom"><strong>{{ zone.cp }}</strong><span v-if="zone.zone && zone.zone !== zone.cp" class="zm-sub">{{ zone.zone }}</span></span>
                <span class="zm-n" :title="zone.workers.length + ' persones'">👥 {{ zone.workers.length }}</span>
                <span class="zm-fletxa">{{ zonaOberta(zone.cp) ? '▾' : '▸' }}</span>
              </button>
              <div v-if="zonaOberta(zone.cp)" class="zm-persones">
                <span v-for="w in zone.workers" :key="w.id" class="zm-persona" :class="{ coincideix: coincidePersona(w) }">{{ nomBonic(w.name) }}</span>
                <div class="zm-hores">🕐 {{ zone.annualHours.toLocaleString('ca-ES') }} h/any teòriques</div>
              </div>
            </div>
          </div>
        </div>
      </div>
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
      <div class="card stat-card" @click="$router.push('/break-settings')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(0,0,0,0.05);">
        <span style="font-size:2.5rem;">⏱️</span>
        <div style="font-weight:700;font-size:1rem;">Pausa Obligatòria</div>
      </div>
      <div class="card stat-card" @click="$router.push('/mail-settings')" style="cursor:pointer;padding:24px;text-align:center;justify-content:center;gap:12px;min-height:140px;background:rgba(0,0,0,0.05);">
        <span style="font-size:2.5rem;">✉️</span>
        <div style="font-weight:700;font-size:1rem;">Configuració Correu</div>
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
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { TILE_URL, TILE_ATTRIBUTION, TILE_MAX_ZOOM } from '../config/geo'
// import { db, BARCELONA_HOLIDAYS_2026 } from '../services/db'
import { db } from '../services/db'
import { getCurrentPosition, BARCELONA_POSTAL_CODES, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { getPolygon, getGeoJsonBounds } from '../services/geoPolygonService'
import { i18n } from '../i18n'
import { formatHM } from '../utils/formatHours'
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

const pilotRadi = ref(null)
async function fetchPilotRadi() {
  if (!authStore.isAdmin) return
  try { pilotRadi.value = await db.getPilotRadi() } catch (e) { /* no admin o API antiga */ }
}

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
  return formatHM(hoursDecimal)
}

const hoursToday = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 10) === today && (!uid || l.user_id === uid))
    .reduce((s, l) => {
      let h = Number((l.effective_hours ?? l.total_hours_worked) || 0)
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
    .reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0))
})

const hoursMonth = computed(() => {
  const month = new Date().toISOString().slice(0, 7)
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 7) === month && (!uid || l.user_id === uid))
    .reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0))
})

const pendingRequests = ref(0)

async function fetchPendingCount() {
  const absences = await db.getAbsences()
  const pendingAbs = absences.filter(a => a.approved === null).length
  pendingRequests.value = workLogStore.logs.filter(l => l.status === 'pending').length + pendingAbs
}

const pendingLogs = computed(() => workLogStore.logs.filter(l => l.status === 'pending').sort((a, b) => new Date(b.date) - new Date(a.date)))
// Còpia abans d'ordenar (sort() sobre el store reordenava la llista de totes les pantalles) i per data i hora.
const recentLogs = computed(() => [...workLogStore.logs]
  .sort((a, b) => String(b.date).slice(0, 10).localeCompare(String(a.date).slice(0, 10)) || String(b.start_time).localeCompare(String(a.start_time)))
  .slice(0, 8))

const totalExtraHours = computed(() => {
  const month = new Date().toISOString().slice(0, 7)
  const uid = authStore.isAdmin ? null : authStore.userId
  return formatH(workLogStore.logs
    .filter(l => l.date && l.date.substring(0, 7) === month && (!uid || l.user_id === uid))
    .reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0) + Number(l.extra_hours_authorized || 0), 0))
})

const todayIndex = computed(() => { const d = new Date().getDay(); return d === 0 ? 6 : d - 1 })

const weekDays = computed(() => {
  const dayNames = ['Dl', 'Dt', 'Dc', 'Dj', 'Dv', 'Ds', 'Dg']
  const now = new Date(); const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1); weekStart.setHours(0,0,0,0)
  const uid = authStore.isAdmin ? null : authStore.userId
  const ws = weekStart.toISOString().split('T')[0]
  const weekLogs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 10) >= ws && (!uid || l.user_id === uid))
  const dayHours = dayNames.map((label, i) => {
    const dayLogs = weekLogs.filter(l => { const d = new Date(l.date.substring(0, 10)).getDay(); return (d === 0 ? 6 : d - 1) === i })
    return { label, hours: dayLogs.reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0) }
  })
  const maxHours = Math.max(...dayHours.map(d => d.hours), 1)
  return dayHours.map(d => ({ ...d, height: Math.max(6, (d.hours / maxHours) * 170) }))
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

// ── Anàlisi per treballador (progressió mensual + desviació diària) ──────
const workSchedules = ref([])
const authCodes = ref([])
const holidaySet = ref(new Set()) // dates 'YYYY-MM-DD' del calendari laboral (festius)
const selectedWorkerId = ref(null)
const analysisMonth = ref(new Date().toISOString().slice(0, 7))

const analysisWorkers = computed(() =>
  users.value.filter(u => u.role === 'worker' && u.active).sort((a, b) => a.name.localeCompare(b.name))
)

const analysisMonths = computed(() => {
  const arr = []
  const now = new Date()
  for (let i = 0; i < 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    arr.push({
      value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' })
    })
  }
  return arr
})

// Horas programadas de un día concreto según el horario (days[].day usa 0=Dg..6=Ds, igual que getDay())
// Un día es laborable si tiene start/end y no está explícitamente desactivado (algunos horarios no traen 'active').
function scheduleDayHours(schedule, jsDay) {
  const cfg = schedule?.days?.find(d => (d.day === jsDay || d.day_num === jsDay))
  if (!cfg || cfg.active === false || !cfg.start || !cfg.end) return 0
  const [sh, sm] = String(cfg.start).split(':').map(Number)
  const [eh, em] = String(cfg.end).split(':').map(Number)
  return Math.max(0, (eh * 60 + em - sh * 60 - sm) / 60)
}

function monthDays(ym) {
  const [y, m] = ym.split('-').map(Number)
  return { y, m, days: new Date(y, m, 0).getDate() }
}

const selectedSchedule = computed(() => {
  const w = users.value.find(u => u.id === selectedWorkerId.value)
  if (!w) return null
  return workSchedules.value.find(s => s.id === w.work_schedule_id) || null
})

const monthlyAnalysis = computed(() => {
  const empty = { worked: 0, basic: 0, complementary: 0, max: 0, overflow: 0 }
  if (!selectedWorkerId.value) return empty
  const ym = analysisMonth.value
  const { y, m, days } = monthDays(ym)
  const sched = selectedSchedule.value

  // Dies EFECTIVAMENT treballats pel treballador (amb fichatge) dins el mes
  const workedDates = new Set(
    workLogStore.logs
      .filter(l => l.user_id === selectedWorkerId.value && l.date && l.date.substring(0, 7) === ym)
      .map(l => l.date.substring(0, 10))
  )

  // Bàsic previst per conveni: hores del conveni segons horari, NOMÉS pels dies
  // efectivament treballats i que són laborables segons el calendari laboral (exclou festius)
  let basic = 0
  if (sched) {
    workedDates.forEach(dateStr => {
      if (holidaySet.value.has(dateStr)) return // festiu del calendari laboral → no compta com a bàsic
      basic += scheduleDayHours(sched, new Date(dateStr + 'T00:00:00').getDay())
    })
  }

  // Complementàries autoritzades: codis assignats a aquest treballador, no revocats, vàlids dins el mes
  const monthStart = new Date(y, m - 1, 1)
  const monthEnd = new Date(y, m - 1, days, 23, 59, 59)
  const complementary = authCodes.value
    .filter(c => !c.revoked && Number(c.user_id) === Number(selectedWorkerId.value))
    .filter(c => {
      const vf = c.valid_from ? new Date(c.valid_from) : null
      const vt = c.valid_to ? new Date(c.valid_to) : null
      return vf && vt && vf <= monthEnd && vt >= monthStart
    })
    .reduce((s, c) => s + Number(c.authorized_hours || 0), 0)

  // Treballat efectiu del mes
  const worked = workLogStore.logs
    .filter(l => l.user_id === selectedWorkerId.value && l.date && l.date.substring(0, 7) === ym)
    .reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0)

  const max = basic + complementary
  return { worked, basic, complementary, max, overflow: Math.max(0, worked - max) }
})

const monthlyChartData = computed(() => {
  const a = monthlyAnalysis.value
  const workedWithinMax = Math.min(a.worked, a.max)
  return {
    labels: ['Màxim autoritzat', 'Treballat'],
    datasets: [
      { label: 'Bàsic contractat', backgroundColor: '#3b82f6', data: [Number(a.basic.toFixed(2)), 0], stack: 's', borderRadius: 4 },
      { label: 'Complementàries autoritzades', backgroundColor: '#f59e0b', data: [Number(a.complementary.toFixed(2)), 0], stack: 's', borderRadius: 4 },
      { label: 'Treballat', backgroundColor: '#10b981', data: [0, Number(workedWithinMax.toFixed(2))], stack: 's', borderRadius: 4 },
      { label: 'Excés no autoritzat', backgroundColor: '#ef4444', data: [0, Number(a.overflow.toFixed(2))], stack: 's', borderRadius: 4 }
    ]
  }
})

const monthlyChartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 8, font: { size: 9 } } },
    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatHM(ctx.raw)}` } }
  },
  scales: {
    x: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 9 } } },
    y: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } }
  }
}

const dailyAnalysis = computed(() => {
  if (!selectedWorkerId.value) return []
  const ym = analysisMonth.value
  const { y, m, days } = monthDays(ym)
  const sched = selectedSchedule.value
  const logs = workLogStore.logs.filter(l => l.user_id === selectedWorkerId.value && l.date && l.date.substring(0, 7) === ym)
  const arr = []
  for (let d = 1; d <= days; d++) {
    const dateStr = `${ym}-${String(d).padStart(2, '0')}`
    const isHoliday = holidaySet.value.has(dateStr)
    // Previst segons calendari laboral: 0 en festius
    const scheduled = (sched && !isHoliday) ? scheduleDayHours(sched, new Date(y, m - 1, d).getDay()) : 0
    const worked = logs
      .filter(l => l.date.substring(0, 10) === dateStr)
      .reduce((s, l) => s + Number((l.effective_hours ?? l.total_hours_worked) || 0), 0)
    arr.push({ d, scheduled, worked, isHoliday })
  }
  return arr
})

const dailyChartData = computed(() => {
  const a = dailyAnalysis.value
  return {
    labels: a.map(x => x.d),
    datasets: [
      { label: 'Previst (horari)', backgroundColor: 'rgba(148,163,184,0.45)', data: a.map(x => Number(x.scheduled.toFixed(2))), borderRadius: 2 },
      {
        label: 'Treballat',
        backgroundColor: a.map(x => x.worked < 0.01 ? 'rgba(148,163,184,0.2)' : (x.worked > x.scheduled + 0.01 ? '#ef4444' : '#10b981')),
        data: a.map(x => Number(x.worked.toFixed(2))),
        borderRadius: 2
      }
    ]
  }
})

const dailyChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 8, font: { size: 9 } } },
    tooltip: { callbacks: { title: (items) => `Dia ${items[0].label}`, label: (ctx) => `${ctx.dataset.label}: ${formatHM(ctx.raw)}` } }
  },
  scales: {
    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 9 } } },
    x: { grid: { display: false }, ticks: { font: { size: 8 }, autoSkip: true, maxTicksLimit: 16 } }
  }
}

async function fetchWorkerAnalysisData() {
  try {
    const bulk = await db.getUsersBulk().catch(() => ({ work_schedules: [] }))
    workSchedules.value = bulk.work_schedules || []
    authCodes.value = await db.getAuthCodes().catch(() => [])
    const holidays = await db.getHolidays().catch(() => [])
    holidaySet.value = new Set((holidays || []).map(h => String(h.date).substring(0, 10)))
    if (!selectedWorkerId.value && analysisWorkers.value.length) {
      selectedWorkerId.value = analysisWorkers.value[0].id
    }
  } catch (e) {
    console.error('[WorkerAnalysis]', e)
  }
}

// ── CP Zone Summary for map pills and markers ──────────────────────────
const ZONE_COLORS = ['#3b82f6','#f97316','#10b981','#8b5cf6','#ef4444','#f59e0b','#06b6d4','#ec4899','#84cc16','#6366f1']
const cpZoneSummary = ref([])

async function fetchCpZoneSummary() {
  const bulk = await db.getUsersBulk().catch(() => ({ users: [], work_schedules: [], zone_assignments: [] }))
  const scheduleMap = Object.fromEntries(bulk.work_schedules.map(s => [s.id, s]))
  const workers = bulk.users.filter(u => u.role === 'worker' && u.active)
  const zoneMap = {}
  
  workers.forEach(w => {
    const activeZone = bulk.zone_assignments.find(z => z.users.some(u => u.id === w.id))
    const sched = scheduleMap[w.work_schedule_id] || null
    
    const codes = activeZone?.postal_codes || (w.postal_code_assigned && w.work_type !== 'DOMICILIARIA_VALLES' ? [w.postal_code_assigned] : [])
    const munis = activeZone?.municipalities || []
    
    // Combine to plot
    const zonesToPlot = [...codes]
    if (w.work_type === 'DOMICILIARIA_VALLES') {
      zonesToPlot.push(...munis)
    }

    const weeklyHours = sched ? parseFloat(sched.total_hours_weekly) : 40
    const annualHours = Math.round(weeklyHours * 46.5)
    
    zonesToPlot.forEach(cp => {
      if (!zoneMap[cp]) {
        const isMuni = !!VALLES_MUNICIPALITIES[cp]
        const zoneName = isMuni ? VALLES_MUNICIPALITIES[cp].name : (BARCELONA_POSTAL_CODES[cp]?.zone || cp)
        zoneMap[cp] = { 
          cp, 
          workers: [], 
          annualHours: 0, 
          zone: zoneName, 
          color: ZONE_COLORS[Object.keys(zoneMap).length % ZONE_COLORS.length] 
        }
      }
      if (!zoneMap[cp].workers.find(existing => existing.id === w.id)) {
        zoneMap[cp].workers.push(w)
      }
      zoneMap[cp].annualHours += annualHours
    })
  })
  cpZoneSummary.value = Object.values(zoneMap)
}

// ── Panell de zones del mapa ────────────────────────────────────────────────
const cercaZona = ref('')
const zonaSeleccionada = ref(null)
const normTxt = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
function coincidePersona(w) {
  const q = normTxt(cercaZona.value.trim())
  return q.length >= 2 && normTxt(w.name).includes(q)
}
const zonesVisibles = computed(() => {
  const q = normTxt(cercaZona.value.trim())
  const totes = [...cpZoneSummary.value].sort((a, b) => b.workers.length - a.workers.length || a.cp.localeCompare(b.cp))
  if (!q) return totes
  return totes.filter(z => normTxt(z.cp).includes(q) || normTxt(z.zone).includes(q) || z.workers.some(w => normTxt(w.name).includes(q)))
})
const personesAmbZona = computed(() => new Set(zonesVisibles.value.flatMap(z => z.workers.map(w => w.id))).size)
// Amb una cerca, les zones trobades surten obertes (és el que es vol veure: on va cadascú).
function zonaOberta(cp) { return zonaSeleccionada.value === cp || (cercaZona.value.trim().length >= 2 && zonesVisibles.value.length <= 6) }
function clicaZona(cp) {
  zonaSeleccionada.value = zonaSeleccionada.value === cp ? null : cp
  enfocaZona(zonaSeleccionada.value)
}
function nomBonic(nom) {
  return String(nom || '').toLowerCase().replace(/(^|[\s'-])(\p{L})/gu, (m, sep, l) => sep + l.toUpperCase())
}
// cp → { capa, color } per poder ressaltar i centrar la zona des del panell
const capesZona = {}
function enfocaZona(cp) {
  const map = window._adminLeafletMapInstance
  Object.entries(capesZona).forEach(([k, c]) => c.capa?.setStyle?.({ weight: k === cp ? 4 : 2.5, fillOpacity: k === cp ? 0.35 : (cp ? 0.06 : 0.15) }))
  const c = cp && capesZona[cp]
  if (map && c?.capa?.getBounds) {
    map.fitBounds(c.capa.getBounds(), { padding: [30, 30], maxZoom: 15 })
  }
}
watch(cercaZona, () => {
  // Una sola zona trobada: es ressalta al mapa sense haver de clicar.
  const v = zonesVisibles.value
  enfocaZona(cercaZona.value.trim() && v.length === 1 ? v[0].cp : null)
})

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
  L.tileLayer(TILE_URL, {
    attribution: TILE_ATTRIBUTION,
    maxZoom: TILE_MAX_ZOOM
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
      layer.on('click', () => { zonaSeleccionada.value = zone.cp })
      if (containerId === 'workers-leaflet-map') capesZona[zone.cp] = { capa: layer }
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
        circle.on('click', () => { zonaSeleccionada.value = zone.cp })
        if (containerId === 'workers-leaflet-map') capesZona[zone.cp] = { capa: circle }
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
    await fetchWorkerAnalysisData()
    await fetchCpZoneSummary()
    await fetchAnalyticsData()
    fetchPilotRadi()
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
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }
function statusBadge(s) { return { pending: 'badge-pending', approved: 'badge-success', rejected: 'badge-danger' }[s] || 'badge-primary' }
</script>

<style scoped>
/* ── Activitat recent ── */
.act-fila { cursor: pointer; }
.act-fila:hover td { background: rgba(9, 78, 140, .04); }
.act-nom { font-weight: 600; }
.act-encurs { display: inline-flex; align-items: center; gap: 4px; font-size: .78rem; font-weight: 700; color: #0A7C5E; background: #E8F8F2; border-radius: 999px; padding: 2px 10px; white-space: nowrap; }
.mobile-log-card { cursor: pointer; }
/* ── Mapa de treballadors per zona: mapa + panell de zones desplegables ── */
.zm-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 14px; margin-top: 14px; }
.zm-mapa { height: 460px; border-radius: 12px; overflow: hidden; border: 1px solid var(--color-border-light); }
.zm-panell { display: flex; flex-direction: column; height: 460px; min-width: 0; }
.zm-cerca { margin-bottom: 6px; }
.zm-resum { font-size: .74rem; color: var(--color-text-muted, #7a8aa0); margin: 0 2px 8px; }
.zm-buit { font-size: .82rem; color: var(--color-text-muted, #7a8aa0); padding: 12px 4px; }
.zm-llista { overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 6px; padding-right: 2px; }
.zm-zona { border: 1px solid var(--color-border-light, #e5eaf0); border-radius: 10px; background: var(--color-bg, #f8fafc); }
.zm-zona.oberta { background: var(--color-surface, #fff); box-shadow: 0 2px 10px rgba(10, 42, 74, .07); }
.zm-fila { width: 100%; display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: none; border: none; cursor: pointer; text-align: left; color: var(--color-text); min-height: 40px; }
.zm-punt { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
.zm-nom { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.2; font-size: .85rem; }
.zm-sub { font-size: .72rem; color: var(--color-text-muted, #7a8aa0); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.zm-n { font-size: .76rem; font-weight: 700; color: var(--color-text-secondary, #475569); white-space: nowrap; }
.zm-fletxa { font-size: .8rem; color: var(--color-text-muted, #94a3b8); width: 10px; }
.zm-persones { display: flex; flex-wrap: wrap; gap: 5px; padding: 0 10px 10px 29px; }
.zm-persona { font-size: .74rem; background: var(--color-bg, #f1f5f9); border: 1px solid var(--color-border-light, #e5eaf0); border-radius: 999px; padding: 2px 9px; }
.zm-persona.coincideix { background: #FFF3C4; border-color: #D9A400; font-weight: 700; }
.zm-hores { width: 100%; font-size: .72rem; color: var(--color-text-muted, #7a8aa0); margin-top: 3px; }
@media (max-width: 900px) {
  .zm-layout { grid-template-columns: 1fr; }
  .zm-mapa { height: 340px; }
  .zm-panell { height: auto; max-height: 420px; }
}

.worker-analysis-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 900px) {
  .worker-analysis-grid { grid-template-columns: 1fr; }
}
</style>
