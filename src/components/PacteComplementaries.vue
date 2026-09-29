<template>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Hores complementàries</h3>
      <p class="card-subtitle">
        Són <strong>voluntàries</strong> i s'accepten <strong>per quinzenes</strong>. Cada període
        caduca sol: si no l'acceptes, no se te'n programen.
      </p>
    </div>

    <div v-if="carregant" class="pc-buit">Carregant…</div>

    <div v-else-if="!potPactar" class="pc-avis">
      {{ estat?.motiu_no || "Aquest pacte no aplica al teu perfil." }}
    </div>

    <template v-else>
      <!-- El text ve de l'API a posta: si visqués també aquí, les dues còpies acabarien
           divergint i no se sabria quina redacció es va acceptar. -->
      <div class="pc-text">
        <h4>{{ estat.text.titol }}</h4>
        <ul>
          <li v-for="(p, i) in estat.text.punts" :key="i" v-html="ambNegreta(p)"></li>
        </ul>
        <p class="pc-versio">Versió del text: {{ estat.text.versio }}</p>
      </div>

      <div v-if="error" class="pc-error">{{ error }}</div>

      <div class="pc-periodes">
        <div v-for="p in estat.periodes" :key="p.period_start"
             class="pc-periode" :class="{ 'pc-ok': p.acceptat }">
          <div>
            <div class="pc-dates">
              Del {{ dataCurta(p.period_start) }} al {{ dataCurta(p.period_end) }}
              <span v-if="p.actual" class="pc-tag">quinzena actual</span>
            </div>
            <div v-if="p.acceptat" class="pc-estat pc-estat-ok">
              Acceptat · fins a {{ p.max_hours }} h
            </div>
            <div v-else-if="p.revoked_at" class="pc-estat">
              Revocat. Pots tornar a acceptar-lo quan vulguis.
            </div>
            <div v-else class="pc-estat">
              Sense acceptar · no se't programaran hores complementàries en aquest període
            </div>
          </div>

          <button v-if="!p.acceptat" class="btn btn-primary" :disabled="desant === p.period_start"
                  @click="accepta(p)">
            {{ desant === p.period_start ? 'Desant…' : 'Accepto aquest període' }}
          </button>
          <button v-else class="btn btn-secondary" :disabled="desant === p.period_start"
                  @click="revoca(p)">
            Revocar
          </button>
        </div>
      </div>

      <p class="pc-peu">
        Pots revocar l'acceptació en qualsevol moment. La revocació afecta les hores encara
        no realitzades; les que ja hagis fet es liquiden igualment.
      </p>
    </template>
  </div>
</template>

<script setup>
/**
 * Pacte d'hores complementàries — l'accepta la persona treballadora, per quinzena.
 *
 * ── PER QUÈ AQUESTA PANTALLA I NO UN TIC A LA FITXA ─────────────────────────────────
 *   L'art. 12.5 de l'Estatut dels Treballadors exigeix que el pacte sigui **escrit i
 *   voluntari**. Un tic que posa administració a la fitxa d'algú no acredita voluntat de
 *   ningú: no diu qui va consentir, ni quan, ni a què. Aquesta pantalla ho converteix en una
 *   declaració amb data, límit d'hores i versió del text.
 *
 *   Es diu **amb totes les lletres que es pot dir que no**, i que no té conseqüències. Un
 *   consentiment que sembla obligatori no és un consentiment.
 */
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/apiClient'

const carregant = ref(true)
const estat = ref(null)
const error = ref(null)
const desant = ref(null)

const potPactar = computed(() => estat.value?.pot_pactar === true)

async function carrega () {
  carregant.value = true
  error.value = null
  try {
    estat.value = await api.get('/v1/pacte-complementaries/estat')
  } catch (e) {
    error.value = "No s'ha pogut carregar l'estat del pacte."
  } finally {
    carregant.value = false
  }
}

async function accepta (periode) {
  desant.value = periode.period_start
  error.value = null
  try {
    await api.post('/v1/pacte-complementaries/accepta', { period_start: periode.period_start })
    await carrega()
  } catch (e) {
    error.value = e?.data?.error || "No s'ha pogut desar l'acceptació."
  } finally {
    desant.value = null
  }
}

async function revoca (periode) {
  /* El motiu es demana però no s'exigeix: es pot revocar sense donar explicacions. */
  const motiu = window.prompt('Motiu de la revocació (opcional):') ?? ''
  desant.value = periode.period_start
  error.value = null
  try {
    await api.post('/v1/pacte-complementaries/revoca', { period_start: periode.period_start, motiu })
    await carrega()
  } catch (e) {
    error.value = e?.data?.error || "No s'ha pogut revocar."
  } finally {
    desant.value = null
  }
}

const MESOS = ['gener', 'febrer', 'març', 'abril', 'maig', 'juny',
               'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre']

function dataCurta (iso) {
  if (!iso) return ''
  const d = new Date(iso + 'T00:00:00')
  const m = MESOS[d.getMonth()]
  return `${d.getDate()} d${'aeiou'.includes(m[0]) ? "'" : 'e '}${m}`
}

/** El text de l'API porta negreta en Markdown mínim. */
function ambNegreta (t) {
  return String(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
}

onMounted(carrega)
</script>

<style scoped>
.pc-buit, .pc-avis { padding: 16px 0; color: #6b7280; font-size: .9rem; }
.pc-avis { background: #f9fafb; border-radius: 8px; padding: 14px 16px; color: #374151; }

.pc-text { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-top: 8px; }
.pc-text h4 { margin: 0 0 10px; font-size: .95rem; color: #111827; }
.pc-text ul { margin: 0; padding-left: 18px; }
.pc-text li { font-size: .88rem; line-height: 1.65; color: #374151; margin-bottom: 6px; }
.pc-versio { margin: 10px 0 0; font-size: .74rem; color: #9ca3af; }

.pc-error { margin-top: 14px; background: #fef2f2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: .88rem; }

.pc-periodes { margin-top: 16px; display: flex; flex-direction: column; gap: 10px; }
.pc-periode { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;
              border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; }
.pc-periode.pc-ok { border-color: #a7f3d0; background: #ecfdf5; }
.pc-dates { font-size: .92rem; font-weight: 600; color: #111827; }
.pc-tag { margin-left: 8px; background: #e5e7eb; color: #374151; border-radius: 5px;
          padding: 2px 7px; font-size: .72rem; font-weight: 500; }
.pc-estat { margin-top: 4px; font-size: .8rem; color: #6b7280; }
.pc-estat-ok { color: #047857; }
.pc-peu { margin-top: 14px; font-size: .78rem; color: #9ca3af; line-height: 1.6; }
</style>
