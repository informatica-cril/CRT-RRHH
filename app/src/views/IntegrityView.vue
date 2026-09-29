<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Integritat i segell FNMT</h1>
        <p class="page-subtitle">Segell de temps diari (RFC 3161) sobre la cadena de hash de totes les firmes i registres — inalterabilitat oposable a tercers</p>
      </div>
      <div class="page-actions">
        <button class="btn" :disabled="sealing" @click="sealNow">{{ sealing ? 'Segellant…' : 'Segellar avui' }}</button>
      </div>
    </div>

    <div class="card mb-lg" style="border-left:4px solid var(--color-primary);">
      <div class="text-small">
        Cada nit (23:45) es tanca un <b>únic segell</b> que engloba les firmes de totes les apps (domi, RRHH…):
        s'encadenen per hash i es timestampa <b>només l'arrel del dia</b> → <b>1 crèdit FNMT/dia</b> sigui quin sigui el volum.
        Si la TSA encara no està configurada, el dia queda protegit per la cadena interna (estat <i>pendent</i>).
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <div class="card">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr class="text-small text-muted" style="text-align:left;">
            <th style="padding:8px;">Dia</th><th>Esdeveniments</th><th>Arrel</th><th>Segell FNMT</th><th>Hora segell</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in seals" :key="s.id" style="border-top:1px solid var(--color-border,#eee);">
            <td style="padding:8px;">{{ fmtDate(s.seal_date) }}</td>
            <td class="text-small">{{ s.events_count }}</td>
            <td class="text-small" style="font-family:monospace;">{{ (s.root_hash || '').slice(0,16) }}…</td>
            <td><span :style="tsaStyle(s.tsa_status)">{{ s.tsa_status }}</span></td>
            <td class="text-small">{{ s.tsa_time ? fmt(s.tsa_time) : '—' }}</td>
            <td style="text-align:right;">
              <button class="btn btn-sm" @click="verify(s)">Verificar</button>
              <span v-if="verdicts[s.id]" class="text-small" :style="{color: verdicts[s.id].ok ? 'var(--color-success)' : 'var(--color-danger)'}">
                {{ verdicts[s.id].ok ? '✓ íntegre' : '✗ trencat' }}
              </span>
            </td>
          </tr>
          <tr v-if="!seals.length"><td colspan="6" class="text-small text-muted" style="padding:12px;">Cap segell encara. S'genera cada nit o amb «Segellar avui».</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/apiClient'

const seals = ref([])
const verdicts = ref({})
const error = ref('')
const sealing = ref(false)

async function load() {
  try { seals.value = await api.get('/v1/integrity/seals') }
  catch (e) { error.value = e?.message || 'Error carregant segells.' }
}
async function verify(s) {
  try { verdicts.value = { ...verdicts.value, [s.id]: await api.get(`/v1/integrity/seals/${s.id}/verify`) } }
  catch (e) { error.value = e?.message || 'Error verificant.' }
}
async function sealNow() {
  sealing.value = true; error.value = ''
  try { await api.post('/v1/integrity/seal-now', {}); await load() }
  catch (e) { error.value = e?.message || 'No s\'ha pogut segellar.' }
  finally { sealing.value = false }
}

function tsaStyle(st) {
  const map = { segellat: 'var(--color-success)', pendent: 'var(--color-warning)', error: 'var(--color-danger)' }
  return { padding: '2px 8px', borderRadius: '10px', fontSize: '0.72rem', fontWeight: '700', color: '#fff', background: map[st] || '#888' }
}
function fmt(ts) { return new Date(ts).toLocaleString('ca-ES') }
function fmtDate(d) { return new Date(d).toLocaleDateString('ca-ES') }

onMounted(load)
</script>
