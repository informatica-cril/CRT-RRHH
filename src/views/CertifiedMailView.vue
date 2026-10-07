<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Comunicació certificada</h1>
        <p class="page-subtitle">
          Enviament de documentació laboral per correu certificat amb segell de temps: acusaments de
          recepció i documentació d'expedient. La certificació acredita lliurament, obertura, lectura
          i descàrrega dels adjunts.
        </p>
      </div>
      <div class="page-actions">
        <button v-if="authStore.isAdmin" class="btn btn-secondary" @click="obreConfig">Configuració</button>
        <button class="btn btn-primary" @click="mostraForm = !mostraForm">
          {{ mostraForm ? 'Tanca' : 'Nou enviament certificat' }}
        </button>
      </div>
    </div>

    <div v-if="!configurat" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="text-small">
        El canal certificat encara no està configurat. Administració ha d'introduir l'usuari API, el
        token i el remitent de Mensatek a «Configuració».
      </div>
    </div>
    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <div v-if="mostraForm" class="card mb-lg">
      <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div>
          <label class="form-label">Tipus de comunicació</label>
          <select v-model="form.tipus" class="form-input">
            <option value="acuse">Documentació amb justificant de recepció</option>
            <option value="expedient">Expedient laboral</option>
          </select>
        </div>
        <div v-if="form.tipus === 'expedient'">
          <label class="form-label">Referència de l'expedient</label>
          <input v-model="form.expedient_ref" class="form-input" placeholder="p. ex. EXP-2026-004" />
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Assumpte</label>
          <input v-model="form.assumpte" class="form-input" maxlength="200" />
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Cos del missatge</label>
          <textarea v-model="form.cos" class="form-input" rows="7"></textarea>
        </div>
        <div>
          <label class="form-label">Programació (opcional)</label>
          <input v-model="form.programat_at" type="datetime-local" class="form-input" />
          <div class="text-small" style="color:var(--color-text-muted);margin-top:4px;">
            Buit = enviament immediat. Amb data, l'enviament queda programat i es pot cancel·lar.
          </div>
        </div>
        <div>
          <label class="form-label">Justificant amb acceptació expressa</label>
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input v-model="form.acceptacio" type="checkbox" />
            <span class="text-small">Demanar acceptació del destinatari (caduca als 10 dies)</span>
          </label>
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Adjunts (màx. 5 · PDF o Word · 8 MB)</label>
          <input ref="fitxers" type="file" multiple accept=".pdf,.doc,.docx,.odt" class="form-input" />
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Destinataris ({{ form.destinataris.length }} seleccionats)</label>
          <input v-model="cerca" class="form-input" placeholder="Cerca per nom…" style="margin-bottom:8px;" />
          <div style="max-height:260px;overflow:auto;border:1px solid var(--color-border);border-radius:8px;padding:8px;">
            <label style="display:flex;align-items:center;gap:8px;padding:3px 4px;font-weight:600;">
              <input type="checkbox" :checked="totsSeleccionats" @change="seleccionaTots" />
              <span class="text-small">Selecciona tots els visibles</span>
            </label>
            <label v-for="u in treballadorsFiltrats" :key="u.id"
                   style="display:flex;align-items:center;gap:8px;padding:3px 4px;">
              <input v-model="form.destinataris" type="checkbox" :value="u.id" />
              <span class="text-small">{{ u.name }} <span style="color:var(--color-text-muted);">· {{ u.email || 'sense correu' }}</span></span>
            </label>
          </div>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;">
        <button class="btn btn-primary" :disabled="enviant || !form.destinataris.length" @click="envia">
          {{ enviant ? 'Enviant…' : (form.programat_at ? 'Programa l\'enviament certificat' : 'Envia certificat ara') }}
        </button>
      </div>
    </div>

    <div v-for="m in mails" :key="m.id" class="card mb-sm">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:start;">
        <div style="flex:1;min-width:260px;">
          <div style="font-weight:700;">{{ m.assumpte }}</div>
          <div class="text-small" style="color:var(--color-text-muted);">
            {{ m.tipus === 'expedient' ? 'Expedient' + (m.expedient_ref ? ' · ' + m.expedient_ref : '') : 'Justificant de recepció' }}
            · {{ m.recipients.length }} destinataris · {{ m.sender?.name }}
            · {{ formataData(m.programat_at || m.created_at) }}
            <span v-if="m.acceptacio"> · amb acceptació expressa</span>
          </div>
          <div v-if="m.error_txt" class="text-small" style="color:var(--color-danger);">{{ m.error_txt }}</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="text-small" :style="{fontWeight:700,color:colorEstat(m.estat_global)}">{{ etiquetaEstat(m.estat_global) }}</span>
          <button class="btn btn-secondary btn-sm" @click="refresca(m)">Actualitza estats</button>
          <button v-if="m.estat_global === 'programat'" class="btn btn-secondary btn-sm"
                  style="color:var(--color-danger);" @click="cancella(m)">Cancel·la</button>
        </div>
      </div>
      <table class="data-table" style="margin-top:10px;width:100%;">
        <thead><tr class="text-small"><th style="text-align:left;">Destinatari</th><th style="text-align:left;">Estat</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in m.recipients" :key="r.id">
            <td class="text-small">{{ r.nom }} · {{ r.email }}</td>
            <td class="text-small">
              <span :style="{color: r.estat >= 11 ? 'var(--color-success)' : 'var(--color-text-muted)'}">
                {{ r.estat_txt || (r.id_mensaje ? 'pendent de report' : '—') }}
              </span>
              <span v-if="r.estat_at" style="color:var(--color-text-muted);"> · {{ formataData(r.estat_at) }}</span>
            </td>
            <td style="text-align:right;">
              <button v-if="r.id_mensaje && r.estat >= 11" class="btn btn-secondary btn-sm"
                      @click="baixaCertificat(r)">Certificat</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="configOberta" class="modal-overlay" @click.self="configOberta = false">
      <div class="card" style="max-width:460px;margin:10vh auto;">
        <h3 style="margin-bottom:10px;">Configuració del canal certificat</h3>
        <label class="form-label">Usuari API</label>
        <input v-model="cfg.usuari_api" class="form-input mb-sm" />
        <label class="form-label">API Token {{ cfg.has_token ? '(desat — deixa-ho buit per mantenir-lo)' : '' }}</label>
        <input v-model="cfg.api_token" type="password" class="form-input mb-sm" autocomplete="new-password" />
        <label class="form-label">Remitent</label>
        <input v-model="cfg.remitent" type="email" class="form-input mb-sm" />
        <div v-if="cfg.last_test_status" class="text-small" style="color:var(--color-text-muted);margin-bottom:8px;">
          Darrera comprovació: {{ cfg.last_test_status }}
        </div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary" :disabled="desantCfg" @click="desaConfig">{{ desantCfg ? 'Comprovant…' : 'Desa i comprova' }}</button>
          <button class="btn btn-secondary" @click="configOberta = false">Tanca</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { computed, onMounted, ref } from 'vue'
