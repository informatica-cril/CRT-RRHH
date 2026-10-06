<template>
  <div class="modal-overlay" @click.self="!enviant && $emit('close')">
    <div class="modal" style="max-width:650px;max-height:90vh;display:flex;flex-direction:column;">
      <div class="modal-header">
        <h3 class="modal-title">🗄️ Importar nòmines del portal antic</h3>
        <button class="icon-btn" :disabled="enviant" @click="$emit('close')">✕</button>
      </div>

      <div style="overflow-y:auto;padding:4px 2px;">
        <p class="text-small text-muted" style="margin-top:0;">
          Tria el fitxer <strong>.sql</strong> exportat del portal antic i les persones. Només s'importen les nòmines que
          <strong>falten</strong>: si la persona ja té el mateix PDF, no es duplica. Les noves entren
          <strong>sense signar</strong>. El fitxer es llegeix en aquest navegador i no es guarda enlloc.
        </p>

        <div v-if="!nomines.length" class="form-group">
          <input type="file" accept=".sql,text/plain" class="form-input" :disabled="llegint" @change="onFitxer" />
          <div v-if="llegint" class="text-small text-muted" style="margin-top:8px;">Llegint el fitxer…</div>
          <div v-if="error" class="text-small" style="margin-top:8px;color:var(--color-danger);">{{ error }}</div>
        </div>

        <template v-else>
          <div class="card" style="padding:12px 16px;margin-bottom:12px;">
            <div><strong>{{ nomines.length }}</strong> nòmines al fitxer ({{ persones.length }} persones), de {{ primerMes }} a {{ darrerMes }}.</div>
          </div>

          <!-- Tria de persones: p. ex. deixar fora qui cobra per una altra entitat. -->
          <div v-if="!fetes" style="margin-bottom:12px;">
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
              <strong style="font-size:0.9rem;">Persones a importar ({{ triades.size }} de {{ persones.length }})</strong>
              <div style="flex:1;"></div>
              <input v-model="cerca" class="form-input" placeholder="Cerca per nom o DNI" style="width:200px;padding:4px 8px;" />
              <button class="btn btn-outline btn-sm" @click="marcaTotes(true)">Totes</button>
              <button class="btn btn-outline btn-sm" @click="marcaTotes(false)">Cap</button>
            </div>
            <div style="max-height:220px;overflow-y:auto;border:1px solid var(--color-border-light);border-radius:8px;">
              <label v-for="p in personesVisibles" :key="p.dni" style="display:flex;gap:8px;align-items:center;padding:6px 10px;font-size:0.85rem;cursor:pointer;border-bottom:1px solid var(--color-border-light);">
                <input type="checkbox" :checked="triades.has(p.dni)" @change="commuta(p.dni)" />
                <span style="flex:1;">{{ p.nom }}</span>
                <span class="text-muted">{{ p.dni }}</span>
                <span class="text-muted" style="width:70px;text-align:right;">{{ p.n }} nòm.</span>
              </label>
            </div>
          </div>

          <div v-if="enviant || acabat" style="margin-bottom:12px;">
            <div class="progress-bar" style="height:8px;background:var(--color-border-light);border-radius:4px;overflow:hidden;">
              <div :style="{ width: progres + '%', height: '100%', background: 'var(--color-primary)', transition: 'width .3s' }"></div>
            </div>
            <div class="text-small text-muted" style="margin-top:6px;">{{ fetes }} de {{ aImportar.length }} revisades</div>
          </div>

          <div v-if="fetes" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px;">
            <div class="stat-card primary"><div class="stat-label">Importades</div><div class="stat-value">{{ compte.importada }}</div></div>
            <div class="stat-card"><div class="stat-label">Ja hi eren</div><div class="stat-value">{{ compte.ja_hi_era }}</div></div>
            <div class="stat-card" style="border-left:3px solid var(--color-warning);"><div class="stat-label">Persona no trobada</div><div class="stat-value">{{ compte.sense_persona }}</div></div>
            <div class="stat-card" style="border-left:3px solid var(--color-danger);"><div class="stat-label">PDF no vàlid</div><div class="stat-value">{{ compte.pdf_no_valid }}</div></div>
          </div>

          <div v-if="error" class="text-small" style="margin-bottom:12px;color:var(--color-danger);">{{ error }}</div>

          <div v-if="acabat && senseP.length">
            <div style="font-weight:600;margin-bottom:6px;">Persones no trobades a l'app ({{ senseP.length }})</div>
            <p class="text-small text-muted" style="margin-top:0;">
              No hi ha cap persona amb aquest DNI. Si ha de tenir les nòmines, dona-la d'alta o corregeix-li
              el DNI i torna a fer la importació: les que ja s'hagin importat no es repeteixen.
            </p>
            <table class="table" style="width:100%;font-size:0.85rem;">
              <thead><tr><th>Nom</th><th>DNI</th><th>Nòmines</th></tr></thead>
              <tbody>
                <tr v-for="p in senseP" :key="p.dni"><td>{{ p.nom }}</td><td>{{ p.dni }}</td><td>{{ p.n }}</td></tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline" :disabled="enviant" @click="$emit('close')">{{ acabat ? 'Tancar' : 'Cancel·lar' }}</button>
        <button v-if="nomines.length && !acabat" class="btn btn-primary" :disabled="enviant || (!fetes && !triades.size)" @click="importa">
          {{ enviant ? 'Important…' : (fetes ? 'Continuar' : 'Importar les que falten') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { db } from '../services/db'
import { llegeixNominesPortalAntic } from '../utils/sqlPortalAntic'

const emit = defineEmits(['close', 'imported'])

// Tandes petites: cada PDF fa desenes de KB i el servidor té límit de mida per petició.
const TANDA = 10

const nomines = ref([])
const llegint = ref(false)
const enviant = ref(false)
const acabat = ref(false)
const error = ref('')
const fetes = ref(0)
const compte = reactive({ importada: 0, ja_hi_era: 0, sense_persona: 0, pdf_no_valid: 0 })
const senseDni = reactive({}) // dni => { nom, dni, n }

const cerca = ref('')
const triades = ref(new Set())
const persones = computed(() => {
  const m = {}
  for (const n of nomines.value) (m[n.dni] ??= { nom: n.nom, dni: n.dni, n: 0 }).n++
  return Object.values(m).sort((a, b) => a.nom.localeCompare(b.nom))
})
const personesVisibles = computed(() => {
  const q = cerca.value.trim().toLowerCase()
  return q ? persones.value.filter(p => p.nom.toLowerCase().includes(q) || p.dni.toLowerCase().includes(q)) : persones.value
})
// La tria es fixa en començar: les tandes es compten sobre aquesta llista.
const aImportar = ref([])
const mesos = computed(() => nomines.value.map(n => n.data.slice(0, 7)).sort())
const primerMes = computed(() => mesos.value[0])
const darrerMes = computed(() => mesos.value[mesos.value.length - 1])
const progres = computed(() => aImportar.value.length ? Math.round(fetes.value / aImportar.value.length * 100) : 0)

function commuta(dni) {
  const s = new Set(triades.value)
  s.has(dni) ? s.delete(dni) : s.add(dni)
  triades.value = s
}
function marcaTotes(si) {
  // Amb una cerca escrita, només afecta les persones que es veuen.
  const s = new Set(triades.value)
  for (const p of personesVisibles.value) si ? s.add(p.dni) : s.delete(p.dni)
  triades.value = s
}
const senseP = computed(() => Object.values(senseDni).sort((a, b) => a.nom.localeCompare(b.nom)))

async function onFitxer(e) {
  const f = e.target.files?.[0]
  if (!f) return
  error.value = ''
  llegint.value = true
  try {
    const llista = llegeixNominesPortalAntic(await f.text())
    if (!llista.length) error.value = 'Aquest fitxer no té cap nòmina del portal antic (taula «nominas»).'
    nomines.value = llista
    triades.value = new Set(llista.map(n => n.dni))
  } catch (err) {
    console.error(err)
    error.value = 'No s\'ha pogut llegir el fitxer.'
  } finally {
    llegint.value = false
  }
}

async function importa() {
  enviant.value = true
  error.value = ''
  try {
    if (!fetes.value) aImportar.value = nomines.value.filter(n => triades.value.has(n.dni))
    // Continua on s'havia quedat si una tanda ha fallat.
    while (fetes.value < aImportar.value.length) {
      const tanda = aImportar.value.slice(fetes.value, fetes.value + TANDA)
      const res = await db.importaPortalAntic(tanda.map(({ dni, data, pdf, nom_fitxer }) => ({ dni, data, pdf, nom_fitxer })))
      res.resultats.forEach((estat, i) => {
        compte[estat] = (compte[estat] || 0) + 1
        if (estat === 'sense_persona') {
          const n = tanda[i]
          senseDni[n.dni] ??= { nom: n.nom, dni: n.dni, n: 0 }
          senseDni[n.dni].n++
        }
      })
      fetes.value += tanda.length
    }
    acabat.value = true
    if (compte.importada) emit('imported')
  } catch (err) {
    console.error(err)
    error.value = 'S\'ha aturat per un error de connexió. Torna a prémer «Continuar» per seguir on s\'ha quedat.'
  } finally {
    enviant.value = false
  }
}
</script>
