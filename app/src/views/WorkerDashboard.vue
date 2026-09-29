<template>
  <div>
    <!-- Tab Navigation -->
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ tabTitles[activeTab] }}</h1>
        <p class="page-subtitle">Portal de treballador — {{ authStore.userName }}</p>
      </div>
    </div>

    <!-- Tab content based on route -->
    <!-- ═══ TAB: FITXATGE ═══ -->
    <div v-if="activeTab === 'clock'">
      <div class="dashboard-grid">
        <div>
          <div class="time-tracker" id="worker-clock">
            <div class="tracker-label">{{ t('time_tracker') }}</div>
            <div class="tracker-time">{{ workLogStore.formattedElapsed }}</div>
            <div class="location-status mb-md" :class="locationClass" style="display:inline-flex;margin:8px auto;">
              <span class="location-dot" :class="locationClass"></span>
              {{ locationText }}
            </div>
            <div class="tracker-buttons">
              <button v-if="!workLogStore.isWorking && authStore.user?.job_profile !== 'Gerencia'" class="btn btn-start" @click="startWorkday" id="btn-clock-in">
                ▶ {{ t('start_workday') }}
              </button>
              <button v-else-if="authStore.user?.job_profile !== 'Gerencia'" class="btn btn-stop" @click="endWorkday" id="btn-clock-out" :disabled="isFinishing">
                <span v-if="isFinishing" class="spinner-small" style="margin-right:8px;"></span>
                <span v-else>⏹</span> {{ isFinishing ? 'Finalitzant...' : t('end_workday') }}
              </button>
              <div v-else class="text-small text-muted text-center" style="padding:12px;">El perfil de Gerència no porta control horari.</div>
            </div>
          </div>

          <!-- Today's summary with authorized/unauthorized split -->
          <div class="card mt-lg">
            <h3 class="card-title mb-md">Resum del dia</h3>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px;">
              <div class="stat-card" style="padding:12px;"><div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Hores avui</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-primary);margin-top:4px;">{{ todayHours }}</div></div>
              <div class="stat-card" style="padding:12px;"><div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Hores setmana</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-accent);margin-top:4px;">{{ weekHours }}</div></div>
              <div class="stat-card" style="padding:12px;border-bottom:3px solid var(--color-success);"><div class="text-small" style="font-weight:600;color:var(--color-success);">✓ H. extra autoritzades (mes)</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-success);margin-top:4px;">{{ todayAuthorized }}</div></div>
              <div class="stat-card" style="padding:12px;border-bottom:3px solid var(--color-danger);"><div class="text-small" style="font-weight:600;color:var(--color-danger);">✗ H. extra no autoritzades (mes)</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);margin-top:4px;">{{ todayUnauthorized }}</div></div>
            </div>
          </div>

          <!-- Auth Code input for extra hours -->
          <div class="card mt-lg" style="border-left:4px solid var(--color-warning);">
            <h3 class="card-title mb-md">🔑 Autoritzar hores extra</h3>
            <p class="text-small text-muted" style="margin-bottom:12px;">Si tens hores extra no autoritzades en un fitxatge anterior, introdueix el codi que t'ha enviat el teu responsable per autoritzar-les.</p>
            <div class="mobile-stack-form" style="display:flex; flex-direction: column; gap:12px; margin-bottom:8px;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Fitxatge</label>
                <select class="form-select" v-model="authLogId" style="width:100%;">
                  <option value="">Selecciona fitxatge...</option>
                  <option v-for="l in logsWithUnauthorized" :key="l.id" :value="l.id">{{ formatDateFull(l.date) }} — {{ Number(l.extra_hours_unauthorized||0).toFixed(1) }}h no aut.</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Codi d'autorització</label>
                <input class="form-input" v-model="authCodeInput" placeholder="XXXX-XXXX-XXXX" style="font-family:monospace;font-size:1rem;letter-spacing:1px; width:100%;" @input="authCodeInput = authCodeInput.toUpperCase()" />
              </div>
              <button class="btn btn-accent" style="width:100%;" @click="submitAuthCode" :disabled="!authCodeInput || !authLogId">Autoritzar</button>
            </div>
            <div v-if="authCodeResult" class="mt-sm" style="padding:10px 14px;border-radius:8px;font-size:0.85rem;"
              :style="{
                background: !authCodeResult.success ? 'rgba(239,68,68,0.1)' : authCodeResult.hasRemaining ? 'rgba(249,115,22,0.1)' : 'rgba(76,175,80,0.1)',
                color: !authCodeResult.success ? 'var(--color-danger)' : authCodeResult.hasRemaining ? '#c2410c' : 'var(--color-success)'
              }">
              {{ authCodeResult.message }}
            </div>
          </div>
        </div>

        <!-- Worker zone map -->
        <div class="card mt-lg">
          <div class="card-header" style="margin-bottom:0;">
            <h3 class="card-title">🗺️ La meva zona de treball</h3>
            <div class="text-small text-muted" v-if="authStore.user?.work_type === 'AMBULATORIA' && activeAmbulatoryCenter">
              Centre: <span class="badge badge-info" style="margin-left:4px;">🏥 {{ activeAmbulatoryCenter.name }}</span>
            </div>
            <div class="text-small text-muted" v-else-if="activeCpAssignment">
              Zones actives:
              <span v-for="cp in activeCpAssignment.postal_codes" :key="cp" class="badge badge-info" style="margin-left:4px;">{{ cp }}</span>
            </div>
          </div>
          <div v-if="authStore.user?.work_type !== 'AMBULATORIA' && !activeCpAssignment" style="padding:16px;text-align:center;color:var(--color-text-secondary);">
            Cap zona assignada
          </div>
          <div v-else id="worker-zone-map" style="height:320px;width:100%;min-width:0;border-radius:12px;overflow:hidden;margin-top:12px;border:1px solid var(--color-border-light);"></div>
        </div>

        <!-- Quick calendar -->
        <div>
          <div class="card">
            <div class="card-header">
              <button class="btn btn-outline btn-sm" @click="prevMonth">◂</button>
              <h3 class="card-title">{{ calendarMonthName }}</h3>
              <button class="btn btn-outline btn-sm" @click="nextMonth">▸</button>
            </div>
            <div class="calendar-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-top:12px;width:100%;min-width:0;">
              <div v-for="dLabel in ['Dl','Dt','Dc','Dj','Dv','Ds','Dg']" :key="dLabel" style="text-align:center;font-size:0.7rem;font-weight:600;color:var(--color-text-secondary);padding:4px;">{{ dLabel }}</div>
              <div v-for="day in calendarDays" :key="day.key"
                :style="{ textAlign: 'center', padding: '6px 4px', borderRadius: '8px', fontSize: '0.8rem', fontWeight: day.isToday ? '700' : '400', background: dayBackground(day), color: dayColor(day), cursor: day.date ? 'default' : 'auto', opacity: day.currentMonth ? '1' : '0.3', border: day.isToday ? '2px solid var(--color-primary)' : 'none' }">
                <span v-if="day.date">{{ day.dayNum }}</span>
                <div v-if="day.holiday" style="width:6px;height:6px;border-radius:50%;background:var(--color-danger);margin:2px auto 0;"></div>
                <div v-if="day.hasPermission" style="width:6px;height:6px;border-radius:50%;background:var(--color-info);margin:2px auto 0;"></div>
                <div v-if="day.hasWorklog" style="width:6px;height:6px;border-radius:50%;background:var(--color-success);margin:2px auto 0;"></div>
              </div>
            </div>
            <div style="display:flex;gap:16px;margin-top:16px;font-size:0.72rem;color:var(--color-text-secondary);">
              <div style="display:flex;align-items:center;gap:4px;"><div style="width:8px;height:8px;border-radius:50%;background:var(--color-danger);"></div> Festiu</div>
              <div style="display:flex;align-items:center;gap:4px;"><div style="width:8px;height:8px;border-radius:50%;background:var(--color-info);"></div> Permís</div>
              <div style="display:flex;align-items:center;gap:4px;"><div style="width:8px;height:8px;border-radius:50%;background:var(--color-success);"></div> Fitxat</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: CALENDAR ═══ -->
    <div v-else-if="activeTab === 'calendar'">
      <div class="dashboard-grid">
        <div style="grid-column:1/-1;">
          <div class="card">
            <div class="card-header">
              <button class="btn btn-outline btn-sm" @click="prevMonth">◂</button>
              <h3 class="card-title">{{ calendarMonthName }}</h3>
              <button class="btn btn-outline btn-sm" @click="nextMonth">▸</button>
            </div>
            <div class="calendar-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-top:16px;">
              <div v-for="dLabel in ['DL','DT','DC','DJ','DV','DS','DG']" :key="dLabel" style="text-align:center;font-size:0.75rem;font-weight:600;color:var(--color-text-secondary);padding:6px;">{{ dLabel }}</div>
              <div v-for="day in calendarDays" :key="day.key" class="calendar-day"
                style="min-height:70px;padding:6px;border-radius:8px;font-size:0.8rem;border:1px solid var(--color-border-light);"
                :style="{ background: dayBackground(day), opacity: day.currentMonth ? '1' : '0.3' }">
                <div :style="{ fontWeight: day.isToday ? '700' : '400', color: dayColor(day) }">{{ day.dayNum }}</div>
                <div v-if="day.holiday" style="font-size:0.65rem;color:var(--color-danger);margin-top:2px;">🔴 {{ day.holiday.name }}</div>
                <div v-if="day.excedencia" style="font-size:0.65rem;color:#7c3aed;margin-top:2px;">🟣 Excedència</div>
                <template v-else-if="day.absence">
                  <div v-if="day.absence.approved === true" style="font-size:0.65rem;margin-top:2px;" :style="{ color: getAbsenceTypeName(day.absence.absence_type_id).toLowerCase().includes('vacan') || getAbsenceTypeName(day.absence.absence_type_id).toLowerCase().includes('vacac') ? '#16a34a' : 'var(--color-info)' }">
                    {{ getAbsenceTypeName(day.absence.absence_type_id).toLowerCase().includes('vacan') || getAbsenceTypeName(day.absence.absence_type_id).toLowerCase().includes('vacac') ? '🟢' : '🔵' }} {{ getAbsenceTypeName(day.absence.absence_type_id) }}
                  </div>
                  <div v-else-if="day.absence.approved === null" style="font-size:0.65rem;color:#ca8a04;margin-top:2px;">🟡 {{ getAbsenceTypeName(day.absence.absence_type_id) }}</div>
                </template>
                <div v-if="day.hasWorklog" style="font-size:0.65rem;color:var(--color-success);margin-top:2px;">✓ Fitxat</div>
              </div>
            </div>
          </div>

          <!-- Periods legend -->
          <div class="card mt-md" v-if="calendarPeriods.length > 0">
            <h3 class="card-title mb-md">Períodes del mes</h3>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div v-for="period in calendarPeriods" :key="period.key" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;border:1px solid var(--color-border-light);" :style="{ background: period.bgColor }">
                <span style="font-size:1.1rem;">{{ period.icon }}</span>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;font-size:0.85rem;">{{ period.label }}</div>
                  <div style="font-size:0.75rem;color:var(--color-text-secondary);">{{ period.dateRange }}</div>
                </div>
                <span class="badge" :class="period.badgeClass">{{ period.statusLabel }}</span>
              </div>
            </div>
          </div>
        </div>
        <div>
          <div class="card">
            <h3 class="card-title mb-md">Festius Barcelona 2026</h3>
            <div v-for="h in upcomingHolidays" :key="h.date" style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--color-border-light);font-size:0.85rem;">
              <span>{{ h.name }}</span>
              <span class="text-muted">{{ formatDate(h.date) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: ABSENCES & PERMISSIONS ═══ -->
    <div v-else-if="activeTab === 'absences'">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Sol·licitar permís o absència</h3>
          <button class="btn btn-accent btn-sm" @click="showPermissionModal = true">+ Nova sol·licitud</button>
        </div>
        <!-- Summary -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:16px;">
          <div v-for="summary in permissionSummary" :key="summary.type_id" class="stat-card" style="padding:12px;">
            <div class="text-small" style="font-weight:600;">{{ summary.name }}</div>
            <div style="display:flex;align-items:baseline;gap:4px;margin-top:4px;">
              <span style="font-size:1.3rem;font-weight:700;color:var(--color-primary);">{{ summary.used }}</span>
              <span v-if="summary.max" class="text-small text-muted">/ {{ summary.max }}</span>
              <span v-else class="text-small text-muted">dies</span>
            </div>
            <div v-if="summary.max && summary.used >= summary.max" class="text-small" style="color:var(--color-danger);margin-top:2px;">Esgotat</div>
          </div>
        </div>
      </div>

      <div class="card mt-lg">
        <h3 class="card-title mb-md">Els meus permisos</h3>
        <div v-if="myAbsences.length === 0" class="empty-state"><p>No hi ha permisos sol·licitats</p></div>
        <div class="table-container" v-else>
          <table>
            <thead><tr><th>Tipus</th><th>Període</th><th>Motiu</th><th>Estat</th></tr></thead>
            <tbody>
              <tr v-for="a in myAbsences" :key="a.id">
                <td style="font-weight:500;">{{ getAbsenceTypeName(a.absence_type_id) }}</td>
                <td class="text-small">{{ formatDate(a.start_date) }} → {{ formatDate(a.end_date) }}</td>
                <td class="text-small text-muted">{{ a.reason || '—' }}</td>
                <td><span class="badge" :class="absenceBadge(a.approved)">{{ absenceStatus(a.approved) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: EXCEDENCIES ═══ -->
    <div v-else-if="activeTab === 'excedencies'">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Sol·licitar excedència</h3>
          <button class="btn btn-accent btn-sm" @click="showExcedenciaModal = true">+ Nova sol·licitud</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;">
          <div v-for="et in excedenciaTypes" :key="et.id" class="stat-card" style="padding:16px;">
            <div style="font-weight:600;margin-bottom:4px;">{{ et.name }}</div>
            <div class="text-small text-muted" style="line-height:1.4;">{{ et.description }}</div>
            <div class="text-small" style="margin-top:8px;color:var(--color-primary);font-weight:500;">Durada: {{ et.min_months || 4 }}-{{ et.max_months || 60 }} mesos</div>
          </div>
        </div>
      </div>
      <div class="card mt-lg">
        <h3 class="card-title mb-md">Les meves excedències</h3>
        <div v-if="myExcedencies.length === 0" class="empty-state"><p>No hi ha excedències sol·licitades</p></div>
        <div class="table-container" v-else>
          <table>
            <thead><tr><th>Tipus</th><th>Inici</th><th>Fi</th><th>Estat</th></tr></thead>
            <tbody>
              <tr v-for="e in myExcedencies" :key="e.id">
                <td>{{ getExcTypeName(e.excedencia_type_id) }}</td><td>{{ formatDate(e.start_date) }}</td><td>{{ formatDate(e.end_date) }}</td>
                <td><span class="badge" :class="absenceBadge(e.status === 'approved' ? true : e.status === 'pending' ? null : false)">{{ excStatus(e.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: HISTORY ═══ -->
    <div v-else-if="activeTab === 'history'">
      <div class="card">
        <h3 class="card-title mb-md">Historial de fitxatges</h3>
        <p class="text-small text-muted mb-md">
          <span style="display:inline-flex;align-items:center;gap:4px;margin-right:16px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--color-success);"></span>Vàlid (dins zona + horari)</span>
          <span style="display:inline-flex;align-items:center;gap:4px;margin-right:16px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f97316;"></span>Extra no autoritzat (dins zona, fora horari)</span>
          <span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--color-danger);"></span>Fora de zona (no computa)</span>
        </p>
        <div v-if="myWorkLogs.length === 0" class="empty-state"><p>No hi ha fitxatges registrats</p></div>
        <div class="table-container" v-else>
          <table>
            <thead>
              <tr>
                <th>Data</th><th>Inici</th><th>Fi</th>
                <th style="color:var(--color-success);">H. Comptades</th>
                <th style="color:#f97316;">⏱ Extra</th>
                <th style="color:var(--color-danger);">📍 Fora zona</th>
                <th style="color:var(--color-success);">✓ Autorit.</th>
                <th>Estat</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in myWorkLogs" :key="l.id"
                :style="l.hour_status === 'out_of_area' ? 'background:rgba(239,68,68,0.06);border-left:3px solid var(--color-danger);' : l.hour_status === 'extra' ? 'background:rgba(249,115,22,0.06);border-left:3px solid #f97316;' : l.end_time ? 'background:rgba(76,175,80,0.04);border-left:3px solid var(--color-success);' : ''">
                <td>{{ formatDate(l.date) }}</td>
                <td class="text-small">{{ l.start_time ? formatTime(l.start_time) : '—' }}</td>
                <td class="text-small">{{ l.end_time ? formatTime(l.end_time) : '—' }}</td>
                <td>
                  <strong :style="l.hour_status === 'ok' ? 'color:var(--color-success)' : 'color:var(--color-text)'">
                    {{ Number(l.total_hours_worked || l.hours_worked || 0).toFixed(2) }}h
                  </strong>
                </td>
                <td>
                  <span v-if="(l.extra_hours_unauthorized||0) > 0" style="color:#f97316;font-weight:600;">{{ Number(l.extra_hours_unauthorized).toFixed(1) }}h</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span v-if="(l.hours_out_of_area||0) > 0" style="color:var(--color-danger);font-weight:600;">{{ Number(l.hours_out_of_area).toFixed(1) }}h</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span v-if="(l.extra_hours_authorized||0) > 0" style="color:var(--color-success);font-weight:600;">+{{ Number(l.extra_hours_authorized).toFixed(1) }}h</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td><span class="badge" :class="l.status === 'approved' ? 'badge-success' : l.status === 'pending' ? 'badge-warning' : 'badge-danger'">{{ l.status === 'approved' ? 'Aprovat' : l.status === 'pending' ? 'Pendent' : l.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: MENU ═══ -->
    <div v-else-if="activeTab === 'menu'">
      <div class="card" style="background: transparent; border: none; padding: 0; box-shadow: none;">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
          <!-- Absences -->
          <router-link to="/worker/absences" class="stat-card" style="padding: 24px 16px; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Permisos i absències</div>
          </router-link>

          <!-- Excedences -->
          <router-link to="/worker/excedencies" class="stat-card" style="padding: 24px 16px; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Excedències</div>
          </router-link>

          <!-- History -->
          <router-link to="/worker/history" class="stat-card" style="padding: 24px 16px; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: var(--color-success); display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><path d="M4.93 4.93l4.24 4.24"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Historial fitxatges</div>
          </router-link>

          <!-- Documents -->
          <router-link to="/worker/documents" class="stat-card" style="padding: 24px 16px; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249, 115, 22, 0.1); color: #f97316; display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Documents</div>
          </router-link>

          <!-- Settings -->
          <router-link to="/worker/settings" class="stat-card" style="padding: 24px 16px; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(107, 114, 128, 0.1); color: #6b7280; display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Configuració</div>
          </router-link>

          <!-- Logout -->
          <div @click="handleLogout" class="stat-card" style="padding: 24px 16px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; height: 100%;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: var(--color-danger); display: flex; align-items: center; justify-content: center;">
              <svg style="width: 28px; height: 28px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-text);">Tancar Sessió</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: SETTINGS ═══ -->
    <div v-else-if="activeTab === 'settings'">
      <div class="card" style="max-width:500px;">
        <h3 class="card-title mb-md">Canviar contrasenya</h3>
        <div class="form-group">
          <label class="form-label">Contrasenya actual</label>
          <input class="form-input" type="password" v-model="pwForm.oldPw" />
        </div>
        <div class="form-group">
          <label class="form-label">Nova contrasenya</label>
          <input class="form-input" type="password" v-model="pwForm.newPw" />
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nova contrasenya</label>
          <input class="form-input" type="password" v-model="pwForm.confirmPw" />
        </div>
        <div v-if="pwResult" class="mt-sm" style="padding:8px 12px;border-radius:8px;font-size:0.85rem;" 
          :style="{ background: pwResult.success ? 'rgba(76,175,80,0.1)' : 'rgba(239,68,68,0.1)', color: pwResult.success ? 'var(--color-success)' : 'var(--color-danger)' }">
          {{ pwResult.message }}
        </div>
        <button class="btn btn-primary mt-md" @click="changePassword" :disabled="!pwForm.newPw || pwForm.newPw !== pwForm.confirmPw">Desar contrasenya</button>
      </div>

      <div class="card mt-lg" style="max-width:500px;">
        <h3 class="card-title mb-md">Geolocalització</h3>
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="location-dot valid"></span>
          <span>Consentiment atorgat</span>
        </div>
        <p class="text-small text-muted mt-sm">La geolocalització és necessària per al registre de jornada conforme al RDL 8/2019.</p>
      </div>

      <!-- CP/Ambulatory Assignments (read-only for worker) -->
      <div class="card mt-lg">
        <h3 class="card-title mb-md">📍 Zones de treball assignades</h3>

        <!-- AMBULATORIA: show ambulatory center -->
        <template v-if="authStore.user?.work_type === 'AMBULATORIA'">
          <div v-if="activeAmbulatoryCenter" style="padding:12px;background:rgba(76,175,80,0.07);border-radius:8px;border-left:3px solid var(--color-success);margin-bottom:16px;">
            <div class="text-small" style="color:var(--color-success);font-weight:600;">Centre assignat (actiu)</div>
            <div style="margin-top:8px;font-weight:600;font-size:1rem;">🏥 {{ activeAmbulatoryCenter.name }}</div>
            <div class="text-small text-muted mt-sm">
              Des de {{ formatDateFull(activeAmbulatoryCenter.pivot?.valid_from) }}{{ activeAmbulatoryCenter.pivot?.valid_to ? ' fins ' + formatDateFull(activeAmbulatoryCenter.pivot.valid_to) : ' (indefinit)' }}
            </div>
          </div>
          <div v-else style="padding:12px;background:rgba(239,68,68,0.07);border-radius:8px;border-left:3px solid var(--color-danger);margin-bottom:16px;">
            <div class="text-small" style="color:var(--color-danger);">Cap centre assignat en data d'avui</div>
          </div>
          <h4 style="font-size:0.85rem;color:var(--color-text-secondary);margin-bottom:8px;">Històric de centres</h4>
          <div class="table-container">
            <table>
              <thead><tr><th>Centre</th><th>Des de</th><th>Fins a</th></tr></thead>
              <tbody>
                <tr v-for="c in ambulatoryCenterHistory" :key="c.id" :style="c.id === activeAmbulatoryCenter?.id ? 'background:rgba(76,175,80,0.04);' : ''">
                  <td>🏥 {{ c.name }}</td>
                  <td class="text-small">{{ c.pivot?.valid_from ? formatDate(c.pivot.valid_from) : '—' }}</td>
                  <td class="text-small">{{ c.pivot?.valid_to ? formatDate(c.pivot.valid_to) : '— (indefinit)' }}</td>
                </tr>
                <tr v-if="ambulatoryCenterHistory.length === 0"><td colspan="3" class="text-center text-muted">Sense assignacions</td></tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- DOMICILIARIA / VALLES: show postal code zones -->
        <template v-else>
          <div v-if="activeCpAssignment" style="padding:12px;background:rgba(76,175,80,0.07);border-radius:8px;border-left:3px solid var(--color-success);margin-bottom:16px;">
            <div class="text-small" style="color:var(--color-success);font-weight:600;">Assignació activa</div>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
              <span v-for="cp in activeCpAssignment.postal_codes" :key="cp" class="badge badge-primary" style="font-size:0.8rem;">{{ cp }} — {{ getCpZone(cp) }}</span>
            </div>
            <div class="text-small text-muted mt-sm">Des de {{ formatDateFull(activeCpAssignment.pivot?.valid_from) }}{{ activeCpAssignment.pivot?.valid_to ? ' fins ' + formatDateFull(activeCpAssignment.pivot.valid_to) : ' (indefinit)' }}</div>
          </div>
          <div v-else style="padding:12px;background:rgba(239,68,68,0.07);border-radius:8px;border-left:3px solid var(--color-danger);margin-bottom:16px;">
            <div class="text-small" style="color:var(--color-danger);">Cap assignació activa en data d'avui</div>
          </div>
          <h4 style="font-size:0.85rem;color:var(--color-text-secondary);margin-bottom:8px;">Històric d'assignacions</h4>
          <div class="table-container">
            <table>
              <thead><tr><th>Codis postals</th><th>Des de</th><th>Fins a</th><th>Notes</th></tr></thead>
              <tbody>
                <tr v-for="a in myCpHistory" :key="a.id" :style="a.id === activeCpAssignment?.id ? 'background:rgba(76,175,80,0.04);' : ''">
                  <td><span v-for="cp in a.postal_codes" :key="cp" class="badge badge-info" style="margin-right:4px;font-size:0.72rem;">{{ cp }}</span></td>
                  <td class="text-small">{{ formatDate(a.pivot?.valid_from) }}</td>
                  <td class="text-small">{{ a.pivot?.valid_to ? formatDate(a.pivot.valid_to) : '— (indefinit)' }}</td>
                  <td class="text-small">{{ a.notes || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <!-- Permission Request Modal -->
    <div v-if="showPermissionModal" class="modal-overlay" @click.self="showPermissionModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">Sol·licitar permís</h3>
          <button class="icon-btn" @click="showPermissionModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Tipus de permís</label>
          <select class="form-select" v-model="permForm.absence_type_id">
            <option v-for="t in absenceTypes" :key="t.id" :value="t.id">
              {{ t.name }} {{ t.max_per_year ? `(max ${t.max_per_year}/any)` : '' }}
            </option>
          </select>
          <div v-if="selectedType" class="text-small text-muted mt-sm">
            <span v-if="selectedType.remunerated" class="badge badge-success" style="font-size:0.65rem;">Remunerat</span>
            <span v-if="selectedType.recoverable" class="badge badge-warning" style="font-size:0.65rem;">Recuperable</span>
            <span v-if="selectedType.max_days" class="badge badge-info" style="font-size:0.65rem;">Max {{ selectedType.max_days }} dies</span>
          </div>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Data inici</label>
            <input class="form-input" type="date" v-model="permForm.start_date" />
          </div>
          <div class="form-group">
            <label class="form-label">Data fi</label>
            <input class="form-input" type="date" v-model="permForm.end_date" />
          </div>
        </div>
        <div v-if="selectedType?.extends_with_travel" class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" v-model="permForm.with_travel" style="width:16px;height:16px;" />
            Necessita desplaçament (+{{ selectedType.extra_days_travel || 2 }} dies)
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">Motiu</label>
          <textarea class="form-textarea" v-model="permForm.reason" :required="selectedType?.requires_justification"></textarea>
          <span v-if="selectedType?.requires_justification" class="text-small" style="color:var(--color-warning);">* Justificació obligatòria</span>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showPermissionModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="submitPermission" :disabled="loadingPermission">
            {{ loadingPermission ? '⌛' : 'Sol·licitar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Excedencia Request Modal -->
    <div v-if="showExcedenciaModal" class="modal-overlay" @click.self="showExcedenciaModal = false">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header">
          <h3 class="modal-title">Sol·licitar excedència</h3>
          <button class="icon-btn" @click="showExcedenciaModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Tipus d'excedència</label>
          <select class="form-select" v-model="excForm.excedencia_type_id">
            <option v-for="et in excedenciaTypes" :key="et.id" :value="et.id">{{ et.name }}</option>
          </select>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Data inici</label>
            <input class="form-input" type="date" v-model="excForm.start_date" />
          </div>
          <div class="form-group">
            <label class="form-label">Data fi</label>
            <input class="form-input" type="date" v-model="excForm.end_date" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Motiu</label>
          <textarea class="form-textarea" v-model="excForm.reason"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showExcedenciaModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="submitExcedencia" :disabled="loadingExc">
            {{ loadingExc ? '⌛' : 'Sol·licitar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Restart after completed shift Modal -->
    <div v-if="showRestartOvertimeModal" class="modal-overlay" style="z-index:99998;">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(249,115,22,0.12),rgba(239,68,68,0.08));border-radius:12px 12px 0 0;">
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
              <span style="font-size:1.5rem;">⏰</span>
              <span class="badge badge-danger" style="font-size:0.75rem;">JORNADA COMPLETADA</span>
            </div>
            <h3 class="modal-title">Ja has fet les hores d'avui</h3>
          </div>
        </div>
        <div style="padding:20px;">
          <div style="background:rgba(249,115,22,0.06);border-radius:12px;padding:16px;margin-bottom:16px;border-left:3px solid #f97316;">
            <p style="font-size:0.9rem;margin-bottom:8px;">
              <strong>⚠️ Ja has registrat {{ restartWorkedHours }} de les {{ restartScheduledHours }} hores programades avui.</strong>
            </p>
            <p style="font-size:0.85rem;color:var(--color-text-secondary);">
              Per iniciar una nova sessió necessites un <strong>codi d'autorització</strong> del teu responsable.
            </p>
          </div>
          <div class="form-group">
            <label class="form-label">Codi d'autorització</label>
            <input class="form-input" v-model="restartAuthCode" placeholder="Introduïu el codi..." style="font-family:monospace;font-size:1rem;text-align:center;letter-spacing:2px;" @input="restartAuthCode = restartAuthCode.toUpperCase()" />
          </div>
          <div v-if="restartAuthError" style="color:var(--color-danger);font-size:0.85rem;margin-bottom:12px;">{{ restartAuthError }}</div>
          <div style="display:flex;gap:12px;">
            <button class="btn btn-primary" style="flex:1;" @click="confirmRestartWithAuth" :disabled="!restartAuthCode.trim()">
              ✅ Validar i iniciar
            </button>
            <button class="btn btn-outline" style="flex:1;" @click="showRestartOvertimeModal = false">
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Overtime Alert Modal -->
    <div v-if="showOvertimeAlert" class="modal-overlay" style="z-index:99998;">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(249,115,22,0.12),rgba(239,68,68,0.08));border-radius:12px 12px 0 0;">
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
              <span style="font-size:1.5rem;">⏰</span>
              <span class="badge badge-danger" style="font-size:0.75rem;">FI DE JORNADA</span>
            </div>
            <h3 class="modal-title">Horari de treball finalitzat</h3>
          </div>
        </div>
        <div style="padding:20px;">
          <div style="background:rgba(249,115,22,0.06);border-radius:12px;padding:16px;margin-bottom:16px;border-left:3px solid #f97316;">
            <p style="font-size:0.9rem;margin-bottom:8px;">
              <strong>⚠️ El vostre horari de treball ha finalitzat fa {{ overtimeMinutes }} minuts.</strong>
            </p>
            <p style="font-size:0.85rem;color:var(--color-text-secondary);">
              Per continuar treballant necessiteu un <strong>codi d'autorització</strong>. Si no disposeu d'un codi, les hores extra es comptaran com a <strong>no autoritzades</strong> i seran descomptades del registre.
            </p>
          </div>
          <div class="form-group">
            <label class="form-label">Codi d'autorització</label>
            <input class="form-input" v-model="overtimeAuthCode" placeholder="Introduïu el codi..." style="font-family:monospace;font-size:1rem;text-align:center;letter-spacing:2px;" />
          </div>
          <div style="display:flex;gap:12px;">
            <button class="btn btn-primary" style="flex:1;" @click="submitOvertimeAuth" :disabled="!overtimeAuthCode.trim()">
              ✅ Validar codi i continuar
            </button>
            <button class="btn btn-danger" style="flex:1;" @click="dismissOvertimeNoAuth">
              ❌ No tinc codi — finalitzar
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="rdl-notice">{{ t('rdl_notice') }}</div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { getCurrentPosition, checkUserLocation, BARCELONA_POSTAL_CODES, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { Capacitor } from '@capacitor/core'
import { getPolygon } from '../services/geoPolygonService'
import { auditLog } from '../services/audit'
import { i18n } from '../i18n'

const props = defineProps({ initialTab: { type: String, default: '' } })
const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const route = useRoute()
const t = (key) => i18n.t(key)

// --- State Declarations ---
const locationStatus = ref('checking'), locationText = ref('')
const lastKnownPos = ref(null)
const locationClass = computed(() => locationStatus.value)

const showPermissionModal = ref(false)
const showExcedenciaModal = ref(false)
const calendarMonth = ref(new Date().getMonth()), calendarYear = ref(new Date().getFullYear())

const absenceTypes = ref([])
const excedenciaTypes = ref([])
const myAbsences = ref([])
const myWorkLogs = computed(() => workLogStore.logs.filter(l => l.user_id === authStore.userId).sort((a, b) => new Date(b.date) - new Date(a.date)))
const myExcedencies = ref([])
const activeCpAssignment = ref(null)
const myCpHistory = ref([])
const activeMuniAssignment = ref(null)
const myMuniHistory = ref([])
const activeAmbulatoryCenter = ref(null)
const ambulatoryCenterHistory = ref([])
const holidays = ref([])

const loadingPermission = ref(false)
const loadingExc = ref(false)
const pwResult = ref(null)

const permForm = reactive({ absence_type_id: 1, start_date: '', end_date: '', reason: '', with_travel: false })
const selectedType = computed(() => absenceTypes.value.find(t => t.id === permForm.absence_type_id))
const excForm = reactive({ excedencia_type_id: 1, start_date: '', end_date: '', reason: '' })
const pwForm = reactive({ oldPw: '', newPw: '', confirmPw: '' })

const authLogId = ref('')
const authCodeInput = ref('')
const authCodeResult = ref(null)
const logsWithUnauthorized = computed(() => myWorkLogs.value.filter(l => (l.extra_hours_unauthorized || 0) > 0))
const isFinishing = ref(false)

const permissionUsage = ref({})
const permissionSummary = computed(() => {
  return absenceTypes.value.filter(t => t.max_per_year).map(t => ({
    type_id: t.id, name: t.name, max: t.max_per_year * (t.max_days || 1),
    used: permissionUsage.value[t.id] || 0
  }))
})

// --- Tab management ---
const tabMap = { 'worker-dashboard': 'clock', 'worker-calendar': 'calendar', 'worker-absences': 'absences', 'worker-excedencies': 'excedencies', 'worker-history': 'history', 'worker-settings': 'settings', 'worker-menu': 'menu' }
const activeTab = computed(() => props.initialTab || tabMap[route.name] || 'clock')
const tabTitles = { clock: 'Fitxatge', calendar: 'Calendari laboral', absences: 'Permisos i absències', excedencies: 'Excedències', history: 'Historial de fitxatges', settings: 'Configuració', menu: 'Menú principal' }

// --- Data Fetching ---
async function fetchData() {
  try {
    const data = await db.getWorkerBulkInfo()
    
    absenceTypes.value = data.absence_types || []
    myAbsences.value = data.absences || []
    excedenciaTypes.value = data.excedencia_types || []
    myExcedencies.value = data.excedencias || []
    activeCpAssignment.value = data.cp_assignment || null
    myCpHistory.value = data.cp_history || []
    activeMuniAssignment.value = data.muni_assignment || null
    myMuniHistory.value = data.muni_history || []
    activeAmbulatoryCenter.value = data.ambulatory_center || null
    ambulatoryCenterHistory.value = data.ambulatory_center_history || []
    holidays.value = data.holidays || []
    permissionUsage.value = data.permission_usage || {}
    
    if (data.user) authStore.user = data.user
    if (data.work_logs) {
      workLogStore.logs = data.work_logs
      // Restore timer if there is any open workday (including previous days)
      const activeLog = data.work_logs.find(l => l.user_id === authStore.userId && !l.end_time)
      if (activeLog && !workLogStore.isWorking) {
        workLogStore.currentLog = activeLog
        workLogStore.isWorking = true
        workLogStore.startTimer()
      }
    }
  } catch (e) {
    console.error('[WorkerDashboard] fetchData failed:', e)
  }
}

// --- Methods ---
function formatH(hoursDecimal) {
  const totalMinutes = Math.round(Number(hoursDecimal) * 60)
  const h = Math.floor(totalMinutes / 60)
  const m = totalMinutes % 60
  if (h === 0 && m === 0) return '0h'
  if (h === 0) return `${m} min`
  if (m === 0) return `${h}h`
  return `${h}h ${m}m`
}

async function changePassword() {
  if (pwForm.newPw !== pwForm.confirmPw) {
    pwResult.value = { success: false, message: 'Les contrasenyes no coincideixen' }
    return
  }
  pwResult.value = await authStore.changePassword(pwForm.oldPw, pwForm.newPw)
  if (pwResult.value.success) {
    pwForm.oldPw = ''; pwForm.newPw = ''; pwForm.confirmPw = ''
  }
}

const todayHours = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  const completed = Number(myWorkLogs.value.filter(l => l.date && l.date.substring(0, 10) === today).reduce((s, l) => s + Number(l.total_hours_worked || l.hours_worked || 0), 0))
  const current = (workLogStore.isWorking && workLogStore.elapsed) ? (workLogStore.elapsed / 3600) : 0
  return formatH(completed + current)
})
const todayAuthorized = computed(() => {
  const now = new Date()
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  const total = myWorkLogs.value.filter(l => l.date && l.date.substring(0, 7) === month).reduce((s, l) => s + Number(l.extra_hours_authorized || 0), 0)
  return formatH(total)
})
const todayUnauthorized = computed(() => {
  const now = new Date()
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  const total = myWorkLogs.value.filter(l => l.date && l.date.substring(0, 7) === month).reduce((s, l) => s + Number(l.extra_hours_unauthorized || 0), 0)
  return formatH(total)
})
const weekHours = computed(() => {
  const now = new Date(); const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1); weekStart.setHours(0,0,0,0)
  const ws = weekStart.toISOString().split('T')[0]
  return formatH(myWorkLogs.value.filter(l => l.date && l.date.substring(0, 10) >= ws).reduce((s, l) => s + Number(l.total_hours_worked || l.hours_worked || 0), 0))
})

const router = useRouter() // Add router to use it for logout and navigation

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

async function submitAuthCode() {
  if (!authLogId.value || !authCodeInput.value) return
  authCodeResult.value = await workLogStore.authorizeExtraHours(authLogId.value, authCodeInput.value.trim())
  if (authCodeResult.value.success) {
    authCodeInput.value = ''
    authLogId.value = ''
    await fetchData()
  }
}

const showRestartOvertimeModal = ref(false)
const restartAuthCode = ref('')
const restartAuthError = ref('')
const restartWorkedHours = ref('0h')
const restartScheduledHours = ref('0h')

async function startWorkday() {
  // Check if scheduled hours already done today
  const scheduledHours = await getScheduledHours()
  if (scheduledHours) {
    const today = new Date().toISOString().split('T')[0]
    const todayCompleted = myWorkLogs.value
      .filter(l => l.date && l.date.substring(0, 10) === today && l.end_time)
      .reduce((s, l) => s + Number(l.total_hours_worked || l.hours_worked || 0), 0)
    if (todayCompleted >= scheduledHours) {
      restartWorkedHours.value = formatH(todayCompleted)
      restartScheduledHours.value = formatH(scheduledHours)
      restartAuthCode.value = ''
      restartAuthError.value = ''
      showRestartOvertimeModal.value = true
      return
    }
  }
  await doStartWorkday()
}

async function confirmRestartWithAuth() {
  if (!restartAuthCode.value.trim()) return
  restartAuthError.value = ''
  try {
    const result = await db.validateAuthCode(restartAuthCode.value.trim())
    if (!result.valid) {
      restartAuthError.value = result.reason || 'Codi invàlid o caducat'
      return
    }
    await db.useAuthCode(restartAuthCode.value.trim())
    auditLog(authStore.userId, 'AUTHORIZE_OVERTIME_RESTART', 'work_log', null, `Reinici de jornada autoritzat amb codi`)
  } catch (e) {
    restartAuthError.value = 'Error en validar el codi. Torneu-ho a provar.'
    return
  }
  showRestartOvertimeModal.value = false
  await doStartWorkday()
}

async function doStartWorkday() {
  try {
    // Reuse the position from checkLocation() if GPS was already marked as poor,
    // to avoid a second getCurrentPosition() that might yield a different accuracy
    // and incorrectly trigger the OUT_OF_ZONE block.
    const pos = (locationStatus.value === 'warning' && cachedPos) ? cachedPos : await getCurrentPosition()
    await workLogStore.startWorkday(pos.lat, pos.lng, pos.accuracy)
    await nextTick()
    setTimeout(initWorkerMap, 500)
  } catch (err) {
    if (err?.code === 'OUT_OF_ZONE') {
      alert(`⚠️ No pots iniciar la jornada.\n\n${err.message}\n\nDesplaça't a la zona assignada per fitxar.`)
    } else {
      alert('Error al carregar la ubicació. Comproveu els permisos de GPS.')
    }
  }
}

async function endWorkday() {
  if (workLogStore.elapsed < 60) {
    if (!confirm('La jornada ha durat menys d\'un minut. Segur que vols finalitzar? (Es guardarà amb 0h)')) return
  }
  
  isFinishing.value = true
  try {
    // GPS Reliable End: Try to get current position with a strict timeout
    const gpsTimeout = new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 6000))
    const pos = await Promise.race([
      getCurrentPosition(),
      gpsTimeout
    ]).catch(() => lastKnownPos.value)

    await workLogStore.endWorkday(pos?.lat || null, pos?.lng || null)
  } catch (err) {
    console.error('Error finalitzant jornada:', err)
    await workLogStore.endWorkday(lastKnownPos.value?.lat || null, lastKnownPos.value?.lng || null)
  } finally {
    isFinishing.value = false
  }
}

async function submitPermission() {
  if (!permForm.start_date || !permForm.end_date) return
  loadingPermission.value = true
  try {
    await db.addAbsence({
      user_id: authStore.userId,
      ...permForm,
      approved: null
    })
    myAbsences.value = await db.getAbsencesByUser(authStore.userId)
    myAbsences.value.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    showPermissionModal.value = false
    auditLog(authStore.userId, 'REQUEST_ABSENCE', 'absence', null, `Sol·licitud: ${selectedType.value.name}`)
  } catch (err) {
    console.error(err)
    alert('Error al sol·licitar el permís')
  } finally {
    loadingPermission.value = false
  }
}

async function submitExcedencia() {
  if (!excForm.excedencia_type_id || !excForm.start_date || !excForm.end_date) return
  loadingExc.value = true
  try {
    const excData = {
      user_id: authStore.userId,
      ...excForm,
      status: 'pending'
    }
    await db.addExcedencia(excData)
    myExcedencies.value = await db.getExcedenciasByUser(authStore.userId)
    showExcedenciaModal.value = false
    const typeName = excedenciaTypes.value.find(t => t.id === excForm.excedencia_type_id)?.name || ''
    auditLog(authStore.userId, 'REQUEST_EXCEDENCIA', 'excedencia', null, `Sol·licitud: ${typeName}`)
    // Reset form
    Object.assign(excForm, { excedencia_type_id: 1, start_date: '', end_date: '', reason: '' })
  } catch (err) {
    console.error(err)
    alert('Error al sol·licitar l\'excedència')
  } finally {
    loadingExc.value = false
  }
}









// Calendar
const calendarMonthName = computed(() => new Date(calendarYear.value, calendarMonth.value).toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' }))
const calendarDays = computed(() => {
  const first = new Date(calendarYear.value, calendarMonth.value, 1)
  const lastDay = new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate()
  const startDow = first.getDay() === 0 ? 6 : first.getDay() - 1
  const today = new Date().toISOString().split('T')[0]
  const days = []
  for (let i = startDow - 1; i >= 0; i--) {
    const d = new Date(calendarYear.value, calendarMonth.value, -i)
    days.push({ key: 'prev' + i, dayNum: d.getDate(), date: null, currentMonth: false })
  }
  for (let d = 1; d <= lastDay; d++) {
    const dateStr = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const dow = new Date(calendarYear.value, calendarMonth.value, d).getDay()
    const isWeekend = dow === 0 || dow === 6
    const holiday = (holidays.value || []).find(h => h.date === dateStr)
    const absence = myAbsences.value.find(a => dateStr >= a.start_date && dateStr <= a.end_date) || null
    const excedencia = myExcedencies.value.find(e => dateStr >= e.start_date && dateStr <= e.end_date) || null
    const hasPermission = !!absence && absence.approved !== false
    const hasWorklog = workLogStore.logs.some(l => l.user_id === authStore.userId && l.date && l.date.substring(0, 10) === dateStr)
    days.push({ key: dateStr, dayNum: d, date: dateStr, currentMonth: true, isToday: dateStr === today, isWeekend, holiday, absence, excedencia, hasPermission, hasWorklog })
  }
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) days.push({ key: 'next' + i, dayNum: i, date: null, currentMonth: false })
  return days
})

const upcomingHolidays = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return (holidays.value || []).filter(h => h.date >= today).slice(0, 8)
})

const calendarPeriods = computed(() => {
  const monthStart = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-01`
  const monthEnd = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-${String(new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate()).padStart(2, '0')}`
  const periods = []

  myAbsences.value.forEach(a => {
    if (a.end_date < monthStart || a.start_date > monthEnd) return
    const typeName = getAbsenceTypeName(a.absence_type_id)
    const isVacation = typeName.toLowerCase().includes('vacan') || typeName.toLowerCase().includes('vacac')
    let icon, bgColor, badgeClass, statusLabel
    if (a.approved === true) {
      icon = isVacation ? '🟢' : '🔵'
      bgColor = isVacation ? 'rgba(34,197,94,0.07)' : 'rgba(59,130,246,0.07)'
      badgeClass = 'badge-success'
      statusLabel = 'Aprovat'
    } else if (a.approved === null) {
      icon = '🟡'
      bgColor = 'rgba(234,179,8,0.07)'
      badgeClass = 'badge-pending'
      statusLabel = 'Pendent'
    } else {
      icon = '⚪'
      bgColor = 'rgba(0,0,0,0.03)'
      badgeClass = 'badge-danger'
      statusLabel = 'Denegat'
    }
    periods.push({
      key: `abs-${a.id}`,
      label: typeName,
      dateRange: `${formatDate(a.start_date)} → ${formatDate(a.end_date)}`,
      icon, bgColor, badgeClass, statusLabel
    })
  })

  myExcedencies.value.forEach(e => {
    if (e.end_date < monthStart || e.start_date > monthEnd) return
    const typeName = excedenciaTypes.value.find(t => t.id === e.excedencia_type_id)?.name || 'Excedència'
    periods.push({
      key: `exc-${e.id}`,
      label: typeName,
      dateRange: `${formatDate(e.start_date)} → ${formatDate(e.end_date)}`,
      icon: '🟣',
      bgColor: 'rgba(139,92,246,0.07)',
      badgeClass: 'badge-info',
      statusLabel: 'Excedència'
    })
  })

  return periods
})

function prevMonth() { if (calendarMonth.value === 0) { calendarMonth.value = 11; calendarYear.value-- } else calendarMonth.value-- }
function nextMonth() { if (calendarMonth.value === 11) { calendarMonth.value = 0; calendarYear.value++ } else calendarMonth.value++ }
function dayBackground(day) {
  if (!day.currentMonth) return 'transparent'
  if (day.holiday) return 'rgba(239,68,68,0.08)'
  if (day.excedencia) return 'rgba(139,92,246,0.10)'
  if (day.absence) {
    if (day.absence.approved === true) {
      const typeName = getAbsenceTypeName(day.absence.absence_type_id).toLowerCase()
      if (typeName.includes('vacan') || typeName.includes('vacac')) return 'rgba(34,197,94,0.12)'
      return 'rgba(59,130,246,0.10)'
    }
    if (day.absence.approved === null) return 'rgba(234,179,8,0.10)'
  }
  if (day.isWeekend) return 'var(--color-bg)'
  return 'transparent'
}
function dayColor(day) {
  if (day.holiday) return 'var(--color-danger)'
  if (day.isWeekend) return 'var(--color-text-light)'
  return 'var(--color-text)'
}


// If the browser reports accuracy worse than this (meters) the device has no GPS
// module and is estimating via IP/WiFi — don't block clock-in in that case.
const GPS_ACCURACY_THRESHOLD = 500
// Cached position from the last checkLocation() call, reused in doStartWorkday()
// to avoid a second getCurrentPosition() that might return different accuracy.
let cachedPos = null

// Location check — uses shared logic including AMBULATORIA check
async function checkLocation() {
  locationStatus.value = 'checking'
  locationText.value = 'Verificant ubicació...'
  cachedPos = null
  try {
    const pos = await getCurrentPosition()
    cachedPos = pos

    if (pos.accuracy > GPS_ACCURACY_THRESHOLD) {
      locationStatus.value = 'warning'
      locationText.value = '⚠️ GPS imprecís — dispositiu sense GPS, ubicació no verificable'
      return
    }

    const assignment = await db.getActiveCpAssignment(authStore.userId)
    const result = await checkUserLocation(authStore.user, assignment, pos.lat, pos.lng)

    locationStatus.value = result.valid ? 'valid' : 'invalid'
    locationText.value = result.valid ? t('location_valid') : (result.message || t('location_invalid'))
  } catch (e) {
    if (e.code === 3) { // Timeout
      locationStatus.value = 'invalid'
      locationText.value = 'Buscant senyal GPS (massa temps d\'espera)...'
    } else {
      console.error('[LocationCheck] Error:', e)
      locationStatus.value = 'invalid'
      locationText.value = 'Error al verificar ubicació'
    }
  }
}

// Privacy by Design (art. 90 LOPDGDD): cap seguiment continu d'ubicació.
// La posició es llegeix només en el moment d'una marca, es valida i es descarta.

onUnmounted(async () => {
  if (overtimeCheckInterval) clearInterval(overtimeCheckInterval)
})

onMounted(async () => {
  await fetchData()
  await checkLocation()
  // Init worker map if already on clock tab
  if (activeTab.value === 'clock') {
    await nextTick()
    setTimeout(initWorkerMap, 400)
  }
  // Start end-of-shift overtime monitor
  startOvertimeMonitor()
})

// ── End-of-shift overtime alert ─────────────────────────────
const showOvertimeAlert = ref(false)
const overtimeMinutes = ref(0)
const overtimeAuthCode = ref('')
const overtimeAlertDismissed = ref(false)
let overtimeCheckInterval = null

async function getScheduledHours() {
  const sched = await db.getWorkSchedule(authStore.user?.work_schedule_id)
  if (!sched) return null
  const today = new Date().getDay() // 0=Sunday
  const dayEntry = sched.days?.find(d => (d.day === today || d.day_num === today) && d.active)
  if (!dayEntry?.start || !dayEntry?.end) return null
  const [sh, sm] = dayEntry.start.split(':').map(Number)
  const [eh, em] = dayEntry.end.split(':').map(Number)
  return (eh * 60 + em - sh * 60 - sm) / 60 // scheduled hours as decimal
}

function startOvertimeMonitor() {
  if (overtimeCheckInterval) clearInterval(overtimeCheckInterval)
  overtimeCheckInterval = setInterval(async () => {
    if (!workLogStore.isWorking || overtimeAlertDismissed.value) return
    if (!workLogStore.currentLog?.start_time) return
    const scheduledHours = await getScheduledHours()
    if (!scheduledHours) return
    const start = new Date(workLogStore.currentLog.start_time)
    const workedHours = (new Date() - start) / 3600000
    if (workedHours > scheduledHours) {
      const mins = Math.round((workedHours - scheduledHours) * 60)
      overtimeMinutes.value = mins
      if (mins >= 1 && !showOvertimeAlert.value) {
        showOvertimeAlert.value = true
      }
    }
  }, 60000) // check every 60s
}

async function submitOvertimeAuth() {
  if (!overtimeAuthCode.value.trim()) return
  try {
    const result = await db.validateAuthCode(overtimeAuthCode.value.trim())
    if (result.valid) {
      await db.useAuthCode(overtimeAuthCode.value.trim())
      auditLog(authStore.userId, 'AUTHORIZE_OVERTIME', 'work_log', workLogStore.currentLog?.id, `Hores extra autoritzades amb codi`)
    } else {
      alert(result.reason || 'Codi invàlid o caducat')
      return
    }
  } catch (e) {
    alert('Error en validar el codi. Torneu-ho a provar.')
    return
  }
  showOvertimeAlert.value = false
  overtimeAlertDismissed.value = true
  overtimeAuthCode.value = ''
}

function dismissOvertimeNoAuth() {
  showOvertimeAlert.value = false
  overtimeAlertDismissed.value = true
  overtimeAuthCode.value = ''
}

function getAbsenceTypeName(id) { return absenceTypes.value.find(a => a.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short' }) }
function formatDateFull(d) { return new Date(d).toLocaleDateString('ca-ES', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Madrid' }) }
function absenceBadge(a) { return a === null ? 'badge-pending' : a ? 'badge-success' : 'badge-danger' }
function absenceStatus(a) { return a === null ? t('pending') : a ? t('approved') : t('rejected') }
function getExcTypeName(id) { return excedenciaTypes.value.find(t => t.id === id)?.name || '—' }
function excStatus(s) { 
  const map = { pending: 'Pendent', approved: 'Aprovada', active: 'Activa', rejected: 'Rebutjada', completed: 'Finalitzada', denied: 'Denegada' }
  return map[s] || s
}

// CP Assignments (worker view)
function getCpZone(code) { return BARCELONA_POSTAL_CODES[code]?.zone || code }

// ── Worker zone map (uses shared geoPolygonService) ──────────────────────────
const WORKER_ZONE_COLORS = ['#3b82f6','#10b981','#8b5cf6','#f97316','#ef4444']

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


async function initWorkerMap() {
  const isAmbulatoria = authStore.user?.work_type === 'AMBULATORIA';
  const isValles = authStore.user?.work_type === 'DOMICILIARIA_VALLES';
  if (!activeCpAssignment.value && !isAmbulatoria && !isValles) return
  await loadLeaflet()
  await nextTick()
  const container = document.getElementById('worker-zone-map')
  if (!container) return
  if (container._leaflet_id) {
    try { window._workerLeafletMap?.remove(); } catch {}
    container._leaflet_id = null
    container.innerHTML = ''
  }
  const L = window.L
  const map = L.map(container, { zoomControl: true, scrollWheelZoom: false }).setView([41.3880, 2.1690], 13)
  window._workerLeafletMap = map
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>', maxZoom: 19
  }).addTo(map)

  const codes = activeCpAssignment.value?.postal_codes || []
  const bounds = []

  const geometries = await Promise.all(codes.map(cp => getPolygon(cp)))

  for (let i = 0; i < codes.length; i++) {
    const cp = codes[i]
    if (authStore.user?.work_type === 'AMBULATORIA') continue; 
    const color = WORKER_ZONE_COLORS[i % WORKER_ZONE_COLORS.length]
    const geometry = geometries[i]
    const cpInfo = BARCELONA_POSTAL_CODES[cp]
    const popupHtml = `<strong>${cp}</strong> &mdash; ${getCpZone(cp)}<br>
      <span style="font-size:0.8rem;color:#666;">Zona autoritzada per al fitxatge</span>
      ${cpInfo ? `<br><span style="font-size:0.72rem;color:#999;">(validaci&oacute; per pol&iacute;gon real)</span>` : ''}`

    if (geometry) {
      const layer = L.geoJSON(geometry, {
        style: { color, weight: 3, opacity: 1, fillColor: color, fillOpacity: 0.18 }
      }).addTo(map).bindPopup(popupHtml)
      bounds.push(layer.getBounds())
    } else {
      if (cpInfo) {
        L.circle([cpInfo.lat, cpInfo.lng], {
          radius: cpInfo.radius, color, weight: 2, fillColor: color, fillOpacity: 0.1, dashArray: '6,4'
        }).addTo(map).bindPopup(popupHtml + '<br><em style="font-size:0.7rem;color:#f97316;">Cercle provisional (carregant pol&iacute;gon...)</em>')
        bounds.push(L.latLng(cpInfo.lat, cpInfo.lng).toBounds(cpInfo.radius * 2))
      }
    }
  }

  if (authStore.user?.work_type === 'AMBULATORIA') {
      try {
        const points = await db.getAmbulatoryPoints(authStore.userId)
        points.forEach(pt => {
            L.circle([pt.lat, pt.lng], {
              radius: pt.radius || 100, color: '#10b981', weight: 2, fillColor: '#10b981', fillOpacity: 0.3
            }).addTo(map).bindPopup(`<strong>${pt.name}</strong><br><span style="font-size:0.8rem;color:#666;">Radi: ${pt.radius || 100}m</span>`)
            bounds.push(L.latLng(pt.lat, pt.lng).toBounds((pt.radius || 100) * 2))
        })
      } catch (err) { console.error('Error loading points:', err) }
  }

  // DOMICILIARIA_VALLES: draw municipal circles
  if (authStore.user?.work_type === 'DOMICILIARIA_VALLES') {
      const assignment = activeMuniAssignment.value
      if (assignment?.municipalities) {
        assignment.municipalities.forEach((muniCode, i) => {
          const muni = VALLES_MUNICIPALITIES[muniCode]
          if (muni) {
            const color = WORKER_ZONE_COLORS[i % WORKER_ZONE_COLORS.length]
            L.circle([muni.lat, muni.lng], {
              radius: muni.radius, color, weight: 2, fillColor: color, fillOpacity: 0.12, dashArray: '6,4'
            }).addTo(map).bindPopup(`<strong>${muni.name}</strong><br><span style="font-size:0.8rem;color:#666;">Terme municipal</span>`)
            bounds.push(L.latLng(muni.lat, muni.lng).toBounds(muni.radius * 2))
          }
        })
      }
  }

  if (bounds.length) {
    const combined = bounds.reduce((acc, b) => acc.extend(b), bounds[0])
    map.fitBounds(combined, { padding: [24, 24] })
  }
}

watch(() => activeTab.value, async (tab) => {
  if (tab === 'clock') {
    await nextTick()
    setTimeout(initWorkerMap, 400)
  }
})
</script>