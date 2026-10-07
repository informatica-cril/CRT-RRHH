<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Informació a la representació legal</h1>
        <p class="page-subtitle">
          Escrits que el XII Conveni sanitari obliga a lliurar a la RLT. Es redacten aquí, es signen aquí i
          queda constància aquí, al costat de l'expedient.
        </p>
      </div>
    </div>

    <div class="card mb-lg" style="border-left:4px solid var(--color-primary);">
      <div class="text-small" style="line-height:1.7;">
        Els fets no els escriu ningú a mà: els serveis (domiciliària, logopèdia domiciliària, ambulatori) empenyen
        els seus paràmetres i la resta surt del registre horari, de les absències i dels expedients d'aquesta
        aplicació. La intel·ligència artificial només <em>redacta</em>, amb la instrucció de no afegir res que no
        consti als fets. <b>L'escrit no s'envia sol i la IA no el signa:</b> primer una persona el revisa, després
        Direcció o RRHH el signa —i llavors el text queda segellat— i finalment es registra el lliurament.
      </div>
    </div>

    <div v-if="!iaOn" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="text-small">
        El servidor d'IA no respon. Els fets es poden consultar igualment i l'escrit es pot redactar a mà.
      </div>
    </div>
    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <div class="card mb-lg">
      <div class="card-header"><h3 class="card-title">Nou escrit</h3></div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
        <div style="min-width:280px;flex:1;">
          <label class="text-small" style="display:block;margin-bottom:4px;color:var(--color-text-muted);">Matèria</label>
          <select v-model="tipusSel" class="input" style="width:100%;" @change="carregaFets">
            <option v-for="(t, k) in tipus" :key="k" :value="k">{{ t.nom }} — {{ t.articles }}</option>
          </select>
        </div>
        <div>
          <label class="text-small" style="display:block;margin-bottom:4px;color:var(--color-text-muted);">Des de</label>
          <input v-model="desde" type="date" class="input" @change="carregaFets" />
        </div>
        <div>
          <label class="text-small" style="display:block;margin-bottom:4px;color:var(--color-text-muted);">Fins a</label>
          <input v-model="fins" type="date" class="input" @change="carregaFets" />
        </div>
        <button class="btn btn-primary" :disabled="generant || !iaOn" @click="genera">
          {{ generant ? 'Redactant…' : 'Generar esborrany' }}
        </button>
      </div>

      <div v-if="tipusSel && tipus[tipusSel]" class="text-small" style="margin-top:12px;color:var(--color-text-muted);">
        <b>{{ tipus[tipusSel].natura === 'informe_previ' ? 'Informe previ' : 'Deure d\'informació' }}.</b>
        {{ tipus[tipusSel].periodicitat }}
        <span v-if="tipus[tipusSel].natura === 'informe_previ'">
          La representació té 15 dies hàbils per emetre informe (article 55.K.7) i la mesura no s'hauria d'executar abans.
        </span>
      </div>

      <details v-if="fets" style="margin-top:14px;">
        <summary class="text-small" style="cursor:pointer;font-weight:700;">Fets que es lliuren a la IA</summary>
        <pre style="margin-top:9px;font-size:.74rem;line-height:1.6;max-height:340px;overflow:auto;
                    background:var(--color-bg);padding:12px;border-radius:8px;">{{ fetsText }}</pre>
      </details>
    </div>

    <div v-if="obert" class="card mb-lg">
      <div class="card-header">
        <h3 class="card-title">Escrit #{{ obert.id }} · {{ tipus[obert.tipus]?.nom || obert.tipus }}</h3>
        <span class="text-small" :style="{color: obert.estat === 'lliurat' ? 'var(--color-success)' : (obert.estat === 'signat' ? 'var(--color-primary)' : 'var(--color-warning)')}">
          {{ obert.estat }}
        </span>
      </div>
      <div class="text-small" style="color:var(--color-text-muted);margin-bottom:9px;">
        {{ obert.periode_desde }} → {{ obert.periode_fins }}
        <span v-if="obert.signat_ts"> · signat {{ obert.signat_ts }}</span>
        <span v-if="obert.lliurat_a"> · lliurat a {{ obert.lliurat_a }}</span>
      </div>
      <textarea v-model="obert.text" :readonly="obert.estat !== 'esborrany'"
        style="width:100%;min-height:480px;font-family:ui-monospace,Menlo,monospace;font-size:.8rem;
               line-height:1.65;padding:14px;border:1px solid var(--color-border);border-radius:9px;"></textarea>
      <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
        <button class="btn" @click="veuDocument">Veure el document amb capçalera</button>
        <button v-if="obert.estat === 'esborrany'" class="btn" @click="desa">Desar canvis</button>
        <button v-if="obert.estat === 'esborrany'" class="btn btn-primary" @click="signa">Signar com a Direcció</button>
        <button v-if="obert.estat === 'signat'" class="btn btn-primary" @click="lliura">Registrar el lliurament</button>
        <span v-if="obert.estat === 'signat'" class="text-small" style="align-self:center;color:var(--color-text-muted);">
          El text ja està segellat; qualsevol canvi trencaria la signatura.
        </span>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Escrits lliurats i esborranys</h3></div>
      <div v-if="!informes.length" class="text-small text-muted">Encara no hi ha cap escrit.</div>
      <table v-else class="table">
        <thead>
          <tr><th>#</th><th>Matèria</th><th>Període</th><th>Estat</th><th>Signat per</th><th>Lliurat a</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="i in informes" :key="i.id">
            <td>{{ i.id }}</td>
            <td>{{ tipus[i.tipus]?.nom || i.tipus }}</td>
            <td class="text-small">{{ i.periode_desde }} → {{ i.periode_fins }}</td>
            <td><span class="text-small" :style="{color: i.estat === 'lliurat' ? 'var(--color-success)' : (i.estat === 'signat' ? 'var(--color-primary)' : 'var(--color-warning)')}">{{ i.estat }}</span></td>
            <td class="text-small">{{ i.signat_per || '—' }}</td>
            <td class="text-small">{{ i.lliurat_a || '—' }}</td>
            <td><button class="btn btn-sm" @click="obre(i.id)">Obrir</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import api, { apiFetchRaw } from '../services/apiClient'

