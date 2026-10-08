<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Observatori de millores</h1>
        <p class="page-subtitle">
          Propostes de millora de totes les aplicacions —RRHH, domiciliària, logopèdia i ambulatori— a partir
          d'indicadors reals. La IA proposa i tu decideixes; el codi el segueix escrivint una persona.
        </p>
      </div>
      <div class="page-actions">
        <span v-if="totalCua > 0" class="text-small" style="align-self:center;margin-right:10px;color:var(--color-text-muted);">
          Cua d'informàtica: <b>{{ totalCua.toFixed(1) }} h</b>
        </span>
        <button class="btn btn-primary" :disabled="generant || !iaOn" @click="genera">
          {{ generant ? 'Analitzant…' : 'Buscar millores ara' }}
        </button>
      </div>
    </div>

    <div v-if="!iaOn" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="text-small">L'assistent no respon. Els indicadors segueixen sent consultables aquí sota.</div>
    </div>
    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <div v-for="grup in agrupades" :key="grup.estat" class="mb-lg">
      <h3 class="text-small" style="text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-muted);
                                    font-weight:700;margin-bottom:8px;">
        {{ etiquetaEstat(grup.estat) }} ({{ grup.items.length }})
      </h3>
      <div v-for="m in grup.items" :key="m.id" class="card mb-sm"
           :style="{borderLeft: '4px solid ' + colorImpacte(m.impacte)}">
        <div style="display:flex;gap:12px;justify-content:space-between;align-items:start;flex-wrap:wrap;">
          <div style="flex:1;min-width:280px;">
            <div style="font-weight:700;color:var(--color-text);">{{ m.titol }}</div>
            <div class="text-small" style="color:var(--color-text-muted);margin-top:2px;">
              {{ m.servei }} · {{ m.ambit }} · impacte {{ m.impacte }} · esforç {{ m.esforc }}
            </div>
            <div class="text-small" style="margin-top:8px;line-height:1.6;">
              <b>Què passa.</b> {{ m.problema }}<br>
              <b>Proposta.</b> {{ m.proposta }}
            </div>
            <details v-if="m.detall_tecnic" style="margin-top:6px;">
              <summary class="text-small" style="cursor:pointer;font-weight:700;color:var(--color-primary);">
                Detall per a informàtica
              </summary>
              <div class="text-small" style="margin-top:5px;line-height:1.6;white-space:pre-wrap;
                          background:var(--color-bg);padding:10px;border-radius:8px;">{{ m.detall_tecnic }}</div>
            </details>
            <div class="text-small" style="margin-top:6px;color:var(--color-text-muted);">
              <span v-if="m.prioritat">Prioritat <b>{{ m.prioritat }}</b> · </span>
              <span v-if="m.hores_admin">{{ m.hores_admin }} h (ajustades)</span>
              <span v-else-if="m.hores_ia">{{ m.hores_ia }} h (estimació de la IA)</span>
              <span v-else>sense estimació</span>
            </div>
            <div class="text-small" style="margin-top:6px;color:var(--color-text-muted);">
              <b>Evidència:</b> {{ m.evidencia }}
            </div>
            <div v-if="m.nota" class="text-small" style="margin-top:6px;font-style:italic;">
              {{ m.nota }} <span v-if="m.decidit_per_nom">— {{ m.decidit_per_nom }}</span>
            </div>
          </div>
          <div v-if="m.estat === 'nova'" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
            <div>
              <label class="text-small" style="display:block;color:var(--color-text-muted);">Prioritat</label>
              <select v-model.number="m._prioritat" class="input" style="width:96px;">
                <option :value="1">1 urgent</option><option :value="2">2 alta</option>
                <option :value="3">3 normal</option><option :value="4">4 baixa</option>
                <option :value="5">5 quan es pugui</option>
              </select>
            </div>
            <div>
              <label class="text-small" style="display:block;color:var(--color-text-muted);">Hores</label>
              <input v-model.number="m._hores" type="number" step="0.5" min="0" class="input" style="width:88px;" />
            </div>
            <button class="btn btn-sm btn-primary" @click="encua(m)">A la cua</button>
            <button class="btn btn-sm" @click="decideix(m, 'descartada')">Descartar</button>
          </div>
          <div v-else-if="m.estat === 'en_cua'" style="display:flex;gap:8px;">
            <button class="btn btn-sm" @click="decideix(m, 'en_curs')">Marcar en curs</button>
            <button class="btn btn-sm" @click="decideix(m, 'feta')">Feta</button>
          </div>
          <div v-else-if="m.estat === 'en_curs'" style="display:flex;gap:8px;">
            <button class="btn btn-sm btn-primary" @click="decideix(m, 'feta')">Feta</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!millores.length && !error" class="card mb-lg">
      <div class="text-small text-muted">
        Encara no hi ha propostes. Prem «Buscar millores ara» o espera el repàs nocturn.
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Indicadors sobre els quals treballa</h3></div>
      <details>
        <summary class="text-small" style="cursor:pointer;font-weight:700;">Veure els indicadors en cru</summary>
        <pre style="margin-top:9px;font-size:.74rem;line-height:1.6;max-height:420px;overflow:auto;
                    background:var(--color-bg);padding:12px;border-radius:8px;">{{ senyalsText }}</pre>
      </details>
      <div class="text-small" style="color:var(--color-text-muted);margin-top:10px;line-height:1.6;">
        Cada servei empeny els seus indicadors; els de RRHH es calculen aquí. Tota proposta ha de portar la xifra
        exacta en què es basa: sense evidència, no s'accepta. La IA no parla de persones ni del seu rendiment,
        només de processos, pantalles i circuits.
      </div>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'

