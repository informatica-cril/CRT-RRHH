<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('privacy_policy') }}</h1>
        <p class="page-subtitle">{{ t('data_protection') }} - RGPD / LOPD-GDD / ENS</p>
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <p class="text-small">{{ error }}</p>
    </div>

    <div v-if="carregant" class="card mb-lg"><p class="text-muted">Carregant la informació vigent…</p></div>

    <!-- El text NO viu en aquesta pantalla: és el document vigent publicat. Si canvia la política,
         canvia el que llegeix la persona, sense tocar codi. -->
    <template v-else-if="doc">
      <div class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title">{{ doc.titol }}</h3>
          <span class="text-small text-muted">versió {{ doc.versio }}<template v-if="doc.published_at"> · publicada el {{ fmtData(doc.published_at) }}</template></span>
        </div>
        <div class="compliance-body text-small" v-html="render(doc.contingut)"></div>
        <p v-if="doc.base_legal" class="text-small text-muted mt-md"><strong>Base legal:</strong> {{ doc.base_legal }}</p>
      </div>
    </template>

    <div v-else class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <p class="text-small">{{ avis }}</p>
    </div>

    <!-- Exercici de drets: desa la sol·licitud i la deixa a la safata fins que algú la contesta. -->
    <div class="card mb-lg">
      <div class="card-header"><h3 class="card-title">Exercici dels vostres drets</h3></div>
      <p class="text-small mb-md">
        Podeu exercir els drets dels art. 15 a 22 del RGPD. La sol·licitud queda registrada amb data i
        s'ha de respondre com a màxim en un mes (art. 12.3 RGPD). També podeu adreçar-vos directament al
        Delegat de Protecció de Dades i reclamar davant l'Autoritat Catalana de Protecció de Dades.
      </p>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;">
        <div v-for="(etiqueta, clau) in drets" :key="clau" class="stat-card">
          <div style="font-weight:600;">{{ etiqueta }}</div>
          <div v-if="pendentPer(clau)" class="text-small mt-sm" style="color:var(--color-warning);font-weight:600;">
            Pendent de resposta des del {{ fmtData(pendentPer(clau).presentada_ts) }}
          </div>
          <div v-else-if="respostaPer(clau)" class="text-small mt-sm" style="color:var(--color-success);font-weight:600;">
            Resposta el {{ fmtData(respostaPer(clau).resposta_ts) }}
          </div>
          <button v-else class="btn btn-sm mt-sm" :disabled="enviant === clau" @click="sollicitar(clau)">
            {{ enviant === clau ? 'Desant…' : 'Sol·licitar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Gestió (admin/hr). La Safata de pendents enllaça aquí: si la cua apuntés a una pantalla on
         la sol·licitud no es pot resoldre, tornaríem a tenir un avís que no porta enlloc. -->
    <div v-if="esGestor && pendents.length" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="card-header"><h3 class="card-title">Sol·licituds de drets per respondre ({{ pendents.length }})</h3></div>
      <p class="text-small text-muted mb-md">El termini de l'art. 12.3 RGPD és d'un mes des de la presentació.</p>
      <div v-for="s in pendents" :key="s.id" class="pv-solc">
        <div class="text-small">
          <strong>{{ drets[s.dret] || s.dret }}</strong> · {{ s.user?.name }}
          · presentada el {{ fmtData(s.presentada_ts) }}
          · <span :style="{color: venç(s) ? 'var(--color-danger)' : 'inherit', fontWeight: venç(s) ? 700 : 400}">venciment {{ fmtData(s.venciment) }}</span>
        </div>
        <div v-if="s.detall" class="text-small text-muted mt-sm">{{ s.detall }}</div>
        <button class="btn btn-sm mt-sm" :disabled="responent === s.id" @click="respondre(s)">
          {{ responent === s.id ? 'Desant…' : 'Respondre…' }}
        </button>
      </div>
    </div>

    <!-- Historial propi: la persona ha de poder veure què va demanar i què li han contestat. -->
    <div v-if="sollicituds.length" class="card mb-lg">
      <div class="card-header"><h3 class="card-title">Les meves sol·licituds ({{ sollicituds.length }})</h3></div>
      <div v-for="s in sollicituds" :key="s.id" class="pv-solc">
        <div class="text-small">
          <strong>{{ drets[s.dret] || s.dret }}</strong> · presentada el {{ fmtData(s.presentada_ts) }}
          <span v-if="!s.resposta_ts" class="text-muted"> · resposta compromesa abans del {{ fmtData(s.venciment) }}</span>
        </div>
        <div v-if="s.detall" class="text-small text-muted mt-sm">{{ s.detall }}</div>
        <div v-if="s.resposta" class="pv-resposta text-small mt-sm">
          <strong>Resposta ({{ fmtData(s.resposta_ts) }}):</strong>
          <div style="white-space:pre-wrap;margin-top:4px;">{{ s.resposta }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { i18n } from '../i18n'
import api from '../services/apiClient'
import { useAuthStore } from '../stores/auth'
import { renderMarkdownSegur } from '../utils/markdownSegur'

const t = (key) => i18n.t(key)
const render = renderMarkdownSegur
const authStore = useAuthStore()
const esGestor = computed(() => ['admin', 'hr'].includes(authStore.user?.role))

const doc = ref(null)
const drets = ref({})
const avis = ref('')
const sollicituds = ref([])
const pendents = ref([])
const carregant = ref(true)
const enviant = ref(null)
const responent = ref(null)
const error = ref('')

function fmtData(v) { return v ? new Date(v).toLocaleDateString('ca-ES') : '—' }
function venç(s) { return new Date(s.venciment) < new Date() }
function pendentPer(clau) { return sollicituds.value.find((s) => s.dret === clau && !s.resposta_ts) }
function respostaPer(clau) { return sollicituds.value.find((s) => s.dret === clau && s.resposta_ts) }

async function carregar() {
  carregant.value = true
  try {
    const r = await api.get('/v1/privacy/policy')
    doc.value = r?.document || null
    drets.value = r?.drets || {}
    avis.value = r?.avis || 'No hi ha cap versió publicada de la informació de protecció de dades.'
    sollicituds.value = await api.get('/v1/privacy/my-requests').catch(() => [])
    if (esGestor.value) pendents.value = await api.get('/v1/privacy/requests?pendents=1').catch(() => [])
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut carregar la informació de protecció de dades.'
  } finally {
    carregant.value = false
  }
}

/**
 * Desa la sol·licitud. Abans aquest botó mostrava un avís del navegador dient que s'havia enviat i
 * no desava res: si el servidor falla, ara es diu que ha fallat en comptes de fingir que ha anat bé.
 */
async function sollicitar(clau) {
  enviant.value = clau; error.value = ''
  const detall = window.prompt(`Sol·licitud del dret de ${drets.value[clau]}.\n\nSi voleu, concreteu a quines dades o a quin període us referiu (opcional):`)
  if (detall === null) { enviant.value = null; return }
  try {
    await api.post('/v1/privacy/requests', { dret: clau, detall: detall.trim() || null })
    sollicituds.value = await api.get('/v1/privacy/my-requests')
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut desar la sol·licitud. Torneu-ho a provar o escriviu al Delegat de Protecció de Dades.'
  } finally {
    enviant.value = null
  }
}

/** Resposta escrita a una sol·licitud. El servidor no en deixa desar cap de buida ni sobreescriure'n una. */
async function respondre(s) {
  const text = window.prompt(`Resposta a la sol·licitud de ${drets.value[s.dret]} de ${s.user?.name}.\n\nQueda desada amb data i el treballador la veurà a la seva pantalla de privadesa:`)
  if (text === null) return
  if (text.trim().length < 10) { error.value = 'La resposta no pot ser buida.'; return }
  responent.value = s.id; error.value = ''
  try {
    await api.post(`/v1/privacy/requests/${s.id}/respond`, { resposta: text.trim() })
    await carregar()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut desar la resposta.' }
  finally { responent.value = null }
}

onMounted(carregar)
</script>

<style scoped>
.compliance-body { line-height: 1.55; }
.compliance-body :deep(h3) { font-size: 1rem; margin: 12px 0 6px; }
.compliance-body :deep(h4) { font-size: 0.9rem; margin: 10px 0 4px; }
.compliance-body :deep(li) { margin-left: 18px; list-style: disc; }
.pv-solc { border-top: 1px solid var(--color-border, #eee); padding: 10px 0; }
.pv-solc:first-of-type { border-top: 0; }
.pv-resposta { background: var(--color-bg, #f6f8fa); border-radius: 8px; padding: 10px; }
</style>