const tipus = ref({})
const iaOn = ref(false)
const tipusSel = ref('sistemes')
const desde = ref(new Date().getFullYear() + '-01-01')
const fins = ref(new Date().toISOString().slice(0, 10))
const fets = ref(null)
const informes = ref([])
const obert = ref(null)
const generant = ref(false)
const error = ref('')

const fetsText = computed(() => fets.value ? JSON.stringify(fets.value, null, 2) : '')

async function carregaTipus () {
  const r = await api.get('/v1/rlt/tipus')
  tipus.value = r.tipus || {}
  iaOn.value = !!r.ia_disponible
}

async function carregaFets () {
  error.value = ''
  try {
    const r = await api.get(`/v1/rlt/fets?tipus=${tipusSel.value}&desde=${desde.value}&fins=${fins.value}`)
    fets.value = r.fets
  } catch (e) { error.value = e?.message || 'No s\'han pogut carregar els fets.' }
}

async function carregaLlista () {
  const r = await api.get('/v1/rlt/informes')
  informes.value = r.informes || []
}

async function genera () {
  generant.value = true
  error.value = ''
  try {
    const r = await api.post('/v1/rlt/informes', { tipus: tipusSel.value, desde: desde.value, fins: fins.value })
    await carregaLlista()
    await obre(r.id)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut generar.' } finally { generant.value = false }
}

async function obre (id) {
  obert.value = await api.get(`/v1/rlt/informes/${id}`)
}

async function veuDocument () {
  error.value = ''
  try {
    const r = await apiFetchRaw(`/v1/rlt/informes/${obert.value.id}/document`)
    if (!r.ok) { error.value = `El servidor ha respost ${r.status} en demanar el document.`; return }
    const html = await r.text()
    if (!html.includes('class="cap"')) {
      error.value = 'La resposta no és el document: revisa la configuració de VITE_API_URL.'
      return
    }
    const url = URL.createObjectURL(new Blob([html], { type: 'text/html;charset=utf-8' }))
    const w = window.open(url, '_blank')
    if (!w) { error.value = 'El navegador ha blocat la finestra emergent: permet-les per a aquest lloc.' }
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    error.value = 'No s\'ha pogut obrir el document: ' + (e?.message || 'error de xarxa')
  }
}

async function desa () {
  try {
    await api.put(`/v1/rlt/informes/${obert.value.id}`, { text: obert.value.text })
    await obre(obert.value.id)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut desar.' }
}

async function signa () {
  if (!await confirma('Signes aquest escrit com a Direcció? A partir d\'aquí el text queda segellat i no es podrà modificar.')) return
  try {
    await api.put(`/v1/rlt/informes/${obert.value.id}`, { text: obert.value.text })
    await api.post(`/v1/rlt/informes/${obert.value.id}/signar`, {})
    await carregaLlista()
    await obre(obert.value.id)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut signar.' }
}

async function lliura () {
  const a = await demana('A qui s\'ha lliurat? (òrgan i persona)')
  if (a === null) return
  const nota = await demana('Com s\'ha lliurat? (data, via, acusament de rebut)')
  if (nota === null) return
  try {
    await api.post(`/v1/rlt/informes/${obert.value.id}/lliurar`, { lliurat_a: a, nota })
    await carregaLlista()
    await obre(obert.value.id)
  } catch (e) { error.value = e?.message || 'No s\'ha pogut registrar el lliurament.' }
}

onMounted(async () => {
  await carregaTipus()
  await carregaLlista()
  await carregaFets()
})
</script>
