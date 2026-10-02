<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('employees') }}</h1></div>
      <div class="page-actions">
        <button class="btn btn-outline" @click="showCsvModal = true">📥 Importar CSV</button>
        <!-- Crear a domi els comptes que falten, sense entrar al servidor. Només apareix si
             realment en falta algun: un botó sempre visible que gairebé mai cal fer res
             s'acaba prement per curiositat. -->
        <button v-if="pendentsDomi > 0" class="btn btn-accent" :disabled="provisionantTanda"
                @click="provisionaTanda">
          {{ provisionantTanda ? 'Creant comptes…' : `🔗 Crear ${pendentsDomi} compte(s) a domi` }}
        </button>
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
              <td><div style="display:flex;align-items:center;gap:8px;"><div class="header-avatar" style="width:28px;height:28px;font-size:0.7rem;">{{ initials(user.name) }}</div>{{ user.name }}<span v-if="user.practiques" class="badge badge-info" style="font-size:.68rem;" title="Alumne/a en pràctiques">🎓 Pràctiques</span></div></td>
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
                  <div v-if="extraData[user.id]?.cpAssignment" style="display:flex;gap:3px;flex-wrap:wrap;">
                    <template v-if="resumCps(extraData[user.id].cpAssignment.postal_codes).totaBcn">
                      <span class="cp-xip-mini tota" :title="resumCps(extraData[user.id].cpAssignment.postal_codes).tots.join(', ')">🏙️ Tota Barcelona · {{ resumCps(extraData[user.id].cpAssignment.postal_codes).total }} CP</span>
                    </template>
                    <template v-else>
                      <span v-for="cp in resumCps(extraData[user.id].cpAssignment.postal_codes).visibles" :key="cp" class="cp-xip-mini">{{ cp }}</span>
                      <span v-if="resumCps(extraData[user.id].cpAssignment.postal_codes).resta" class="cp-xip-mini mes" :title="resumCps(extraData[user.id].cpAssignment.postal_codes).tots.join(', ')">+{{ resumCps(extraData[user.id].cpAssignment.postal_codes).resta }}</span>
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
                  <button class="btn btn-outline btn-sm" @click="openCalendarModal(user)" title="Calendari laboral">📆</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Employee Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal" style="max-width:760px;">
        <div class="modal-header">
          <h3 class="modal-title">{{ editingUser ? t('edit') : t('create') }} {{ t('employee') }}</h3>
          <button class="icon-btn" @click="showEditModal = false" :disabled="isSaving">✕</button>
        </div>
        <!-- Fitxa en pestanyes: abans era una sola columna llarguíssima i no es trobava res. -->
        <div class="fitxa-tabs">
          <button v-for="p in pestanyesFitxa" :key="p.clau" type="button" class="fitxa-tab" :class="{ actiu: pestanyaFitxa === p.clau }" @click="pestanyaFitxa = p.clau">{{ p.nom }}</button>
        </div>

        <div v-show="pestanyaFitxa === 'dades'">
        <div class="form-group">
          <label class="form-label">{{ t('name') }}</label>
          <input class="form-input" v-model="form.name" :disabled="isSaving" />
        </div>
          <div class="fitxa-graella">
          <div class="form-group">
            <label class="form-label">{{ t('email') }}</label>
            <input class="form-input" type="email" v-model="form.email" :disabled="isSaving" />
          </div>
          <div class="form-group">
            <label class="form-label">DNI / NIE</label>
            <input class="form-input" v-model="form.dni" placeholder="12345678A" style="font-family:monospace;" :disabled="isSaving" />
          </div>
          <div class="form-group">
            <label class="form-label">Telèfon corporatiu (SIM de tauleta o fix del lloc de treball)</label>
            <input class="form-input" v-model="form.device_phone" placeholder="+34600000000 o 934000000" style="font-family:monospace;" :disabled="isSaving" />
          </div>
          <div class="form-group">
            <label class="form-label">Data alta (antiguitat)</label>
            <input class="form-input" type="date" v-model="form.seniority_date" :disabled="isSaving" />
          </div>
          </div>
        </div>

        <div v-show="pestanyaFitxa === 'lloc'">
          <div class="fitxa-graella">
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
              <option value="Terapeuta Ocupacional">Terapeuta ocupacional</option>
              <option value="Coordinación">Coordinació</option>
              <option value="Administracion">Administració</option>
              <option value="Recepción">Recepció</option>
              <option value="Informatica">Informàtica</option>
              <option value="Limpieza">Neteja</option>
              <option value="Responsable RRHH">Responsable RRHH</option>
              <option value="Gerencia">Gerència</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Horari</label>
            <select class="form-select" v-model="form.work_schedule_id" :disabled="isSaving">
              <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          </div>
          <div v-if="['Fisioterapeuta', 'Logopeda', 'Terapeuta Ocupacional'].includes(form.job_profile)">
        <div class="form-group">
          <label class="form-label">Especialitats clíniques</label>
          <div style="display:flex;flex-wrap:wrap;gap:14px;padding:2px 0;">
            <label v-for="sp in specialtiesCatalog" :key="sp.code" style="display:flex;align-items:center;gap:5px;font-size:0.85rem;cursor:pointer;">
              <input type="checkbox" :value="sp.code" v-model="form.specialties" :disabled="isSaving" />
              {{ sp.name }}
            </label>
          </div>
          <div class="text-small text-muted" style="margin-top:2px;">Determina quins pacients se li poden assignar a domi. Un fisio pot tenir-ne diverses.</div>
        </div>

          </div>
        <div class="form-group" v-if="lotsCatalog.length">
          <label class="form-label">Lot territorial</label>
          <div style="display:flex;flex-wrap:wrap;gap:14px;padding:2px 0;">
            <label v-for="l in lotsCatalog" :key="l.code" style="display:flex;align-items:center;gap:5px;font-size:0.85rem;cursor:pointer;">
              <input type="checkbox" :value="l.code" v-model="form.lots" :disabled="isSaving" />
              {{ l.code }} · {{ l.name }}
            </label>
          </div>
          <div class="text-small text-muted" style="margin-top:2px;">Territori ample del contracte amb CatSalut. Es pot cobrir més d'un lot.</div>
        </div>

        <div class="form-group" v-if="departmentsCatalog.length">
          <label class="form-label">Departament</label>
          <div style="display:flex;flex-wrap:wrap;gap:14px;padding:2px 0;">
            <label v-for="d in departmentsCatalog" :key="d.code" style="display:flex;align-items:center;gap:5px;font-size:0.85rem;cursor:pointer;">
              <input type="checkbox" :value="d.code" v-model="form.departments" :disabled="isSaving" />
              {{ d.name }}
            </label>
          </div>
          <div class="text-small text-muted" style="margin-top:2px;">Servei i modalitat. Qui fa ambulatòria i domiciliària en marca les dues.</div>
        </div>

        <!-- Info box about work type -->
        <div v-if="form.work_type === 'AMBULATORIA'" style="background:rgba(59,130,246,0.06);border-radius:8px;padding:10px;margin-bottom:12px;font-size:0.82rem;">
          ℹ️ <strong>Ambulatori:</strong> Podeu configurar punts geolocalitzats específics per aquest treballador después de crear-lo (botó 📍 Punts).
        </div>
        <div v-if="form.work_type === 'DOMICILIARIA_VALLES'" style="background:rgba(147,51,234,0.06);border-radius:8px;padding:10px;margin-bottom:12px;font-size:0.82rem;">
          ℹ️ <strong>Domiciliari Vallès:</strong> El fichatge geolocalitzat és vàlid en qualsevol punt dels termes municipals assignats. Configureu-los amb el botó 🏘️ Munis.
        </div>

        </div>

        <div v-show="pestanyaFitxa === 'jornada'">
          <div class="form-group">
            <label class="form-label" style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" v-model="form.pacte_complementaries" :disabled="isSaving" />
              Pacte d'hores complementàries (art. 12.5 ET)
            </label>
            <small style="color:#6b7280;">Complementàries (art. 12.5 ET): exclusives del contracte parcial. La planificació va sempre 2% per sota del màxim pactat.</small>
          </div>
          <div class="form-group" v-if="editingUser && form.pacte_complementaries && !esJornadaCompleta">
            <label class="form-label">Percentatge de complementàries (conveni: fins al 50%)</label>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <input type="range" :min="minPactablePct" :max="maxPactablePct" step="1" v-model.number="ratioPct" :disabled="aplicantRatio" style="flex:1;min-width:160px;" />
              <input type="number" :min="minPactablePct" :max="maxPactablePct" step="1" v-model.number="ratioPct" :disabled="aplicantRatio" style="width:70px;" class="form-input" />
              <span style="font-weight:700;">%</span>
              <button type="button" class="btn btn-secondary btn-sm" :disabled="aplicantRatio || ratioPct===Math.round(ratioActual*100)"
                      @click="aplicaRatio">{{ aplicantRatio ? 'Desant…' : 'Aplicar (preavís 7 dies)' }}</button>
            </div>
            <small style="color:#6b7280;">
              Aquest professional fa el <strong>{{ jornadaPct }}%</strong> de la jornada completa.
              El diferencial fins al 100% (1726 h) marca el màxim PACTABLE: <strong>{{ maxPactablePct }}%</strong>
              (per sobre superaria les 1726 h i per això no es pot ni pactar).
              <br>Vigent ara: <strong>{{ Math.round(ratioActual*100) }}%</strong> · el planificador aplica el <strong>{{ Math.round(Math.max(0,(Math.min(ratioActual,ratioCapTope)-0.02))*100) }}%</strong> (2% menys).
              <span v-if="ratioPendent">· Pendent {{ Math.round(ratioPendent*100) }}% des del {{ ratioPendentDes }}.</span>
            </small>
            <div v-if="ratioErr" class="text-small" style="color:#be123c;margin-top:3px;">{{ ratioErr }}</div>
            <div v-if="ratioMsg" class="text-small" style="color:#065f46;margin-top:3px;">{{ ratioMsg }}</div>
          </div>
          <div class="form-group" v-else-if="editingUser && form.pacte_complementaries && esJornadaCompleta">
            <small style="color:#92400e;">Jornada completa (100%): no admet complementàries, només extraordinàries (80 h/any a 1,25×).</small>
          </div>
        <!-- PRÀCTIQUES: hores del conveni amb el centre formatiu. Les fetes surten dels fitxatges
             aprovats del període, així que es van restant soles quan RRHH valida. -->
        <div v-if="editingUser && potGestionar2fa" class="fitxa-bloc">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" v-model="prac.practiques" :disabled="desantPrac" />
            🎓 Alumne/a en pràctiques
          </label>
          <template v-if="prac.practiques">
            <div class="form-group">
              <label class="form-label">Tipus de conveni</label>
              <select class="form-select" v-model="prac.practiques_tipus" :disabled="desantPrac">
                <option value="" disabled>Tria la modalitat…</option>
                <option v-for="(t, clau) in pracCataleg" :key="clau" :value="clau">{{ t.nom }}</option>
              </select>
            </div>
            <!-- Recordatoris de la modalitat triada (no són validacions: mana el conveni signat) -->
            <div v-for="(a, i) in (tipusPrac?.avisos || [])" :key="i" class="prac-avis">ℹ️ {{ a }}</div>
            <div class="fitxa-graella">
              <div v-if="tePrac('estudis')" class="form-group">
                <label class="form-label">{{ tipusPrac?.estudis || 'Estudis' }}</label>
                <input class="form-input" v-model="prac.detall.estudis" placeholder="p. ex. Tècnic superior en…" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('curs')" class="form-group">
                <label class="form-label">Curs</label>
                <input class="form-input" v-model="prac.detall.curs" placeholder="p. ex. 2n / 2026-27" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('ects')" class="form-group">
                <label class="form-label">Crèdits ECTS</label>
                <input class="form-input" type="number" min="0" step="0.5" v-model.number="prac.detall.ects" @input="horesDesDeEcts" :disabled="desantPrac" />
                <small class="text-muted">Les hores es calculen a {{ pracHoresEcts }} h per crèdit (es poden corregir).</small>
              </div>
              <div class="form-group">
                <label class="form-label">Hores del conveni</label>
                <input class="form-input" type="number" min="1" step="0.5" v-model.number="prac.practiques_hores" placeholder="p. ex. 300" :disabled="desantPrac" />
              </div>
              <div class="form-group">
                <label class="form-label">Inici</label>
                <input class="form-input" type="date" v-model="prac.practiques_inici" :disabled="desantPrac" />
              </div>
              <div class="form-group">
                <label class="form-label">Fi prevista <span class="text-muted">(opcional)</span></label>
                <input class="form-input" type="date" v-model="prac.practiques_fi" :disabled="desantPrac" />
              </div>
              <div class="form-group">
                <label class="form-label">Centre formatiu <span class="text-muted">(opcional)</span></label>
                <input class="form-input" v-model="prac.practiques_centre" placeholder="Institut, universitat…" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('tutor_centre_nom')" class="form-group">
                <label class="form-label">Tutor/a del centre</label>
                <input class="form-input" v-model="prac.detall.tutor_centre_nom" placeholder="Nom i cognoms" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('tutor_centre_email')" class="form-group">
                <label class="form-label">Correu del tutor/a del centre</label>
                <input class="form-input" type="email" v-model="prac.detall.tutor_centre_email" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('tutor_empresa')" class="form-group">
                <label class="form-label">Tutor/a a l'empresa</label>
                <input class="form-input" v-model="prac.detall.tutor_empresa" placeholder="Qui l'acompanya a CRIL" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('num_conveni')" class="form-group">
                <label class="form-label">Núm. de conveni</label>
                <input class="form-input" v-model="prac.detall.num_conveni" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('alta_ss')" class="form-group">
                <label class="form-label">Alta a la Seguretat Social</label>
                <input class="form-input" type="date" v-model="prac.detall.alta_ss" :disabled="desantPrac" />
              </div>
              <div v-if="tePrac('remunerada')" class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;">
                  <input type="checkbox" v-model="prac.detall.remunerada" :disabled="desantPrac || tipusPrac?.beca_obligatoria" />
                  Remunerada (beca)
                </label>
                <input v-if="prac.detall.remunerada || tipusPrac?.beca_obligatoria" class="form-input" type="number" min="0" step="0.01"
                  v-model.number="prac.detall.beca_mensual" placeholder="Import mensual (€)" :disabled="desantPrac" />
              </div>
            </div>
            <div v-if="tePrac('observacions')" class="form-group">
              <label class="form-label">Observacions</label>
              <textarea class="form-textarea" rows="2" v-model="prac.detall.observacions" :disabled="desantPrac"></textarea>
            </div>
            <div v-if="tePrac('alta_ss') && !prac.detall.alta_ss" class="prac-avis prac-avis-alerta">⚠ {{ pracAvisSs }}</div>
            <div v-if="pracEstat?.practiques && pracEstat.hores_conveni" class="prac-progres">
              <div class="prac-barra"><div :style="{ width: pracEstat.percentatge + '%' }"></div></div>
              <div class="prac-xifres">
                <span><strong>{{ formatHM(pracEstat.fetes) }}</strong> fetes i validades</span>
                <span><strong>{{ formatHM(pracEstat.restants) }}</strong> en queden</span>
                <span v-if="pracEstat.pendents_validar" class="text-muted">· {{ formatHM(pracEstat.pendents_validar) }} pendents de validar</span>
              </div>
              <div v-if="pracEstat.excedides > 0" class="prac-avis prac-avis-alerta">⚠ S'han superat les hores del conveni en {{ formatHM(pracEstat.excedides) }}: el conveni no les cobreix.</div>
            </div>
          </template>
          <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
            <button type="button" class="btn btn-secondary btn-sm" :disabled="desantPrac" @click="desaPractiques">{{ desantPrac ? 'Desant…' : 'Desar pràctiques' }}</button>
            <span v-if="pracMsg" class="text-small" :style="{ color: pracOk ? '#065f46' : '#be123c' }">{{ pracMsg }}</span>
          </div>
        </div>
        </div>

        <div v-show="pestanyaFitxa === 'acces'">
        <!-- SEGON FACTOR. Direcció (01-08-2026): l'ha de poder activar l'admin O RRHH per a
             cada usuari. Dos mètodes, i la tria no és de gust: depèn del dispositiu amb què
             entra la persona. Vegeu el text d'ajuda de sota. -->
        <div class="form-group" v-if="editingUser && potGestionar2fa">
          <label class="form-label">Segon factor d'autenticació</label>
          <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
            <label style="display:flex;align-items:center;gap:5px;font-size:0.85rem;cursor:pointer;">
              <input type="radio" value="dispositiu" v-model="form.second_factor" :disabled="desant2fa" />
              Tauleta corporativa
            </label>
            <label style="display:flex;align-items:center;gap:5px;font-size:0.85rem;cursor:pointer;">
              <input type="radio" value="totp" v-model="form.second_factor" :disabled="desant2fa" />
              App d'autenticació (codi de 6 xifres)
            </label>
            <button type="button" class="btn btn-secondary btn-sm" :disabled="desant2fa" @click="desa2fa">
              {{ desant2fa ? 'Desant…' : 'Aplicar' }}
            </button>
            <span v-if="form.second_factor === 'totp'"
                  :style="{ background: form.totp_confirmed ? '#ecfdf5' : '#fffbeb',
                            color: form.totp_confirmed ? '#065f46' : '#92400e',
                            border: '1px solid ' + (form.totp_confirmed ? '#a7f3d0' : '#fde68a'),
                            borderRadius: '6px', padding: '2px 8px', fontSize: '.78rem' }">
              {{ form.totp_confirmed ? '✓ Ja l\'ha configurat' : 'Pendent que la persona l\'enroli' }}
            </span>
          </div>
          <div v-if="msg2fa" class="text-small" :style="{ color: ok2fa ? '#94a3b8' : '#be123c' }"
               style="margin-top:4px;">{{ msg2fa }}</div>
          <div class="text-small text-muted" style="margin-top:2px;">
            <strong>Tauleta corporativa</strong>: el segon factor és el propi dispositiu de
            l'empresa; qui el porta no ha d'escriure res.
            <strong>App d'autenticació</strong>: per a qui treballa des d'un ordinador o des
            del seu mòbil. La persona l'enrola des del seu perfil i rep 10 codis de
            recuperació d'un sol ús per si perd el telèfon.
          </div>
        </div>

        <!-- COMPTE A DOMI. L'alta individual l'aprovisiona sola, però la càrrega massiva no
             (vuitanta crides HTTP no caben en una petició web). Sense aquest bloc, després
             d'una càrrega la plantilla és a RRHH i no a domi, i no ho sap ningú. -->
        <div class="form-group" v-if="editingUser && esDomiciliari">
          <label class="form-label">Compte a Domiciliària</label>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span v-if="form.domi_provisioned_at"
                  style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:6px;padding:2px 8px;font-size:.78rem;">
              ✓ Creat{{ form.domi_username ? ' · ' + form.domi_username : '' }}
            </span>
            <span v-else
                  style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:6px;padding:2px 8px;font-size:.78rem;">Encara NO té compte a domi</span>
            <button type="button" class="btn btn-secondary btn-sm"
                    :disabled="provisionant" @click="provisionaDomi">
              {{ provisionant ? 'Creant…' : (form.domi_provisioned_at ? 'Tornar a sincronitzar' : 'Crear compte a domi') }}
            </button>
          </div>
          <div v-if="provisioMsg" class="text-small" :style="{ color: provisioOk ? '#94a3b8' : '#be123c' }"
               style="margin-top:4px;">{{ provisioMsg }}</div>
          <div class="text-small text-muted" style="margin-top:2px;">
            Sense compte a domi, la persona no pot entrar-hi ni se li poden programar visites.
          </div>
        </div>

        </div>
        <div class="modal-footer">
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
                <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.name }} ({{ formatHM(s.total_hours_weekly) }}/set)</option>
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

        <!-- Aplicar un mateix horari a diversos dies d'un cop, en lloc d'anar un per un -->
        <div style="margin-top:14px;padding:10px 12px;background:var(--color-bg);border-radius:8px;">
          <div class="form-label" style="margin-bottom:6px;">Aplicar un horari a diversos dies</div>
          <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
            <input class="form-input" type="time" v-model="bulkStart" style="width:115px;" />
            <span>→</span>
            <input class="form-input" type="time" v-model="bulkEnd" style="width:115px;" />
            <button type="button" class="btn btn-outline btn-sm" :disabled="!bulkStart || !bulkEnd" @click="applyBulkSchedule([1,2,3,4,5])">Laborables (Dl-Dv)</button>
            <button type="button" class="btn btn-outline btn-sm" :disabled="!bulkStart || !bulkEnd" @click="applyBulkSchedule([1,2,3,4,5,6,0])">Tota la setmana</button>
            <button type="button" class="btn btn-outline btn-sm" :disabled="!bulkStart || !bulkEnd" @click="applyBulkSchedule([1,3,5])">Dl · Dc · Dv</button>
            <button type="button" class="btn btn-outline btn-sm" :disabled="!bulkStart || !bulkEnd" @click="applyBulkSchedule([2,4])">Dt · Dj</button>
          </div>
          <div class="text-small text-muted" style="margin-top:6px;">
            Posa l'hora d'entrada i sortida i tria a quins dies s'aplica. Després pots ajustar dies concrets a sota si cal.
          </div>
        </div>

        <!-- Editor de dies (comú als dos modes). Suporta jornades partides: diversos trams al mateix dia. -->
        <div v-for="(d, index) in scheduleForm.days" :key="index" style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--color-border-light);">
          <label style="width:100px;font-weight:500;">
            <input v-if="!isExtraTramo(index)" type="checkbox" v-model="d.active" style="width:14px;height:14px;margin-right:6px;" />
            <span v-else style="display:inline-block;width:20px;"></span>
            {{ isExtraTramo(index) ? '↳ tram' : (d.name || dayName(d.day)) }}
          </label>
          <input v-if="d.active" class="form-input" type="time" v-model="d.start" style="width:115px;" />
          <span v-if="d.active">→</span>
          <input v-if="d.active" class="form-input" type="time" v-model="d.end" style="width:115px;" />
          <span v-if="d.active" class="text-small text-muted">{{ formatHM(calcHours(d)) }}</span>
          <span v-else class="text-small text-muted">Descans</span>
          <button v-if="d.active && !isExtraTramo(index)" type="button" class="btn btn-outline btn-sm" title="Afegir un segon tram (jornada partida)" @click="addTramo(index)">+ tram</button>
          <button v-if="isExtraTramo(index)" type="button" class="btn btn-outline btn-sm" style="color:var(--color-danger);" title="Treure aquest tram" @click="removeTramo(index)">✕</button>
        </div>

        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;">
          <span class="text-small"><strong>Total setmanal:</strong> {{ formatHM(totalWeeklyHours) }}</span>
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
    <!-- Calendari laboral (PDF incrustat, no descarregable) -->
    <div v-if="showCalendarModal" class="modal-overlay" @click.self="showCalendarModal = false">
      <div class="modal" style="max-width:680px;">
        <div class="modal-header">
          <h3 class="modal-title">📆 Calendari laboral — {{ calendarUser?.name }}</h3>
          <button class="icon-btn" @click="showCalendarModal = false">✕</button>
        </div>
        <CalendarPdfViewer v-if="calendarUser" :worker="calendarUser" :schedule="calendarSchedule" />
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCalendarModal = false">{{ t('close') || 'Tancar' }}</button>
        </div>
      </div>
    </div>

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
        <!-- Assignació actual. Abans es llegia d'una promesa sense esperar-la i aquesta caixa no sortia mai. -->
        <div v-if="activeCpAssignment" class="cp-actual">
          <div class="text-small" style="color:var(--color-success);font-weight:700;">✓ Assignació actual</div>
          <div class="cp-xips">
            <span v-if="resumCps(activeCpAssignment.postal_codes).totaBcn" class="cp-xip fixa"><strong>🏙️ Tota Barcelona</strong> {{ activeCpAssignment.postal_codes.length }} CP</span>
            <template v-else>
              <span v-for="cp in [...activeCpAssignment.postal_codes].sort()" :key="cp" class="cp-xip fixa"><strong>{{ cp }}</strong> {{ getCpZoneFn(cp) }}</span>
            </template>
          </div>
        </div>
        <div v-else class="text-small text-muted" style="margin-bottom:14px;">Aquesta persona encara no té cap codi postal assignat.</div>

        <h4 style="font-size:0.9rem;margin-bottom:8px;">{{ activeCpAssignment ? 'Canviar l’assignació' : 'Nova assignació' }}</h4>
        <!-- Triats: a dalt i amb ✕, perquè es vegi d'un cop d'ull què es desarà -->
        <div class="cp-triats">
          <span class="text-small" style="font-weight:700;">Triats ({{ newCpForm.postal_codes.length }}):</span>
          <span v-if="!newCpForm.postal_codes.length" class="text-small text-muted">cap — tria'n a la llista de sota</span>
          <span v-if="resumCps(newCpForm.postal_codes).totaBcn" class="cp-xip triat"><strong>🏙️ Tota Barcelona</strong> {{ newCpForm.postal_codes.length }} CP</span>
          <template v-else>
            <span v-for="cp in [...newCpForm.postal_codes].sort()" :key="cp" class="cp-xip triat">
              <strong>{{ cp }}</strong> {{ getCpZoneFn(cp) }}
              <button type="button" class="cp-treu" :title="'Treure ' + cp" @click="toggleCp(cp)">✕</button>
            </span>
          </template>
          <button v-if="newCpForm.postal_codes.length" type="button" class="btn btn-outline btn-sm" @click="newCpForm.postal_codes = []">Treure'ls tots</button>
        </div>
        <div class="cp-cerca-fila">
          <input v-model="cercaCp" type="search" class="form-input" placeholder="🔍 Cerca per codi o barri (p. ex. «sants», «gràcia», «08015»)" />
          <!-- Amb una cerca, selecciona només els que es veuen (p. ex. tot l'Eixample); sense, tots. -->
          <button type="button" class="btn btn-outline btn-sm cp-tots" :disabled="!cpsPerAfegir" @click="seleccionaTotsCp">
            {{ cercaCp.trim() ? `Seleccionar els ${cpsFiltrats.length} trobats` : 'Seleccionar tots' }}
          </button>
        </div>
        <div class="cp-graella">
          <button v-for="cp in cpsFiltrats" :key="cp.code" type="button" class="cp-opcio" :class="{ sel: newCpForm.postal_codes.includes(cp.code) }" @click="toggleCp(cp.code)">
            <span class="cp-codi">{{ newCpForm.postal_codes.includes(cp.code) ? '✓ ' : '' }}{{ cp.code }}</span>
            <span class="cp-barri">{{ cp.zone }}</span>
            <span class="cp-qui" :title="'Persones que ja tenen aquest codi assignat'">👥 {{ cobertura[cp.code] || 0 }}</span>
          </button>
          <div v-if="!cpsFiltrats.length" class="text-small text-muted" style="grid-column:1/-1;padding:8px;">Cap codi coincideix amb «{{ cercaCp }}»</div>
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
        <button class="btn btn-primary" @click="saveCpAssignment" :disabled="!newCpForm.postal_codes.length || !newCpForm.valid_from">
          Desar l'assignació ({{ newCpForm.postal_codes.length }} codi{{ newCpForm.postal_codes.length === 1 ? '' : 's' }})
        </button>
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
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { db } from '../services/db'
import api from '../services/apiClient'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { i18n } from '../i18n'
import { getPostalCodeList, BARCELONA_POSTAL_CODES, getMunicipalityList, VALLES_MUNICIPALITIES } from '../services/geolocation'
import { resumCps } from '../utils/resumCps'
import CalendarPdfViewer from '../components/CalendarPdfViewer.vue'
import { formatHM } from '../utils/formatHours'

const authStore = useAuthStore()
const t = (key) => i18n.t(key)
const postalCodes = getPostalCodeList()
const municipalityList = getMunicipalityList()
const showEditModal = ref(false), showScheduleModal = ref(false), showCsvModal = ref(false), isSaving = ref(false)
const editingUser = ref(null), scheduleUser = ref(null)
const ratioPct = ref(30), ratioActual = ref(0.30), ratioPendent = ref(null), ratioPendentDes = ref(null)
const jornadaPct = ref(0), ratioCapTope = ref(0.50)
const aplicantRatio = ref(false), ratioMsg = ref(''), ratioErr = ref('')
const maxPactablePct = computed(() => Math.min(50, Math.floor(ratioCapTope.value * 100)))
const minPactablePct = computed(() => 0)
const esJornadaCompleta = computed(() => {
  const sch = schedules.value.find(s => s.id === form.work_schedule_id)
  return !!sch && Number(sch.total_hours_weekly) >= 37.5 - 0.01
})
async function carregaJornadaTope(userId){
  try {
    const r = await api.get(`/v1/pacte-complementaries/info/${userId}`)
    jornadaPct.value = r.jornada_pct; ratioCapTope.value = r.ratio_cap_tope
    if (ratioPct.value > maxPactablePct.value) ratioPct.value = maxPactablePct.value
    if (ratioPct.value < minPactablePct.value) ratioPct.value = minPactablePct.value
  } catch(e){ /* si no hi ha info, es queda amb els valors per defecte */ }
}
async function aplicaRatio(){
  aplicantRatio.value = true; ratioMsg.value = ''; ratioErr.value = ''
  try {
    const r = await api.post('/v1/pacte-complementaries/ratio', { user_id: editingUser.value.id, ratio: ratioPct.value / 100 })
    ratioActual.value = r.ratio_actual; ratioPendent.value = r.ratio_pendent; ratioPendentDes.value = r.efecte
    if (r.jornada_pct != null) jornadaPct.value = r.jornada_pct
    ratioMsg.value = r.missatge
  } catch(e){ ratioErr.value = e?.response?.data?.message || 'No s\'ha pogut aplicar.' }
  finally { aplicantRatio.value = false }
}
const showCalendarModal = ref(false), calendarUser = ref(null)
const calendarSchedule = computed(() => schedules.value.find(s => s.id === calendarUser.value?.work_schedule_id) || null)
function openCalendarModal(user) { calendarUser.value = user; showCalendarModal.value = true }
const searchQuery = ref('')
const sortKey = ref('name')
const sortDir = ref(1)

const workers = ref([])
const schedules = ref([])
const specialtiesCatalog = ref([])
/* Lots i departaments: el quadre de disponibilitat s'hi filtra, i sense assignació una
   persona no surt a cap filtre. Es carreguen de l'endpoint de filtres del quadre per no
   duplicar el catàleg en dos llocs. */
const pendentsDomi = ref(0)
const provisionantTanda = ref(false)

/** Quants domiciliaris no tenen encara compte a domi. Es recompta des de la plantilla. */
function recomptaPendentsDomi () {
  pendentsDomi.value = (workers.value || []).filter(u =>
    u.role === 'worker' && String(u.work_type || '').startsWith('DOMICILIARIA') &&
    u.active && !u.domi_provisioned_at
  ).length
}

/**
 * Crea a domi tots els comptes que falten. Va per tandes perquè cada alta és una crida HTTP
 * amb timeout: fer-les totes en una petició la faria caure pel mig. El bucle s'atura quan el
 * servidor diu que no continuï —o sigui, quan una tanda no ha aconseguit crear-ne CAP—,
 * perquè insistir només repetiria el mateix error.
 */
async function provisionaTanda () {
  provisionantTanda.value = true
  const errors = []
  let fets = 0
  try {
    for (let volta = 0; volta < 20; volta++) {
      const r = await api.post('/v1/users/provisiona-domi-pendents', {})
      fets += r.fets || 0
      if (r.errors?.length) errors.push(...r.errors)
      pendentsDomi.value = r.queden ?? 0
      if (!r.continua) break
    }
    await fetchData()
    recomptaPendentsDomi()
    alert(errors.length
      ? `S'han creat ${fets} compte(s). Han fallat:\n\n` + errors.slice(0, 10).join('\n')
      : `Fet: ${fets} compte(s) creats a domi.`)
  } catch (e) {
    alert('No s\'ha pogut completar: ' + (e?.data?.error || e?.message || 'error desconegut'))
  } finally {
    provisionantTanda.value = false
  }
}

const desant2fa = ref(false)
const msg2fa = ref('')
const ok2fa = ref(true)

/* Qui pot canviar el segon factor d'algú: administració i RRHH. Coordinació no —decidir com
   entra una persona a l'aplicació és administració de comptes, no direcció del dia a dia—,
   i el backend ho talla igualment. */
const potGestionar2fa = computed(() => ['admin', 'hr'].includes(authStore.user?.role))

async function desa2fa () {
  desant2fa.value = true
  msg2fa.value = ''
  try {
    const r = await api.put(`/v1/users/${editingUser.value.id}/2fa`, { method: form.second_factor })
    ok2fa.value = true
    msg2fa.value = r.message || 'Segon factor actualitzat.'
  } catch (e) {
    ok2fa.value = false
    msg2fa.value = e?.data?.message || e?.message || 'No s\'ha pogut canviar el segon factor.'
  } finally {
    desant2fa.value = false
  }
}

const provisionant = ref(false)
const provisioMsg = ref('')
const provisioOk = ref(true)

/* Només el personal domiciliari té compte a domi: per a la resta, el bloc no es pinta. */
const esDomiciliari = computed(() =>
  form.role === 'worker' && String(form.work_type || '').startsWith('DOMICILIARIA'))

async function provisionaDomi () {
  provisionant.value = true
  provisioMsg.value = ''
  try {
    const r = await api.post(`/v1/users/${editingUser.value.id}/provisiona-domi`, {})
    form.domi_username = r.domi_username
    form.domi_provisioned_at = r.domi_provisioned_at
    provisioOk.value = true
    provisioMsg.value = 'Compte creat a domi: ' + (r.domi_username || '')
  } catch (e) {
    /* Que es vegi el motiu. Un botó que no fa res i no diu res és pitjor que no tenir-lo. */
    provisioOk.value = false
    provisioMsg.value = e?.data?.error || e?.message || 'No s\'ha pogut crear el compte a domi.'
  } finally {
    provisionant.value = false
  }
}

const lotsCatalog = ref([])
const departmentsCatalog = ref([])
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
    try { specialtiesCatalog.value = await db.getSpecialties() } catch (e) { specialtiesCatalog.value = [] }
  recomptaPendentsDomi()
  /* Si l'usuari no és admin ni RRHH, l'endpoint respon 403 i els catàlegs es queden buits:
     llavors els blocs de lot i departament no es pinten. És coherent amb el backend, que
     tampoc deixa que un coordinador els canviï. */
  try {
    const f = await api.get('/v1/disponibilitat/filtres')
    lotsCatalog.value = f.lots || []
    departmentsCatalog.value = f.departaments || []
  } catch (e) { lotsCatalog.value = []; departmentsCatalog.value = [] }
    ambulatoryCentersList.value = bulk.ambulatory_centers || []
    
    const data = {}
    workers.value.forEach(u => {
      // Find zones where this user is assigned
      const assignedZones = bulk.zone_assignments.filter(z => {
          const pivot = z.users.find(uz => uz.id === u.id)?.pivot
          return pivot && pivot.valid_to == null
        })
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

// ?obre=<id> (des del cercador de la capçalera): obre directament la fitxa d'aquella persona.
const route = useRoute()
async function obreDesDeRuta() {
  const id = Number(route.query.obre)
  if (!id) return
  const u = workers.value.find(w => w.id === id) || await db.getUser(id).catch(() => null)
  if (u) openEditModal(u)
}
onMounted(async () => { await fetchData(); obreDesDeRuta() })
watch(() => route.query.obre, obreDesDeRuta)

const csvPreview = ref([])
const importResults = ref([])

const form = reactive({ name: '', email: '', dni: '', device_phone: '', pacte_complementaries: false, work_type: 'AMBULATORIA', job_profile: 'Fisioterapeuta', postal_code_assigned: '', work_schedule_id: 1, seniority_date: '', specialties: [], lots: [], departments: [], domi_username: '', domi_provisioned_at: null, second_factor: 'dispositiu', totp_confirmed: false })
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

// ── Pestanyes de la fitxa ──
const pestanyaFitxa = ref('dades')
const pestanyesFitxa = computed(() => [
  { clau: 'dades', nom: '👤 Dades' },
  { clau: 'lloc', nom: '💼 Lloc i servei' },
  { clau: 'jornada', nom: '⏱ Jornada' },
  ...(editingUser.value ? [{ clau: 'acces', nom: '🔐 Accés' }] : []),
])

// ── Pràctiques ──
const detallBuit = () => ({ estudis: '', curs: '', ects: null, tutor_centre_nom: '', tutor_centre_email: '', tutor_empresa: '',
  num_conveni: '', remunerada: false, beca_mensual: null, alta_ss: '', observacions: '' })
const prac = reactive({ practiques: false, practiques_tipus: '', practiques_hores: null, practiques_inici: '', practiques_fi: '', practiques_centre: '', detall: detallBuit() })
const pracEstat = ref(null)
// El catàleg de modalitats ve del servidor: les regles de cada tipus no s'escriuen dues vegades.
const pracCataleg = ref({})
const pracAvisSs = ref('')
const pracHoresEcts = ref(25)
const tipusPrac = computed(() => pracCataleg.value[prac.practiques_tipus] || null)
const tePrac = (camp) => !!tipusPrac.value?.camps?.includes(camp)
function horesDesDeEcts() {
  const e = Number(prac.detall.ects)
  if (e > 0) prac.practiques_hores = Math.round(e * pracHoresEcts.value * 2) / 2
}
const desantPrac = ref(false)
const pracMsg = ref('')
const pracOk = ref(true)
async function carregaPractiques(id) {
  pracMsg.value = ''
  pracEstat.value = null
  Object.assign(prac, { practiques: false, practiques_tipus: '', practiques_hores: null, practiques_inici: '', practiques_fi: '', practiques_centre: '', detall: detallBuit() })
  try {
    const e = await api.get(`/v1/practiques/${id}`)
    pracEstat.value = e
    pracCataleg.value = e.cataleg || {}
    pracAvisSs.value = e.avis_ss || ''
    pracHoresEcts.value = e.hores_ects || 25
    Object.assign(prac, { practiques: !!e.practiques, practiques_tipus: e.tipus || '', practiques_hores: e.hores_conveni,
      practiques_inici: e.inici || '', practiques_fi: e.fi || '', practiques_centre: e.centre || '',
      detall: { ...detallBuit(), ...(e.detall || {}) } })
  } catch { /* sense dades, el bloc surt buit */ }
}
async function desaPractiques() {
  desantPrac.value = true
  pracMsg.value = ''
  try {
    pracEstat.value = await api.put(`/v1/practiques/${editingUser.value.id}`, {
      ...prac, practiques_fi: prac.practiques_fi || null, practiques_centre: prac.practiques_centre || null,
      practiques_tipus: prac.practiques_tipus || null,
      // Els buits no viatgen: el servidor només desa les dades que té la modalitat.
      detall: Object.fromEntries(Object.entries(prac.detall).filter(([, v]) => v !== '' && v !== null && v !== undefined)),
    })
    pracOk.value = true
    pracMsg.value = prac.practiques ? 'Desat.' : 'Pràctiques desactivades.'
  } catch (e) {
    pracOk.value = false
    pracMsg.value = e?.message || "No s'ha pogut desar."
  } finally {
    desantPrac.value = false
  }
}

async function openEditModal(user) {
  editingUser.value = user
  pestanyaFitxa.value = 'dades'
  if (user) carregaPractiques(user.id)
  if (user) {
    const sDate = user.seniority_date ? user.seniority_date.split('T')[0] : ''
    Object.assign(form, { name: user.name, email: user.email, dni: user.dni || '', device_phone: user.device_phone || '', pacte_complementaries: !!user.pacte_complementaries,  work_type: user.work_type || 'DOMICILIARIA', job_profile: user.job_profile || 'Fisioterapeuta', postal_code_assigned: user.postal_code_assigned, work_schedule_id: user.work_schedule_id, seniority_date: sDate, specialties: [], lots: [], departments: [], domi_username: '', domi_provisioned_at: null, second_factor: 'dispositiu', totp_confirmed: false })
    ratioActual.value = Number(user.complementary_ratio ?? 0.30)
    ratioPct.value = Math.round(ratioActual.value * 100)
    ratioPendent.value = user.complementary_ratio_pending != null ? Number(user.complementary_ratio_pending) : null
    ratioPendentDes.value = user.complementary_ratio_pending_from || null
    ratioMsg.value = ''
    carregaJornadaTope(user.id)
    showEditModal.value = true
    // Les especialitats vénen de la fitxa completa (show() les carrega).
    try {
        const full = await db.getUser(user.id)
        form.specialties = (full.specialties || []).map(s => s.code)
        form.second_factor = full.second_factor || 'dispositiu'
        form.totp_confirmed = !!full.totp_confirmed
        form.domi_username = full.domi_username || ''
        form.domi_provisioned_at = full.domi_provisioned_at || null
        form.lots = (full.lots || []).map(l => l.code)
        form.departments = (full.departments || []).map(d => d.code)
      } catch (e) { /* es queda buit */ }
  } else {
    Object.assign(form, { name: '', email: '', dni: '', device_phone: '', pacte_complementaries: false, work_type: 'AMBULATORIA', job_profile: 'Fisioterapeuta', postal_code_assigned: '', work_schedule_id: 1, seniority_date: new Date().toISOString().split('T')[0], specialties: [], lots: [], departments: [], domi_username: '', domi_provisioned_at: null, second_factor: 'dispositiu', totp_confirmed: false })
    showEditModal.value = true
  }
}

async function saveUser() {
  if (!form.name || !form.email) return alert('El nom i email són obligatoris.')
  isSaving.value = true
  try {
    if (editingUser.value) {
      await db.updateUser({ ...editingUser.value, ...form })
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

// ── Jornades partides: diversos trams (entrades) amb el mateix `day` ──
const DAY_NAMES = { 0: 'Diumenge', 1: 'Dilluns', 2: 'Dimarts', 3: 'Dimecres', 4: 'Dijous', 5: 'Divendres', 6: 'Dissabte' }
function dayName(d) { return DAY_NAMES[d] ?? '' }
// Una entrada és un tram "extra" si abans ja n'hi ha una del mateix dia
function isExtraTramo(index) {
  const d = scheduleForm.days[index]
  return scheduleForm.days.slice(0, index).some(x => x.day === d.day)
}
function addTramo(index) {
  const d = scheduleForm.days[index]
  scheduleForm.days.splice(index + 1, 0, { day: d.day, name: d.name || dayName(d.day), active: true, start: '', end: '' })
}
function removeTramo(index) { scheduleForm.days.splice(index, 1) }

// Aplicar un horari a diversos dies d'un cop (en lloc d'anar dia per dia).
// Nomes toca el PRIMER tram de cada dia seleccionat: si algu ja tenia jornada
// partida amb trams extra, aquests no es toquen.
const bulkStart = ref('')
const bulkEnd = ref('')
function applyBulkSchedule(dayNumbers) {
  if (!bulkStart.value || !bulkEnd.value) return
  for (const d of scheduleForm.days) {
    if (dayNumbers.includes(d.day) && !isExtraTramo(scheduleForm.days.indexOf(d))) {
      d.active = true
      d.start = bulkStart.value
      d.end = bulkEnd.value
    }
  }
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
// L'assignació activa ja ve carregada a extraData (bulk-index): abans es cridava l'API sense esperar
// la resposta i el modal rebia una promesa, de manera que «Assignació activa» no sortia mai.
const activeCpAssignment = computed(() => (cpUser.value ? extraData.value[cpUser.value.id]?.cpAssignment : null) || null)
const cercaCp = ref('')
const normCp = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const cpsFiltrats = computed(() => {
  const q = normCp(cercaCp.value.trim())
  return q ? postalCodes.filter(cp => cp.code.includes(q) || normCp(cp.zone).includes(q)) : postalCodes
})
// Quantes persones (sense comptar la que s'està editant) tenen cada CP assignat ara mateix.
const cobertura = computed(() => {
  const n = {}
  Object.entries(extraData.value || {}).forEach(([uid, d]) => {
    if (Number(uid) === cpUser.value?.id) return
    ;(d?.cpAssignment?.postal_codes || []).forEach(cp => { n[cp] = (n[cp] || 0) + 1 })
  })
  return n
})
const cpsPerAfegir = computed(() => cpsFiltrats.value.filter(cp => !newCpForm.postal_codes.includes(cp.code)).length)
function seleccionaTotsCp() {
  cpsFiltrats.value.forEach(cp => { if (!newCpForm.postal_codes.includes(cp.code)) newCpForm.postal_codes.push(cp.code) })
}
function toggleCp(cp) {
  const i = newCpForm.postal_codes.indexOf(cp)
  if (i >= 0) newCpForm.postal_codes.splice(i, 1)
  else newCpForm.postal_codes.push(cp)
}
const newCpForm = reactive({ postal_codes: [], valid_from: new Date().toISOString().split('T')[0], valid_to: '' })

async function openCpModal(user) {
  cpUser.value = user
  cercaCp.value = ''
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
    // if (user) await db.updateUser({ ...user, postal_code_assigned: newCpForm.postal_codes[0] })
    if (user) await db.updateUser({ id: user.id, postal_code_assigned: newCpForm.postal_codes[0], specialties: (user.specialties || []).map(s => s.code), departments: (user.departments || []).map(d => d.code), lots: (user.lots || []).map(l => l.code) })
    
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

<style scoped>
/* ── Fitxa del treballador ── */
.fitxa-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--color-border-light, #e5eaf0); margin-bottom: 16px; overflow-x: auto; }
.fitxa-tab { background: none; border: none; padding: 9px 14px; font: inherit; font-size: .86rem; font-weight: 700; color: var(--color-text-muted, #7a8aa0); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; white-space: nowrap; }
.fitxa-tab.actiu { color: var(--color-primary, #094E8C); border-bottom-color: var(--color-primary, #094E8C); }
.fitxa-graella { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 16px; }
.fitxa-bloc { border: 1px solid var(--color-border-light, #e5eaf0); border-radius: 10px; padding: 12px 14px; margin: 8px 0 14px; background: var(--color-bg, #f8fafc); }
.prac-progres { margin-top: 4px; }
.prac-avis { font-size: .8rem; background: rgba(9, 78, 140, .06); border-left: 3px solid var(--color-primary, #094E8C); border-radius: 6px; padding: 7px 10px; margin: 0 0 10px; color: var(--color-text-secondary, #475569); }
.prac-avis-alerta { background: #FFF8E1; border-left-color: #D9A400; color: #7a5a00; margin-top: 8px; }
.prac-barra { height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.prac-barra > div { height: 100%; background: linear-gradient(90deg, #0DAF83, #0A7C5E); border-radius: 999px; transition: width .3s; }
.prac-xifres { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 6px; font-size: .82rem; }
/* ── Codis postals compactes a la taula ── */
.cp-xip-mini { background: var(--color-bg); border: 1px solid var(--color-border-light); border-radius: 4px; padding: 2px 6px; font-size: .72rem; font-variant-numeric: tabular-nums; }
.cp-xip-mini.mes { background: rgba(9, 78, 140, .08); border-color: rgba(9, 78, 140, .25); color: var(--color-primary, #094E8C); font-weight: 700; cursor: help; }
.cp-xip-mini.tota { background: rgba(13, 175, 131, .1); border-color: rgba(13, 175, 131, .35); color: #0A7C5E; font-weight: 700; cursor: help; }
/* ── Modal d'assignació de codis postals ── */
.cp-actual { background: rgba(76, 175, 80, .07); border-radius: 10px; padding: 12px; border-left: 3px solid var(--color-success); margin-bottom: 16px; }
.cp-xips { margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap; }
.cp-xip { display: inline-flex; align-items: center; gap: 5px; font-size: .78rem; padding: 3px 10px; border-radius: 999px; background: #fff; border: 1px solid var(--color-border-light, #e5eaf0); }
.cp-xip.triat { background: rgba(9, 78, 140, .08); border-color: var(--color-primary, #094E8C); color: var(--color-primary, #094E8C); }
.cp-treu { background: none; border: none; cursor: pointer; color: inherit; font-size: .8rem; padding: 0 0 0 2px; opacity: .7; }
.cp-treu:hover { opacity: 1; }
.cp-triats { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; min-height: 30px; }
.cp-cerca-fila { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.cp-cerca-fila .form-input { flex: 1; min-width: 0; }
.cp-tots { white-space: nowrap; min-height: 42px; }
.cp-graella { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px; max-height: 260px; overflow-y: auto; border: 1px solid var(--color-border-light, #e5eaf0); border-radius: 10px; padding: 8px; margin-bottom: 14px; }
.cp-opcio { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; text-align: left; padding: 7px 9px; border-radius: 8px; border: 1px solid var(--color-border-light, #e5eaf0); background: var(--color-surface, #fff); cursor: pointer; min-height: 44px; }
.cp-opcio:hover { border-color: var(--color-primary, #094E8C); }
.cp-opcio.sel { background: rgba(9, 78, 140, .08); border-color: var(--color-primary, #094E8C); }
.cp-codi { font-weight: 800; font-size: .84rem; color: var(--color-text); }
.cp-opcio.sel .cp-codi { color: var(--color-primary, #094E8C); }
.cp-barri { font-size: .72rem; color: var(--color-text-muted, #7a8aa0); line-height: 1.2; }
.cp-qui { font-size: .68rem; color: var(--color-text-muted, #94a3b8); margin-top: 2px; }
</style>
