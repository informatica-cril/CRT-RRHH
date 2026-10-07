<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Conciliació d'identitats</h1>
        <p class="page-subtitle">Comptes històrics de domi sense DNI a la fitxa: el sistema suggereix la parella, tu la confirmes</p>
      </div>
      <div class="page-actions">
        <button class="btn" :disabled="loading" @click="load">{{ loading ? 'Carregant…' : 'Actualitzar' }}</button>
      </div>
    </div>

    <div class="card mb-lg" style="border-left:4px solid var(--color-primary);">
      <div class="text-small">
        Les altes noves es vinculen soles (DNI a l'alta → compte domi automàtic). Aquí només queden els comptes
        <b>antics</b>, creats a mà i sense DNI, que ningú pot creuar automàticament.
        <b>Cap vinculació s'aplica per semblança:</b> un fals positiu atribuiria l'activitat — i les faltes — a la persona equivocada.
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>
    <div v-if="ok" class="card mb-lg" style="border-left:4px solid var(--color-success);">
      <div class="text-small" style="color:var(--color-success);">{{ ok }}</div>
    </div>

    <div v-if="!pendents.length && !loading" class="card">
      <div class="text-small text-muted">Cap compte pendent de conciliar. Tot el personal de camp de domi està vinculat.</div>
    </div>

    <div v-for="p in pendents" :key="p.username" class="card mb-lg">
      <div class="card-header">
        <h3 class="card-title">
          <code>{{ p.username }}</code>
          <span class="text-small text-muted" style="font-weight:400;"> · {{ p.nom_domi || '(sense nom a la fitxa)' }} · {{ p.privilegio }}</span>
        </h3>
        <span v-if="!p.te_dni" class="text-small" style="color:var(--color-warning);">sense DNI a domi</span>
      </div>

      <div v-if="!p.candidats.length" class="text-small text-muted">
        Cap candidat per semblança. Opcions: completar el DNI a la fitxa de domi i tornar a creuar, o triar manualment el treballador.
      </div>

      <div v-for="c in p.candidats" :key="c.user_id"
           style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--color-border,#eee);">
        <div class="text-small">
          <b>{{ c.name }}</b> <span class="text-muted">· {{ c.email }}</span>
          <span :style="badge(c.score)">{{ etiqueta(c.score) }}</span>
        </div>
        <button class="btn btn-sm" :class="{'btn-primary': c.score >= 100}" :disabled="desant" @click="vincular(p, c)">
          Vincular
        </button>
      </div>

      <details style="margin-top:10px;">
        <summary class="text-small text-muted" style="cursor:pointer;">Triar-ne un altre manualment</summary>
        <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
          <SelectorPersona v-model="manual[p.username]" :persones="totsWorkers" valor-key="user_id" :buida="{ valor: null, text: '— tria un treballador —' }" style="width:320px;" />
          <button class="btn btn-sm" :disabled="!manual[p.username] || desant"
                  @click="vincular(p, { user_id: manual[p.username], name: nomDe(manual[p.username]) })">Vincular</button>
        </div>
      </details>
    </div>
  </div>
</template>

<script setup>
import SelectorPersona from '../components/SelectorPersona.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'

const pendents = ref([])
const loading = ref(false)
const desant = ref(false)
const error = ref('')
const ok = ref('')
const manual = ref({})

// Unió de tots els candidats (per al selector manual): treballadors sense vincle.
const totsWorkers = computed(() => {
  const m = new Map()
  for (const p of pendents.value) for (const c of p.candidats) if (!m.has(c.user_id)) m.set(c.user_id, c)
  return [...m.values()].sort((a, b) => a.name.localeCompare(b.name, 'ca'))
})
function nomDe(id) { return totsWorkers.value.find(w => w.user_id === id)?.name || '' }

async function load() {
  loading.value = true; error.value = ''
  try {
    const r = await api.get('/v1/conciliacio')
    pendents.value = r.pendents || []
  } catch (e) { error.value = e?.message || 'Error carregant la conciliació.' }
  finally { loading.value = false }
}

async function vincular(p, c) {
  if (!await confirma(`Vincular el compte domi «${p.username}» amb ${c.name}?\n\nTota l'activitat d'aquest compte a domi (sessions, firmes, retards) s'atribuirà a aquesta persona.`)) return
  desant.value = true; error.value = ''; ok.value = ''
  try {
    await api.post('/v1/conciliacio', { user_id: c.user_id, username: p.username })
    ok.value = `Vinculat «${p.username}» ↔ ${c.name}.`
    await load()
  } catch (e) { error.value = e?.message || 'No s\'ha pogut vincular.' }
  finally { desant.value = false }
}

function etiqueta(score) {
  if (score >= 100) return 'coincidència forta (inicial + cognom)'
  if (score >= 70) return 'cognom coincideix'
  return 'coincidència parcial'
}
function badge(score) {
  const col = score >= 100 ? 'var(--color-success)' : (score >= 70 ? 'var(--color-warning)' : 'var(--color-muted,#888)')
  return { marginLeft: '8px', padding: '1px 8px', borderRadius: '10px', fontSize: '0.7rem',
           fontWeight: '700', color: col, border: `1px solid ${col}` }
}

onMounted(load)
</script>