import api, { apiFetchRaw } from '../services/apiClient'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const mails = ref([])
const configurat = ref(true)
const treballadors = ref([])
const cerca = ref('')
const mostraForm = ref(false)
const enviant = ref(false)
const error = ref('')
const fitxers = ref(null)
const configOberta = ref(false)
const desantCfg = ref(false)
const cfg = ref({ usuari_api: '', api_token: '', remitent: '', has_token: false, last_test_status: '' })

const form = ref({ tipus: 'acuse', expedient_ref: '', assumpte: '', cos: '', acceptacio: false, programat_at: '', destinataris: [] })

const treballadorsFiltrats = computed(() => {
  const q = cerca.value.toLowerCase()
  return treballadors.value.filter(u => !q || (u.name || '').toLowerCase().includes(q))
})
const totsSeleccionats = computed(() =>
  treballadorsFiltrats.value.length > 0 &&
  treballadorsFiltrats.value.every(u => form.value.destinataris.includes(u.id)))

function seleccionaTots() {
  const ids = treballadorsFiltrats.value.map(u => u.id)
  if (totsSeleccionats.value) {
    form.value.destinataris = form.value.destinataris.filter(id => !ids.includes(id))
  } else {
    form.value.destinataris = [...new Set([...form.value.destinataris, ...ids])]
  }
}

