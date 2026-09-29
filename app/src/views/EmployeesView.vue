<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('employees') }}</h1></div>
      <div class="page-actions">
        <button class="btn btn-outline" @click="showCsvModal = true">📥 Importar CSV</button>
        <button class="btn btn-primary" @click="openEditModal(null)">+ {{ t('create') }}</button>
      </div>
    </div>

    <div class="card" style="margin-bottom:12px;padding:12px;">
      <div style="display:flex;gap:12px;align-items:center;">
        <div style="flex:1;position:relative;">
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:0.5;">🔍</span>
          <input class="form-input" v-model="searchQuery" :placeholder="'Cerca per nom, email o DNI...'" style="padding-left:36px;" />
        </div>
        <div class="text-small text-muted">Mostrant {{ filteredWorkers.length }} de {{ workers.length }}</div>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th @click="toggleSort('name')" style="cursor:pointer;white-space:nowrap;">{{ t('name') }} {{ sortKey === 'name' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th @click="toggleSort('dni')" style="cursor:pointer;white-space:nowrap;">DNI {{ sortKey === 'dni' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th @click="toggleSort('email')" style="cursor:pointer;white-space:nowrap;">{{ t('email') }} {{ sortKey === 'email' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th @click="toggleSort('job_profile')" style="cursor:pointer;white-space:nowrap;">Perfil {{ sortKey === 'job_profile' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th>Zona</th>
              <th>Horari</th>
              <th @click="toggleSort('seniority_date')" style="cursor:pointer;white-space:nowrap;">Antiguitat {{ sortKey === 'seniority_date' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th @click="toggleSort('active')" style="cursor:pointer;white-space:nowrap;">{{ t('status') }} {{ sortKey === 'active' ? (sortDir === 1 ? '↑' : '↓') : '' }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredWorkers" :key="user.id">
              <td><div style="display:flex;align-items:center;gap:8px;"><div class="header-avatar" style="width:28px;height:28px;font-size:0.7rem;">{{ initials(user.name) }}</div>{{ user.name }}</div></td>
              <td style="font-family:monospace;font-size:0.8rem;">{{ user.dni || '—' }}</td>
              <td>{{ user.email }}</td>
              <td>
                <div style="font-size:0.8rem;font-weight:600;">{{ user.job_profile || 'Fisioterapeuta' }}</div>
                <div class="text-small text-muted" style="font-size:0.7rem;">
                  <span :class="workTypeBadgeClass(user.work_type)">{{ workTypeLabel(user.work_type) }}</span>
                </div>
              </td>
              <td>
                <!-- DOMICILIARIA: postal codes -->
                <div v-if="user.work_type === 'DOMICILIARIA'">
                  <div v-if="extraData[user.id]?.cpAssignment">
                    <template v-if="extraData[user.id].cpAssignment.postal_codes.filter(c => c.startsWith('08')).length >= 30">
                      <span style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.3);border-radius:4px;padding:2px 8px;font-size:0.72rem;color:#1d4ed8;font-weight:600;">
                        🗺️ Tots BCN ({{ extraData[user.id].cpAssignment.postal_codes.length }} CPs)
                      </span>
                    </template>
                    <template v-else>
                      <div style="display:flex;gap:3px;flex-wrap:wrap;">
                        <span v-for="cp in extraData[user.id].cpAssignment.postal_codes" :key="cp"
                          style="background:var(--color-bg);border:1px solid var(--color-border-light);border-radius:4px;padding:2px 5px;font-size:0.72rem;">{{ cp }}</span>
                      </div>
                    </template>
                  </div>
                  <span v-else class="text-muted">—</span>
                </div>
                <!-- DOMICILIARIA_VALLES: municipal terms -->
                <div v-else-if="user.work_type === 'DOMICILIARIA_VALLES'">
                  <div v-if="extraData[user.id]?.muniAssignment" style="display:flex;gap:3px;flex-wrap:wrap;">
                    <span v-for="muni in extraData[user.id].muniAssignment.municipalities" :key="muni"
                      style="background:rgba(147,51,234,0.08);border:1px solid rgba(147,51,234,0.2);border-radius:4px;padding:2px 5px;font-size:0.72rem;color:rgba(147,51,234,1);">
                      {{ getMuniName(muni) }}
                    </span>
                  </div>
                  <span v-else class="text-muted">—</span>
                </div>
                <!-- AMBULATORIA: points count -->
                <div v-else>
                  <div style="font-size:0.78rem;">
                    <span v-if="extraData[user.id]?.centers?.length" style="color:var(--color-primary);font-weight:600;margin-right:6px;">
                      🏢 {{ extraData[user.id].centers.length }} centre{{ extraData[user.id].centers.length > 1 ? 's' : '' }}
                    </span>
                    <span v-if="extraData[user.id]?.points?.length">
                      📍 {{ extraData[user.id].points.length }} punt{{ extraData[user.id].points.length > 1 ? 's' : '' }}
                    </span>
                    <span v-if="!extraData[user.id]?.points?.length && !extraData[user.id]?.centers?.length" class="text-muted text-small">
                      Sense punts
                    </span>
                  </div>
                </div>
              </td>
              <td>{{ getScheduleName(user.work_schedule_id) }}</td>
              <td class="text-small text-muted">{{ user.seniority_date ? formatDate(user.seniority_date) : '—' }}</td>
              <td><span class="badge" :class="user.active ? 'badge-success' : 'badge-danger'">{{ user.active ? 'Actiu' : 'Inactiu' }}</span></td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <button class="btn btn-outline btn-sm" @click="openEditModal(user)">✎</button>
                  <button v-if="user.work_type === 'DOMICILIARIA'" class="btn btn-outline btn-sm" @click="openCpModal(user)">📍 CP</button>
                  <button v-if="user.work_type === 'DOMICILIARIA_VALLES'" class="btn btn-outline btn-sm" @click="openMuniModal(user)" style="color:rgba(147,51,234,1);">🏘️ Munis</button>
                  <button v-if="user.work_type === 'AMBULATORIA'" class="btn btn-outline btn-sm" @click="openPointsModal(user)" style="color:var(--color-primary);">📍 Punts</button>
                  <button class="btn btn-outline btn-sm" @click="openScheduleModal(user)">📅</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Employee Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingUser ? t('edit') : t('create') }} {{ t('employee') }}</h3>
          <button class="icon-btn" @click="showEditModal = false" :disabled="isSaving">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('name') }}</label>
          <input class="form-input" v-model="form.name" :disabled="isSaving" />
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">{{ t('email') }}</label>
            <input class="form-input" type="email" v-model="form.email" :disabled="isSaving" />
          </div>
          <div class="form-group">
            <label class="form-label">DNI / NIE</label>
            <input class="form-input" v-model="form.dni" placeholder="12345678A" style="font-family:monospace;" :disabled="isSaving" />
          </div>
        </div>
        
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Tipus de treball</label>
            <select class="form-select" v-model="form.work_type" :disabled="isSaving">
              <option value="DOMICILIARIA">DOMICILIÀRIA</option>
              <option value="DOMICILIARIA_VALLES">DOMICILIÀRIA VALLÈS</option>
              <option value="AMBULATORIA">AMBULATÒRIA</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Perfil / Lloc</label>
            <select class="form-select" v-model="form.job_profile" :disabled="isSaving">
              <option value="Fisioterapeuta">Fisioterapeuta</option>
              <option value="Logopeda">Logopeda</option>
              <option value="Administracion">Administració</option>
              <option value="Informatica">Informàtica</option>
              <option value="Gerencia">Gerència</option>
            </select>
          </div>
        </div>

        <!-- Info box about work type -->
        <div v-if="form.work_type === 'AMBULATORIA'" style="background:rgba(59,130,246,0.06);border-radius:8px;padding:10px;margin-bottom:12px;font-size:0.82rem;">
          ℹ️ <strong>Ambulatori:</strong> Podeu configurar punts geolocalitzats específics per aquest treballador después de crear-lo (botó 📍 Punts).
        </div>
        <div v-if="form.work_type === 'DOMICILIARIA_VALLES'" style="background:rgba(147,51,234,0.06);border-radius:8px;padding:10px;margin-bottom:12px;font-size:0.82rem;">
          ℹ️ <strong>Domiciliari Vallès:</strong> El fichatge geolocalitzat és vàlid en qualsevol punt dels termes municipals assignats. Configureu-los amb el botó 🏘️ Munis.
        </div>

        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Horari</label>
            <select class="form-select" v-model="form.work_schedule_id" :disabled="isSaving">
              <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Data alta (antiguitat)</label>
            <input class="form-input" type="date" v-model="form.seniority_date" :disabled="isSaving" />
          </div>
        </div>
        <div class="modal-footer">
          <button v-if="editingUser" class="btn btn-outline" style="color:var(--color-danger);border-color:var(--color-danger);margin-right:auto;" @click="deleteUser" :disabled="isSaving">🗑️ Eliminar</button>
          <button class="btn btn-outline" @click="showEditModal = false" :disabled="isSaving">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="saveUser" :disabled="isSaving">
            {{ isSaving ? 'Guardant...' : t('save') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Schedule Editor Modal -->
    <div v-if="showScheduleModal" class="modal-overlay" @click.self="showScheduleModal = false">
      <div class="modal" style="max-width:650px;">
        <div class="modal-header">
          <h3 class="modal-title">Horari detallat — {{ scheduleUser?.name }}</h3>
          <button class="icon-btn" @click="showScheduleModal = false">✕</button>
        </div>

        <!-- Mode: seleccionar plantilla existent -->
        <div v-if="!isNewSchedule">
          <div class="form-group" style="display:flex;gap:8px;align-items:flex-end;">
            <div style="flex:1;">
              <label class="form-label">Plantilla horària</label>
              <select class="form-select" v-model="scheduleForm.id" @change="loadScheduleTemplate">
                <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.name }} ({{ s.total_hours_weekly }}h/set)</option>
              </select>
            </div>
            <button class="btn btn-accent btn-sm" style="white-space:nowrap;margin-bottom:1px;" @click="startNewSchedule">+ Nova plantilla</button>
          </div>
        </div>

        <!-- Mode: crear nova plantilla -->
        <div v-else>
          <div class="form-group" style="display:flex;gap:8px;align-items:flex-end;">
            <div style="flex:1;">
              <label class="form-label">Nom de la nova plantilla</label>
              <input class="form-input" v-model="newScheduleName" placeholder="Ex: 35h Dl-Dv 09:00-16:00" />
            </div>
            <button class="btn btn-outline btn-sm" style="white-space:nowrap;margin-bottom:1px;" @click="isNewSchedule = false">← Plantilles</button>
          </div>
        </div>

        <!-- Editor de dies (comú als dos modes) -->
        <div v-for="d in scheduleForm.days" :key="d.day" style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--color-border-light);">
          <label style="width:100px;font-weight:500;">
            <input type="checkbox" v-model="d.active" style="width:14px;height:14px;margin-right:6px;" />
            {{ d.name }}
          </label>
          <input v-if="d.active" class="form-input" type="time" v-model="d.start" style="width:130px;" />
          <span v-if="d.active">→</span>
          <input v-if="d.active" class="form-input" type="time" v-model="d.end" style="width:130px;" />
          <span v-if="d.active" class="text-small text-muted">{{ calcHours(d) }}h</span>
          <span v-else class="text-small text-muted">Descans</span>
        </div>

        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;">
          <span class="text-small"><strong>Total setmanal:</strong> {{ totalWeeklyHours }}h</span>
          <span class="text-small text-muted">Anual estimat: {{ (totalWeeklyHours * 46.5).toFixed(0) }}h</span>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showScheduleModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="saveSchedule">
            {{ isNewSchedule ? 'Crear i assignar' : t('save') + ' horari' }}
          </button>
        </div>
      </div>
    </div>

    <!-- CSV Import Modal -->
    <div v-if="showCsvModal" class="modal-overlay" @click.self="showCsvModal = false">
      <div class="modal" style="max-width:700px;">
        <div class="modal-header">
          <h3 class="modal-title">📥 Importar treballadors des de CSV</h3>
          <button class="icon-btn" @click="showCsvModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Format esperat</label>
          <code style="display:block;background:var(--color-bg);padding:12px;border-radius:8px;font-size:0.8rem;line-height:1.8;">
            nom;email;dni;codi_postal;horari_id;data_alta;tipus_treball<br>
            Maria López;maria.lopez@crtbcn.cat;12345678A;08001;1;2024-01-15;DOMICILIARIA
          </code>
        </div>
        <div class="form-group" style="position:relative;">
          <div style="border:2px dashed var(--color-border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;" @click="$refs.csvFile.click()" @dragover.prevent @drop.prevent="handleDrop">
            <p style="font-size:1.5rem;margin-bottom:8px;">📄</p>
            <p>Arrossegueu el fitxer CSV aquí o feu clic per seleccionar</p>
          </div>
          <input ref="csvFile" type="file" accept=".csv" @change="handleFileSelect" style="display:none;" />
        </div>
        <div v-if="csvPreview.length" class="form-group">
          <label class="form-label">Vista prèvia ({{ csvPreview.length }} treballadors)</label>
          <div class="table-container" style="max-height:200px;overflow-y:auto;">
            <table>
              <thead><tr><th>Nom</th><th>Email</th><th>DNI</th><th>Contrasenya / Hash</th><th>Tipus</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in csvPreview" :key="i">
                  <td>{{ row.name }}</td><td>{{ row.email }}</td><td>{{ row.dni }}</td>
                  <td style="font-family:monospace;font-size:0.7rem;">{{ row.password ? '✓ [Present]' : '— [Auto]' }}</td>
                  <td>{{ row.work_type }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCsvModal = false; csvPreview = []; importResults = []">{{ t('cancel') }}</button>
          <button class="btn btn-accent" @click="importCsv" :disabled="!csvPreview.length">Importar {{ csvPreview.length }} treballadors</button>
        </div>
        <div v-if="importResults.length" style="margin-top:16px;border-top:2px solid var(--color-accent);padding-top:16px;">
          <h4 style="color:var(--color-success);margin-bottom:8px;">✓ {{ importResults.length }} treballadors importats!</h4>
          <div class="table-container" style="max-height:200px;overflow-y:auto;">
            <table>
              <thead><tr><th>Nom</th><th>Email (usuari)</th><th>Contrasenya</th></tr></thead>
              <tbody>
                <tr v-for="r in importResults" :key="r.email">
                  <td>{{ r.name }}</td><td><code>{{ r.email }}</code></td>
                  <td><code style="background:rgba(76,175,80,0.1);padding:2px 8px;border-radius:4px;font-weight:600;">{{ r.password }}</code></td>
                </tr>
              </tbody>
            </table>
          </div>
          <button class="btn btn-primary btn-sm mt-md" @click="showCsvModal = false; importResults = []">Tancar</button>
        </div>
      </div>
    </div>

    <!-- CP Assignment Modal (DOMICILIARIA) -->
    <div v-if="showCpModal" class="modal-overlay" @click.self="showCpModal = false">
      <div class="modal" style="max-width:640px;">
        <div class="modal-header">
          <h3 class="modal-title">📍 Codis Postals assignats — {{ cpUser?.name }}</h3>
          <button class="icon-btn" @click="showCpModal = false">✕</button>
        </div>
        <div v-if="activeCpAssignment" style="background:rgba(76,175,80,0.07);border-radius:8px;padding:12px;border-left:3px solid var(--color-success);margin-bottom:16px;">
          <div class="text-small" style="color:var(--color-success);font-weight:600;">Assignació activa</div>
          <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
            <span v-for="cp in activeCpAssignment.postal_codes" :key="cp" class="badge badge-primary">{{ cp }} — {{ getCpZoneFn(cp) }}</span>
          </div>
        </div>
        <h4 style="font-size:0.9rem;margin-bottom:12px;">Nova assignació</h4>
        <div style="margin-bottom:12px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
            <div class="form-label" style="margin:0;">Codis postals <span class="text-muted" style="font-weight:400;">({{ newCpForm.postal_codes.length }} seleccionats)</span></div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button class="btn btn-sm btn-outline" @click="newCpForm.postal_codes = postalCodes.map(c => c.code)">✓ Tots els CP</button>
              <button class="btn btn-sm btn-outline" @click="newCpForm.postal_codes = postalCodes.filter(c => c.code.startsWith('08')).map(c => c.code)">🏙️ Tots BCN (08xxx)</button>
              <button class="btn btn-sm btn-outline" style="color:var(--color-danger);" @click="newCpForm.postal_codes = []">✗ Cap</button>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;max-height:180px;overflow-y:auto;border:1px solid var(--color-border-light);border-radius:8px;padding:10px;">
            <label v-for="cp in postalCodes" :key="cp.code" style="display:flex;align-items:center;gap:6px;font-size:0.78rem;cursor:pointer;padding:3px 0;">
              <input type="checkbox" :value="cp.code" v-model="newCpForm.postal_codes" style="width:13px;height:13px;" />
              <span><strong>{{ cp.code }}</strong></span>
            </label>
          </div>
        </div>
        <div class="form-date-row" style="margin-bottom:12px;">
          <div class="form-group">
            <label class="form-label">Vàlid des de</label>
            <input class="form-input" type="date" v-model="newCpForm.valid_from" />
          </div>
          <div class="form-group">
            <label class="form-label">Vàlid fins a <span class="text-muted">(buit = indefinit)</span></label>
            <input class="form-input" type="date" v-model="newCpForm.valid_to" />
          </div>
        </div>
        <button class="btn btn-primary" @click="saveCpAssignment" :disabled="!newCpForm.postal_codes.length || !newCpForm.valid_from">Desar nova assignació</button>
      </div>
    </div>

    <!-- ═══ AMBULATORY POINTS MODAL ═══ -->
    <div v-if="showPointsModal" class="modal-overlay" @click.self="showPointsModal = false">
      <div class="modal" style="max-width:650px;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(59,130,246,0.06),rgba(16,185,129,0.04));border-radius:12px 12px 0 0;">
          <div>
            <h3 class="modal-title">📍 Ubicacions de Fichatge — {{ pointsUser?.name }}</h3>
            <div class="text-small text-muted">Treballador ambulatori · {{ pointsUser?.job_profile }}</div>
          </div>
          <button class="icon-btn" @click="showPointsModal = false">✕</button>
        </div>

        <!-- Current Centers -->
        <div v-if="userCenters.length" style="padding:16px 16px 0;">
          <h4 style="font-size:0.85rem;margin-bottom:8px;">🏢 Centros Asignados ({{ userCenters.length }})</h4>
          <div v-for="center in userCenters" :key="center.id" style="display:flex;align-items:center;gap:12px;padding:10px;border:1px solid var(--color-border-light);border-radius:8px;margin-bottom:8px;background:rgba(59,130,246,0.04);">
            <div style="font-size:1.2rem;">🏢</div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.85rem;">{{ center.name }}</div>
              <div class="text-small text-muted">{{ center.work_locations?.length || 0 }} puntos válidos dentro del centro</div>
            </div>
            <button class="btn btn-danger btn-sm" @click="removeCenter(center.id)" title="Quitar centro">✕</button>
          </div>
        </div>

        <!-- Current Points -->
        <div v-if="userPoints.length" style="padding:16px 16px 0;">
          <h4 style="font-size:0.85rem;margin-bottom:8px;">📍 Puntos Manuales Aislados ({{ userPoints.length }})</h4>
          <div v-for="pt in userPoints" :key="pt.id" style="display:flex;align-items:center;gap:12px;padding:10px;border:1px solid var(--color-border-light);border-radius:8px;margin-bottom:8px;background:rgba(76,175,80,0.03);">
            <div style="font-size:1.2rem;">📍</div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.85rem;">{{ pt.name }}</div>
              <div class="text-small text-muted" style="font-family:monospace;">{{ Number(pt.lat).toFixed(6) }}, {{ Number(pt.lng).toFixed(6) }} · ⊘ {{ pt.radius }}m</div>
            </div>
            <button class="btn btn-danger btn-sm" @click="removePoint(pt.id)" title="Desactivar">✕</button>
          </div>
        </div>
        
        <div v-if="!userPoints.length && !userCenters.length" style="padding:16px;text-align:center;" class="text-muted">
          No hay ninguna ubicación configurada. Añada un centro o punto manual a continuación.
        </div>

        <div style="padding:0 16px; margin-top:16px;">
          <div style="display:flex;flex-wrap:wrap;gap:12px;border-bottom:2px solid var(--color-border-light);margin-bottom:12px;">
            <button :style="{padding:'8px 12px', background:'none', border:'none', cursor:'pointer', fontWeight:'600', color: pointsTab === 'center' ? 'var(--color-primary)' : 'var(--color-muted)', borderBottom: pointsTab === 'center' ? '3px solid var(--color-primary)' : 'none', marginBottom:'-2px'}" @click="pointsTab = 'center'">🏢 Centro Existente</button>
            <button :style="{padding:'8px 12px', background:'none', border:'none', cursor:'pointer', fontWeight:'600', color: pointsTab === 'manual' ? 'var(--color-primary)' : 'var(--color-muted)', borderBottom: pointsTab === 'manual' ? '3px solid var(--color-primary)' : 'none', marginBottom:'-2px'}" @click="pointsTab = 'manual'">📍 Punto Manual</button>
          </div>
        </div>

        <!-- Add Center Tab -->
        <div v-if="pointsTab === 'center'" style="padding:0 16px 16px;">
          <div class="form-group">
            <label class="form-label">Seleccione un Centro Ambulatorio</label>
            <select class="form-select" v-model="newCenterId">
              <option value="">-- Seleccionar Centro --</option>
              <option v-for="c in ambulatoryCentersList" :key="c.id" :value="c.id">{{ c.name }} ({{ c.work_locations?.length || 0 }} coords)</option>
            </select>
          </div>
          <button class="btn btn-primary" @click="addCenter" :disabled="!newCenterId">+ Asignar Centro</button>
        </div>

        <!-- Add Manual Point Tab -->
        <div v-if="pointsTab === 'manual'" style="padding:0 16px 16px;">
          <div class="form-group">
            <label class="form-label">Nom del punt manual</label>
            <input class="form-input" v-model="newPoint.name" placeholder="p.ex. Clínica Sant Pau" />
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <div class="form-group" style="flex:1 1 120px;">
              <label class="form-label">Latitud</label>
              <input class="form-input" type="number" step="0.000001" v-model.number="newPoint.lat" />
            </div>
            <div class="form-group" style="flex:1 1 120px;">
              <label class="form-label">Longitud</label>
              <input class="form-input" type="number" step="0.000001" v-model.number="newPoint.lng" />
            </div>
            <div class="form-group" style="flex:1 1 80px;">
              <label class="form-label">Radi (m)</label>
              <input class="form-input" type="number" v-model.number="newPoint.radius" min="50" max="1000" />
            </div>
          </div>
          <div class="text-small text-muted" style="margin-bottom:12px;">
            💡 Podeu obtenir coordenades des de Google Maps (clic dret → coordenades). Radi recomanat: 50-200m.
          </div>
          <button class="btn btn-primary" @click="addPoint" :disabled="!newPoint.name || !newPoint.lat || !newPoint.lng">+ Afegir Punt Manual</button>
        </div>
      </div>
    </div>

    <!-- ═══ MUNICIPAL ASSIGNMENT MODAL (DOMICILIARIA_VALLES) ═══ -->
    <div v-if="showMuniModal" class="modal-overlay" @click.self="showMuniModal = false">
      <div class="modal" style="max-width:650px;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(147,51,234,0.06),rgba(59,130,246,0.04));border-radius:12px 12px 0 0;">
          <div>
            <h3 class="modal-title">🏘️ Termes Municipals — {{ muniUser?.name }}</h3>
            <div class="text-small text-muted">Domiciliari del Vallès · El fichatge és vàlid en qualsevol punt del terme municipal</div>
          </div>
          <button class="icon-btn" @click="showMuniModal = false">✕</button>
        </div>

        <!-- Active assignment -->
        <div v-if="activeMuniAssignment" style="padding:16px 16px 0;">
          <div style="background:rgba(147,51,234,0.07);border-radius:8px;padding:12px;border-left:3px solid rgba(147,51,234,0.6);margin-bottom:12px;">
            <div class="text-small" style="color:rgba(147,51,234,1);font-weight:600;">Assignació activa</div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
              <span v-for="muni in activeMuniAssignment.municipalities" :key="muni" 
                style="background:rgba(147,51,234,0.1);border:1px solid rgba(147,51,234,0.2);border-radius:6px;padding:4px 8px;font-size:0.78rem;font-weight:500;">
                {{ getMuniName(muni) }}
              </span>
            </div>
            <div class="text-small text-muted" style="margin-top:6px;">Des de {{ activeMuniAssignment.valid_from }}{{ activeMuniAssignment.valid_to ? ' fins ' + activeMuniAssignment.valid_to : ' (indefinit)' }}</div>
          </div>
        </div>

        <!-- New assignment -->
        <div style="padding:16px;">
          <h4 style="font-size:0.85rem;margin-bottom:12px;">Nova assignació de termes municipals</h4>
          <div class="form-label mb-sm">Municipis del Vallès</div>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;max-height:250px;overflow-y:auto;border:1px solid var(--color-border-light);border-radius:8px;padding:10px;">
            <label v-for="muni in municipalityList" :key="muni.code" style="display:flex;align-items:center;gap:6px;font-size:0.82rem;cursor:pointer;padding:4px 0;">
              <input type="checkbox" :value="muni.code" v-model="newMuniForm.municipalities" style="width:14px;height:14px;" />
              <span>{{ muni.name }}</span>
            </label>
          </div>
          <div class="text-small text-muted mt-sm" v-if="newMuniForm.municipalities.length">
            Seleccionats: {{ newMuniForm.municipalities.map(c => getMuniName(c)).join(', ') }}
          </div>
          <div class="form-date-row" style="margin-top:12px;">
            <div class="form-group">
              <label class="form-label">Vàlid des de</label>
              <input class="form-input" type="date" v-model="newMuniForm.valid_from" />
            </div>
            <div class="form-group">
              <label class="form-label">Vàlid fins a <span class="text-muted">(buit = indefinit)</span></label>
              <input class="form-input" type="date" v-model="newMuniForm.valid_to" />
            </div>
          </div>
          <button class="btn btn-primary" @click="saveMuniAssignment" :disabled="!newMuniForm.municipalities.length || !newMuniForm.valid_from" style="margin-top:8px;">
            Desar assignació
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { i18n } from '../i18n'
import { getPostalCodeList, BARCELONA_POSTAL_CODES, getMunicipalityList, VALLES_MUNICIPALITIES } from '../services/geolocation'

const authStore = useAuthStore()
const t = (key) => i18n.t(key)
const postalCodes = getPostalCodeList()
const municipalityList = getMunicipalityList()
const showEditModal = ref(false), showScheduleModal = ref(false), showCsvModal = ref(false), isSaving = ref(false)
const editingUser = ref(null), scheduleUser = ref(null)
const searchQuery = ref('')
const sortKey = ref('name')
const sortDir = ref(1)

const workers = ref([])
const schedules = ref([])
const ambulatoryCentersList = ref([])
const extraData = ref({}) // Stores assignments and points per worker

const filteredWorkers = computed(() => {
  let list = [...workers.value]
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(u => 
      u.name.toLowerCase().includes(q) || 
      u.email.toLowerCase().includes(q) || 
      (u.dni && u.dni.toLowerCase().includes(q))
    )
  }
  list.sort((a, b) => {
    let valA = a[sortKey.value] || ''
    let valB = b[sortKey.value] || ''
    if (typeof valA === 'string') valA = valA.toLowerCase()
    if (typeof valB === 'string') valB = valB.toLowerCase()
    if (valA < valB) return -1 * sortDir.value
    if (valA > valB) return 1 * sortDir.value
    return 0
  })
  return list
})

function toggleSort(key) {
  if (sortKey.value === key) sortDir.value *= -1
  else { sortKey.value = key; sortDir.value = 1 }
}

async function fetchData() {
  try {
    const bulk = await db.getUsersBulk()
    workers.value = bulk.users.filter(u => u.role === 'worker' || u.job_profile === 'Gerencia')
    schedules.value = bulk.work_schedules
    ambulatoryCentersList.value = bulk.ambulatory_centers || []
    
    const data = {}
    workers.value.forEach(u => {
      // Find zones where this user is assigned
      const assignedZones = bulk.zone_assignments.filter(z => z.users.some(uz => uz.id === u.id))
      // Filter by type if needed, but normally they only have one active zone
      const cpZone = assignedZones.find(z => z.type === 'CP' || (z.postal_codes && z.postal_codes.length > 0))
      const muniZone = assignedZones.find(z => z.type === 'MUNICIPALITY' || (z.municipalities && z.municipalities.length > 0))
      
      const locAssignments = bulk.location_assignments.filter(l => l.users.some(ul => ul.id === u.id))
      const centerAssignments = bulk.center_assignments ? bulk.center_assignments.filter(c => c.users.some(ul => ul.id === u.id)) : []

      data[u.id] = {
        cpAssignment: cpZone || null,
        muniAssignment: muniZone || null,
        points: locAssignments || [],
        centers: centerAssignments || []
      }
    })
    extraData.value = data
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const csvPreview = ref([])
const importResults = ref([])

const form = reactive({ name: '', email: '', dni: '', work_type: 'AMBULATORIA', job_profile: 'Fisioterapeuta', postal_code_assigned: '', work_schedule_id: 1, seniority_date: '' })
const scheduleForm = reactive({ id: 1, days: [] })
const isNewSchedule = ref(false)
const newScheduleName = ref('')

const DEFAULT_DAYS = () => [
  { day: 1, name: 'Dilluns',   active: true,  start: '09:00', end: '17:00' },
  { day: 2, name: 'Dimarts',   active: true,  start: '09:00', end: '17:00' },
  { day: 3, name: 'Dimecres',  active: true,  start: '09:00', end: '17:00' },
  { day: 4, name: 'Dijous',    active: true,  start: '09:00', end: '17:00' },
  { day: 5, name: 'Divendres', active: true,  start: '09:00', end: '17:00' },
  { day: 6, name: 'Dissabte',  active: false, start: '',      end: ''      },
  { day: 0, name: 'Diumenge',  active: false, start: '',      end: ''      },
]

function workTypeLabel(type) {
  switch (type) {
    case 'DOMICILIARIA': return 'DOMICILIÀRIA'
    case 'DOMICILIARIA_VALLES': return 'DOM. VALLÈS'
    case 'AMBULATORIA': return 'AMBULATÒRIA'
    default: return type || 'AMBULATORIA'
  }
}

function workTypeBadgeClass(type) {
  // Returns inline style classes could be used but we do inline
  return ''
}

function getMuniName(code) {
  return VALLES_MUNICIPALITIES[code]?.name || code
}

function openEditModal(user) {
  editingUser.value = user
  if (user) {
    const sDate = user.seniority_date ? user.seniority_date.split('T')[0] : ''
    Object.assign(form, { name: user.name, email: user.email, dni: user.dni || '', work_type: user.work_type || 'DOMICILIARIA', job_profile: user.job_profile || 'Fisioterapeuta', postal_code_assigned: user.postal_code_assigned, work_schedule_id: user.work_schedule_id, seniority_date: sDate })
  } else {
    Object.assign(form, { name: '', email: '', dni: '', work_type: 'AMBULATORIA', job_profile: 'Fisioterapeuta', postal_code_assigned: '', work_schedule_id: 1, seniority_date: new Date().toISOString().split('T')[0] })
  }
  showEditModal.value = true
}

async function saveUser() {
  if (!form.name || !form.email) return alert('El nom i email són obligatoris.')
  isSaving.value = true
  try {
    if (editingUser.value) {
      const finalRole = form.job_profile === 'Gerencia' ? 'admin' : 'worker'
      await db.updateUser({ ...editingUser.value, ...form, role: finalRole })
      auditLog(authStore.userId, 'UPDATE_USER', 'user', editingUser.value.id, `Actualitzat: ${form.name}`)
    } else {
      const pw = db.generateRandomPasswordSync()
      const finalRole = form.job_profile === 'Gerencia' ? 'admin' : 'worker'
      await db.addUser({ ...form, password: pw, role: finalRole, active: true, privacy_consent: false })
      auditLog(authStore.userId, 'CREATE_USER', 'user', null, `Creat: ${form.name} (DNI: ${form.dni})`)
      alert(`Treballador creat.\nUsuari: ${form.email}\nContrasenya: ${pw}`)
    }
    await fetchData()
    showEditModal.value = false; editingUser.value = null
  } catch (e) {
    console.error(e)
    alert(`Error en desar: ${e.message || 'Error desconegut'}\n\nSi persisteix, revisi els camps (DNI duplicat, email invàlid, etc).`)
  } finally {
    isSaving.value = false
  }
}

async function deleteUser() {
  if (!editingUser.value) return
  if (!confirm(`Eliminar l'usuari "${editingUser.value.name}"?\n\nAquesta acció no es pot desfer i eliminarà totes les dades associades.`)) return
  isSaving.value = true
  try {
    await db.deleteUser(editingUser.value.id)
    auditLog(authStore.userId, 'DELETE_USER', 'user', editingUser.value.id, `Usuari eliminat: ${editingUser.value.name}`)
    await fetchData()
    showEditModal.value = false; editingUser.value = null
  } catch (e) {
    console.error(e)
    alert(`Error en eliminar: ${e.message || 'Error desconegut'}`)
  } finally {
    isSaving.value = false
  }
}

async function openScheduleModal(user) {
  scheduleUser.value = user
  isNewSchedule.value = false
  newScheduleName.value = ''
  const sched = await db.getWorkSchedule(user.work_schedule_id) || schedules.value[0]
  scheduleForm.id = sched.id
  scheduleForm.days = JSON.parse(JSON.stringify(sched.days))
  showScheduleModal.value = true
}

function startNewSchedule() {
  isNewSchedule.value = true
  newScheduleName.value = ''
  scheduleForm.days = DEFAULT_DAYS()
}

async function loadScheduleTemplate() {
  const sched = await db.getWorkSchedule(scheduleForm.id)
  if (sched) scheduleForm.days = JSON.parse(JSON.stringify(sched.days))
}

function calcHours(d) {
  if (!d.start || !d.end) return 0
  const [sh, sm] = d.start.split(':').map(Number); const [eh, em] = d.end.split(':').map(Number)
  return ((eh * 60 + em - sh * 60 - sm) / 60).toFixed(1)
}

const totalWeeklyHours = computed(() => scheduleForm.days.filter(d => d.active).reduce((s, d) => s + parseFloat(calcHours(d)), 0).toFixed(1))

async function saveSchedule() {
  try {
    let scheduleId = scheduleForm.id
    if (isNewSchedule.value) {
      if (!newScheduleName.value.trim()) { alert('Cal indicar un nom per a la nova plantilla'); return }
      const created = await db.addWorkSchedule({
        name: newScheduleName.value.trim(),
        total_hours_weekly: parseFloat(totalWeeklyHours.value),
        days: scheduleForm.days
      })
      scheduleId = created.id
      auditLog(authStore.userId, 'CREATE_SCHEDULE', 'work_schedule', created.id, `Nova plantilla "${created.name}" creada per ${scheduleUser.value.name}`)
    } else {
      const originalSched = await db.getWorkSchedule(scheduleForm.id)
      const sched = { ...originalSched, days: scheduleForm.days, total_hours_weekly: parseFloat(totalWeeklyHours.value) }
      await db.updateWorkSchedule(sched)
      auditLog(authStore.userId, 'UPDATE_SCHEDULE', 'work_schedule', scheduleForm.id, `Horari actualitzat per ${scheduleUser.value.name}`)
    }
    await db.updateUser({ ...scheduleUser.value, work_schedule_id: scheduleId })
    await fetchData()
    showScheduleModal.value = false
    isNewSchedule.value = false
  } catch (e) { console.error(e) }
}

function handleFileSelect(e) { parseCsv(e.target.files[0]) }
function handleDrop(e) { parseCsv(e.dataTransfer.files[0]) }

function parseCsv(file) {
  if (!file) return
  const reader = new FileReader()
  reader.onload = (e) => {
    const lines = e.target.result.split('\n').map(l => l.trim()).filter(l => l)
    if (lines.length === 0) return
    const sep = lines[0]?.includes(';') ? ';' : ','
    const header = lines[0].toLowerCase()
    const startIdx = (header.includes('nom') || header.includes('name') || header.includes('id')) ? 1 : 0
    
    csvPreview.value = lines.slice(startIdx).map(line => {
      const cols = line.split(sep).map(c => c.trim().replace(/^"|"$/g, ''))
      if (cols.length < 2) return null
      
      // Detection logic:
      const email = cols.find(c => c.includes('@'))
      const password = cols.find(c => c.startsWith('$2y$'))
      const dni = cols.find(c => /^[0-9XYZ][0-9]{7}[A-Z]$/i.test(c))
      
      // Fallback for name: first column with spaces that isn't a hash or email
      const name = cols.find(c => c.includes(' ') && !c.includes('@') && !c.startsWith('$2y$')) || cols[1]

      return {
        name: name || 'Sense Nom',
        email: email || (cols[2] || cols[0]) + '@crtbcn.cat', // Fallback generating email from numeric col
        dni: dni || cols[3] || '',
        password: password || null,
        postal_code_assigned: cols.find(c => /^[0-9]{5}$/.test(c)) || '08001',
        work_schedule_id: parseInt(cols.find(c => /^[0-9]{1,2}$/.test(c) && c.length < 3)) || 1,
        seniority_date: cols.find(c => /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(c)) || new Date().toISOString().split('T')[0],
        work_type: cols.find(c => ['DOMICILIARIA', 'AMBULATORIA', 'DOMICILIARIA_VALLES'].includes(c.toUpperCase())) || 'AMBULATORIA'
      }
    }).filter(r => r && r.name && r.name !== 'Sense Nom')
  }
  reader.readAsText(file)
}

async function importCsv() {
  const results = []
  const newUsers = csvPreview.value.map(u => {
    // Priority: use password from CSV if it exists, otherwise generate one
    const pw = u.password || db.generateRandomPasswordSync()
    results.push({ name: u.name, email: u.email, password: pw.startsWith('$2y$') ? '[OCULT / JA ENCRIPTAT]' : pw })
    return { ...u, password: pw, role: 'worker', active: true, privacy_consent: false, must_change_password: !u.password }
  })
  await db.addUsers(newUsers)
  auditLog(authStore.userId, 'IMPORT_CSV', 'user', null, `Importats ${newUsers.length} treballadors`)
  await fetchData()
  importResults.value = results
  csvPreview.value = []
}

function initials(n) { return n.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() }
function getScheduleName(id) { return schedules.value.find(s => s.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }

// ── CP Assignment management ──
const showCpModal = ref(false)
const cpUser = ref(null)
const activeCpAssignment = computed(() => cpUser.value ? db.getActiveCpAssignment(cpUser.value.id) : null)
const newCpForm = reactive({ postal_codes: [], valid_from: new Date().toISOString().split('T')[0], valid_to: '' })

async function openCpModal(user) {
  cpUser.value = user
  Object.assign(newCpForm, { postal_codes: [], valid_from: new Date().toISOString().split('T')[0], valid_to: '' })
  const active = extraData.value[user.id]?.cpAssignment
  if (active) newCpForm.postal_codes = [...active.postal_codes]
  showCpModal.value = true
}

async function saveCpAssignment() {
  if (!newCpForm.postal_codes.length || !newCpForm.valid_from) return
  try {
    // 1. Get all zones
    const zones = await db.getZones()
    // 2. Find a zone that matches exactly these postal codes, or create one
    const sortedNew = [...newCpForm.postal_codes].sort().join(',')
    let zone = zones.find(z => {
      const sortedZ = [...(z.postal_codes || [])].sort().join(',')
      return sortedZ === sortedNew && z.type === 'CP'
    })

    if (!zone) {
      zone = await db.addZone({
        name: `Zona CP ${newCpForm.postal_codes[0]}${newCpForm.postal_codes.length > 1 ? '...' : ''}`,
        type: 'CP',
        province: 'Barcelona',
        postal_codes: [...newCpForm.postal_codes],
        municipalities: [],
        active: true
      })
    }

    // 3. Assign worker to zone
    await db.assignZone(zone.id, cpUser.value.id, newCpForm.valid_from, newCpForm.valid_to || null)
    
    // Update legacy field for compatibility
    const user = await db.getUser(cpUser.value.id)
    if (user) await db.updateUser({ ...user, postal_code_assigned: newCpForm.postal_codes[0] })
    
    auditLog(authStore.userId, 'ASSIGN_ZONE', 'user', cpUser.value.id, `Zona CP: ${newCpForm.postal_codes.join(',')}`)
    await fetchData()
    showCpModal.value = false
    alert('✅ Assignació de zona (CP) desada correctament!')
  } catch (e) {
    console.error(e)
    alert('Error en desar l\'assignació de zona.')
  }
}

function getCpZoneFn(code) { return BARCELONA_POSTAL_CODES[code]?.zone || code }

// ── Ambulatory Points management ──
const showPointsModal = ref(false)
const pointsTab = ref('center')
const pointsUser = ref(null)
const userPoints = computed(() => pointsUser.value ? (extraData.value[pointsUser.value.id]?.points || []) : [])
const userCenters = computed(() => pointsUser.value ? (extraData.value[pointsUser.value.id]?.centers || []) : [])
const newPoint = reactive({ name: '', lat: 41.3770, lng: 2.1600, radius: 100 })
const newCenterId = ref('')

function openPointsModal(user) {
  pointsUser.value = user
  pointsTab.value = 'center'
  newCenterId.value = ''
  Object.assign(newPoint, { name: '', lat: 41.3770, lng: 2.1600, radius: 100 })
  showPointsModal.value = true
}

async function addCenter() {
  if (!newCenterId.value) return
  try {
    await db.assignAmbulatoryCenter(newCenterId.value, pointsUser.value.id, new Date().toISOString().split('T')[0], null)
    auditLog(authStore.userId, 'ASSIGN_CENTER', 'user', pointsUser.value.id, `Centro assignat: ${newCenterId.value}`)
    newCenterId.value = ''
    await fetchData()
  } catch (e) { console.error(e) }
}

async function removeCenter(id) {
  if (confirm('Retirar aquest centre per al treballador?')) {
    try {
      await db.removeAmbulatoryCenterWorker(id, pointsUser.value.id)
      auditLog(authStore.userId, 'REMOVE_CENTER', 'user', pointsUser.value?.id, `Centre retirat: ${id}`)
      await fetchData()
    } catch (e) { console.error(e) }
  }
}

async function addPoint() {
  if (!newPoint.name || !newPoint.lat || !newPoint.lng) return
  try {
    const locations = await db.getWorkLocations()
    let loc = locations.find(l => l.name === newPoint.name)
    if (!loc) {
      loc = await db.addWorkLocation({
        name: newPoint.name,
        address: 'Ambulatòria Manual',
        lat: newPoint.lat,
        lng: newPoint.lng,
        radius: newPoint.radius,
        active: true
      })
    }
    await db.assignWorkLocation(loc.id, pointsUser.value.id, new Date().toISOString().split('T')[0], null)
    
    auditLog(authStore.userId, 'ADD_WORK_LOCATION', 'user', pointsUser.value.id, `Punt manual afegit: ${newPoint.name}`)
    Object.assign(newPoint, { name: '', lat: 41.3770, lng: 2.1600, radius: 100 })
    await fetchData()
  } catch (e) { console.error(e) }
}

async function removePoint(id) {
  if (confirm('Desactivar aquest punt manual per al treballador?')) {
    try {
      await db.removeWorkLocationWorker(id, pointsUser.value.id)
      auditLog(authStore.userId, 'REMOVE_WORK_LOCATION', 'user', pointsUser.value?.id, `Punt manual retirat: ${id}`)
      await fetchData()
    } catch (e) { console.error(e) }
  }
}

// ── Municipal Assignment management (VALLES) ──
const showMuniModal = ref(false)
const muniUser = ref(null)
const activeMuniAssignment = computed(() => muniUser.value ? db.getActiveMunicipalAssignment(muniUser.value.id) : null)
const newMuniForm = reactive({ municipalities: [], valid_from: new Date().toISOString().split('T')[0], valid_to: '' })

async function openMuniModal(user) {
  muniUser.value = user
  Object.assign(newMuniForm, { municipalities: [], valid_from: new Date().toISOString().split('T')[0], valid_to: '' })
  const active = extraData.value[user.id]?.muniAssignment
  if (active) newMuniForm.municipalities = [...active.municipalities]
  showMuniModal.value = true
}

async function saveMuniAssignment() {
  if (!newMuniForm.municipalities.length || !newMuniForm.valid_from) return
  try {
    const zones = await db.getZones()
    const sortedNew = [...newMuniForm.municipalities].sort().join(',')
    let zone = zones.find(z => {
      const sortedZ = [...(z.municipalities || [])].sort().join(',')
      return sortedZ === sortedNew && z.type === 'MUNICIPALITY'
    })

    if (!zone) {
      zone = await db.addZone({
        name: `Zona Muni ${newMuniForm.municipalities[0]}${newMuniForm.municipalities.length > 1 ? '...' : ''}`,
        type: 'MUNICIPALITY',
        province: 'Barcelona',
        postal_codes: [],
        municipalities: [...newMuniForm.municipalities],
        active: true
      })
    }

    await db.assignZone(zone.id, muniUser.value.id, newMuniForm.valid_from, newMuniForm.valid_to || null)
    
    auditLog(authStore.userId, 'ASSIGN_ZONE_MUNI', 'user', muniUser.value.id, `Municipis: ${newMuniForm.municipalities.join(',')}`)
    await fetchData()
    showMuniModal.value = false
    alert('✅ Assignació de zona (Municipis) desada correctament!')
  } catch (e) {
    console.error(e)
    alert('Error en desar l\'assignació municipal.')
  }
}
</script>
