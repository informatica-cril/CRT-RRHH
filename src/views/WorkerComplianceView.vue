<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Informació i polítiques</h1>
        <p class="page-subtitle">Documents que has de llegir i confirmar. La confirmació deixa constància amb data.</p>
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <!-- Notificacions disciplinàries: acusament de recepció OBLIGATORI dins l'app -->
    <div v-for="n in notificacions" :key="'disc'+n.id" class="card mb-lg" :style="!n.acus_ts ? 'border-left:4px solid var(--color-danger);' : ''">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <img src="/assets/crt-logo.png" alt="CRT" style="height:32px;" />
          <h3 class="card-title">Notificació disciplinària · {{ n.tipus }}</h3>
        </div>
        <span class="text-small" :style="{color: n.acus_ts ? 'var(--color-success)' : 'var(--color-danger)', fontWeight:700}">
          {{ n.acus_ts ? '✓ recepció acusada el ' + fmt(n.acus_ts) : 'PENDENT D\'ACUSAR RECEPCIÓ' }}
        </span>
      </div>
      <div class="doc-paper">
        <div class="compliance-body text-small" v-html="render(n.contingut)"></div>
      </div>
      <div v-if="!n.acus_ts" style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-primary" :disabled="acking==='d'+n.id" @click="acusarNotificacio(n)">
          {{ acking==='d'+n.id ? 'Registrant…' : 'Acuso recepció d\'aquest escrit' }}
        </button>
        <span class="text-small text-muted">L'acusament acredita únicament la <b>recepció</b>, no la conformitat amb el contingut. Queda registrat amb data i origen.</span>
      </div>
    </div>

    <!--
      TRÀMIT D'AUDIÈNCIA (ET 55.1 i art. 55.K.6 del conveni). El plec de càrrecs et diu que
      presentis les al·legacions davant de RRHH: aquesta és la via, i és teva. Ningú més hi pot
      escriure, i mentre el termini corri l'expedient no es pot resoldre.
    -->
    <div v-for="c in expedients" :key="'cas'+c.id" class="card mb-lg"
         :style="c.pot_alegar ? 'border-left:4px solid var(--color-warning);' : ''">
      <div class="card-header">
        <h3 class="card-title">Expedient disciplinari #{{ c.id }} · {{ c.tipus_falta }}</h3>
        <span class="text-small text-muted">estat: {{ c.estat }}</span>
      </div>
      <div class="text-small" style="line-height:1.7;">
        <div>Comunicat el <b>{{ fmt(c.comunicat_ts) }}</b>. Tens fins al
          <b>{{ c.termini_alegacions?.substring(0,10) || '—' }}</b> per presentar les teves al·legacions
          <span v-if="c.dies_per_alegar !== null && c.dies_per_alegar >= 0">({{ c.dies_per_alegar }} dies)</span>.
        </div>
        <div v-if="c.alegacions_ts" style="margin-top:8px;color:var(--color-success);font-weight:700;">
          ✓ Vas presentar al·legacions el {{ fmt(c.alegacions_ts) }}.
        </div>
        <div v-if="c.alegacions_text" style="margin-top:6px;padding:10px;background:var(--color-bg);border-radius:8px;white-space:pre-wrap;">{{ c.alegacions_text }}</div>
        <div v-else-if="c.renuncia_termini_ts" style="margin-top:8px;color:var(--color-warning);font-weight:700;">
          Vas renunciar al termini d'al·legacions el {{ fmt(c.renuncia_termini_ts) }}.
        </div>
      </div>

      <template v-if="c.pot_alegar">
        <FrasesRapides clau="allegacio_disciplinari" v-model="alegacio[c.id]" />
        <textarea class="form-input" v-model="alegacio[c.id]" rows="6" style="width:100%;margin-top:12px;"
                  placeholder="Escriu aquí la teva versió dels fets, les proves que vulguis proposar i el que consideris rellevant (mínim 10 caràcters)."></textarea>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;">
          <input type="file" @change="e => triaAdjunt(c.id, e)" />
          <span v-if="adjunt[c.id]" class="text-small text-muted">📎 {{ adjunt[c.id].nom }}</span>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
          <button class="btn btn-primary" :disabled="enviant===c.id || (alegacio[c.id]||'').trim().length < 10"
                  @click="presentarAlegacions(c)">
            {{ enviant===c.id ? 'Registrant…' : 'Presentar les meves al·legacions' }}
          </button>
          <button class="btn" :disabled="enviant===c.id" @click="renunciar(c)">Renuncio al termini</button>
        </div>
        <div class="text-small text-muted" style="margin-top:8px;">
          La data de presentació la posa el sistema. Renunciar al termini és decisió teva i només
          teva: si hi renuncies, l'empresa podrà resoldre l'expedient abans de la data límit.
        </div>
      </template>
    </div>

    <div v-if="!pending.length && !notificacions.length && !expedients.length && !loading" class="card">
      <div class="text-small text-muted">No tens cap document pendent de confirmar. Gràcies.</div>
    </div>

    <div v-for="d in pending" :key="d.id" class="card mb-lg">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <img src="/assets/crt-logo.png" alt="CRT" style="height:32px;" />
          <h3 class="card-title">{{ d.titol }}</h3>
        </div>
        <span class="text-small text-muted">v{{ d.versio }} · {{ d.base_legal }}</span>
      </div>
      <div class="compliance-body text-small" v-html="render(d.contingut)"></div>
      <div style="margin-top:14px;display:flex;align-items:center;gap:10px;">
        <button class="btn btn-primary" :disabled="acking===d.id" @click="acknowledge(d)">
          {{ acking===d.id ? 'Desant…' : 'He llegit i confirmo la recepció' }}
        </button>
        <span class="text-small text-muted">Es registrarà la data i l'origen de la confirmació.</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, onMounted } from 'vue'
