<template>
  <div>
    <!-- Alert Banner (alertas molestas) -->
    <WorkLogAlertBanner />

    <!-- Sense horari assignat: no es poden calcular hores fora d'horari, extres ni la pausa -->
    <div v-if="senseHorari" class="avis-sense-horari" role="alert">
      <span class="ash-ico">🗓️</span>
      <div>
        <strong>Encara no tens horari assignat.</strong>
        <div>Pots fitxar igualment, però fins que no el tinguis no es poden calcular bé les hores fora d'horari, les extres ni la pausa. Avisa RRHH perquè te l'assigni: <a href="mailto:rrhh@lirc.es">rrhh@lirc.es</a>.</div>
      </div>
    </div>

    <!-- Break Timer Modal (pausa obligatoria, no se puede omitir) -->
    <BreakTimerModal
      :visible="breakModalVisible"
      :duration-minutes="breakDuration"
      :start-time="breakStartTime"
      :clock-offset-ms="breakClockOffsetMs"
      @complete="onBreakComplete"
    />

    <!-- Tab Navigation -->
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ tabTitles[activeTab] }}</h1>
        <p class="page-subtitle">{{ tabAjuda[activeTab] }}</p>
      </div>
    </div>

    <!-- Tab content based on route -->
    <!-- ═══ TAB: FITXATGE ═══ -->
    <div v-if="activeTab === 'clock'">
      <!-- Jornades d'altres dies sense sortida: la persona pot declarar l'hora (el registre no es toca) -->
      <AvisSenseSortida @carregat="pendentsSortida = $event" />
      <div class="dashboard-grid">
        <div>
          <div class="time-tracker" id="worker-clock">
            <div class="tracker-label">{{ workLogStore.isWorking ? 'Estàs treballant' : 'Encara no has començat la jornada' }}</div>
            <div class="tracker-time">{{ workLogStore.formattedElapsed }}</div>
            <div v-if="workLogStore.isWorking && workLogStore.horaEntrada" class="tracker-entrada">
              Entrada a les <strong>{{ workLogStore.horaEntrada }}</strong>
              <template v-if="sortidaPrevista"> · sortida prevista a les <strong>{{ sortidaPrevista }}</strong></template>
            </div>
            <!-- Temps fora d'horari: es veu en directe i diu si està autoritzat o no -->
            <div v-if="workLogStore.isWorking && segonsFora > 0" class="tracker-extra" :class="'tracker-extra--' + estatExtra.to">
              <div class="tracker-extra-cap">⏰ Fora d'horari <strong>+{{ fmtHMS(segonsFora) }}</strong></div>
              <div class="tracker-extra-text">{{ estatExtra.text }}</div>
              <button v-if="estatExtra.to === 'cap' || estatExtra.to === 'denegada'" class="tracker-extra-btn" @click="obreAvisExtra">
                Demanar autorització o posar codi
              </button>
            </div>
            <div class="location-status mb-md" :class="locationClass" style="display:inline-flex;margin:8px auto;">
              <span class="location-dot" :class="locationClass"></span>
              {{ locationText }}
            </div>
            <div class="tracker-buttons">
              <button v-if="!workLogStore.isWorking" class="btn btn-start" @click="startWorkday" id="btn-clock-in">
                ▶ {{ t('start_workday') }}
              </button>
              <button v-else class="btn btn-stop" @click="endWorkday" id="btn-clock-out" :disabled="isFinishing">
                <span v-if="isFinishing" class="spinner-small" style="margin-right:8px;"></span>
                <span v-else>⏹</span> {{ isFinishing ? 'Finalitzant...' : t('end_workday') }}
              </button>
            </div>
          </div>

          <!-- Break scheduled info banner -->
          <div v-if="breakScheduledTime" class="card mt-md" style="border-left:4px solid var(--color-warning);padding:12px 16px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:1.5rem;">⏱️</span>
            <div>
              <div style="font-weight:600;color:var(--color-warning);">Pausa obligatòria programada</div>
              <div class="text-small text-muted">La pausa s'activarà automàticament a les <strong>{{ breakScheduledTime.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' }) }}</strong>. No es podrà ometre.</div>
            </div>
          </div>

          <!-- Today's summary with authorized/unauthorized split -->
          <div class="card mt-lg">
            <h3 class="card-title mb-md">Resum del dia</h3>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px;">
              <div class="stat-card" style="padding:12px;"><div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Hores fetes avui</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-primary);margin-top:4px;">{{ todayHours }}</div></div>
              <div class="stat-card" style="padding:12px;"><div class="text-small" style="font-weight:600;color:var(--color-text-secondary);">Hores aquesta setmana</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-accent);margin-top:4px;">{{ weekHours }}</div></div>
              <div class="stat-card" style="padding:12px;border-bottom:3px solid var(--color-success);"><div class="text-small" style="font-weight:600;color:var(--color-success);">✓ Hores extra aprovades aquest mes</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-success);margin-top:4px;">{{ todayAuthorized }}</div></div>
              <div class="stat-card" style="padding:12px;border-bottom:3px solid var(--color-danger);"><div class="text-small" style="font-weight:600;color:var(--color-danger);">✗ Hores extra sense aprovar aquest mes</div><div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);margin-top:4px;">{{ todayUnauthorized }}</div></div>
            </div>
          </div>

          <!-- Auth Code input for extra hours -->
          <!-- Només surt si hi ha alguna cosa a fer: un fitxatge amb hores extra sense aprovar o un codi vigent -->
          <div v-if="logsWithUnauthorized.length || myAuthCodes.length" class="card mt-lg" style="border-left:4px solid var(--color-warning);">
            <h3 class="card-title mb-md">🔑 Aprovar hores extra amb un codi</h3>
            <div v-if="myAuthCodes.length" style="margin-bottom:12px;">
              <div class="text-small" style="font-weight:600;margin-bottom:6px;">Els teus codis vigents — toca'n un per fer-lo servir:</div>
              <div style="display:flex;flex-direction:column;gap:6px;">
                <button v-for="c in myAuthCodes" :key="c.id" @click="authCodeInput = c.code"
                  style="text-align:left;border:1.5px solid var(--color-border);border-radius:9px;padding:9px 12px;background:var(--color-surface);cursor:pointer;min-height:44px;"
                  :style="authCodeInput === c.code ? 'border-color:var(--color-accent);' : ''">
                  <code style="font-weight:700;">{{ c.code }}</code>
                  · {{ formatHM(c.authorized_hours) }} {{ c.type === 'extraordinaria' ? 'extraordinàries (1,25×)' : 'complementàries' }}
                  <div class="text-small text-muted">{{ c.concept }}</div>
                </button>
              </div>
            </div>
            <p class="text-small text-muted" style="margin-bottom:12px;">{{ myAuthCodes.length ? 'O escriu un codi que t\'hagin donat per una altra via.' : 'Tens hores extra sense aprovar. Tria el dia i escriu el codi que t\'ha donat el teu responsable.' }}</p>
            <div class="mobile-stack-form" style="display:flex; flex-direction: column; gap:12px; margin-bottom:8px;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Quin dia?</label>
                <select class="form-select" v-model="authLogId" style="width:100%;">
                  <option value="">Tria el dia…</option>
                  <option v-for="l in logsWithUnauthorized" :key="l.id" :value="l.id">{{ formatDateFull(l.date) }} — {{ formatHM(l.extra_hours_unauthorized) }} sense aprovar</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Codi</label>
                <input class="form-input" v-model="authCodeInput" placeholder="XXXX-XXXX-XXXX" style="font-family:monospace;font-size:1rem;letter-spacing:1px; width:100%;" @input="authCodeInput = authCodeInput.toUpperCase()" />
              </div>
              <button class="btn btn-accent" style="width:100%;" @click="submitAuthCode" :disabled="!authCodeInput || !authLogId">Aprovar les hores</button>
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

          <!-- Horari d'avui i de demà: dins la mateixa targeta, just sota el mapa -->
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--color-border-light);display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
            <div v-for="d in [horariDia(0), horariDia(1)]" :key="d.etiqueta">
              <div class="text-small text-muted">📅 {{ d.etiqueta }} <span style="opacity:.75;">· {{ d.data }}</span></div>
              <span v-if="d.horari" style="font-size:1.15rem;font-weight:700;" :style="{ color: d.avui ? 'var(--color-primary)' : 'var(--color-text)' }">{{ d.horari }}</span>
              <span v-else class="text-small" :style="{ color: d.motiu ? 'var(--color-danger)' : 'var(--color-text-muted)', fontWeight: d.motiu ? 600 : 400 }">{{ d.motiu || 'Lliure' }}</span>
            </div>
          </div>

          <!-- Quick calendar: dins la mateixa targeta, sota l'horari d'avui -->
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--color-border-light);">
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
      <!-- Pacte d'hores complementàries: va a la pestanya de fitxatge perquè és on el
           professional decideix sobre la seva jornada, i perquè sense acceptar-lo no se li
           en poden programar. Amagat aquí, ningú el trobaria. -->
      <div class="dashboard-grid" style="margin-top:16px">
        <div style="grid-column:1/-1;">
          <PacteComplementaries />
          <!-- Pràctiques: hores del conveni i les que queden (es resten quan RRHH valida els fitxatges) -->
          <div v-if="mevesPractiques?.practiques && mevesPractiques.hores_conveni" class="card mt-md">
            <div class="card-header">
              <h3 class="card-title">🎓 Les meves pràctiques</h3>
              <span v-if="mevesPractiques.tipus_nom" class="badge badge-info" style="font-size:.72rem;">{{ mevesPractiques.tipus_nom }}</span>
            </div>
            <div class="text-small" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px;color:var(--color-text-secondary);">
              <span v-if="mevesPractiques.detall?.estudis">📚 {{ mevesPractiques.detall.estudis }}</span>
              <span v-if="mevesPractiques.centre">🏫 {{ mevesPractiques.centre }}</span>
              <span v-if="mevesPractiques.detall?.tutor_centre_nom">👩‍🏫 Tutor/a del centre: {{ mevesPractiques.detall.tutor_centre_nom }}</span>
              <span v-if="mevesPractiques.detall?.tutor_empresa">🤝 Tutor/a a CRT: {{ mevesPractiques.detall.tutor_empresa }}</span>
            </div>
            <div style="display:flex;gap:24px;flex-wrap:wrap;margin:10px 0;">
              <div><div class="text-small text-muted">Conveni</div><strong>{{ formatHM(mevesPractiques.hores_conveni) }}</strong></div>
              <div><div class="text-small text-muted">Fetes i validades</div><strong style="color:var(--color-success);">{{ formatHM(mevesPractiques.fetes) }}</strong></div>
              <div><div class="text-small text-muted">En queden</div><strong>{{ formatHM(mevesPractiques.restants) }}</strong></div>
              <div v-if="mevesPractiques.pendents_validar"><div class="text-small text-muted">Pendents de validar</div><strong style="color:#ca8a04;">{{ formatHM(mevesPractiques.pendents_validar) }}</strong></div>
            </div>
            <div style="height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden;">
              <div :style="{ width: mevesPractiques.percentatge + '%', height: '100%', background: 'linear-gradient(90deg,#0DAF83,#0A7C5E)', borderRadius: '999px' }"></div>
            </div>
            <div class="text-small text-muted" style="margin-top:6px;">
              {{ mevesPractiques.percentatge }}% del conveni · des del {{ String(mevesPractiques.inici).split('-').reverse().join('/') }}<template v-if="mevesPractiques.fi"> fins al {{ String(mevesPractiques.fi).split('-').reverse().join('/') }}</template>.
              Només compten els fitxatges que RRHH ja ha validat.
            </div>
            <div v-if="mevesPractiques.excedides > 0" class="text-small" style="margin-top:6px;color:#7a5a00;background:#FFF8E1;border-radius:6px;padding:6px 10px;">
              ⚠ Has superat les hores del conveni en {{ formatHM(mevesPractiques.excedides) }}. Parla-ho amb RRHH.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: CALENDAR ═══ -->
    <div v-else-if="activeTab === 'calendar'">
      <div class="dashboard-grid">
        <div style="grid-column:1/-1;">
          <!-- Horari de tota la setmana: fins ara només es veia el d'avui, sota el mapa del fitxatge. -->
          <div class="card mb-md">
            <div class="card-header">
              <h3 class="card-title">🗓️ El meu horari setmanal</h3>
              <span v-if="workerSchedule" class="text-small text-muted">{{ workerSchedule.name }} · {{ formatHM(horesSetmana) }} a la setmana</span>
            </div>
            <div v-if="!workerSchedule" class="text-small text-muted" style="margin-top:8px;">
              Encara no tens cap horari assignat. Comenta-ho a Recursos Humans.
            </div>
            <div v-else style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;margin-top:12px;">
              <div v-for="d in horariSetmana" :key="d.jsDay"
                style="border-radius:10px;padding:10px 12px;border:1px solid var(--color-border-light);"
                :style="d.avui ? 'border:2px solid var(--color-primary);background:rgba(9,78,140,.06);' : (d.trams.length ? '' : 'opacity:.6;background:var(--color-bg);')">
                <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;" :style="{ color: d.avui ? 'var(--color-primary)' : 'var(--color-text-secondary)' }">
                  {{ d.nom }}<span v-if="d.avui"> · avui</span>
                </div>
                <template v-if="d.trams.length">
                  <div v-for="(tr, i) in d.trams" :key="i" style="font-weight:700;font-size:.95rem;font-variant-numeric:tabular-nums;margin-top:4px;">{{ tr }}</div>
                  <div class="text-small text-muted" style="margin-top:2px;">{{ formatHM(d.hores) }}</div>
                </template>
                <div v-else style="font-size:.85rem;margin-top:4px;" class="text-muted">Lliure</div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <button class="btn btn-outline btn-sm" @click="prevMonth">◂</button>
              <h3 class="card-title">{{ calendarMonthName }}</h3>
              <button class="btn btn-outline btn-sm" @click="nextMonth">▸</button>
            </div>
            <!-- Un sol calendari: l'horari de cada dia (quadrant de RRHH o el de sempre), els festius, els permisos i els fitxatges. -->
            <div v-if="resumQuadrantCal" class="text-small text-muted" style="text-align:center;margin-top:4px;">
              {{ resumQuadrantCal }}
            </div>
            <div class="calendar-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-top:16px;">
              <div v-for="dLabel in ['DL','DT','DC','DJ','DV','DS','DG']" :key="dLabel" style="text-align:center;font-size:0.75rem;font-weight:600;color:var(--color-text-secondary);padding:6px;">{{ dLabel }}</div>
              <div v-for="day in calendarDays" :key="day.key" class="calendar-day"
                style="min-height:70px;padding:6px;border-radius:8px;font-size:0.8rem;border:1px solid var(--color-border-light);"
                :style="{ background: dayBackground(day), opacity: day.currentMonth ? '1' : '0.3', ...(diaCanviat(day) ? { borderColor: '#D9A400', boxShadow: 'inset 0 0 0 1px #D9A400' } : {}) }"
                :title="diaCanviat(day) ? (quadrantCal[day.date].nota || 'Horari canviat per RRHH') : ''">
                <div :style="{ fontWeight: day.isToday ? '700' : '400', color: dayColor(day) }">{{ day.dayNum }}</div>
                <div v-if="horariCal(day)" style="font-size:0.65rem;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums;" :style="{ color: diaCanviat(day) ? '#92400E' : 'var(--color-primary)' }">{{ horariCal(day) }}</div>
                <div v-if="day.holiday" style="font-size:0.65rem;color:var(--color-danger);margin-top:2px;">🔴 {{ day.holiday.name }}</div>
                <div v-if="plaDelDia(day)" style="font-size:0.65rem;color:var(--color-primary);margin-top:2px;">🕔 Surt {{ plaDelDia(day).sortida?.slice(0,5) }}<template v-if="plaDelDia(day).surt_abans_min > 5"> ({{ plaDelDia(day).surt_abans_min }}′ abans)</template></div>
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

          <!-- Bossa anual d'hores. Si no es pot calcular es diu: abans la targeta
               desapareixia i el treballador no sabia que li faltava el quadre horari. -->
          <div class="card mt-md" v-if="bossa && bossa.calculable === false">
            <h3 class="card-title mb-md">La meva bossa anual d'hores · {{ new Date().getFullYear() }}</h3>
            <p class="text-small" style="color:var(--color-danger);">
              ⚠ Encara no es pot calcular: {{ bossa.motiu_no_calculable }}
              Comenta-ho a Recursos Humans perquè completin la teva fitxa.
            </p>
          </div>
          <div class="card mt-md" v-else-if="bossa">
            <h3 class="card-title mb-md">La meva bossa anual d'hores · {{ new Date().getFullYear() }}</h3>
            <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:12px;">
              <div><div class="text-small text-muted">Jornada</div><strong>{{ bossa.pct_jornada }}%</strong></div>
              <div><div class="text-small text-muted">Bossa anual</div><strong>{{ bossa.bossa_anual_h }} h</strong></div>
              <div><div class="text-small text-muted">Treballades</div><strong>{{ bossa.treballat_h }} h</strong></div>
              <div><div class="text-small text-muted">Pendents</div><strong>{{ bossa.pendent_h }} h</strong></div>
              <div><div class="text-small text-muted">Ritme</div>
                <strong :style="{ color: bossa.desviacio_h < -10 ? 'var(--color-danger)' : (bossa.desviacio_h > 10 ? '#ca8a04' : 'var(--color-success)') }">
                  {{ bossa.desviacio_h > 0 ? '+' : '' }}{{ bossa.desviacio_h }} h</strong></div>
            </div>
            <div style="background:var(--color-border-light);border-radius:8px;height:14px;overflow:hidden;">
              <div :style="{ width: Math.min(100, Math.round(100 * bossa.treballat_h / Math.max(1, bossa.bossa_anual_h))) + '%',
                             background: 'var(--color-primary)', height: '100%' }"></div>
            </div>
            <p class="text-small text-muted" style="margin-top:10px;">
              <template v-if="bossa.es_complet">Jornada completa: sense hores complementàries; màxim
                {{ bossa.extra_tope_h }} h extraordinàries l'any (portes {{ bossa.extra_consumides_h }}), remunerades a ×{{ bossa.extra_factor }}.</template>
              <template v-else>Jornada parcial: les hores complementàries pactades es remuneren a part i mai
                poden portar-te per sobre de la jornada d'un temps complet.</template>
            </p>
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
          <h3 class="card-title">Quants dies has fet servir aquest any</h3>
          <button class="btn btn-accent btn-sm" @click="showPermissionModal = true">+ Demanar vacances o un permís</button>
        </div>
        <!-- Summary -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:16px;">
          <div v-for="summary in permissionSummary" :key="summary.type_id" class="stat-card" style="padding:12px;">
            <div class="text-small" style="font-weight:600;">{{ summary.name }}</div>
            <div style="display:flex;align-items:baseline;gap:4px;margin-top:4px;">
              <span style="font-size:1.3rem;font-weight:700;color:var(--color-primary);">{{ summary.used }}</span>
              <span v-if="summary.max" class="text-small text-muted">de {{ summary.max }} dies</span>
              <span v-else class="text-small text-muted">dies</span>
            </div>
            <div v-if="summary.max && summary.used >= summary.max" class="text-small" style="color:var(--color-danger);margin-top:2px;">Ja els has fet servir tots</div>
          </div>
        </div>
      </div>

      <div class="card mt-lg">
        <h3 class="card-title mb-md">El que has demanat</h3>
        <div v-if="myAbsences.length === 0" class="empty-state"><p>Encara no has demanat cap dia. Toca «Demanar vacances o un permís» per fer-ho.</p></div>
        <div class="table-container" v-else>
          <table>
            <thead><tr><th>Què</th><th>Quins dies</th><th>Motiu</th><th>Resposta de RRHH</th></tr></thead>
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
          <h3 class="card-title">Tipus d'excedència que pots demanar</h3>
          <button class="btn btn-accent btn-sm" @click="showExcedenciaModal = true">+ Demanar una excedència</button>
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
        <div v-if="myExcedencies.length === 0" class="empty-state"><p>No has demanat cap excedència.</p></div>
        <div class="table-container" v-else>
          <table>
            <thead><tr><th>Tipus</th><th>Des del</th><th>Fins al</th><th>Resposta de RRHH</th></tr></thead>
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
        <h3 class="card-title mb-md">Què vol dir cada color</h3>
        <p class="text-small text-muted mb-md">
          <span style="display:inline-flex;align-items:center;gap:4px;margin-right:16px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--color-success);"></span>Correcte: dins de la teva zona i del teu horari</span>
          <span style="display:inline-flex;align-items:center;gap:4px;margin-right:16px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f97316;"></span>Has fet hores fora de l'horari i encara no estan aprovades</span>
          <span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--color-danger);"></span>Fora de la teva zona: aquestes hores no compten fins que RRHH ho revisi</span>
        </p>
        <div v-if="myWorkLogs.length === 0" class="empty-state"><p>Encara no tens cap fitxatge.</p></div>
        <div class="table-container" v-else>
          <table>
            <thead>
              <tr>
                <th>Dia</th><th>Entrada</th><th>Sortida</th>
                <th style="color:var(--color-success);" title="Les hores que compten per a la teva jornada">Hores que compten</th>
                <th style="color:#f97316;" title="Hores fora de l'horari que encara no estan aprovades">⏱ Extra sense aprovar</th>
                <th style="color:var(--color-danger);" title="Hores fetes fora de la teva zona">📍 Fora de zona</th>
                <th style="color:var(--color-success);" title="Hores extra que ja estan aprovades">✓ Extra aprovades</th>
                <th>Revisat per RRHH?</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in myWorkLogs" :key="l.id"
                @click="router.push(`/work-logs/${l.id}/detail`)" style="cursor:pointer"
                :style="l.hour_status === 'out_of_area' ? 'background:rgba(239,68,68,0.06);border-left:3px solid var(--color-danger);cursor:pointer;' : l.hour_status === 'extra' ? 'background:rgba(249,115,22,0.06);border-left:3px solid #f97316;cursor:pointer;' : l.end_time ? 'background:rgba(76,175,80,0.04);border-left:3px solid var(--color-success);cursor:pointer;' : 'cursor:pointer;'">
                <td>{{ formatDate(l.date) }}</td>
                <td class="text-small">{{ l.start_time ? formatTime(l.start_time) : '—' }}</td>
                <td class="text-small">{{ l.end_time ? formatTime(l.end_time) : '—' }}</td>
                <td>
                  <strong :style="l.hour_status === 'ok' ? 'color:var(--color-success)' : 'color:var(--color-text)'">
                    {{ formatHM(l.effective_hours ?? l.total_hours_worked ?? l.hours_worked) }}
                  </strong>
                  <div v-if="sortidaAltreDia(l)" style="margin-top:3px;font-size:.74rem;font-weight:700;color:var(--color-danger);white-space:nowrap;" :title="'La jornada es va iniciar el ' + String(l.date).slice(0, 10).split('-').reverse().join('/') + ' i la sortida no es va fitxar fins al ' + sortidaAltreDia(l).data">
                    ⚠ Sortida fitxada el {{ sortidaAltreDia(l).data }}<span v-if="sortidaAltreDia(l).dies > 1"> (+{{ sortidaAltreDia(l).dies }} dies)</span>
                  </div>
                </td>
                <td>
                  <span v-if="(l.extra_hours_unauthorized||0) > 0" style="color:#f97316;font-weight:600;">{{ formatHM(l.extra_hours_unauthorized) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span v-if="(l.hours_out_of_area||0) > 0" style="color:var(--color-danger);font-weight:600;">{{ formatHM(l.hours_out_of_area) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span v-if="(l.extra_hours_authorized||0) > 0" style="color:var(--color-success);font-weight:600;">+{{ formatHM(l.extra_hours_authorized) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td><span class="badge" :class="l.status === 'approved' ? 'badge-success' : l.status === 'pending' ? 'badge-warning' : 'badge-danger'">{{ etiqueta('fitxatge', l.status) }}</span></td>
                <!-- La fila ja obre el detall, però no es veia: el botó diu que hi ha trams i pausa per consultar. -->
                <td><button class="btn btn-outline btn-sm" style="white-space:nowrap;" @click.stop="router.push(`/work-logs/${l.id}/detail`)">🔍 Veure el detall</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: MENU ═══ -->
    <div v-else-if="activeTab === 'menu'">
      <!-- Totes les pantalles, agrupades com al menú lateral i amb una frase que diu per a què serveix cada una -->
      <section v-for="sec in seccionsMenu" :key="sec.titol" class="menu-seccio">
        <h2 class="menu-seccio-titol">{{ sec.titol }}</h2>
        <div class="menu-graella">
          <component :is="e.to ? 'router-link' : 'button'" v-for="e in sec.entrades" :key="e.nom" :to="e.to" type="button"
            class="menu-entrada" @click="e.accio && e.accio()">
            <span class="menu-icona" :style="{ background: e.fons, color: e.color }">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-html="e.icona"></svg>
            </span>
            <span class="menu-text">
              <strong>{{ e.nom }}</strong>
              <small>{{ e.ajuda }}</small>
            </span>
          </component>
        </div>
      </section>
    </div>

    <!-- ═══ TAB: SETTINGS ═══ -->
    <div v-else-if="activeTab === 'settings'">
      <div class="card" style="max-width:500px;">
        <h3 class="card-title mb-md">Canviar contrasenya</h3>
        <div class="form-group">
          <label class="form-label">Contrasenya actual</label>
          <CampContrasenya class="form-input" v-model="pwForm.oldPw" autocomplete="current-password" />
        </div>
        <div class="form-group">
          <label class="form-label">Nova contrasenya</label>
          <CampContrasenya class="form-input" v-model="pwForm.newPw" autocomplete="new-password" />
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nova contrasenya</label>
          <CampContrasenya class="form-input" v-model="pwForm.confirmPw" autocomplete="new-password" />
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
                  <td>
                    <span v-if="resumCps(a.postal_codes).totaBcn" class="badge badge-success" style="font-size:0.72rem;" :title="resumCps(a.postal_codes).tots.join(', ')">🏙️ Tota Barcelona · {{ resumCps(a.postal_codes).total }} CP</span>
                    <template v-else>
                      <span v-for="cp in resumCps(a.postal_codes, 6).visibles" :key="cp" class="badge badge-info" style="margin-right:4px;font-size:0.72rem;">{{ cp }}</span>
                      <span v-if="resumCps(a.postal_codes, 6).resta" class="badge badge-primary" style="font-size:0.72rem;cursor:help;" :title="resumCps(a.postal_codes, 6).tots.join(', ')">+{{ resumCps(a.postal_codes, 6).resta }}</span>
                    </template>
                  </td>
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
          <FrasesRapides clau="motiu_absencia" v-model="permForm.reason" />
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
          <FrasesRapides clau="motiu_excedencia" v-model="excForm.reason" />
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
    <!-- Registre manual amb justificació (GPS no actiu) — EIPD §6.4 -->
    <div v-if="showManualRegModal" class="modal-overlay" style="z-index:100002;">
      <div class="modal" style="max-width:380px;">
        <div class="modal-body" style="padding:20px;">
          <h3 style="margin-bottom:8px;">📍 GPS no activat</h3>
          <p class="text-small" style="margin-bottom:12px;">Cap problema: <strong>la teva feina no s'atura</strong>. El registre es farà en <strong>mode manual amb justificació</strong> (queda anotat, mai en contra teva).</p>
          <select v-model="manualRegMotiu" class="form-input" style="margin-bottom:8px;width:100%;">
            <option>Tauleta d'empresa robada o extraviada — ús del dispositiu personal</option>
            <option>Sense senyal o cobertura</option>
            <option>Permís d'ubicació denegat al dispositiu personal</option>
            <option>Altres</option>
          </select>
          <FrasesRapides clau="justificacio_gps" v-model="manualRegText" />
          <input v-model="manualRegText" class="form-input" placeholder="Explica-ho breument — OBLIGATORI, mínim 15 caràcters" style="margin-bottom:4px;width:100%;" />
          <div class="text-small" :style="{ color: manualRegText.trim().length < 15 ? 'var(--color-danger)' : 'var(--color-success)', marginBottom: '12px' }">
            {{ manualRegText.trim().length < 15 ? `Justificació obligatòria: falten ${15 - manualRegText.trim().length} caràcters` : '✓ Justificació vàlida' }}
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button class="btn btn-outline" @click="showManualRegModal = false">Cancel·lar</button>
            <button class="btn btn-primary" @click="confirmManualReg" :disabled="manualRegText.trim().length < 15">Registrar manualment</button>
          </div>
        </div>
      </div>
    </div>

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
              <strong>⚠️ El teu horari d'avui ha acabat fa {{ overtimeMinutes }} minuts.</strong>
            </p>
            <p style="font-size:0.85rem;color:var(--color-text-secondary);margin:0;">
              Si continues treballant, aquest temps necessita <strong>autorització</strong>. Si no en té, comptarà com a <strong>no autoritzat</strong> i no se sumarà a les teves hores.
            </p>
          </div>

          <div class="extra-opcions">
            <button class="extra-opcio" :class="{ actiu: modeExtra === 'codi' }" @click="modeExtra = 'codi'">
              <span>🔑</span><b>Tinc un codi</b><small>Me l'ha donat RRHH o el responsable</small>
            </button>
            <button class="extra-opcio" :class="{ actiu: modeExtra === 'demanar' }" @click="modeExtra = 'demanar'">
              <span>✋</span><b>Demanar autorització</b><small>S'envia a RRHH i pots continuar</small>
            </button>
          </div>

          <div v-if="modeExtra === 'codi'" class="extra-panell">
            <div v-if="myAuthCodes.length" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
              <button v-for="c in myAuthCodes" :key="c.id" class="extra-xip" @click="overtimeAuthCode = c.code">
                {{ c.code }} · {{ formatHM(c.authorized_hours) }}
              </button>
            </div>
            <input class="form-input" v-model="overtimeAuthCode" placeholder="Introdueix el codi..." style="font-family:monospace;font-size:1rem;text-align:center;letter-spacing:2px;" @input="overtimeAuthCode = overtimeAuthCode.toUpperCase()" />
            <button class="btn btn-primary" style="width:100%;margin-top:10px;" @click="submitOvertimeAuth" :disabled="!overtimeAuthCode.trim() || enviantExtra">
              ✅ Validar codi i continuar
            </button>
          </div>

          <div v-if="modeExtra === 'demanar'" class="extra-panell">
            <label class="form-label">Quant temps més calcules?</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
              <button v-for="h in [0.5, 1, 1.5, 2, 3, 4]" :key="h" class="extra-xip" :class="{ actiu: horesDemanades === h }" @click="horesDemanades = h">
                {{ formatHM(h) }}
              </button>
            </div>
            <label class="form-label">Motiu</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
              <button v-for="m in MOTIUS_EXTRA" :key="m" class="extra-xip" @click="motiuExtra = m">{{ m }}</button>
            </div>
            <textarea class="form-input" v-model="motiuExtra" rows="2" maxlength="500" placeholder="Per què has de continuar treballant?"></textarea>
            <button class="btn btn-primary" style="width:100%;margin-top:10px;" @click="demanaAutoritzacioExtra" :disabled="motiuExtra.trim().length < 5 || enviantExtra">
              📨 Enviar sol·licitud a RRHH
            </button>
          </div>

          <div v-if="errorExtra" style="color:var(--color-danger);font-size:0.85rem;margin-top:10px;">{{ errorExtra }}</div>

          <div style="display:flex;gap:12px;margin-top:16px;padding-top:14px;border-top:1px solid var(--color-border);">
            <button class="btn btn-outline" style="flex:1;" @click="dismissOvertimeNoAuth">
              Continuar sense codi
            </button>
            <button class="btn btn-danger" style="flex:1;" @click="finalitzaDesDeAvis" :disabled="isFinishing">
              ⏹ Finalitzar jornada ara
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Calendari laboral del treballador (PDF incrustat, no descarregable) -->
    <div class="card mt-lg" id="worker-calendar-card" v-if="authStore.user && activeTab === 'calendar'">
      <CalendarPdfViewer :worker="authStore.user" :schedule="workerSchedule" />
    </div>

    <div class="rdl-notice">{{ t('rdl_notice') }}</div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue'
import CampContrasenya from '../components/CampContrasenya.vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { TILE_URL, TILE_ATTRIBUTION, TILE_MAX_ZOOM } from '../config/geo'
import { getCurrentPosition, posicioRapida, checkUserLocation, BARCELONA_POSTAL_CODES, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { Capacitor } from '@capacitor/core'
import { getPolygon } from '../services/geoPolygonService'
import { auditLog } from '../services/audit'
import { i18n } from '../i18n'
import { formatHM } from '../utils/formatHours'
import { sortidaAltreDia } from '../utils/sortidaAltreDia'
import { etiqueta } from '../utils/etiquetes'
import { resumCps } from '../utils/resumCps'
import WorkLogAlertBanner from '../components/WorkLogAlertBanner.vue'
import PacteComplementaries from '../components/PacteComplementaries.vue'
import BreakTimerModal from '../components/BreakTimerModal.vue'
import AvisSenseSortida from '../components/AvisSenseSortida.vue'
import CalendarPdfViewer from '../components/CalendarPdfViewer.vue'

// --- Break state (pausa obligatoria basada en horario configurado) ---
// Jornades d'altres dies sense sortida i sense declarar (les omple AvisSenseSortida)
const pendentsSortida = ref([])
const breakModalVisible = ref(false)
const breakDuration = ref(20)
const breakStartTime = ref(null)
const breakClockOffsetMs = ref(0)
const currentWorkLogId = ref(null)
const breakScheduledTime = ref(null) // Hora prevista de la pausa (per mostrar al dashboard)
let breakTriggerTimeout = null
let breakPollInterval = null

/**
 * Calcula las horas previstas para el día actual según el WorkSchedule del trabajador.
 * @param {object} schedule - Objeto WorkSchedule con campo 'days' (array)
 * @returns {number} Horas previstas para hoy (0 si no trabaja)
 */
function getScheduledHoursForToday(schedule) {
  if (!schedule || !Array.isArray(schedule.days)) return 0
  const jsDay = new Date().getDay() // 0=Diumenge ... 6=Dissabte
  // Actiu si té start/end i no està explícitament desactivat (alguns horaris no porten 'active')
  const dayEntry = schedule.days.find(d => d.day === jsDay)
  if (!dayEntry || dayEntry.active === false || !dayEntry.start || !dayEntry.end) return 0
  const [sh, sm] = dayEntry.start.split(':').map(Number)
  const [eh, em] = dayEntry.end.split(':').map(Number)
  const startMin = sh * 60 + sm
  const endMin = eh * 60 + em
  return Math.max(0, (endMin - startMin) / 60)
}

/**
 * Programa la pausa obligatoria según la configuración del admin y el horario del trabajador.
 * Lógica:
 * 1. Obtiene la configuración de pausa (break_settings)
 * 2. Obtiene el WorkSchedule del trabajador
 * 3. Calcula las horas previstas para hoy
 * 4. Si supera el threshold_hours, programa la pausa:
 *    - mode 'offset': X minutos después del inicio del fitxatge
 *    - mode 'fixed': a la hora fijada por el admin
 */
async function startBreakCheckTimer() {
  stopBreakCheckTimer()
  if (!workLogStore.isWorking || !workLogStore.currentLog?.id) return
  // Pausa ja feta (o omesa) en aquesta jornada: en recarregar la pantalla, el càlcul d'hora
  // donava "ja és hora" i la tornava a demanar.
  if (['completed', 'skipped'].includes(workLogStore.currentLog.break_status) || workLogStore.currentLog.break_end_time) return

  try {
    const settings = await db.getBreakSettings()

    // ── Fetch fresh user data (authStore.user may be stale) ──
    const freshUser = await db.getUser(authStore.userId)
    const userOverride = freshUser?.break_override
    const userOverrideTime = freshUser?.break_override_time
    const scheduleId = freshUser?.work_schedule_id

    console.log('[Break] User override:', userOverride, 'time:', userOverrideTime, 'scheduleId:', scheduleId)

    // Determinar si la pausa està activada per a aquest treballador
    let breakEnabled = settings.enabled
    if (userOverride === true) breakEnabled = true
    else if (userOverride === false) breakEnabled = false

    if (!breakEnabled) {
      console.log('[Break] Pausa desactivada per a aquest treballador')
      return
    }

    // Obtener el horario del trabajador
    if (!scheduleId) {
      console.warn('[Break] Treballador sense work_schedule_id assignat')
      return
    }
    const schedule = await db.getWorkSchedule(scheduleId).catch(() => null)
    if (!schedule) return
    const scheduledHours = getScheduledHoursForToday(schedule)

    console.log(`[Break] Hores previstes avui: ${scheduledHours}, llindar: ${settings.threshold_hours}`)

    // Verificar si el horario previsto supera el threshold
    // (si l'admin ha forçat ON amb override=true, es salta aquesta comprovació)
    if (userOverride !== true && scheduledHours <= settings.threshold_hours) {
      console.log('[Break] Jornada inferior al llindar, sense pausa obligatòria')
      return
    }

    // Obtener el work log actual para saber el start_time
    const currentLog = workLogStore.currentLog
    if (!currentLog || !currentLog.start_time) return

    const clockInTime = new Date(currentLog.start_time)
    let triggerTime = null

    // ── Determinar hora d'activació ──
    // Prioritat: 1) break_override_time del treballador, 2) config global
    // "00:00:00" és el valor per defecte de la columna quan NO s'ha fixat cap
    // hora (no mitjanit real): tractar-lo com "sense override" per no descartar
    // la pausa creient que la mitjanit ja ha passat.
    const hasOverrideTime = !!userOverrideTime && userOverrideTime !== '00:00:00'
    const effectiveMode = hasOverrideTime ? 'fixed' : settings.break_start_mode

    if (effectiveMode === 'fixed') {
      // Mode fixed: hora concreta del dia
      const fixedStr = (hasOverrideTime ? userOverrideTime : null) || settings.break_start_fixed_time || '12:00'
      const [fh, fm] = String(fixedStr).substring(0, 5).split(':').map(Number)
      triggerTime = new Date(clockInTime)
      triggerTime.setHours(fh, fm, 0, 0)
      // Si la hora fixa ja ha passat respecte al fitxatge, no es programa (ja seria tard)
      if (triggerTime.getTime() < Date.now()) {
        // Si ja ha passat però fa menys de 30 min, activar ara mateix
        const diffMin = (Date.now() - triggerTime.getTime()) / 60000
        if (diffMin > 30) {
          console.log('[Break] Hora fixa ja passada fa més de 30 min, no s\'activa')
          return
        }
        triggerTime = new Date() // activar immediatament
      }
    } else {
      // Mode offset: X minuts després del fitxatge
      const offsetMin = settings.break_start_offset_minutes || (settings.threshold_hours * 60)
      triggerTime = new Date(clockInTime.getTime() + offsetMin * 60000)
    }

    const delayMs = triggerTime.getTime() - Date.now()
    if (delayMs <= 0) {
      // Ja és hora de la pausa
      await triggerBreak(settings)
      return
    }

    breakScheduledTime.value = triggerTime
    console.log(`[Break] Pausa programada per ${triggerTime.toLocaleTimeString()} (en ${Math.round(delayMs / 1000)}s)`)

    // Programar amb setTimeout (màxim suportat pel navegador ~24.8 dies)
    breakTriggerTimeout = setTimeout(() => {
      triggerBreak(settings)
    }, delayMs)

    // Poll de seguretat cada 10s: si el timeout falla o el tab estava inactiu
    breakPollInterval = setInterval(async () => {
      if (breakModalVisible.value) return
      if (Date.now() >= triggerTime.getTime()) {
        clearInterval(breakPollInterval)
        breakPollInterval = null
        await triggerBreak(settings)
      }
    }, 10000)
  } catch (e) {
    console.error('[Break] Error programant pausa:', e)
  }
}

/**
 * Activa la pausa obligatoria: crida a l'API per iniciar-la i mostra el modal.
 */
// El temporitzador i la comprovació cada 10 s podien disparar-se alhora i enviar dues peticions
// d'inici: mentre n'hi ha una en marxa, la segona no surt.
let iniciantPausa = false
async function triggerBreak(settings) {
  if (breakModalVisible.value || iniciantPausa) return
  if (!workLogStore.isWorking || !workLogStore.currentLog?.id) return
  iniciantPausa = true
  stopBreakCheckTimer()
  try {
    currentWorkLogId.value = workLogStore.currentLog.id
    breakDuration.value = settings.break_duration_minutes
    const res = await db.startBreak(workLogStore.currentLog.id, await verdicteZona())
    breakStartTime.value = res.break_start_time
    breakClockOffsetMs.value = res.server_time ? (new Date(res.server_time).getTime() - Date.now()) : 0
    breakModalVisible.value = true
    console.log('[Break] Pausa activada')
  } catch (e) {
    console.error('[Break] Error activant pausa:', e)
    // 409 = el backend ja té una pausa oberta (p.ex. la pestanya es va tancar a mig
    // pausa). Sense això, el treballador es queda encallat: mai li surt el modal i
    // mai pot "acabar" una pausa que, pel servidor, ja està en marxa.
    if (e?.status === 409) {
      try {
        const detail = await db.getWorkLogDetail(workLogStore.currentLog.id)
        if (detail?.break_start_time && !detail?.break_end_time) {
          breakStartTime.value = detail.break_start_time
          breakClockOffsetMs.value = detail.server_time ? (new Date(detail.server_time).getTime() - Date.now()) : 0
          breakModalVisible.value = true
          console.log('[Break] Pausa ja oberta al servidor: recuperant el modal')
        }
      } catch (e2) {
        console.error('[Break] Error recuperant la pausa oberta:', e2)
      }
    }
  } finally {
    iniciantPausa = false
  }
}

async function onBreakComplete() {
  breakModalVisible.value = false
  try {
    if (currentWorkLogId.value) {
      const log = await db.completeBreak(currentWorkLogId.value, await verdicteZona())
      // Sense això, en tornar a muntar la pantalla (canvi de pestanya) el store encara creu que no hi ha pausa.
      if (log && workLogStore.currentLog?.id === log.id) {
        Object.assign(workLogStore.currentLog, { break_status: log.break_status, break_start_time: log.break_start_time, break_end_time: log.break_end_time })
      }
    }
  } catch (e) { console.error(e) }
}

function stopBreakCheckTimer() {
  if (breakTriggerTimeout) { clearTimeout(breakTriggerTimeout); breakTriggerTimeout = null }
  if (breakPollInterval) { clearInterval(breakPollInterval); breakPollInterval = null }
}

const props = defineProps({ initialTab: { type: String, default: '' } })
const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const route = useRoute()
const t = (key) => i18n.t(key)

// --- State Declarations ---
const workerSchedule = ref(null)
// Persona que fitxa sense horari assignat (els autònoms no fan registre de jornada).
// Un horari sense cap dia actiu és com no tenir-ne.
const senseHorari = computed(() => {
  const u = authStore.user
  if (!u || u.role !== 'worker' || u.relacio === 'autonom') return false
  if (!u.work_schedule_id) return true
  const dies = workerSchedule.value?.days
  return Array.isArray(dies) && !dies.some(d => d && d.start && d.end && d.active !== false)
})
async function loadWorkerSchedule() {
  carregaQuadrantProper()
  const sid = authStore.user?.work_schedule_id
  if (!sid) { workerSchedule.value = null; return }
  workerSchedule.value = await db.getWorkSchedule(sid).catch(() => null)
}
// Dies d'aquest mes i del següent amb l'horari del quadrant (si RRHH n'ha canviat algun, mana sobre la plantilla).
const quadrantProper = ref({})
async function carregaQuadrantProper() {
  if (authStore.user?.role !== 'worker') return
  const d = new Date()
  const mesos = [0, 1].map(n => { const x = new Date(d.getFullYear(), d.getMonth() + n, 1); return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}` })
  const dies = {}
  const { api } = await import('../services/apiClient')
  for (const m of mesos) {
    try { Object.assign(dies, (await api.get(`/v1/el-meu-quadrant?mes=${m}`))?.dies || {}) } catch { /* servidor sense quadrant: plantilla */ }
  }
  quadrantProper.value = dies
}
// Horari de tota la setmana (pestanya Calendari), de dilluns a diumenge.
const NOMS_DIES = { 1: 'Dilluns', 2: 'Dimarts', 3: 'Dimecres', 4: 'Dijous', 5: 'Divendres', 6: 'Dissabte', 0: 'Diumenge' }
const horariSetmana = computed(() => {
  const dies = workerSchedule.value?.days
  if (!Array.isArray(dies)) return []
  const avui = new Date().getDay()
  const min = (h) => { const [a, b] = String(h).split(':').map(Number); return a * 60 + (b || 0) }
  return [1, 2, 3, 4, 5, 6, 0].map(jsDay => {
    const entrades = dies.filter(d => d.day === jsDay && d.active !== false && d.start && d.end)
    return {
      jsDay, nom: NOMS_DIES[jsDay], avui: jsDay === avui,
      trams: entrades.map(d => `${d.start.substring(0, 5)} – ${d.end.substring(0, 5)}`),
      hores: entrades.reduce((s, d) => s + Math.max(0, min(d.end) - min(d.start)), 0) / 60,
    }
  })
})
const horesSetmana = computed(() => horariSetmana.value.reduce((s, d) => s + d.hores, 0))

// Horari (entrada-sortida) del dia d'avui, per omplir el buit sota el mapa de zona.
const todayScheduleLabel = computed(() => {
  const sched = workerSchedule.value
  if (!sched || !Array.isArray(sched.days)) return ''
  const jsDay = new Date().getDay()
  const entries = sched.days.filter(d => d.day === jsDay && d.active !== false && d.start && d.end)
  if (!entries.length) return ''
  return entries.map(d => `${d.start.substring(0, 5)} – ${d.end.substring(0, 5)}`).join('  ·  ')
})
// Avui (offset 0) i demà (offset 1): horari del quadrant, o per què no es treballa (festiu o permís aprovat).
function horariDia(offset) {
  const d = new Date()
  d.setDate(d.getDate() + offset)
  const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const etiqueta = offset === 0 ? 'Avui' : 'Demà'
  const data = d.toLocaleDateString('ca-ES', { weekday: 'short', day: 'numeric', month: 'numeric' })
  const festiu = (holidays.value || []).find(h => h.date === dateStr)
  if (festiu) return { etiqueta, data, horari: '', motiu: `Festiu · ${festiu.name}`, avui: offset === 0 }
  const permis = (myAbsences.value || []).find(a => a.approved === true && dateStr >= a.start_date && dateStr <= a.end_date)
  if (permis) return { etiqueta, data, horari: '', motiu: getAbsenceTypeName(permis.absence_type_id), avui: offset === 0 }
  const q = quadrantProper.value[dateStr]
  const entrades = q && q.origen === 'quadrant'
    ? q.trams
    : (workerSchedule.value?.days || []).filter(x => x.day === d.getDay() && x.active !== false && x.start && x.end)
  const horari = entrades.map(x => `${x.start.substring(0, 5)} – ${x.end.substring(0, 5)}`).join('  ·  ')
  return { etiqueta, data, horari, motiu: '', avui: offset === 0 }
}
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
const bossa = ref(null)
const esRepresentant = ref(false)
const teApps = ref(false)
const mevesPractiques = ref(null)
const jornadaPla = ref([])
function plaDelDia(day) {
  if (!day || !day.currentMonth) return null
  const p = (jornadaPla.value || []).find(x => x.date === day.date && x.jornada_min > 0)
  return p && p.sortida ? p : null
}

const loadingPermission = ref(false)
const loadingExc = ref(false)
const pwResult = ref(null)

const permForm = reactive({ absence_type_id: 1, start_date: '', end_date: '', reason: '', with_travel: false })
const selectedType = computed(() => absenceTypes.value.find(t => t.id === permForm.absence_type_id))
const excForm = reactive({ excedencia_type_id: 1, start_date: '', end_date: '', reason: '' })
const pwForm = reactive({ oldPw: '', newPw: '', confirmPw: '' })

const authLogId = ref('')
const authCodeInput = ref('')
const myAuthCodes = ref([])
async function loadMyAuthCodes() {
  try {
    const r = await db.getMyAuthCodes()
    myAuthCodes.value = Array.isArray(r) ? r : (r?.data ?? [])
  } catch (e) { myAuthCodes.value = [] }
}
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
// Pantalla «Més» del mòbil: les mateixes seccions que el menú lateral, amb una frase per entrada.
const ICONES = {
  safata: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
  xat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  calendari: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
  rellotge: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  full: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',
  persona: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  euro: '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
  llibre: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
  avis: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
  escut: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
  grafic: '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
  apps: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  engranatge: '<circle cx="12" cy="12" r="3"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
  sortir: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
}
const entrada = (nom, ajuda, to, icona, color, extra = {}) => ({ nom, ajuda, to, icona: ICONES[icona], color, fons: color + '1a', ...extra })
const seccionsMenu = computed(() => [
  { titol: 'Avui', entrades: [
    entrada("Safata d'avisos", 'Coses que et demanen o que has de saber', '/worker/safata', 'safata', '#B3352F'),
    entrada('Missatges', 'Parla amb RRHH o amb el teu equip', '/worker/chat', 'xat', '#094E8C'),
  ] },
  { titol: 'El meu temps', entrades: [
    entrada('Calendari i horari', 'El teu horari, els festius i les teves vacances', '/worker/calendar', 'calendari', '#1d5fbf'),
    entrada('Els meus fitxatges', "Les hores que has fet cada dia i si estan aprovades", '/worker/history', 'rellotge', '#0A7C5E'),
    entrada('Vacances i permisos', 'Demana dies lliures o un permís i mira com van', '/worker/absences', 'full', '#1d5fbf'),
    entrada('Excedències', 'Demana una temporada sense treballar', '/worker/excedencies', 'persona', '#8b5cf6'),
  ] },
  { titol: 'Els meus papers', entrades: [
    entrada('Les meves nòmines', 'Descarrega les teves nòmines', '/worker/payrolls', 'euro', '#0A7C5E'),
    entrada('Documents per llegir i signar', "Contractes i documents que t'envia l'empresa", '/worker/documents', 'full', '#c2570c'),
    entrada("Biblioteca de l'empresa", 'Protocols, guies i formacions', '/worker/biblioteca', 'llibre', '#094E8C'),
  ] },
  { titol: 'Ajuda', entrades: [
    entrada('Tinc un problema', "Explica a RRHH què passa (un fitxatge malament, un dubte…)", '/worker/incidencia', 'avis', '#d97706'),
    entrada('Normes i informació', "Les normes de l'empresa que has de conèixer", '/worker/compliance', 'escut', '#b8860b'),
  ] },
  { titol: 'Més', entrades: [
    entrada('El meu rendiment', 'Com et va la feina, segons els indicadors', '/worker/rendiment', 'grafic', '#1d5fbf'),
    ...(esRepresentant.value ? [entrada('Hores de comitè', 'Les hores que fas com a representant', '/worker/comite', 'rellotge', '#094E8C')] : []),
    ...(teApps.value ? [entrada('Les meves apps', "Entra a les altres aplicacions de l'empresa", '/portal', 'apps', '#0A7C5E')] : []),
  ] },
  { titol: 'El meu compte', entrades: [
    entrada('Configuració', 'Canvia la contrasenya i les preferències', '/worker/settings', 'engranatge', '#6b7280'),
    entrada('Tancar sessió', "Surt de l'app en aquest aparell", null, 'sortir', '#dc2626', { accio: handleLogout }),
  ] },
])

const tabMap = { 'worker-dashboard': 'clock', 'worker-calendar': 'calendar', 'worker-absences': 'absences', 'worker-excedencies': 'excedencies', 'worker-history': 'history', 'worker-settings': 'settings', 'worker-menu': 'menu' }
const activeTab = computed(() => props.initialTab || tabMap[route.name] || 'clock')
const tabTitles = { clock: 'Fitxar entrada i sortida', calendar: 'Calendari i horari', absences: 'Vacances i permisos', excedencies: 'Excedències', history: 'Els meus fitxatges', settings: 'Configuració', menu: 'Totes les opcions' }
// Una frase sota el títol de cada pestanya que diu què s'hi fa.
const tabAjuda = {
  clock: 'Toca «Iniciar jornada» quan comencis a treballar i «Finalitzar jornada» quan acabis.',
  calendar: 'El teu horari de cada dia, els festius i les vacances que tens aprovades.',
  absences: 'Demana vacances o un permís. Quan RRHH respongui, ho veuràs aquí mateix.',
  excedencies: 'Demana una temporada sense treballar (excedència). RRHH et respondrà aquí.',
  history: 'Les hores que has fet cada dia. «Aprovat» vol dir que RRHH ja ho ha revisat.',
  settings: 'Canvia la teva contrasenya i mira les teves zones de treball.',
  menu: "Totes les pantalles de l'app, agrupades.",
}

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
    jornadaPla.value = data.jornada_pla || []
    permissionUsage.value = data.permission_usage || {}
    
    if (data.user) authStore.user = data.user
    if (data.work_logs) {
      workLogStore.logs = data.work_logs
      // Restore timer if there is an open workday
      const today = new Date().toISOString().split('T')[0]
      const activeLog = data.work_logs.find(l => l.user_id === authStore.userId && l.date && l.date.substring(0, 10) === today && !l.end_time)
      if (activeLog && !workLogStore.isWorking) {
        workLogStore.currentLog = activeLog
        workLogStore.isWorking = true
        // En restaurar (recàrrega a mig torn) també cal l'offset: aquí no és "ara
        // mateix" com a l'inici de jornada, però server_time del mateix fetch val igual.
        workLogStore.clockOffsetMs = data.server_time ? (new Date(data.server_time).getTime() - Date.now()) : 0
        workLogStore.startTimer()
      }
    }
  } catch (e) {
    console.error('[WorkerDashboard] fetchData failed:', e)
  }
}

// --- Methods ---
function formatH(hoursDecimal) {
  return formatHM(hoursDecimal)
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
  const completed = Number(myWorkLogs.value.filter(l => l.date && l.date.substring(0, 10) === today).reduce((s, l) => s + Number(l.effective_hours ?? l.total_hours_worked ?? l.hours_worked ?? 0), 0))
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
  return formatH(myWorkLogs.value.filter(l => l.date && l.date.substring(0, 10) >= ws).reduce((s, l) => s + Number(l.effective_hours ?? l.total_hours_worked ?? l.hours_worked ?? 0), 0))
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
  // Avís (no bloqueja: el registre no es pot impedir) si hi ha jornades anteriors sense sortida.
  if (pendentsSortida.value.length) {
    const dies = pendentsSortida.value.map(j => String(j.date).slice(0, 10).split('-').reverse().join('/')).join(', ')
    if (!await confirma(`No vas fitxar la sortida del ${dies}. Pots declarar a quina hora vas acabar al requadre de dalt del Tauler.\n\nVols iniciar igualment la jornada d'avui?`)) return
  }
  // Check if scheduled hours already done today
  const scheduledHours = await getScheduledHours()
  if (scheduledHours) {
    const today = new Date().toISOString().split('T')[0]
    const todayCompleted = myWorkLogs.value
      .filter(l => l.date && l.date.substring(0, 10) === today && l.end_time)
      .reduce((s, l) => s + Number(l.effective_hours ?? l.total_hours_worked ?? l.hours_worked ?? 0), 0)
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
    const pos = await getCurrentPosition()
    await workLogStore.startWorkday(pos.lat, pos.lng)
    await nextTick()
    setTimeout(initWorkerMap, 500)
    startBreakCheckTimer()
  } catch (err) {
    // El registre MAI es bloqueja (EIPD §6.4):
    //  - codi 1 (permís DENEGAT — només possible en dispositiu personal; a la tablet
    //    Hexnode força la ubicació): flux de registre manual amb justificació.
    //  - resta (sensor/senyal/timeout): registre automàtic amb anotació.
    if (err && err.code === 1) {
      if (localStorage.getItem('crt_disp')) {
        // DISPOSITIU D'EMPRESA (Hexnode): la ubicació no es pot desactivar — cap flux manual
        alert('🔒 Aquest és un dispositiu d\'empresa: la ubicació és obligatòria i no es pot desactivar. Reactiva-la o contacta amb coordinació.')
        return
      }
      showManualRegModal.value = true
    } else {
      await startManualWorkday('GPS no disponible (sense senyal o error del sensor) — anotació automàtica', 'unavailable')
    }
  }
}

// ── Registre manual amb justificació (GPS no actiu) ──
const showManualRegModal = ref(false)
const manualRegMotiu = ref("Tauleta d'empresa robada o extraviada — ús del dispositiu personal")
const manualRegText = ref('')

async function confirmManualReg() {
  // Justificació OBLIGATÒRIA amb mínim de caràcters (el servidor també ho força)
  if (manualRegText.value.trim().length < 15) return
  const just = `${manualRegMotiu.value}: ${manualRegText.value.trim()}`
  showManualRegModal.value = false
  await startManualWorkday(just)
}

async function startManualWorkday(justificacio, gpsError = 'denied') {
  try {
    await workLogStore.startWorkday(null, null, justificacio, gpsError)
    startBreakCheckTimer()
  } catch (e) {
    alert('No s\'ha pogut registrar la jornada. Torna-ho a provar o avisa coordinació.')
  }
}

async function endWorkday() {
  if (workLogStore.elapsed < 60) {
    if (!await confirma('La jornada ha durat menys d\'un minut. Segur que vols finalitzar? (Es guardarà amb 0h)')) return
  }
  
  isFinishing.value = true
  const logId = workLogStore.currentLog?.id
  try {
    // Posició sense fer esperar: la que el dispositiu ja té (fins a 2 min) o una de nova, la primera
    // que arribi i com a màxim 4 s. Abans s'esperava una posició nova d'alta precisió fins a 6 s i
    // molta gent marxava sense que la sortida s'acabés de registrar.
    const pos = (await posicioRapida(4000)) || lastKnownPos.value

    await workLogStore.endWorkday(pos?.lat || null, pos?.lng || null,
      pos?.lat ? null : 'GPS no disponible al tancament de jornada — anotació automàtica',
      pos?.lat ? null : 'unavailable')
    stopBreakCheckTimer()
  } catch (err) {
    console.error('Error finalitzant jornada:', err)
    const lk = lastKnownPos.value
    await workLogStore.endWorkday(lk?.lat || null, lk?.lng || null,
      lk?.lat ? null : 'GPS no disponible al tancament de jornada — anotació automàtica',
      lk?.lat ? null : 'unavailable')
  } finally {
    isFinishing.value = false
    stopBreakCheckTimer()
  }
  overtimeAlertDismissed.value = false
  if (!workLogStore.isWorking) await aplicaCodiExtra(logId)
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

// Horari de cada dia del mes que es veu al calendari (quadrant de RRHH o la plantilla de sempre).
const quadrantCal = ref({})
const minutsQuadrantCal = ref(0)
async function carregaQuadrantCal() {
  if (authStore.user?.role !== 'worker') return
  const mes = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}`
  try {
    const { api } = await import('../services/apiClient')
    const r = await api.get(`/v1/el-meu-quadrant?mes=${mes}`)
    quadrantCal.value = r?.dies || {}
    minutsQuadrantCal.value = r?.minuts || 0
  } catch { quadrantCal.value = {}; minutsQuadrantCal.value = 0 }
}
watch([calendarMonth, calendarYear], carregaQuadrantCal, { immediate: true })
// No es mostra l'horari els dies de festiu, permís aprovat o excedència: ja surt el motiu.
function horariCal(day) {
  const q = day.currentMonth && quadrantCal.value[day.date]
  if (!q || !q.trams.length || day.holiday || day.excedencia || (day.absence && day.absence.approved === true)) return ''
  return q.trams.map(t => `${t.start}–${t.end}`).join(' · ')
}
const diaCanviat = day => !!(day.currentMonth && quadrantCal.value[day.date]?.origen === 'quadrant')
const resumQuadrantCal = computed(() => {
  if (!minutsQuadrantCal.value) return ''
  const canviats = Object.values(quadrantCal.value).filter(d => d.origen === 'quadrant').length
  return `${formatHM(minutsQuadrantCal.value / 60)} previstes aquest mes` + (canviats ? ` · ${canviats} dia${canviats === 1 ? '' : 's'} amb l'horari canviat (vora groga)` : '')
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


// Veredicte de zona per a una marca: llegeix la posició en l'instant, la compara
// amb la zona i la DESCARTA — al servidor només viatja el booleà.
async function verdicteZona() {
  try {
    const pos = await getCurrentPosition()
    const assignment = await db.getActiveCpAssignment(authStore.userId)
    const result = await checkUserLocation(authStore.user, assignment, pos.lat, pos.lng)
    return { location_match: !!result.valid }
  } catch (e) {
    return {}
  }
}

// Location check — uses shared logic including AMBULATORIA check
async function checkLocation() {
  locationStatus.value = 'checking'
  locationText.value = 'Verificant ubicació...'
  try {
    const pos = await getCurrentPosition()
    const assignment = await db.getActiveCpAssignment(authStore.userId)
    const result = await checkUserLocation(authStore.user, assignment, pos.lat, pos.lng)
    
    // Sense centre ni zona assignats: es pot fitxar, però no és «ubicació vàlida» (no hi ha res a comparar).
    if (result.senseZona) {
      locationStatus.value = 'checking'
      locationText.value = authStore.user?.work_type === 'DOMICILIARIA_VALLES' ? 'Sense municipis assignats — avisa RRHH' : 'Sense centre assignat — avisa RRHH'
      return
    }
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

// Unified location handler — called by both background and foreground tracking
// ⚠️ Privacy by Design (art. 90 LOPDGDD): NO hi ha seguiment continu de la ubicació.
// La posició es llegeix NOMÉS en el moment d'una marca o d'una comprovació puntual
// demanada per la persona, es valida per zona i es descarta: no es desa ni s'acumula.

onUnmounted(async () => {
  if (overtimeCheckInterval) clearInterval(overtimeCheckInterval)
  stopBreakCheckTimer()
})

onMounted(async () => {
  await fetchData()
  loadWorkerSchedule()
  loadMyAuthCodes()
  import('../services/apiClient').then(({ api }) =>
    api.get('/v1/bossa-anual').then(b => { bossa.value = b }).catch(() => {}))
  import('../services/apiClient').then(({ api }) =>
    api.get('/v1/comite/me').then(r => { esRepresentant.value = !!r?.membre }).catch(() => {}))
  import('../services/apiClient').then(({ api }) =>
    api.get('/v1/portal/apps').then(r => { teApps.value = (r?.apps || []).length > 0 }).catch(() => {}))
  if (authStore.userId) import('../services/apiClient').then(({ api }) =>
    api.get(`/v1/practiques/${authStore.userId}`).then(r => { mevesPractiques.value = r }).catch(() => {}))
  await checkLocation()
  // Init worker map if already on clock tab
  if (activeTab.value === 'clock') {
    await nextTick()
    setTimeout(initWorkerMap, 400)
  }
  // Start end-of-shift overtime monitor
  startOvertimeMonitor()
  // If already working (page reload), start break check timer
  if (workLogStore.isWorking && workLogStore.currentLog?.id) {
    console.log('[Break] Treballador ja fitxat, iniciant break check timer')
    startBreakCheckTimer()
  }
})

// ── Fi d'horari: avís, temps fora d'horari i autorització ─────────────
const showOvertimeAlert = ref(false)
const overtimeMinutes = ref(0)
const overtimeAuthCode = ref('')
const overtimeAlertDismissed = ref(false)
let overtimeCheckInterval = null

const modeExtra = ref('')
const horesDemanades = ref(1)
const motiuExtra = ref('')
const errorExtra = ref('')
const enviantExtra = ref(false)
const MOTIUS_EXTRA = ['Formació', 'Atenció a un usuari', 'Reunió', 'Acabar feina urgent']
// Codi vàlid per a la jornada en curs (validat aquí o arribat per una sol·licitud autoritzada).
// No es gasta fins a fitxar la sortida: llavors s'aplica al fitxatge.
const codiExtra = ref(null)
// La darrera sol·licitud d'avui (pendent, autoritzada o denegada).
const sollicitudAvui = ref(null)

// Hores previstes avui segons l'horari assignat (null si no n'hi ha).
const horesPrevistesAvui = computed(() => {
  const avui = new Date().getDay()
  const dia = workerSchedule.value?.days?.find(d => (d.day === avui || d.day_num === avui) && d.active)
  if (!dia?.start || !dia?.end) return null
  const [sh, sm] = dia.start.split(':').map(Number)
  const [eh, em] = dia.end.split(':').map(Number)
  const h = (eh * 60 + em - sh * 60 - sm) / 60
  return h > 0 ? h : null
})
const sortidaPrevista = computed(() => {
  if (!horesPrevistesAvui.value || !workLogStore.horaEntrada) return ''
  const [h, m] = workLogStore.horaEntrada.split(':').map(Number)
  const t = h * 60 + m + Math.round(horesPrevistesAvui.value * 60)
  return `${String(Math.floor(t / 60) % 24).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`
})
const segonsFora = computed(() => {
  if (!workLogStore.isWorking || !horesPrevistesAvui.value) return 0
  return Math.max(0, Math.floor(workLogStore.elapsed - horesPrevistesAvui.value * 3600))
})
function fmtHMS(s) {
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(x).padStart(2, '0')}`
}
const estatExtra = computed(() => {
  if (codiExtra.value) {
    const max = Number(codiExtra.value.hours) || 0
    const tipus = codiExtra.value.type === 'extraordinaria' ? 'extraordinàries' : 'complementàries'
    if (max && segonsFora.value > max * 3600) {
      return { to: 'passat', text: `Autoritzades ${formatHM(max)} ${tipus}. El que passi d'aquí no està autoritzat.` }
    }
    return { to: 'ok', text: `✔ Autoritzat fins a ${formatHM(max)} (${tipus}) · codi ${codiExtra.value.code}` }
  }
  const s = sollicitudAvui.value
  if (s?.status === 'pending') return { to: 'pendent', text: `Sol·licitud de ${formatHM(s.hours)} enviada a RRHH · pendent de resposta` }
  if (s?.status === 'denied') return { to: 'denegada', text: `RRHH ha denegat la sol·licitud${s.denial_reason ? ': ' + s.denial_reason : ''}` }
  return { to: 'cap', text: "Sense autorització: aquest temps no comptarà fins que s'autoritzi." }
})

const clauCodi = () => workLogStore.currentLog?.id ? `cril_codi_extra_${workLogStore.currentLog.id}` : null
function desaCodiExtra(c) {
  codiExtra.value = c
  const k = clauCodi()
  try { if (k) c ? localStorage.setItem(k, JSON.stringify(c)) : localStorage.removeItem(k) } catch (e) {}
}
function recuperaCodiExtra() {
  const k = clauCodi()
  try { const v = k && localStorage.getItem(k); if (v) codiExtra.value = JSON.parse(v) } catch (e) {}
}

// Sol·licitud d'avui: si RRHH l'ha autoritzada mentre la jornada és oberta, el codi passa a la jornada.
async function carregaSollicitudAvui() {
  try {
    const r = await db.getMyOvertimeRequests()
    const llista = Array.isArray(r) ? r : (r?.data ?? [])
    const avui = new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Madrid' })
    const s = llista.find(x => x.period_from === avui && x.origen === 'treballador') || null
    sollicitudAvui.value = s
    if (s?.status === 'authorized' && s.code && !s.code_used && !codiExtra.value && workLogStore.isWorking) {
      desaCodiExtra({ code: s.code, hours: s.hours, type: s.type })
    }
  } catch (e) { /* sense xarxa: es torna a provar al minut següent */ }
}

async function getScheduledHours() {
  // Sense horari assignat no hi ha hores previstes: abans es demanava /work-schedules/null
  // cada pocs segons (404) i l'error trencava el botó de fitxar.
  const sid = authStore.user?.work_schedule_id
  if (!sid) return null
  const sched = workerSchedule.value?.id === sid ? workerSchedule.value : await db.getWorkSchedule(sid).catch(() => null)
  if (!sched) return null
  const today = new Date().getDay() // 0=Sunday
  const dayEntry = sched.days?.find(d => (d.day === today || d.day_num === today) && d.active)
  if (!dayEntry?.start || !dayEntry?.end) return null
  const [sh, sm] = dayEntry.start.split(':').map(Number)
  const [eh, em] = dayEntry.end.split(':').map(Number)
  return (eh * 60 + em - sh * 60 - sm) / 60 // scheduled hours as decimal
}

function obreAvisExtra() {
  errorExtra.value = ''
  modeExtra.value = ''
  showOvertimeAlert.value = true
}

function startOvertimeMonitor() {
  if (overtimeCheckInterval) clearInterval(overtimeCheckInterval)
  recuperaCodiExtra()
  carregaSollicitudAvui()
  overtimeCheckInterval = setInterval(async () => {
    if (!workLogStore.isWorking) return
    if (sollicitudAvui.value?.status === 'pending') await carregaSollicitudAvui()
    if (overtimeAlertDismissed.value || codiExtra.value || sollicitudAvui.value?.status === 'pending') return
    if (!workLogStore.currentLog?.start_time) return
    const scheduledHours = await getScheduledHours()
    if (!scheduledHours) return
    const start = new Date(workLogStore.currentLog.start_time)
    const workedHours = (new Date() - start) / 3600000
    if (workedHours > scheduledHours) {
      const mins = Math.round((workedHours - scheduledHours) * 60)
      overtimeMinutes.value = mins
      if (mins >= 1 && !showOvertimeAlert.value) {
        horesDemanades.value = 1
        modeExtra.value = ''
        showOvertimeAlert.value = true
      }
    }
  }, 60000) // check every 60s
}

// El codi es VALIDA ara però no es gasta: s'aplica al fitxatge en fitxar la sortida.
// Abans es marcava com a usat aquí i el fitxatge quedava igualment amb les hores no autoritzades.
async function submitOvertimeAuth() {
  const codi = overtimeAuthCode.value.trim()
  if (!codi) return
  errorExtra.value = ''
  enviantExtra.value = true
  try {
    const result = await db.validateAuthCode(codi)
    if (!result.valid) {
      const motius = { code_invalid: 'Aquest codi no existeix.', code_used: "Aquest codi ja s'ha fet servir.", code_expired: 'Aquest codi no és vàlid avui.' }
      errorExtra.value = motius[result.reason] || result.reason || 'Codi invàlid o caducat'
      return
    }
    if (result.code?.user_id && result.code.user_id !== authStore.userId) {
      errorExtra.value = "Aquest codi és d'una altra persona."
      return
    }
    desaCodiExtra({ code: codi, hours: Number(result.code?.authorized_hours) || 0, type: result.code?.type })
    auditLog(authStore.userId, 'AUTHORIZE_OVERTIME', 'work_log', workLogStore.currentLog?.id, `Codi ${codi} validat per a la jornada en curs`)
    tancaAvisExtra()
  } catch (e) {
    errorExtra.value = 'Error en validar el codi. Torna-ho a provar.'
  } finally {
    enviantExtra.value = false
  }
}

async function demanaAutoritzacioExtra() {
  errorExtra.value = ''
  enviantExtra.value = true
  try {
    await db.requestMyOvertime({ hours: horesDemanades.value, reason: motiuExtra.value.trim(), work_log_id: workLogStore.currentLog?.id || null })
    auditLog(authStore.userId, 'REQUEST_OVERTIME', 'work_log', workLogStore.currentLog?.id, `Sol·licitud de ${horesDemanades.value} h fora d'horari`)
    await carregaSollicitudAvui()
    tancaAvisExtra()
  } catch (e) {
    errorExtra.value = e?.message || "No s'ha pogut enviar la sol·licitud. Torna-ho a provar."
  } finally {
    enviantExtra.value = false
  }
}

function tancaAvisExtra() {
  showOvertimeAlert.value = false
  overtimeAlertDismissed.value = true
  overtimeAuthCode.value = ''
  motiuExtra.value = ''
  modeExtra.value = ''
}

function dismissOvertimeNoAuth() {
  tancaAvisExtra()
}

async function finalitzaDesDeAvis() {
  tancaAvisExtra()
  await endWorkday()
}

// Després de fitxar la sortida: el codi validat durant la jornada s'aplica al fitxatge.
async function aplicaCodiExtra(logId) {
  const c = codiExtra.value
  codiExtra.value = null
  try { localStorage.removeItem(`cril_codi_extra_${logId}`) } catch (e) {}
  if (!c || !logId) return
  try {
    workLogStore.logs = await db.getWorkLogsByUser(authStore.userId)
    const log = workLogStore.logs.find(l => l.id === logId)
    if (!log || !(Number(log.extra_hours_unauthorized) > 0)) return
    const r = await workLogStore.authorizeExtraHours(logId, c.code)
    alert(r.message)
  } catch (e) {
    console.error("[Extra] No s'ha pogut aplicar el codi:", e)
  }
}

function getAbsenceTypeName(id) { return absenceTypes.value.find(a => a.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short' }) }
function formatDateFull(d) { return new Date(d).toLocaleDateString('ca-ES', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }
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
  L.tileLayer(TILE_URL, {
    attribution: TILE_ATTRIBUTION, maxZoom: TILE_MAX_ZOOM
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
<style scoped>
.menu-seccio { margin-bottom: 18px; }
.menu-seccio-titol { margin: 0 0 8px; font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--color-text-secondary); }
.menu-graella { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.menu-entrada { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--color-border); background: var(--color-bg-card); color: var(--color-text); text-decoration: none; text-align: left; cursor: pointer; font: inherit; width: 100%; }
.menu-entrada:active { transform: scale(.99); }
.menu-icona { flex: none; width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.menu-icona svg { width: 24px; height: 24px; }
.menu-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.menu-text strong { font-size: .95rem; }
.menu-text small { font-size: .8rem; color: var(--color-text-secondary); line-height: 1.3; }
.avis-sense-horari { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; padding: 12px 16px;
  border-radius: 12px; background: #fff4d6; border: 1px solid #f0d58a; border-left: 5px solid #D9A400; color: #5c4400; font-size: .9rem; line-height: 1.4; }
.avis-sense-horari strong { display: block; margin-bottom: 2px; }
.ash-ico { font-size: 1.4rem; line-height: 1; }

/* Temps fora d'horari dins del comptador (fons fosc) */
.tracker-extra { margin: 10px auto 4px; max-width: 420px; padding: 10px 14px; border-radius: 12px;
  background: rgba(249, 115, 22, 0.16); border: 1px solid rgba(249, 115, 22, 0.55); color: #fff; text-align: center; }
.tracker-extra-cap { font-size: 1rem; }
.tracker-extra-cap strong { font-variant-numeric: tabular-nums; font-size: 1.15rem; margin-left: 4px; }
.tracker-extra-text { font-size: .8rem; opacity: .9; margin-top: 2px; }
.tracker-extra--ok { background: rgba(76, 175, 80, 0.16); border-color: rgba(76, 175, 80, 0.6); }
.tracker-extra--pendent { background: rgba(250, 204, 21, 0.14); border-color: rgba(250, 204, 21, 0.55); }
.tracker-extra--denegada, .tracker-extra--passat { background: rgba(239, 68, 68, 0.16); border-color: rgba(239, 68, 68, 0.6); }
.tracker-extra-btn { margin-top: 8px; padding: 7px 14px; min-height: 36px; border-radius: 999px; border: 1px solid rgba(255,255,255,.6);
  background: transparent; color: #fff; font-weight: 600; font-size: .82rem; cursor: pointer; }
.tracker-extra-btn:hover { background: rgba(255,255,255,.12); }

/* Avís de fi d'horari: opcions */
.extra-opcions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.extra-opcio { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px; border-radius: 12px; text-align: left;
  border: 1.5px solid var(--color-border); background: var(--color-surface); color: inherit; cursor: pointer; min-height: 44px; }
.extra-opcio span { font-size: 1.3rem; }
.extra-opcio small { color: var(--color-text-secondary); font-size: .75rem; }
.extra-opcio.actiu { border-color: var(--color-accent); box-shadow: 0 0 0 3px rgba(59, 130, 246, .12); }
.extra-panell { margin-top: 12px; }
.extra-xip { padding: 6px 12px; min-height: 34px; border-radius: 999px; border: 1px solid var(--color-border); background: var(--color-surface);
  color: inherit; font-size: .8rem; cursor: pointer; }
.extra-xip.actiu { border-color: var(--color-accent); background: rgba(59, 130, 246, .1); font-weight: 600; }
@media (max-width: 480px) { .extra-opcions { grid-template-columns: 1fr; } }
</style>
