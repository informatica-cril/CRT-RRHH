<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Fitxatges</h1>
        <p class="page-subtitle">Les entrades i sortides de tothom. Els fitxatges correctes s'aproven sols; aquí revises la resta.</p>
      </div>
      <div class="page-actions">
        <select class="form-select" v-model="selectedMonth">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}{{ pendentsPerMes[m.value] ? ` · ${pendentsPerMes[m.value]} pendent${pendentsPerMes[m.value] > 1 ? 's' : ''}` : '' }}</option>
        </select>
        <SelectorPersona v-if="authStore.isStaff" v-model="selectedUser" :persones="workers" :buida="{ valor: 'all', text: `${t('total')} ${t('employees')}` }" style="width:240px;" />
        <button v-if="authStore.isStaff" type="button" class="btn btn-outline wl-excel" :disabled="exportant" @click="exportaExcel"
          title="Descarrega en Excel el que es veu: el mes, la persona i el filtre triats">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13l3 4M11 13l-3 4"/><line x1="14" y1="15" x2="17" y2="15"/></svg>
          {{ exportant ? 'Generant…' : 'Exportar Excel' }}
        </button>
      </div>
    </div>

    <!-- Vistes de revisió: un filtre alhora, amb el recompte del mes i la persona triats.
         Fora de zona es revisa amb la DISTÀNCIA a la zona, mai amb la posició (les coordenades
         no viatgen al llistat). -->
    <div v-if="authStore.isStaff" class="wl-filtres">
      <button v-for="f in filtres.filter(f => f.clau !== 'marge' || authStore.canManageStaff)" :key="f.clau" type="button" class="wl-filtre"
        :class="[f.to, { actiu: vista === f.clau, buit: !recomptes[f.clau] && f.clau !== 'tots' }]"
        @click="vista = f.clau">
        <span>{{ f.icona }}</span> {{ f.nom }}
        <span class="wl-n">{{ recomptes[f.clau] || 0 }}</span>
      </button>
    </div>

    <!-- Ajustos de revisió: un sol botó. Al panell hi ha tres seccions amb títol: aprovació, saldo de minuts i
         persones fora d'horari. Els formularis i la llista s'obren dins de cada secció només quan es demanen. -->
    <div v-if="authStore.isStaff && (confAuto || confSaldo?.disponible || (authStore.canManageStaff && avisosMarge.length))" class="wl-eines">
      <button type="button" class="btn btn-outline btn-sm" :class="{ 'wl-ajustos-oberts': obreAjustos }" @click="obreAjustos = !obreAjustos">
        ⚙️ Ajustos de revisió {{ obreAjustos ? '▴' : '▾' }}
        <span v-if="authStore.canManageStaff && avisosMarge.length" class="wl-bolet" :title="`${avisosMarge.length} persones fora d'horari`">{{ avisosMarge.length }}</span>
      </button>
      <button v-if="confAuto?.enabled && modeAprovar === 'auto' && pendentsTancats.length" type="button" class="btn btn-success btn-sm" :disabled="aprovantCorrectes" @click="aprovaCorrectes"
        title="Aprova els pendents d'aquest mes que tenen l'horari, la zona i les hores bé. La resta es queden per revisar.">
        {{ aprovantCorrectes ? 'Revisant…' : `✅ Aprovar els correctes (${pendentsTancats.length})` }}
      </button>
    </div>

    <div v-if="obreAjustos" class="wl-panell">
      <div v-if="confAuto" class="wl-ajust-fila">
        <strong class="wl-ajust-titol">Aprovació</strong>
        <span>
          <template v-if="!confAuto.enabled">Apagada: tots els fitxatges s'han d'aprovar a mà.</template>
          <template v-else-if="modeAprovar === 'auto'">Automàtica (± {{ confAuto.marge_minuts }} min): s'aproven sols els que quadren amb l'horari, són dins de zona i no tenen hores extra ni incidències.</template>
          <template v-else>Manual: marques tu els fitxatges i s'aproven tal com estan.</template>
          <span v-if="confAuto.enabled" class="wl-mode" role="group" aria-label="Com vols aprovar" style="margin-left:8px;">
            <button type="button" :class="{ actiu: modeAprovar === 'auto' }" @click="modeAprovar = 'auto'">🤖 Automàtica</button>
            <button type="button" :class="{ actiu: modeAprovar === 'manual' }" @click="modeAprovar = 'manual'">✋ Manual</button>
          </span>
        </span>
        <button v-if="potConfigurarAuto && !editantAuto" type="button" class="btn btn-outline btn-sm" @click="configuraAuto">Canviar</button>
      </div>
      <div v-if="editantAuto" class="wl-auto-form wl-form-dins">
        <label><input type="checkbox" v-model="editantAuto.enabled" /> Aprovar sols els fitxatges correctes</label>
        <label>Marge sobre l'horari:
          <input class="form-input" type="number" min="0" max="60" v-model="editantAuto.marge_minuts" style="width:70px;" :disabled="!editantAuto.enabled" /> min
        </label>
        <button type="button" class="btn btn-primary btn-sm" @click="desaAuto">Desa</button>
        <button type="button" class="btn btn-outline btn-sm" @click="editantAuto = null">Cancel·la</button>
      </div>

      <div v-if="confSaldo?.disponible" class="wl-ajust-fila">
        <strong class="wl-ajust-titol">Saldo de minuts</strong>
        <span>
          <template v-if="confSaldo.enabled">Activat. Els minuts fitxats fins a {{ confSaldo.finestra_minuts }} min abans de l'entrada o després de la sortida van a un saldo per persona
            (màxim ±{{ confSaldo.tope_minuts }} min), des del {{ dataCurta(confSaldo.actiu_des) }}. El fitxatge real no es toca.</template>
          <template v-else>Desactivat: els minuts sueltos continuen com fins ara.</template>
        </span>
        <span class="wl-ajust-botons">
          <button v-if="authStore.canManageStaff && confSaldo.enabled" type="button" class="btn btn-outline btn-sm" @click="obreSaldos">Veure els saldos</button>
          <button v-if="potConfigurarAuto && !editantSaldo" type="button" class="btn btn-outline btn-sm" @click="configuraSaldo">Canviar</button>
        </span>
      </div>
      <div v-if="editantSaldo" class="wl-auto-form wl-form-dins">
        <label><input type="checkbox" v-model="editantSaldo.enabled" /> Activar el saldo de minuts</label>
        <label>Finestra:
          <input class="form-input" type="number" min="1" max="15" v-model="editantSaldo.finestra_minuts" style="width:70px;" /> min
        </label>
        <label>Tope del saldo: ±
          <input class="form-input" type="number" min="1" max="60" v-model="editantSaldo.tope_minuts" style="width:70px;" /> min
        </label>
        <label>Compta des del:
          <input class="form-input" type="date" v-model="editantSaldo.actiu_des" />
        </label>
        <button type="button" class="btn btn-primary btn-sm" @click="desaSaldo">Desa</button>
        <button type="button" class="btn btn-outline btn-sm" @click="editantSaldo = null">Cancel·la</button>
      </div>

      <div v-if="authStore.canManageStaff && avisosMarge.length" class="wl-ajust-fila">
        <strong class="wl-ajust-titol">Fora d'horari</strong>
        <span>{{ avisosMarge.length }} {{ avisosMarge.length === 1 ? 'persona porta' : 'persones porten' }} 3 fitxatges o més seguits fora de l'horari (de més o de menys). Només informa: no bloqueja cap aprovació.</span>
        <button type="button" class="btn btn-outline btn-sm" @click="obreMarge = !obreMarge">{{ obreMarge ? 'Amaga la llista' : 'Veure la llista' }}</button>
      </div>
      <ul v-if="obreMarge && authStore.canManageStaff && avisosMarge.length" class="wl-marge-llista">
        <li v-for="a in avisosMarge" :key="a.user_id">
          <strong>{{ a.nom }}</strong>: {{ a.dies }} dies seguits des del {{ dataCurta(a.inici) }}
          ({{ resumDesviacio(a) }})
          <button type="button" class="btn btn-outline btn-sm" @click="selectedUser = a.user_id; vista = 'marge'">Veure</button>
          <button type="button" class="btn btn-outline btn-sm" @click="descartaAvisMarge(a)">Descartar</button>
        </li>
      </ul>
    </div>

    <!-- Pendents d'altres mesos: el comptador del menú els suma tots i aquí només es veu el mes triat -->
    <div v-if="authStore.isStaff && altresMesosPendents.length" class="wl-altres-mesos">
      <span>⏳ Hi ha fitxatges pendents en altres mesos:</span>
      <button v-for="m in altresMesosPendents" :key="m.mes" type="button" class="wl-mes-xip" @click="anarAPendents(m.mes)">
        {{ m.label }} <strong>{{ m.n }}</strong>
      </button>
    </div>

    <!-- Accions conjuntes: apareixen quan hi ha fitxatges seleccionats -->
    <div v-if="ambCaselles && seleccionats.length" class="wl-seleccio">
      <strong>{{ seleccionats.length }}</strong> fitxatge{{ seleccionats.length === 1 ? '' : 's' }} seleccionat{{ seleccionats.length === 1 ? '' : 's' }}
      <template v-if="selPendents.length">
        <button type="button" class="btn btn-success btn-sm" :disabled="aprovant" @click="aprovaSeleccionats"
          title="S'aproven tal com estan, encara que tinguin coses per revisar">
          {{ aprovant ? 'Aprovant…' : `✅ Aprovar (${selPendents.length})` }}
        </button>
        <button type="button" class="btn btn-danger btn-sm" :disabled="aprovant" @click="rebutjaSeleccionats">❌ Rebutjar ({{ selPendents.length }})</button>
      </template>
      <button v-if="selResolts.length && authStore.canManageStaff" type="button" class="btn btn-outline btn-sm" :disabled="aprovant" @click="reobreSeleccionats"
        title="Tornen a «Pendent» per revisar-los una altra vegada">↺ Tornar a revisar ({{ selResolts.length }})</button>
      <button type="button" class="btn btn-outline btn-sm" @click="seleccionats = []">Treure la selecció</button>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-if="ambCaselles" class="wl-check">
                <input type="checkbox" :checked="totsSeleccionats" :indeterminate.prop="seleccionats.length > 0 && !totsSeleccionats"
                  :disabled="!pendentsVisibles.length" title="Seleccionar tots els fitxatges visibles" @change="seleccionaTots($event.target.checked)" />
              </th>
              <th v-for="c in columnes" :key="c.clau" v-show="c.clau !== 'treballador' || authStore.isStaff"
                class="th-ord" :class="{ actiu: ordre.col === c.clau }" :aria-sort="ordre.col === c.clau ? (ordre.dir === 'asc' ? 'ascending' : 'descending') : 'none'"
                @click="ordena(c.clau)" :title="'Ordenar per ' + c.text.toLowerCase()">
                {{ c.text }}
                <span class="ord-fletxa">{{ ordre.col === c.clau ? (ordre.dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
              </th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in filteredLogs" :key="log.id" :class="{ 'wl-fila-sel': seleccionats.includes(log.id) }">
              <td v-if="ambCaselles" class="wl-check">
                <input v-if="seleccionable(log)" type="checkbox" :value="log.id" v-model="seleccionats" title="Seleccionar" />
              </td>
              <td v-if="authStore.isStaff">{{ getUserName(log.user_id) }}</td>
              <td>{{ formatDate(log.date) }}</td>
              <td>{{ formatTime(log.start_time) }}</td>
              <td>{{ log.end_time ? formatTime(log.end_time) : '—' }}</td>
              <td>
                <strong v-if="log.end_time" :title="'Hores brutes: ' + formatHM(log.total_hours_worked)">
                  {{ formatHM(log.effective_hours ?? log.total_hours_worked) }}
                </strong>
                <span v-else>—</span>
                <div v-if="Number(log.extra_hours_authorized || 0) > 0" class="wl-nota">inclou +{{ formatHM(log.extra_hours_authorized) }} d'extra aprovades</div>
                <div v-if="log.saldo_minuts" class="wl-nota" title="Moviment del saldo de minuts d'aquest dia">
                  ⏱ saldo {{ fmtMin(log.saldo_minuts.delta_aplicat) }} → {{ fmtMin(log.saldo_minuts.saldo_despres) }}
                  <span v-if="log.saldo_minuts.excedent"> ({{ Math.abs(log.saldo_minuts.excedent) }} min fora del tope)</span>
                </div>
                <div v-if="sortidaAltreDia(log)" class="sortida-altre-dia" :title="'La jornada es va iniciar el ' + String(log.date).slice(0, 10).split('-').reverse().join('/') + ' i la sortida no es va fitxar fins al ' + sortidaAltreDia(log).data">
                    ⚠ Sortida fitxada el {{ sortidaAltreDia(log).data }}<span v-if="sortidaAltreDia(log).dies > 1"> (+{{ sortidaAltreDia(log).dies }} dies)</span>
                  </div>
              </td>
              <td>
                <!-- Una sola llista del que cal mirar, en frases curtes; si no hi ha res, es diu clarament. -->
                <ul v-if="avisos(log).length" class="wl-avisos">
                  <li v-for="a in avisos(log)" :key="a.text" :class="'wl-avis-' + a.to" :title="a.ajuda">{{ a.text }}</li>
                </ul>
                <!-- Hores de més sense autoritzar (p. ex. ha cobert un torn): RRHH i admin les accepten aquí mateix. -->
                <button v-if="authStore.canManageStaff && log.end_time && Number(log.extra_hours_unauthorized || 0) > 0"
                  type="button" class="btn btn-outline btn-sm wl-accepta" :disabled="acceptant === log.id" @click="acceptaHoresDeMes(log)"
                  title="Les compta com a hores de més autoritzades (extra o complementàries segons la jornada)">
                  ✔ Acceptar {{ formatHM(log.extra_hours_unauthorized) }} de més
                </button>
                <span v-else-if="log.status === 'pending' && log.end_time && log.motiu_no_auto" class="wl-avis-groc wl-motiu" title="Per això no s'ha aprovat sol">• {{ etiqueta('noAuto', log.motiu_no_auto) }}</span>
                <span v-else-if="log.status === 'pending' && log.end_time && 'motiu_no_auto' in log" class="wl-ok" title="Compleix les condicions: s'aprova amb «Aprovar els correctes»">✓ Tot quadra</span>
                <span v-else-if="log.status === 'pending' && log.end_time" class="text-muted">—</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-direction:column;align-items:flex-start;">
                  <span class="badge" :class="statusBadge(log.status)" style="display:flex; align-items:center; gap:4px;">
                    <span v-if="log.status === 'pending'">⏳</span>
                    <span v-else-if="log.status === 'approved'">✅</span>
                    <span v-else-if="log.status === 'rejected'">❌</span>
                    {{ t(log.status) }}
                  </span>
                  <span v-if="log.fitxatge_automatic" class="wl-auto"
                        title="Fitxatge automàtic (gerència): l'ha creat l'app amb l'horari del dia">🤖 Fitxatge automàtic</span>
                  <span v-else-if="log.status === 'approved' && log.auto_approved_at" class="wl-auto"
                        title="L'app l'ha aprovat sola: horari, zona i hores correctes">🤖 Automàtic</span>
                  <span v-if="log.hour_status === 'in_progress'"
                        class="badge"
                        style="font-size:0.75rem; display:flex; align-items:center; gap:4px;"
                        :class="log.hour_status === 'out_of_area' ? 'badge-danger' : 'badge-warning'">
                    <span v-if="log.hour_status === 'out_of_area'">📍</span>
                    <span v-else-if="log.hour_status === 'in_progress'">🕒</span>
                    {{ t(log.hour_status) }}
                  </span>
                  <span v-if="distanciaTxt(log)" class="fz-dist" :title="'Distància a la zona assignada (entrada / sortida). No es mostra cap posició.'">
                    {{ distanciaTxt(log) }}
                  </span>
                </div>
              </td>
              <td>
                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                  <button class="btn btn-outline btn-sm" @click="viewDetail(log.id)" title="Veure com es divideix el fitxatge i el seu historial">🔍 Detall</button>
                  <template v-if="authStore.isStaff && log.status === 'pending'">
                    <button class="btn btn-success btn-sm" @click="approve(log.id)">{{ t('approve') }}</button>
                    <button class="btn btn-danger btn-sm" @click="openReject(log.id)">{{ t('reject') }}</button>
                  </template>
                  <!-- RRHH i admin poden tornar a revisar el que ja s'ha resolt (p. ex. coordinació). -->
                  <button v-if="authStore.canManageStaff && log.status !== 'pending'" class="btn btn-outline btn-sm" @click="reobrir(log)" title="Torna el fitxatge a «Pendent» per revisar-lo una altra vegada">↺ Tornar a revisar</button>
                  <template v-if="!authStore.isStaff && log.status === 'rejected'">
                    <button class="btn btn-outline btn-sm" @click="openModify(log)">{{ t('modify_record') }}</button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td :colspan="(authStore.isStaff ? 8 : 7) + (ambCaselles ? 1 : 0)" class="text-center text-muted" style="padding: 32px;">
                No hi ha registres per aquest període
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rdl-notice">{{ t('rdl_retention') }}</div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay" @click.self="showRejectModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ t('reject') }}</h3>
          <button class="icon-btn" @click="showRejectModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('rejection_reason') }}</label>
          <FrasesRapides clau="rebuig_fitxatge" v-model="rejectReason" />
          <textarea class="form-textarea" v-model="rejectReason" required></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRejectModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-danger" @click="confirmReject">{{ t('reject') }}</button>
        </div>
      </div>
    </div>

    <!-- Saldos de minuts: llista per persona i la decisió de RRHH/admin (compensar, ajustar o tancar a zero). -->
    <div v-if="saldos" class="modal-overlay" @click.self="tancaSaldos">
      <div class="modal" style="max-width:680px;">
        <div class="modal-header">
          <h3 class="modal-title">⏱ {{ detallSaldo ? detallSaldo.nom : 'Saldos de minuts' }}</h3>
          <button class="icon-btn" @click="tancaSaldos">✕</button>
        </div>

        <template v-if="!detallSaldo">
          <p class="text-small text-muted">El saldo no caduca sol: a final de mes RRHH o admin decideix què se'n fa (compensar-lo en temps, ajustar-lo o tancar-lo a zero).</p>
          <label class="text-small" style="display:flex;align-items:center;gap:6px;margin:8px 0;">Mostra només els que passen de
            <input class="form-input" type="number" min="0" max="60" v-model.number="minSaldo" style="width:70px;" @change="obreSaldos" /> min (en positiu o negatiu)
          </label>
          <div v-if="!saldos.persones.length" class="text-muted" style="padding:16px 0;">Ningú té saldo amb aquest filtre.</div>
          <div v-else class="table-container">
            <table>
              <thead><tr><th>Persona</th><th>Saldo</th><th>Últim dia</th><th></th></tr></thead>
              <tbody>
                <tr v-for="p in saldos.persones" :key="p.user_id">
                  <td>{{ p.nom }}</td>
                  <td><strong :style="{ color: p.saldo < 0 ? 'var(--color-danger)' : 'var(--color-success)' }">{{ fmtMin(p.saldo) }}</strong>
                    <span v-if="Math.abs(p.saldo) >= saldos.tope" class="text-small text-muted"> (al tope)</span></td>
                  <td class="text-small">{{ dataCurta(p.ultim_dia) }}</td>
                  <td><button class="btn btn-outline btn-sm" @click="veuSaldo(p)">Veure i decidir</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <template v-else>
          <button type="button" class="btn btn-outline btn-sm" @click="detallSaldo = null">← Tornar a la llista</button>
          <p style="margin:10px 0 4px;">Saldo actual:
            <strong :style="{ color: detallSaldo.saldo < 0 ? 'var(--color-danger)' : 'var(--color-success)' }">{{ fmtMin(detallSaldo.saldo) }}</strong>
            <span class="text-small text-muted"> (tope ±{{ detallSaldo.tope }} min)</span></p>
          <div class="table-container" style="max-height:240px;overflow:auto;">
            <table>
              <thead><tr><th>Dia</th><th>Entrada</th><th>Sortida</th><th>Entra al saldo</th><th>Saldo</th></tr></thead>
              <tbody>
                <tr v-for="a in detallSaldo.apunts" :key="a.date + '-' + a.saldo_despres + '-' + (a.work_log_id || 'a')">
                  <td class="text-small">{{ dataCurta(a.date) }}</td>
                  <template v-if="a.tipus === 'ajust'"><td colspan="2" class="text-small">{{ a.nota }}</td></template>
                  <template v-else><td class="text-small">{{ fmtMin(a.entrada_delta) }}</td><td class="text-small">{{ fmtMin(a.sortida_delta) }}</td></template>
                  <td class="text-small">{{ fmtMin(a.delta_aplicat) }}<span v-if="a.excedent" class="text-muted"> ({{ Math.abs(a.excedent) }} fora del tope)</span></td>
                  <td><strong>{{ fmtMin(a.saldo_despres) }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="authStore.canManageStaff" class="wl-auto-form" style="margin-top:12px;">
            <label style="flex-basis:100%;">Decisió:
              <select class="form-select" v-model="formAjust.decisio">
                <option value="compensat">Compensat en temps (el saldo queda a zero)</option>
                <option value="zero">Tancar a zero sense compensar</option>
                <option value="ajustat">Ajustar un nombre de minuts</option>
              </select>
            </label>
            <label v-if="formAjust.decisio === 'ajustat'">Minuts (+ o −):
              <input class="form-input" type="number" min="-60" max="60" v-model.number="formAjust.minuts" style="width:80px;" />
            </label>
            <label style="flex-basis:100%;">Motiu (obligatori, queda escrit):
              <input class="form-input" type="text" maxlength="400" v-model="formAjust.nota" style="width:100%;" placeholder="Ex.: compensats el divendres 30/10 sortint 12 min abans" />
            </label>
            <button type="button" class="btn btn-primary btn-sm" :disabled="enviantAjust || formAjust.nota.trim().length < 5 || (formAjust.decisio !== 'ajustat' && detallSaldo.saldo === 0)" @click="enviaAjust">
              {{ enviantAjust ? 'Desant…' : 'Desar la decisió' }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import SelectorPersona from '../components/SelectorPersona.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import api, { apiFetchRaw } from '../services/apiClient'
import { i18n } from '../i18n'
import { formatHM } from '../utils/formatHours'
import { etiqueta } from '../utils/etiquetes'
import { avisa } from '../utils/avisos'
import { sortidaAltreDia } from '../utils/sortidaAltreDia'
import { useEstatUrl, idOText } from '../composables/useEstatUrl'

const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const router = useRouter()
const t = (key) => i18n.t(key)

function viewDetail(logId) {
  router.push({ name: 'work-log-detail', params: { id: logId } })
}

const now = new Date()
const selectedMonth = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const selectedUser = ref('all')
useEstatUrl('mes', selectedMonth)
useEstatUrl('persona', selectedUser, idOText)
const showRejectModal = ref(false)
const rejectReason = ref('')
const rejectLogId = ref(null)

const workers = ref([])
const users = ref([])

async function fetchData() {
  try {
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
    workLogStore.mes = selectedMonth.value
    await workLogStore.loadLogs()
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

// Pendents per mes (tots els mesos): per saber on són sense haver d'anar obrint mes a mes.
const pendentsPerMes = ref({})
async function carregaPendents() {
  if (!authStore.isStaff) return
  try {
    const r = await api.get('/v1/work-logs/pendents-per-mes')
    pendentsPerMes.value = Object.fromEntries((Array.isArray(r) ? r : []).map(x => [x.mes, Number(x.n) || 0]))
  } catch (e) { pendentsPerMes.value = {} }
}
onMounted(carregaPendents)
// Després d'aprovar o rebutjar, la llista es torna a carregar: els recomptes també.
watch(() => workLogStore.logs, carregaPendents)
const nomMes = (mes) => {
  const [a, m] = String(mes).split('-').map(Number)
  return new Date(a, m - 1, 1).toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { month: 'long', year: 'numeric' })
}
const altresMesosPendents = computed(() => Object.entries(pendentsPerMes.value)
  .filter(([mes, n]) => n > 0 && mes !== selectedMonth.value)
  .sort((a, b) => b[0].localeCompare(a[0]))
  .map(([mes, n]) => { const l = nomMes(mes); return { mes, n, label: l.charAt(0).toUpperCase() + l.slice(1) } }))
function anarAPendents(mes) {
  selectedMonth.value = mes
  vista.value = 'perrevisar'
}
// Cada mes es demana sencer a l'API (abans es filtrava sobre els 500 últims i els mesos vells sortien buits).
watch(selectedMonth, (m) => { workLogStore.mes = m; workLogStore.loadLogs() })
onUnmounted(() => { workLogStore.mes = null })

const months = computed(() => {
  const result = []
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    result.push({
      value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { month: 'long', year: 'numeric' })
    })
  }
  // Mesos més antics amb pendents (o el que ve a la URL): també s'han de poder triar.
  for (const mes of [...Object.keys(pendentsPerMes.value), selectedMonth.value]) {
    if (/^\d{4}-\d{2}$/.test(mes) && !result.some(r => r.value === mes)) result.push({ value: mes, label: nomMes(mes) })
  }
  return result.sort((a, b) => b.value.localeCompare(a.value))
})


// Fora de zona = alguna verificació d'ubicació fallida o hores descomptades per zona.
// Es treballa amb el booleà i la DISTÀNCIA que ja viatgen al llistat; les coordenades
// no hi són mai (minimització, EIPD §6.3) — igual que a la revisió de marques de domi.
const diaDe = l => String(l.date).slice(0, 10)
// Dia d'avui al rellotge del navegador (AAAA-MM-DD), com el text de work_logs.date.
function avui() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function esForaZona(l) {
  return l.hour_status === 'out_of_area'
    || l.location_match === false
    || l.start_location_match === false
    || l.end_location_match === false
    || Number(l.hours_out_of_area || 0) > 0
}

function distanciaTxt(l) {
  if (!esForaZona(l)) return ''
  const d = Math.max(Number(l.start_location_distance || 0), Number(l.end_location_distance || 0))
  if (!d) return ''
  return d >= 1000 ? (d / 1000).toFixed(1).replace('.', ',') + ' km de la zona' : Math.round(d) + ' m de la zona'
}

// Hores fetes fora de l'horari amb el tram encara pendent (no sumen a l'efectiu).
function horesForaHorari(l) {
  return Number(l.minuts_fora_horari_pendents || 0) / 60
}

// El que gestió ha de mirar d'un fitxatge, en llenguatge clar.
function avisos(l) {
  const a = []
  if (Number(l.extra_hours_unauthorized || 0) > 0) {
    a.push({ text: `${formatHM(l.extra_hours_unauthorized)} extra sense aprovar`, to: 'groc', ajuda: "Hores per sobre de la jornada que encara no s'han autoritzat" })
  }
  if (horesForaHorari(l) > 0) {
    a.push({ text: `${formatHM(horesForaHorari(l))} fora d'horari`, to: 'groc', ajuda: "Temps fet fora de l'horari del servei: no compta fins que s'aprova al detall" })
  }
  if (Number(l.hours_out_of_area || 0) > 0) {
    a.push({ text: `${formatHM(l.hours_out_of_area)} fora de zona (no compten)`, to: 'vermell', ajuda: "Temps fitxat lluny de la zona assignada; es descompta" })
  }
  return a
}

// Acceptar les hores de més d'un fitxatge: s'aplica qualsevol autorització d'aquell dia que ja hi hagués
// i el que falti s'autoritza ara (mateixes regles i topalls que una petició).
const acceptant = ref(null)
async function acceptaHoresDeMes(log) {
  const nom = getUserName(log.user_id)
  const motiu = await demana(`Acceptar ${formatHM(log.extra_hours_unauthorized)} de més de ${nom} el ${formatDate(log.date)}?
Motiu (opcional): p. ex. ha cobert el torn d'una companya.`, { obligatori: false })
  if (motiu === null) return
  acceptant.value = log.id
  try {
    const r = await api.post(`/v1/work-logs/${log.id}/accepta-hores-de-mes`, { motiu: motiu.trim() || null })
    avisa(`✅ ${formatHM(r.aplicades || 0)} de més acceptades${r.pendents > 0 ? ` (en queden ${formatHM(r.pendents)} sense autoritzar)` : ''}.`)
    await workLogStore.loadLogs()
  } catch (e) {
    avisa(e?.message || "No s'han pogut acceptar.", 'error')
  } finally {
    acceptant.value = null
  }
}

// Més de 14 h: gairebé sempre una sortida que no es va fitxar a temps (molts venen de l'app antiga).
function esLlarg(l) {
  return l.start_time && l.end_time && (new Date(l.end_time) - new Date(l.start_time)) > 14 * 3600 * 1000
}

// ── Avís: persones que porten 3 fitxatges seguits fora d'horari, de més o de menys (només RRHH i admin) ──
function resumDesviacio(a) {
  return [[a.entrades_tard, 'entrades tard'], [a.sortides_abans, 'sortides abans'],
    [a.entrades_aviat, "entrades abans d'hora"], [a.sortides_tard, 'sortides més tard']]
    .filter(([n]) => n > 0).map(([n, t]) => `${n} ${t}`).join(', ')
}
const avisosMarge = ref([])
async function carregaAvisosMarge() {
  if (!authStore.canManageStaff) return
  try { avisosMarge.value = await api.get('/v1/avisos-marge') } catch { avisosMarge.value = [] }
}
onMounted(carregaAvisosMarge)
const idsMarge = computed(() => new Set(avisosMarge.value.flatMap(a => a.log_ids)))
async function descartaAvisMarge(a) {
  if (!await confirma(`Descartar l'avís de ${a.nom}? Només tornarà a sortir si comença una ratxa nova.`)) return
  try {
    await api.post(`/v1/avisos-marge/${a.user_id}/descarta`, { inici: a.inici })
    await carregaAvisosMarge()
  } catch (e) {
    alert(e?.message || "No s'ha pogut descartar.")
  }
}
const dataCurta = d => String(d).slice(0, 10).split('-').reverse().slice(0, 2).join('/')

// Gestió entra directament al que cal revisar: pendents ja tancats (els «En curs» encara no es poden aprovar).
const vista = ref(authStore.isStaff ? 'perrevisar' : 'tots')
useEstatUrl('vista', vista)
const filtres = [
  { clau: 'tots', nom: 'Tots', icona: '📋', to: 'neutre', fn: () => true },
  { clau: 'encurs', nom: 'Treballant ara', icona: '🟢', to: 'neutre', fn: l => !l.end_time && diaDe(l) === avui() },  // Qui és treballant ara (va entrar avui) i qui es va deixar la sortida un altre dia, per separat.
  { clau: 'perrevisar', nom: 'Per revisar', icona: '👀', to: 'groc', fn: l => l.status === 'pending' && !!l.end_time },
  { clau: 'marge', nom: "Fora d'horari seguit", icona: '⏰', to: 'taronja', fn: l => idsMarge.value.has(l.id) },
  { clau: 'forazona', nom: 'Fora de zona', icona: '📍', to: 'vermell', fn: esForaZona },
  { clau: 'forahorari', nom: "Fora d'horari per revisar", icona: '🕘', to: 'taronja', fn: l => horesForaHorari(l) > 0 },
  { clau: 'extra', nom: 'Hores extra sense aprovar', icona: '⏱', to: 'taronja', fn: l => Number(l.extra_hours_unauthorized || 0) > 0 },
  { clau: 'sensesortida', nom: "Sense sortida d'altres dies", icona: '🕒', to: 'taronja', fn: l => !l.end_time && diaDe(l) < avui() },
  { clau: 'llargs', nom: 'Més de 14 h', icona: '⚠️', to: 'vermell', fn: esLlarg },
  { clau: 'aprovats', nom: 'Aprovats', icona: '✅', to: 'verd', fn: l => l.status === 'approved' },
  { clau: 'rebutjats', nom: 'Rebutjats', icona: '❌', to: 'neutre', fn: l => l.status === 'rejected' },
]

// Mes i persona: la base sobre la qual compten i filtren les vistes.
const logsDelMes = computed(() => {
  let logs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === selectedMonth.value)
  if (authStore.isStaff && selectedUser.value !== 'all') {
    logs = logs.filter(l => l.user_id == selectedUser.value)
  }
  if (authStore.isWorker) {
    logs = logs.filter(l => l.user_id === authStore.userId)
  }
  return logs
})

// ── Aprovació conjunta ──
// Mode triat pel gestor (es recorda en aquest navegador): «auto» = «Aprovar els correctes»;
// «manual» = caselles i «Aprovar seleccionats». Sense configuració d'aprovació, sempre manual.
const CLAU_MODE = 'wl-mode-aprovar'
const modeAprovar = ref((() => { try { return localStorage.getItem(CLAU_MODE) === 'manual' ? 'manual' : 'auto' } catch { return 'auto' } })())
watch(modeAprovar, m => {
  seleccionats.value = []
  try { localStorage.setItem(CLAU_MODE, m) } catch { /* sense emmagatzematge: només aquesta visita */ }
})
// Les caselles hi són sempre: amb la selecció es pot aprovar, rebutjar o tornar a revisar.
const ambCaselles = computed(() => authStore.isStaff)
const seleccionats = ref([])
const aprovant = ref(false)
// Pendents: aprovar o rebutjar. Resolts: tornar a revisar (només RRHH i admin, com el botó de cada fila).
const seleccionable = (l) => l.status === 'pending' || authStore.canManageStaff
const pendentsVisibles = computed(() => filteredLogs.value.filter(seleccionable).map(l => l.id))
const estatDe = (id) => workLogStore.logs.find(l => l.id === id)?.status
const selPendents = computed(() => seleccionats.value.filter(id => estatDe(id) === 'pending'))
const selResolts = computed(() => seleccionats.value.filter(id => estatDe(id) && estatDe(id) !== 'pending'))
const totsSeleccionats = computed(() => pendentsVisibles.value.length > 0 && pendentsVisibles.value.every(id => seleccionats.value.includes(id)))
function seleccionaTots(marcat) {
  seleccionats.value = marcat ? [...pendentsVisibles.value] : []
}
// Canviar de mes, persona o vista buida la selecció: no s'han d'aprovar fitxatges que ja no es veuen.
watch([selectedMonth, selectedUser, vista], () => { seleccionats.value = [] })
async function aprovaSeleccionats() {
  const n = selPendents.value.length
  if (!await confirma(`Aprovar ${n} fitxatge${n === 1 ? '' : 's'}?`)) return
  aprovant.value = true
  try {
    const r = await api.post('/v1/work-logs/aprova-seleccionats', { ids: selPendents.value })
    seleccionats.value = []
    await workLogStore.loadLogs()
    if (r?.omesos) alert(`${r.aprovats} aprovats. ${r.omesos} ja no eren pendents i no s'han tocat.`)
  } catch (e) {
    alert(e?.message || "No s'han pogut aprovar.")
  } finally {
    aprovant.value = false
  }
}

async function rebutjaSeleccionats() {
  const n = selPendents.value.length
  const motiu = await demana(`Per què es rebutgen ${n} fitxatge${n === 1 ? '' : 's'}? (mínim 5 caràcters)
El motiu el veurà cada persona.`, { frases: 'rebuig_fitxatge' })
  if (motiu === null) return
  if (motiu.trim().length < 5) return alert("Cal un motiu d'almenys 5 caràcters.")
  aprovant.value = true
  try {
    const r = await api.post('/v1/work-logs/rebutja-seleccionats', { ids: selPendents.value, motiu: motiu.trim() })
    seleccionats.value = []
    await workLogStore.loadLogs()
    if (r?.omesos) alert(`${r.rebutjats} rebutjats. ${r.omesos} ja no eren pendents i no s'han tocat.`)
  } catch (e) {
    alert(e?.message || "No s'han pogut rebutjar.")
  } finally {
    aprovant.value = false
  }
}
async function reobreSeleccionats() {
  const n = selResolts.value.length
  const motiu = await demana(`Per què cal revisar de nou ${n} fitxatge${n === 1 ? '' : 's'}? (mínim 10 caràcters)
Quedarà registrat a la traçabilitat.`, { frases: 'revisar_de_nou' })
  if (motiu === null) return
  if (motiu.trim().length < 10) return alert("Cal un motiu d'almenys 10 caràcters.")
  aprovant.value = true
  try {
    await api.post('/v1/work-logs/reobre-seleccionats', { ids: selResolts.value, motiu: motiu.trim() })
    seleccionats.value = []
    await workLogStore.loadLogs()
  } catch (e) {
    alert(e?.message || "No s'han pogut tornar a revisar.")
  } finally {
    aprovant.value = false
  }
}

// ── «Aprovar tots els correctes» (la regla és la de l'aprovació automàtica, al servidor) ──
const pendentsTancats = computed(() => logsDelMes.value.filter(l => l.status === 'pending' && l.end_time).map(l => l.id))
const aprovantCorrectes = ref(false)
const confAuto = ref(null)
const potConfigurarAuto = computed(() => authStore.canManageStaff)
onMounted(async () => {
  if (!authStore.isStaff) return
  try { confAuto.value = await api.get('/v1/aprovacio-automatica') } catch (e) { /* sense la ruta (servidor antic): no es mostra */ }
})
const editantAuto = ref(null)
function configuraAuto() {
  editantAuto.value = { enabled: confAuto.value.enabled, marge_minuts: confAuto.value.marge_minuts }
}
async function desaAuto() {
  try {
    confAuto.value = await api.put('/v1/aprovacio-automatica', { enabled: !!editantAuto.value.enabled, marge_minuts: Number(editantAuto.value.marge_minuts) || 0 })
    editantAuto.value = null
  } catch (e) {
    alert(e?.message || "No s'ha pogut desar.")
  }
}
// ── Saldo de minuts (RRHH i admin): configuració, saldos per persona i decisió ──
const confSaldo = ref(null)
const editantSaldo = ref(null)
const fmtMin = n => `${n > 0 ? '+' : ''}${n} min`
onMounted(async () => {
  if (!authStore.isStaff) return
  try { confSaldo.value = await api.get('/v1/saldo-minuts/config') } catch (e) { /* servidor sense saldo: no es mostra */ }
})
function configuraSaldo() {
  const c = confSaldo.value
  editantSaldo.value = { enabled: c.enabled, finestra_minuts: c.finestra_minuts, tope_minuts: c.tope_minuts, actiu_des: c.actiu_des ? String(c.actiu_des).slice(0, 10) : '' }
}
async function desaSaldo() {
  const e = editantSaldo.value
  try {
    confSaldo.value = await api.put('/v1/saldo-minuts/config', {
      enabled: !!e.enabled, finestra_minuts: Number(e.finestra_minuts), tope_minuts: Number(e.tope_minuts), actiu_des: e.actiu_des || null,
    })
    editantSaldo.value = null
  } catch (err) {
    alert(err?.message || "No s'ha pogut desar.")
  }
}
const obreMarge = ref(false)
const obreAjustos = ref(false)
const saldos = ref(null)
const minSaldo = ref(0)
const detallSaldo = ref(null)
const enviantAjust = ref(false)
const formAjust = ref({ decisio: 'compensat', minuts: 0, nota: '' })
async function obreSaldos() {
  try {
    saldos.value = await api.get(`/v1/saldo-minuts/persones?min=${Number(minSaldo.value) || 0}`)
  } catch (e) {
    alert(e?.message || "No s'han pogut carregar els saldos.")
  }
}
function tancaSaldos() { saldos.value = null; detallSaldo.value = null }
async function veuSaldo(p) {
  try {
    detallSaldo.value = await api.get(`/v1/saldo-minuts/persones/${p.user_id}`)
    formAjust.value = { decisio: 'compensat', minuts: 0, nota: '' }
  } catch (e) {
    alert(e?.message || "No s'ha pogut carregar la persona.")
  }
}
async function enviaAjust() {
  const d = detallSaldo.value
  const f = formAjust.value
  const text = f.decisio === 'ajustat' ? `ajustar ${fmtMin(Number(f.minuts) || 0)}` : (f.decisio === 'compensat' ? 'compensar-lo en temps (queda a zero)' : 'tancar-lo a zero sense compensar')
  if (!await confirma(`Vols ${text} el saldo de ${d.nom} (ara ${fmtMin(d.saldo)})? Queda escrit amb el teu nom i el motiu.`)) return
  enviantAjust.value = true
  try {
    await api.post(`/v1/saldo-minuts/persones/${d.user_id}/ajust`, {
      decisio: f.decisio, minuts: f.decisio === 'ajustat' ? Number(f.minuts) : null, nota: f.nota.trim(),
    })
    await veuSaldo({ user_id: d.user_id })
    await obreSaldos()
  } catch (e) {
    alert(e?.message || "No s'ha pogut desar la decisió.")
  } finally {
    enviantAjust.value = false
  }
}
async function aprovaCorrectes() {
  const n = pendentsTancats.value.length
  if (!await confirma(`Revisar ${n} fitxatge${n === 1 ? '' : 's'} pendent${n === 1 ? '' : 's'} i aprovar els que estiguin bé? Els que tinguin alguna cosa a mirar es quedaran pendents.`)) return
  aprovantCorrectes.value = true
  try {
    const r = await api.post('/v1/work-logs/aprova-correctes', { ids: pendentsTancats.value })
    await workLogStore.loadLogs()
    const motius = Object.entries(r?.motius || {}).map(([m, k]) => `· ${m}: ${k}`).join('\n')
    alert(`${r.aprovats} aprovats.` + (r.pendents ? `\n${r.pendents} es queden per revisar:\n${motius}` : ''))
  } catch (e) {
    alert(e?.message || "No s'han pogut aprovar.")
  } finally {
    aprovantCorrectes.value = false
  }
}

const exportant = ref(false)
async function exportaExcel() {
  exportant.value = true
  try {
    const q = new URLSearchParams({ mes: selectedMonth.value, vista: vista.value })
    if (selectedUser.value !== 'all') q.set('user_id', selectedUser.value)
    const resp = await apiFetchRaw(`/v1/work-logs/export?${q}`)
    if (!resp.ok) throw new Error()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(await resp.blob())
    a.download = `registre_jornada_${selectedMonth.value}${vista.value !== 'tots' ? '_' + vista.value : ''}.xlsx`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch (e) {
    alert("No s'ha pogut generar l'Excel.")
  } finally {
    exportant.value = false
  }
}

const recomptes = computed(() => Object.fromEntries(filtres.map(f => [f.clau, logsDelMes.value.filter(f.fn).length])))

// Ordenació per columna: un clic ordena, el segon inverteix. Per defecte, data més recent primer.
const columnes = [
  { clau: 'treballador', text: 'Persona' },
  { clau: 'data', text: 'Dia' },
  { clau: 'inici', text: 'Entrada' },
  { clau: 'fi', text: 'Sortida' },
  { clau: 'hores', text: 'Hores que compten' },
  { clau: 'extres', text: 'Què cal revisar' },
  { clau: 'estat', text: 'Estat' },
]
const ordre = ref({ col: 'data', dir: 'desc' })
function ordena(col) {
  ordre.value = ordre.value.col === col
    ? { col, dir: ordre.value.dir === 'asc' ? 'desc' : 'asc' }
    // Text i hores comencen per A→Z / menys→més; dates, per la més recent.
    : { col, dir: ['data'].includes(col) ? 'desc' : 'asc' }
}
// start_time/end_time arriben en hora de Madrid sense zona: l'hora es llegeix del text, no via Date.
const horaDe = (ts) => (ts ? String(ts).slice(11, 16) : null)
const ORDRE_ESTAT = { pending: 0, approved: 1, rejected: 2 }
const valorOrdre = {
  treballador: l => getUserName(l.user_id),
  data: l => String(l.date).slice(0, 10) + ' ' + (horaDe(l.start_time) || ''),
  inici: l => horaDe(l.start_time),
  fi: l => horaDe(l.end_time),
  hores: l => (l.end_time ? Number(l.effective_hours ?? l.total_hours_worked ?? 0) : null),
  extres: l => Number(l.extra_hours_authorized || 0) + Number(l.extra_hours_unauthorized || 0) + horesForaHorari(l),
  estat: l => ORDRE_ESTAT[l.status] ?? 9,
}

const filteredLogs = computed(() => {
  const f = filtres.find(x => x.clau === vista.value) || filtres[0]
  const { col, dir } = ordre.value
  const val = valorOrdre[col]
  const sign = dir === 'asc' ? 1 : -1
  return logsDelMes.value.filter(f.fn).sort((a, b) => {
    const va = val(a), vb = val(b)
    // Els buits (sense sortida, sense hores) sempre al final, ordenis com ordenis.
    if (va === null && vb !== null) return 1
    if (vb === null && va !== null) return -1
    const c = typeof va === 'string' ? va.localeCompare(vb, 'ca') : (va ?? 0) - (vb ?? 0)
    // Desempat: data més recent primer.
    return c !== 0 ? c * sign : String(b.date).localeCompare(String(a.date))
  })
})

// onMounted moved up

function approve(id) { workLogStore.approveLog(id) }

function openReject(id) {
  rejectLogId.value = id
  rejectReason.value = ''
  showRejectModal.value = true
}

function confirmReject() {
  if (rejectLogId.value && rejectReason.value) {
    workLogStore.rejectLog(rejectLogId.value, rejectReason.value)
    showRejectModal.value = false
  }
}

// «Revisar de nou»: el fitxatge torna a pendent; la decisió anterior i el motiu queden a la traçabilitat.
async function reobrir(log) {
  const motiu = await demana('Per què cal revisar de nou aquest fitxatge? (mínim 10 caràcters)\nQuedarà registrat a la traçabilitat.', { frases: 'revisar_de_nou' })
  if (motiu === null) return
  if (motiu.trim().length < 10) return alert("Cal un motiu d'almenys 10 caràcters.")
  try {
    await api.post(`/v1/work-logs/${log.id}/reobrir`, { motiu: motiu.trim() })
    await fetchData()
  } catch (e) {
    alert(e?.message || "No s'ha pogut reobrir el fitxatge.")
  }
}

function openModify(_log) { /* To be implemented: edit form */ }

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || '—' 
}
function formatDate(d) { return new Date(d).toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { weekday: 'short', day: 'numeric', month: 'short' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }
function statusBadge(s) { return { 'badge-pending': s === 'pending', 'badge-success': s === 'approved', 'badge-danger': s === 'rejected' } }
</script>

<style scoped>
.wl-eines { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
.wl-ajustos-oberts { background: #eef3f8; }
.wl-bolet { display: inline-block; min-width: 18px; padding: 0 6px; margin-left: 4px; border-radius: 999px; background: #F5A524; color: #fff; font-size: .72rem; font-weight: 700; text-align: center; }
.wl-panell { margin-bottom: 12px; padding: 4px 14px 10px; border-radius: 12px; background: #f7f9fb; border: 1px solid #e1e6ec; }
.wl-ajust-fila { display: grid; grid-template-columns: 130px 1fr auto; gap: 12px; align-items: center; padding: 10px 0; border-top: 1px solid #e1e6ec; font-size: .86rem; }
.wl-ajust-fila:first-child { border-top: 0; }
.wl-ajust-titol { font-size: .74rem; text-transform: uppercase; letter-spacing: .04em; color: #5b6573; }
.wl-ajust-botons { display: flex; gap: 6px; flex-wrap: wrap; }
.wl-form-dins { padding: 4px 0 10px; border-top: 0; }
.wl-marge-llista { margin: 0 0 6px; padding-left: 18px; }
.wl-marge-llista li { margin: 4px 0; font-size: .85rem; }
@media (max-width: 700px) { .wl-ajust-fila { grid-template-columns: 1fr; gap: 6px; } }

/* Jornada tancada un altre dia (sortida oblidada): s'ha de veure d'un cop d'ull. */
.wl-marge { margin-bottom: 12px; padding: 10px 14px; border-radius: 12px; background: #FFF7E6; border: 1px solid #F5C77E; color: #7A4B00; font-size: .86rem; }
.wl-marge summary { cursor: pointer; }
.wl-marge[open] .wl-marge-obre { display: none; }
.wl-marge-obre { font-size: .8rem; text-decoration: underline; }
.wl-marge ul { margin: 6px 0 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; }
.wl-marge li { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.sortida-altre-dia { margin-top: 3px; font-size: .74rem; font-weight: 700; color: var(--color-danger, #B3352F); white-space: nowrap; }
.wl-altres-mesos { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; padding: 10px 14px; border-radius: 12px; background: #fff7e0; border: 1px solid #f0d58a; color: #6b5100; font-size: .86rem; }
.wl-mes-xip { border: 1px solid #e0c060; background: #fff; color: #6b5100; border-radius: 99px; padding: 3px 12px; cursor: pointer; font-size: .82rem; }
.wl-mes-xip:hover { background: #ffefb8; }
.wl-mes-xip strong { margin-left: 4px; background: #D9A400; color: #fff; border-radius: 99px; padding: 0 7px; font-size: .74rem; }
.wl-seleccio { position: sticky; top: 8px; z-index: 5; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; margin-bottom: 12px; border-radius: 12px; background: #E8F8F2; border: 1px solid #0DAF83; color: #0A5C46; font-size: .88rem; box-shadow: 0 4px 14px rgba(10, 92, 70, .12); }
.wl-auto-barra.apagada { background: #f4f5f7; border-color: #d5d9e0; color: #3b4252; }
.wl-auto-form { flex-basis: 100%; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.08); }
.wl-auto-form label { display: flex; align-items: center; gap: 6px; }
.wl-mode { display: inline-flex; border: 1px solid #9fdcc8; border-radius: 99px; overflow: hidden; background: #fff; }
.wl-mode button { border: 0; background: transparent; padding: 4px 12px; font-size: .8rem; font-weight: 600; color: #0A5C46; cursor: pointer; }
.wl-mode button.actiu { background: #0A5C46; color: #fff; }
.wl-auto-accions { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
.wl-auto-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; padding: 10px 14px; border-radius: 12px; background: #E8F8F2; border: 1px solid #9fdcc8; color: #0A5C46; font-size: .86rem; }
.wl-nota { font-size: .72rem; color: var(--color-text-muted, #6b7280); margin-top: 2px; }
.wl-avisos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 3px; font-size: .78rem; font-weight: 500; }
.wl-avisos li::before { content: '•'; margin-right: 6px; }
.wl-avis-groc { color: #b45309; }
.wl-avis-vermell { color: #b91c1c; }
.wl-ok { font-size: .78rem; color: #15803d; }
.wl-motiu { font-size: .78rem; font-weight: 500; }
.wl-accepta { margin-top: 6px; font-size: .74rem; padding: 3px 9px; }
.wl-auto { font-size: .7rem; padding: 2px 8px; border-radius: 99px; background: #eef2ff; color: #3730a3; }
.wl-check { width: 34px; text-align: center; }
.wl-check input { width: 17px; height: 17px; cursor: pointer; }
.wl-fila-sel td { background: rgba(13, 175, 131, .07); }
.th-ord { cursor: pointer; user-select: none; white-space: nowrap; }
.th-ord:hover { color: var(--color-primary, #094E8C); }
.th-ord.actiu { color: var(--color-primary, #094E8C); }
.ord-fletxa { font-size: .7rem; margin-left: 3px; opacity: .45; }
.th-ord.actiu .ord-fletxa { opacity: 1; }
.wl-excel { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; white-space: nowrap; }
.wl-filtres { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.wl-filtre {
  display: inline-flex; align-items: center; gap: 7px;
  border: 1px solid var(--color-border, #e2e8f0); border-radius: 999px;
  padding: 8px 14px; min-height: 40px; /* objectiu tàctil */
  font-size: .82rem; font-weight: 700; cursor: pointer; user-select: none;
  background: var(--color-surface, #fff); color: var(--color-text-secondary, #64748B);
  transition: background .15s, border-color .15s;
}
.wl-filtre.buit { opacity: .55; }
.wl-n {
  border-radius: 999px; padding: 1px 9px; font-size: .74rem; font-weight: 800;
  background: #e2e8f0; color: #475569; font-variant-numeric: tabular-nums;
}
.wl-filtre.groc .wl-n { background: #D9A400; color: #fff; }
.wl-filtre.vermell .wl-n { background: #B3352F; color: #fff; }
.wl-filtre.taronja .wl-n { background: #f97316; color: #fff; }
.wl-filtre.verd .wl-n { background: #0DAF83; color: #fff; }
.wl-filtre.buit .wl-n { background: #e2e8f0; color: #94a3b8; }
.wl-filtre.actiu { border-color: var(--color-primary, #094E8C); background: rgba(9, 78, 140, .08); color: var(--color-primary, #094E8C); }
.wl-filtre.actiu.groc { border-color: #D9A400; background: #FFF8E1; color: #8a6a00; }
.wl-filtre.actiu.vermell { border-color: #B3352F; background: #FDF1F0; color: #B3352F; }
.wl-filtre.actiu.taronja { border-color: #f97316; background: #FFF4EC; color: #c2560c; }
.wl-filtre.actiu.verd { border-color: #0DAF83; background: #E8F8F2; color: #0A7C5E; }
.fz-dist {
  font-size: .74rem; font-weight: 700; color: #B3352F; white-space: nowrap;
}
</style>