const millores = ref([])
const senyals = ref(null)
const iaOn = ref(false)
const generant = ref(false)
const error = ref('')

const ORDRE = ['nova', 'en_curs', 'en_cua', 'feta', 'descartada']

const senyalsText = computed(() => senyals.value ? JSON.stringify(senyals.value, null, 2) : '')

const agrupades = computed(() => ORDRE
  .map(e => ({ estat: e, items: millores.value.filter(m => m.estat === e) }))
  .filter(g => g.items.length))

function etiquetaEstat (e) {
  return { nova: 'Per decidir', en_cua: 'A la cua d\'informàtica', en_curs: 'En curs',
           feta: 'Fetes', descartada: 'Descartades' }[e] || e
}

function colorImpacte (i) {
  return i === 'alt' ? 'var(--color-danger)' : (i === 'mitja' ? 'var(--color-warning)' : 'var(--color-border)')
}

async function carrega () {
  error.value = ''
  try {
    const r = await api.get('/v1/millores')
    millores.value = (r.millores || []).map(m => ({
      ...m,
      _prioritat: m.prioritat || 3,
      _hores: m.hores_admin ?? m.hores_ia ?? null,
    }))
    senyals.value = r.senyals || null
    iaOn.value = !!r.ia_disponible
  } catch (e) { error.value = e?.message || 'No s\'han pogut carregar les millores.' }
}

async function genera () {
  generant.value = true
  error.value = ''
  try {
    await api.post('/v1/millores/generar', {})
    await carrega()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut generar.' } finally { generant.value = false }
}

const totalCua = computed(() => millores.value
  .filter(m => m.estat === 'en_cua' || m.estat === 'en_curs')
  .reduce((t, m) => t + Number(m.hores_admin ?? m.hores_ia ?? 0), 0))

async function encua (m) {
  try {
    await api.put(`/v1/millores/${m.id}`, {
      estat: 'en_cua', prioritat: m._prioritat, hores_admin: m._hores,
    })
    await carrega()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut posar a la cua.' }
}

async function decideix (m, estat) {
  let nota = null
  if (estat === 'descartada') {
    nota = await demana('Per què la descartes? (opcional)', { frases: 'descartar_millora' })
    if (nota === null) return
  }
  try {
    await api.put(`/v1/millores/${m.id}`, { estat, nota })
    await carrega()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut desar.' }
}

onMounted(carrega)
</script>