import api from '../services/apiClient'
import { renderMarkdownSegur } from '../utils/markdownSegur'

const pending = ref([])
const notificacions = ref([])
const expedients = ref([])
const alegacio = ref({})
const adjunt = ref({})
const enviant = ref(null)
const loading = ref(true)
const acking = ref(null)
const error = ref('')

async function load() {
  loading.value = true
  try {
    pending.value = await api.get('/v1/compliance/pending')
    notificacions.value = await api.get('/v1/disciplinary/my-notifications').catch(() => [])
    expedients.value = await api.get('/v1/disciplinary/my-cases').catch(() => [])
  } catch (e) { error.value = e?.message || 'Error carregant documents.' }
  finally { loading.value = false }
}

/** Adjunt opcional de les al·legacions (part mèdic, correu, full de servei…). */
function triaAdjunt(casId, e) {
  const f = e.target.files?.[0]
  if (!f) { adjunt.value = { ...adjunt.value, [casId]: null }; return }
  const r = new FileReader()
  r.onload = () => { adjunt.value = { ...adjunt.value, [casId]: { nom: f.name, b64: String(r.result).split(',').pop() } } }
  r.readAsDataURL(f)
}

async function presentarAlegacions(c) {
  enviant.value = c.id; error.value = ''
  try {
    await api.post(`/v1/disciplinary/cases/${c.id}/alegacions`, {
      text: alegacio.value[c.id], adjunt: adjunt.value[c.id]?.b64 || null, adjunt_nom: adjunt.value[c.id]?.nom || null,
    })
    alegacio.value = { ...alegacio.value, [c.id]: '' }
    adjunt.value = { ...adjunt.value, [c.id]: null }
    await load()
  } catch (e) { error.value = e?.message || 'No s\'han pogut registrar les al·legacions.' }
  finally { enviant.value = null }
}

async function renunciar(c) {
  if (!await confirma('Renunciar al termini vol dir que l\'empresa podrà resoldre l\'expedient abans de la data límit, sense esperar les teves al·legacions. Ho confirmes?')) return
  enviant.value = c.id; error.value = ''
  try { await api.post(`/v1/disciplinary/cases/${c.id}/renuncia-termini`, { confirmo: true }); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut registrar la renúncia.' }
  finally { enviant.value = null }
}

async function acusarNotificacio(n) {
  acking.value = 'd' + n.id; error.value = ''
  try { await api.post(`/v1/disciplinary/documents/${n.id}/acknowledge`, {}); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut registrar l\'acusament.' }
  finally { acking.value = null }
}

function fmt(ts) { return ts ? new Date(ts).toLocaleString('ca-ES') : '' }

async function acknowledge(d) {
  acking.value = d.id; error.value = ''
  try { await api.post(`/v1/compliance/${d.id}/acknowledge`, {}); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut confirmar.' }
  finally { acking.value = null }
}

// Render markdown mínim i segur: viu a utils/markdownSegur.js, compartit amb la pantalla de privadesa.
const render = renderMarkdownSegur

onMounted(load)
</script>

<style scoped>
.compliance-body { line-height: 1.55; }
.compliance-body :deep(h3) { font-size: 1rem; margin: 12px 0 6px; }
.compliance-body :deep(h4) { font-size: 0.9rem; margin: 10px 0 4px; }
.compliance-body :deep(li) { margin-left: 18px; list-style: disc; }
.doc-paper {
  background: #fff; color: #1a202c; border: 1px solid #d8dee5; border-radius: 4px;
  padding: 26px 32px; font-family: Georgia, 'Times New Roman', serif;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
</style>
