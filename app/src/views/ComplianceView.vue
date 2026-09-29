<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:14px;">
        <img src="/assets/crt-logo.png" alt="CRT" style="height:40px;" />
        <div>
          <h1 class="page-title">Compliment i governança</h1>
          <p class="page-subtitle">Publicació i constància d'acusament dels documents que habiliten el control laboral (art. 90 LOPDGDD, gestió algorítmica, RoPA)</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="nou = true">+ Nou document</button>
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <!-- Editor nou / edició -->
    <div v-if="nou || editant" class="card mb-lg">
      <div class="card-header"><h3 class="card-title">{{ editant ? 'Editar esborrany' : 'Nou document' }}</h3></div>
      <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr;">
        <div>
          <label class="text-small text-muted">Tipus</label>
          <select class="form-select" v-model="form.tipus">
            <option value="info_art90">Informació prèvia (art. 90)</option>
            <option value="politica_algoritmica">Política de gestió algorítmica</option>
            <option value="ropa">Registre d'activitats (RoPA)</option>
            <option value="eipd">EIPD</option>
            <option value="altre">Altre</option>
          </select>
        </div>
        <div>
          <label class="text-small text-muted">Versió</label>
          <input class="form-input" v-model="form.versio" placeholder="1.0" />
        </div>
      </div>
      <div style="margin-top:10px;">
        <label class="text-small text-muted">Títol</label>
        <input class="form-input" v-model="form.titol" style="width:100%;" />
      </div>
      <div style="margin-top:10px;">
        <label class="text-small text-muted">Base legal</label>
        <input class="form-input" v-model="form.base_legal" style="width:100%;" placeholder="LOPDGDD art. 90 · RGPD art. 13" />
      </div>
      <div style="margin-top:10px;">
        <label class="text-small text-muted">Contingut (markdown)</label>
        <textarea class="form-input" v-model="form.contingut" rows="10" style="width:100%;font-family:monospace;"></textarea>
      </div>
      <label class="text-small" style="display:flex;align-items:center;gap:8px;margin-top:10px;">
        <input type="checkbox" v-model="form.requereix_acus" /> Requereix acusament individual del treballador (art. 90)
      </label>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <button class="btn btn-primary" :disabled="saving" @click="desar">{{ saving ? 'Desant…' : 'Desar esborrany' }}</button>
        <button class="btn" @click="cancelar">Cancel·lar</button>
      </div>
    </div>

    <!-- Llista -->
    <div class="card">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr class="text-small text-muted" style="text-align:left;">
            <th style="padding:8px;">Document</th><th>Tipus</th><th>Versió</th><th>Estat</th><th>Acusaments</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in docs" :key="d.id" style="border-top:1px solid var(--color-border,#eee);">
            <td style="padding:8px;">{{ d.titol }}</td>
            <td class="text-small">{{ d.tipus }}</td>
            <td class="text-small">{{ d.versio }}</td>
            <td><span :style="estatStyle(d.estat)">{{ d.estat }}</span></td>
            <td class="text-small">{{ d.acknowledgements_count ?? 0 }}<span v-if="d.requereix_acus"> · requerit</span></td>
            <!--
              Publicar és IRREVERSIBLE i d'un sol cop: un document ja publicat no es republica
              (una versió nova és un document nou). Abans s'oferia un botó «Republicar» que el
              backend rebutjava sempre; ara el control desapareix i al seu lloc hi ha la
              constància de quan es va publicar.
            -->
            <td style="text-align:right;white-space:nowrap;">
              <button v-if="d.estat==='esborrany'" class="btn btn-sm" @click="editar(d)">Editar</button>
              <button v-if="potPublicar(d)" class="btn btn-sm btn-primary" @click="publicar(d)">Publicar</button>
              <button class="btn btn-sm" @click="veureEstat(d)">Acusaments</button>
              <button v-if="d.estat!=='arxivat'" class="btn btn-sm" @click="arxivar(d)">Arxivar</button>
              <div v-if="motiuNoPublicar(d)" class="text-small text-muted"
                   style="white-space:normal;max-width:280px;margin-top:4px;text-align:right;">
                🔒 {{ motiuNoPublicar(d) }}
              </div>
            </td>
          </tr>
          <tr v-if="!docs.length"><td colspan="6" class="text-small text-muted" style="padding:12px;">Cap document. Sembra els esborranys amb <code>php artisan db:seed --class=ComplianceSeeder</code>.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Estat d'acusaments -->
    <div v-if="estatDoc" class="card mt-lg">
      <div class="card-header">
        <h3 class="card-title">Acusaments · {{ estatDoc.titol }}</h3>
        <button class="btn btn-sm" @click="estatDoc = null; estatRows = []">Tancar</button>
      </div>
      <!-- El panell segueix TOTA la plantilla (worker, coordinator, hr i admin), no només els
           worker: el compliment del personal de gestió també s'ha de poder acreditar. -->
      <p class="text-small text-muted" style="padding:0 6px 8px;">
        {{ resumEstat.acusats }} de {{ resumEstat.total }} han acusat recepció
        <span v-if="resumEstat.inactius"> · {{ resumEstat.inactius }} de comptes desactivats</span>
      </p>
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr class="text-small text-muted" style="text-align:left;"><th style="padding:6px;">Persona</th><th>Rol</th><th>Estat</th></tr></thead>
        <tbody>
          <tr v-for="r in estatRows" :key="r.user_id" style="border-top:1px solid var(--color-border,#eee);">
            <td style="padding:6px;">
              {{ r.name }}
              <span v-if="r.active === false" class="text-small text-muted"> · desactivat</span>
            </td>
            <td class="text-small text-muted">{{ r.role }}</td>
            <td class="text-small">
              <span v-if="r.acknowledged_at" style="color:var(--color-success);">✓ {{ fmt(r.acknowledged_at) }}</span>
              <span v-else style="color:var(--color-warning);">pendent</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'

const docs = ref([])
const error = ref('')
const nou = ref(false)
const editant = ref(null)
const saving = ref(false)
const estatDoc = ref(null)
const estatRows = ref([])
const form = ref(blank())

// Resum del panell: el que Direcció ha de poder llegir d'un cop d'ull per acreditar el compliment.
const resumEstat = computed(() => ({
  total: estatRows.value.length,
  acusats: estatRows.value.filter(r => r.acknowledged_at).length,
  inactius: estatRows.value.filter(r => r.active === false).length,
}))

function blank() {
  return { tipus: 'info_art90', versio: '1.0', titol: '', base_legal: '', contingut: '', requereix_acus: true }
}

async function load() {
  try { docs.value = await api.get('/v1/compliance') }
  catch (e) { error.value = e?.message || 'Error carregant documents.' }
}

function editar(d) { editant.value = d; nou.value = false; form.value = { ...d } }
function cancelar() { nou.value = false; editant.value = null; form.value = blank() }

async function desar() {
  saving.value = true; error.value = ''
  try {
    if (editant.value) await api.put(`/v1/compliance/${editant.value.id}`, form.value)
    else await api.post('/v1/compliance', form.value)
    cancelar(); await load()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut desar.' }
  finally { saving.value = false }
}

/**
 * Espill EXACTE de la guarda de ComplianceController@publish. No hi ha cap validació relaxada al
 * backend: aquí només es decideix si té sentit oferir el botó.
 *   · arxivat  → no es publica
 *   · ja publicat (amb data) → no es republica: la traçabilitat exigeix un document nou per versió
 */
function potPublicar(d) {
  return d.estat !== 'arxivat' && !(d.estat === 'publicat' && d.published_at)
}

/**
 * Motiu llegible pel qual ara mateix no es pot publicar (buit si sí que es pot, i buit també per a
 * un arxivat: allà el control no s'ha ofert MAI i l'etiqueta d'estat ja ho explica).
 */
function motiuNoPublicar(d) {
  if (potPublicar(d) || d.estat === 'arxivat') return ''
  return `Ja publicat el ${fmt(d.published_at)}. Un document publicat no es torna a publicar: `
       + 'per canviar-ne el contingut, creeu un document nou amb una versió nova.'
}

async function publicar(d) {
  if (!potPublicar(d)) {
    error.value = motiuNoPublicar(d) || 'Document arxivat: no es pot publicar.'
    return
  }
  if (!confirm(`Publicar «${d.titol}» v${d.versio}? Un cop publicat no s'edita ni es republica.`)) return
  try { await api.post(`/v1/compliance/${d.id}/publish`); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut publicar.' }
}
async function arxivar(d) {
  if (!confirm(`Arxivar «${d.titol}»?`)) return
  try { await api.post(`/v1/compliance/${d.id}/archive`); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut arxivar.' }
}
async function veureEstat(d) {
  estatDoc.value = d
  try { estatRows.value = await api.get(`/v1/compliance/${d.id}/status`) }
  catch (e) { error.value = e?.message || 'Error carregant acusaments.' }
}

function estatStyle(estat) {
  const map = { publicat: 'var(--color-success)', esborrany: 'var(--color-warning)', arxivat: 'var(--color-muted,#888)' }
  return { padding: '2px 8px', borderRadius: '10px', fontSize: '0.72rem', fontWeight: '700',
           color: '#fff', background: map[estat] || '#888' }
}
function fmt(ts) { return ts ? new Date(ts).toLocaleString('ca-ES') : '—' }

onMounted(load)
</script>