function formataData(d) { return d ? new Date(d).toLocaleString('ca-ES', { dateStyle: 'short', timeStyle: 'short' }) : '' }
function etiquetaEstat(e) {
  return { esborrany: 'Esborrany', enviat: 'Enviat', programat: 'Programat', cancellat: 'Cancel·lat', error: 'Error' }[e] || e
}
function colorEstat(e) {
  return { enviat: 'var(--color-success)', programat: 'var(--color-warning)', cancellat: 'var(--color-text-muted)', error: 'var(--color-danger)' }[e] || 'var(--color-text)'
}

async function carrega() {
  try {
    const d = await api.get('/v1/certified-mails')
    mails.value = d.mails || []
    configurat.value = !!d.configurat
  } catch (e) { error.value = e.message || 'No s\'ha pogut carregar.' }
  try {
    const u = await api.get('/v1/users/bulk-index')
    treballadors.value = (u.users || u || []).filter(x => x.email)
  } catch (e) { /* la llista pot fallar sense trencar la pantalla */ }
}

async function envia() {
  error.value = ''
  enviant.value = true
  try {
    const fd = new FormData()
    fd.append('tipus', form.value.tipus)
    if (form.value.expedient_ref) fd.append('expedient_ref', form.value.expedient_ref)
    fd.append('assumpte', form.value.assumpte)
    fd.append('cos', form.value.cos)
    fd.append('acceptacio', form.value.acceptacio ? '1' : '0')
    if (form.value.programat_at) fd.append('programat_at', form.value.programat_at.replace('T', ' '))
    form.value.destinataris.forEach(id => fd.append('destinataris[]', id))
    const files = fitxers.value?.files || []
    for (const f of files) fd.append('adjunts[]', f)
    await api.upload('/v1/certified-mails', fd)
    mostraForm.value = false
    form.value = { tipus: 'acuse', expedient_ref: '', assumpte: '', cos: '', acceptacio: false, programat_at: '', destinataris: [] }
    await carrega()
  } catch (e) { error.value = e.message || 'No s\'ha pogut enviar.' } finally { enviant.value = false }
}

async function refresca(m) {
  try {
    const d = await api.post(`/v1/certified-mails/${m.id}/refresh`, {})
    const i = mails.value.findIndex(x => x.id === m.id)
    if (i >= 0 && d.mail) mails.value[i] = { ...mails.value[i], ...d.mail }
  } catch (e) { error.value = e.message }
}

async function cancella(m) {
  if (!await confirma('Cancel·lar aquest enviament programat?')) return
  try {
    await api.post(`/v1/certified-mails/${m.id}/cancel`, {})
    await carrega()
  } catch (e) { error.value = e.message }
}

async function baixaCertificat(r) {
  try {
    const resp = await apiFetchRaw(`/v1/certified-mails/recipients/${r.id}/certificate`)
    if (!resp.ok) throw new Error('Encara no hi ha certificat disponible.')
    const blob = await resp.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `certificat-${r.id_mensaje}.zip`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch (e) { error.value = e.message }
}

async function obreConfig() {
  try { cfg.value = { ...cfg.value, ...(await api.get('/v1/certified-mails/settings')), api_token: '' } } catch (e) { /* buit */ }
  configOberta.value = true
}

async function desaConfig() {
  desantCfg.value = true
  error.value = ''
  try {
    const r = await api.put('/v1/certified-mails/settings', {
      usuari_api: cfg.value.usuari_api, api_token: cfg.value.api_token || null, remitent: cfg.value.remitent,
    })
    cfg.value.last_test_status = r.ok ? `ok · ${r.credits} crèdits` : 'error de connexió o credencials'
    if (r.ok) { configurat.value = true; configOberta.value = false }
  } catch (e) { error.value = e.message } finally { desantCfg.value = false }
}

onMounted(carrega)
</script>
