<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Importar l'Excel de RRHH</h1>
        <p class="text-small text-muted">Vacances, dies de conveni, permisos, baixes i hores sindicals. Primer es revisa i després s'aplica: mana el que diu l'Excel.</p>
      </div>
      <div class="page-actions">
        <router-link to="/absences" class="btn btn-outline">← Absències</router-link>
      </div>
    </div>

    <!-- 1. Triar l'Excel -->
    <div class="card" style="padding:16px;">
      <div class="form-label">1. Trieu l'Excel («RRHH 2026.xlsx»)</div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="file" accept=".xlsx,.xlsm" @change="tria" :disabled="llegint || aplicant" />
        <button class="btn btn-primary" :disabled="!fitxer || llegint || aplicant" @click="previsualitza">
          {{ llegint ? 'Llegint…' : 'Revisar' }}
        </button>
      </div>
      <div class="text-small text-muted" style="margin-top:6px;">No es guarda res fins que premeu «Aplicar». Es pot tornar a importar el mateix Excel: no es duplica res.</div>
      <div v-if="error" class="error-import" role="alert">{{ error }}</div>
    </div>

    <template v-if="prev">
      <!-- 2. Resum -->
      <div class="card" style="padding:16px;margin-top:12px;">
        <div class="form-label">2. Què es farà</div>
        <div class="resum-import">
          <div><strong>{{ prev.resum.a_crear }}</strong><span>es crearan</span></div>
          <div><strong>{{ prev.resum.a_actualitzar }}</strong><span>s'actualitzaran (l'Excel diu una altra cosa)</span></div>
          <div><strong>{{ prev.resum.ja_hi_son }}</strong><span>ja hi són igual</span></div>
          <div :class="{ 'resum-avis': pendentsPersona > 0 }"><strong>{{ pendentsPersona }}</strong><span>sense persona (vegeu el punt 3)</span></div>
          <div><strong>{{ prev.resum.duplicats_excel }}</strong><span>repetides a l'Excel (es creen un sol cop)</span></div>
          <div :class="{ 'resum-avis': prev.resum.sense_data + prev.resum.sense_tipus > 0 }"><strong>{{ prev.resum.sense_data + prev.resum.sense_tipus }}</strong><span>no es poden passar (punt 4)</span></div>
        </div>
      </div>

      <!-- 3. Persones -->
      <div class="card" style="padding:16px;margin-top:12px;">
        <div class="form-label">3. Persones</div>
        <div class="text-small text-muted" style="margin-bottom:8px;">
          {{ prev.persones.filter(p => p.casament === 'exacte').length }} noms casen exactament amb la fitxa de l'app.
          Reviseu els de sota: els aproximats ja surten triats; els que no s'han trobat, trieu-los o deixeu-los sense importar.
        </div>
        <table class="taula-import" v-if="personesRevisar.length">
          <thead><tr><th>Nom a l'Excel</th><th>Files</th><th>Persona a l'app</th></tr></thead>
          <tbody>
            <tr v-for="p in personesRevisar" :key="p.nom">
              <td>{{ p.nom }} <span class="etiqueta" :class="p.casament">{{ p.casament === 'aproximat' ? 'aproximat' : 'no trobat' }}</span></td>
              <td>{{ p.files }}</td>
              <td>
                <select class="form-select" v-model="assignacions[p.nom]" style="min-width:260px;">
                  <option :value="null">— No importar —</option>
                  <option v-for="s in p.suggeriments" :key="'s' + s.id" :value="s.id">{{ s.name }}{{ s.actiu ? '' : ' (inactiu)' }}</option>
                  <option disabled>──────────</option>
                  <option v-for="u in usuarisOrdenats" :key="u.id" :value="u.id">{{ u.name }}{{ u.active ? '' : ' (inactiu)' }}</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else class="text-small">Totes les persones s'han trobat.</div>
      </div>

      <!-- 4. El que no es pot passar -->
      <div v-if="prev.sense_data.length || Object.keys(prev.sense_tipus).length" class="card" style="padding:16px;margin-top:12px;">
        <div class="form-label">4. No es poden passar (cal corregir-ho a l'Excel)</div>
        <ul class="text-small" style="margin:0;padding-left:18px;">
          <li v-for="(s, i) in prev.sense_data" :key="'d' + i">Fulla «{{ s.full }}», fila {{ s.fila }}: {{ s.nom }} · {{ s.motiu || 'sense motiu' }} — <strong>sense data</strong></li>
          <li v-for="(n, motiu) in prev.sense_tipus" :key="'t' + motiu">{{ n }} fila{{ n === 1 ? '' : 'es' }} amb el motiu «{{ motiu }}»: <strong>no és a la llista de permisos</strong></li>
        </ul>
      </div>

      <!-- 5. Detall i aplicar -->
      <div class="card" style="padding:16px;margin-top:12px;">
        <div class="form-label">5. Detall</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
          <select class="form-select" v-model="filtreAccio" style="width:auto;">
            <option value="">Tot</option>
            <option value="crear">Es crearan</option>
            <option value="actualitzar">S'actualitzaran</option>
            <option value="igual">Ja hi són</option>
            <option value="sense_persona">Sense persona</option>
          </select>
          <input class="form-input" v-model="cerca" placeholder="Cerca per nom o motiu…" style="max-width:260px;" />
          <span class="text-small text-muted">{{ registresFiltrats.length }} files</span>
        </div>
        <div class="table-container" style="max-height:420px;overflow:auto;">
          <table class="taula-import">
            <thead><tr><th>Persona</th><th>Tipus</th><th>Dates</th><th>Hores</th><th>Estat a l'Excel</th><th>Què es farà</th></tr></thead>
            <tbody>
              <tr v-for="r in registresFiltrats.slice(0, 500)" :key="r.clau">
                <td>{{ nomApp(r) }}</td>
                <td>{{ r.tipus }}</td>
                <td>{{ fmtData(r.ini) }}<template v-if="r.fi !== r.ini"> → {{ fmtData(r.fi) }}</template></td>
                <td>{{ r.hora_inici ? `${r.hora_inici}–${r.hora_fi || '?'}` : (r.minuts ? fmtMin(r.minuts) : '') }}</td>
                <td>{{ r.estat || '—' }}</td>
                <td><span class="etiqueta" :class="accioDe(r)">{{ NOMS_ACCIO[accioDe(r)] }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="registresFiltrats.length > 500" class="text-small text-muted">Es mostren les 500 primeres.</div>

        <label class="text-small" style="display:flex;gap:6px;align-items:flex-start;margin-top:12px;">
          <input type="checkbox" v-model="ambMotiuBaixes" />
          <span>Passar també el motiu de les baixes (el diagnòstic que hi ha a l'Excel). És una dada de salut: només la veu gestió.</span>
        </label>

        <div class="modal-footer" style="padding:12px 0 0;">
          <button class="btn btn-primary" :disabled="aplicant || !aImportar" @click="aplica">
            {{ aplicant ? 'Aplicant…' : `Aplicar (${aImportar} absències)` }}
          </button>
        </div>
        <div v-if="resultat" class="ok-import">
          ✅ Fet: {{ resultat.creades }} creades, {{ resultat.actualitzades }} actualitzades, {{ resultat.iguals }} ja hi eren<span v-if="resultat.saltades">, {{ resultat.saltades }} sense importar</span>.
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import api from '../services/apiClient'
import { db } from '../services/db'
import { avisa } from '../utils/avisos'
import { confirma } from '../utils/dialegs'

const fitxer = ref(null)
const llegint = ref(false), aplicant = ref(false)
const error = ref('')
const prev = ref(null)
const assignacions = reactive({})
const usuaris = ref([])
const filtreAccio = ref(''), cerca = ref('')
const ambMotiuBaixes = ref(true)
const resultat = ref(null)

const NOMS_ACCIO = { crear: 'Es crearà', actualitzar: "S'actualitzarà", igual: 'Ja hi és', sense_persona: 'Sense persona' }
const fmtData = (d) => (d ? d.split('-').reverse().join('/') : '')
const fmtMin = (m) => `${Math.floor(m / 60)} h${m % 60 ? ' ' + (m % 60) + ' min' : ''}`

function tria(e) {
  fitxer.value = e.target.files?.[0] || null
  prev.value = null; resultat.value = null; error.value = ''
}

async function previsualitza() {
  error.value = ''; resultat.value = null
  llegint.value = true
  try {
    const fd = new FormData()
    fd.append('file', fitxer.value)
    const [p, us] = await Promise.all([api.upload('/v1/absences/importa-excel/previsualitza', fd), db.getUsers()])
    usuaris.value = us
    for (const k of Object.keys(assignacions)) delete assignacions[k]
    for (const pers of p.persones) assignacions[pers.nom] = pers.user_id ?? null
    prev.value = p
  } catch (e) {
    error.value = e?.status === 413
      ? "L'Excel és massa gran per al servidor. Deseu-lo sense les files buides de la fulla «hores sindicals» i torneu-ho a provar."
      : `No s'ha pogut llegir l'Excel: ${e?.message || 'error del servidor.'}`
  } finally {
    llegint.value = false
  }
}

const personesRevisar = computed(() => (prev.value?.persones || []).filter(p => p.casament !== 'exacte'))
const usuarisOrdenats = computed(() => [...usuaris.value].filter(u => u.role !== 'service').sort((a, b) => a.name.localeCompare(b.name, 'ca')))
const nomsUsuari = computed(() => Object.fromEntries(usuaris.value.map(u => [u.id, u.name])))
function uidDe(r) { return r.nom in assignacions ? assignacions[r.nom] : r.user_id }
function nomApp(r) { const id = uidDe(r); return id ? (nomsUsuari.value[id] || r.nom) : r.nom }
// Si RRHH tria a mà la persona d'un registre «sense persona», passa a «es crearà».
function accioDe(r) {
  if (!uidDe(r)) return 'sense_persona'
  return r.accio === 'sense_persona' || uidDe(r) !== r.user_id ? 'crear' : r.accio
}
const pendentsPersona = computed(() => (prev.value?.registres || []).filter(r => !uidDe(r)).length)
const aImportar = computed(() => (prev.value?.registres || []).filter(r => uidDe(r) && accioDe(r) !== 'igual').length)
const registresFiltrats = computed(() => {
  const q = cerca.value.trim().toLowerCase()
  return (prev.value?.registres || []).filter(r =>
    (!filtreAccio.value || accioDe(r) === filtreAccio.value)
    && (!q || nomApp(r).toLowerCase().includes(q) || r.nom.toLowerCase().includes(q) || r.tipus.toLowerCase().includes(q)))
})

async function aplica() {
  if (!(await confirma(`S'aplicaran ${aImportar.value} absències de l'Excel. Les que l'Excel diu d'una altra manera s'actualitzaran. Continuar?`))) return
  aplicant.value = true; error.value = ''
  try {
    resultat.value = await api.post('/v1/absences/importa-excel/aplica', {
      registres: prev.value.registres,
      assignacions: { ...assignacions },
      amb_motiu_baixes: ambMotiuBaixes.value,
    })
    avisa(`Importació feta: ${resultat.value.creades} creades, ${resultat.value.actualitzades} actualitzades.`, 'ok')
    // Tornar a revisar: ara tot ha de sortir com «ja hi és».
    await previsualitza()
  } catch (e) {
    error.value = `No s'ha aplicat res: ${e?.message || 'error del servidor.'}`
    avisa(error.value, 'error')
  } finally {
    aplicant.value = false
  }
}
</script>

<style scoped>
.resum-import { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.resum-import > div { display: flex; flex-direction: column; padding: 10px; border-radius: 8px; background: var(--color-bg, #f8fafc); }
.resum-import strong { font-size: 1.4rem; }
.resum-import span { font-size: 0.8rem; color: var(--color-text-secondary, #64748b); }
.resum-avis { background: #fff7ed !important; color: #9a3412; }
.taula-import { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.taula-import th, .taula-import td { padding: 5px 8px; border-bottom: 1px solid var(--color-border-light, #e5e7eb); text-align: left; }
.etiqueta { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 0.72rem; background: #e5e7eb; }
.etiqueta.aproximat, .etiqueta.actualitzar { background: #fef3c7; color: #92400e; }
.etiqueta.cap, .etiqueta.sense_persona { background: #ffe4e6; color: #be123c; }
.etiqueta.crear { background: #dcfce7; color: #065f46; }
.error-import { margin-top: 10px; padding: 10px 12px; border: 1px solid #fecdd3; border-radius: 8px; background: #fff1f2; color: #be123c; font-size: 0.85rem; }
.ok-import { margin-top: 10px; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; color: #065f46; font-size: 0.85rem; }
</style>
