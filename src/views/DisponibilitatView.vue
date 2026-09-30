<template>
  <div class="disponibilitat">
    <div class="page-head">
      <h1>Quadre de disponibilitat</h1>
      <p class="sub">
        Qui treballa cada dia i en quin horari, segons el contracte i les absències
        <strong>aprovades</strong>. Les pendents d'aprovar no compten.
      </p>
    </div>

    <!-- Filtres -->
    <div class="card filtres">
      <div class="f">
        <label>Setmana</label>
        <div class="setmana">
          <button class="btn-mini" @click="moureSetmana(-7)" title="Setmana anterior">‹</button>
          <input v-model="setmana" type="date" />
          <button class="btn-mini" @click="moureSetmana(7)" title="Setmana següent">›</button>
        </div>
      </div>

      <div class="f">
        <label>Lot</label>
        <select v-model="filtres.lot_id">
          <option value="">Tots</option>
          <option v-for="l in opcions.lots" :key="l.id" :value="l.id">{{ l.nom }} ({{ l.treballadors }})</option>
        </select>
      </div>

      <div class="f">
        <label>Departament</label>
        <select v-model="filtres.departament_id">
          <option value="">Tots</option>
          <option v-for="d in opcions.departaments" :key="d.id" :value="d.id">{{ d.nom }} ({{ d.treballadors }})</option>
        </select>
      </div>

      <div class="f">
        <label>Zona</label>
        <select v-model="filtres.zona_id">
          <option value="">Totes</option>
          <option v-for="z in opcions.zones" :key="z.id" :value="z.id">{{ z.nom }} ({{ z.treballadors }})</option>
        </select>
      </div>

      <div class="f">
        <label>Ubicació de competència</label>
        <select v-model="filtres.ubicacio_id">
          <option value="">Totes</option>
          <option v-for="u in opcions.ubicacions" :key="u.id" :value="u.id">{{ u.nom }} ({{ u.treballadors }})</option>
        </select>
      </div>

      <div class="f">
        <label>Rol</label>
        <select v-model="filtres.rol">
          <option value="">Tots</option>
          <option v-for="r in opcions.rols" :key="r.id" :value="r.id">{{ r.nom }}</option>
        </select>
      </div>

      <button v-if="!capFiltre" class="btn-mini net" @click="netejaFiltres">Treu els filtres</button>
    </div>

    <div v-if="error" class="avis">{{ error }}</div>
    <div v-else-if="carregant" class="buit">Carregant el quadre…</div>

    <template v-else-if="quadre">
      <!-- Resum per dia -->
      <div class="card resum">
        <div class="resum-head">
          <strong>{{ quadre.setmana.etiqueta }}</strong>
          <span>
            {{ quadre.resum.treballadors }} persones
            <em v-if="quadre.resum.sense_horari" class="alerta">
              · {{ quadre.resum.sense_horari }} sense horari assignat
            </em>
          </span>
        </div>
        <div class="dies">
          <div v-for="(d, i) in quadre.dies" :key="d.data" class="dia">
            <div class="nom">{{ d.nom }}</div>
            <div class="data">{{ d.data.slice(8) }}/{{ d.data.slice(5, 7) }}</div>
            <div v-if="d.festiu" class="festiu">{{ d.festiu }}</div>
            <template v-else>
              <div class="n">{{ quadre.resum.per_dia[i].treballa }}</div>
              <div class="et">treballen</div>
              <div v-if="quadre.resum.per_dia[i].absent" class="absents">
                {{ quadre.resum.per_dia[i].absent }} absents
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Quadre -->
      <div class="card taula-wrap">
        <table class="taula">
          <thead>
            <tr>
              <th class="fixa">Persona</th>
              <th>Horari</th>
              <th class="dret">h/set</th>
              <th v-for="d in quadre.dies" :key="d.data" class="centre">
                {{ d.nom.slice(0, 2) }} {{ d.data.slice(8) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in quadre.files" :key="f.id">
              <td class="fixa">
                <div class="nom-persona">{{ f.nom }}</div>
                <div v-if="f.lots.length || f.departaments.length" class="meta forta">
                  {{ [...f.lots, ...f.departaments].join(' · ') }}
                </div>
                <div v-if="f.zones.length || f.ubicacions.length" class="meta">
                  {{ [...f.zones, ...f.ubicacions].join(' · ') }}
                </div>
              </td>
              <td class="meta">
                <div class="horari-base">
                  <span v-if="f.horari">{{ f.horari }}</span>
                  <span v-else class="alerta">sense horari assignat</span>
                </div>
                <div v-if="f.compl_pacte" class="horari-compl" :title="'Complementàries pactades (art. 12.5 ET), sostre limitat pel màxim anual de 1726 h'">
                  + complementàries: fins a {{ formatHM(f.compl_hores_setmana) }}/set · {{ f.compl_ratio_pct }}%
                </div>
                <div v-else-if="f.nomes_extraordinaries" class="horari-compl extra" title="Jornada completa: no fa complementàries, només extraordinàries">
                  + extraordinàries: 80 h/any · 1,25×
                </div>
              </td>
              <td class="dret nums">
                <div class="hores-base">{{ formatHM(f.hores_setmana) }}</div>
                <div v-if="f.compl_hores_setmana > 0" class="hores-compl">+{{ formatHM(f.compl_hores_setmana) }}</div>
              </td>
              <td v-for="c in f.dies" :key="c.data" class="cel">
                <div class="casella" :class="c.estat" :title="c.detall || etiquetes[c.estat]">
                  <template v-if="c.estat === 'treballa' || c.estat === 'absent'">{{ c.detall }}</template>
                  <template v-else>{{ etiquetes[c.estat] }}</template>
                </div>
                <div v-if="c.compl_tram" class="casella-compl"
                     :title="'Complementàries: fins a ' + c.compl_tram.start + '–' + c.compl_tram.end">
                  +{{ c.compl_tram.start }}–{{ c.compl_tram.end }}
                </div>
              </td>
            </tr>
            <tr v-if="!quadre.files.length">
              <td :colspan="10" class="buit">
                Amb aquests filtres no hi ha ningú.
                <span v-if="!capFiltre">
                  Compte: pot ser que en aquest lot o zona encara no hi hagi ningú assignat,
                  no que ningú estigui disponible.
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="peu">
        Diu qui <strong>hauria</strong> de treballar segons contracte i absències aprovades,
        no qui ha fitxat. Per al que ha passat de veritat, mireu el control de presència.
        L'horari es mostra <strong>desagregat</strong>: les hores de contracte i, a part, les
        complementàries que es poden programar (sostre limitat pel màxim anual de 1726 h). La
        jornada completa no en fa: només extraordinàries.
      </p>
    </template>
  </div>
</template>

<script setup>
/**
 * Quadre de disponibilitat setmanal — petició de Direcció, 31-07-2026.
 *
 * Respon a una sola pregunta: QUI ESTÀ DISPONIBLE aquesta setmana, i en quin horari.
 * La pantalla només pinta: qui hi pot entrar ho decideix l'API (role:admin,hr).
 */
import { ref, computed, onMounted, watch } from 'vue'
import api from '../services/apiClient'
import { formatHM } from '../utils/formatHours'

const carregant = ref(true)
const error = ref('')
const quadre = ref(null)
const opcions = ref({ lots: [], departaments: [], zones: [], ubicacions: [], centres: [], rols: [] })

const setmana = ref(dillunsDe(new Date()))
const filtres = ref({ lot_id: '', departament_id: '', zona_id: '', ubicacio_id: '', rol: '' })

function dillunsDe (d) {
  const x = new Date(d)
  // getDay(): 0 = diumenge. Amb diumenge cal retrocedir 6 dies, no 1.
  x.setDate(x.getDate() - ((x.getDay() + 6) % 7))
  return x.toISOString().slice(0, 10)
}

function moureSetmana (dies) {
  const d = new Date(setmana.value)
  d.setDate(d.getDate() + dies)
  setmana.value = d.toISOString().slice(0, 10)
}

function netejaFiltres () {
  filtres.value = { lot_id: '', departament_id: '', zona_id: '', ubicacio_id: '', rol: '' }
}

const capFiltre = computed(() => Object.values(filtres.value).every(v => v === ''))

async function carregar () {
  carregant.value = true
  error.value = ''
  try {
    const p = new URLSearchParams({ setmana: setmana.value })
    for (const [k, v] of Object.entries(filtres.value)) if (v !== '') p.set(k, v)
    quadre.value = await api.get(`/v1/disponibilitat?${p}`)
  } catch (e) {
    // Que es vegi. Un quadre en blanc sense explicació es llegeix com «no hi ha ningú».
    error.value = e?.status === 403
      ? 'Aquesta pantalla és només per a Administració i Recursos Humans.'
      : (e?.message || 'No s\'ha pogut carregar el quadre.')
    quadre.value = null
  } finally {
    carregant.value = false
  }
}

onMounted(async () => {
  try { opcions.value = await api.get('/v1/disponibilitat/filtres') } catch (e) { /* els filtres són ajuda, no bloquegen */ }
  await carregar()
})
watch([setmana, filtres], carregar, { deep: true })

const etiquetes = {
  treballa: '', absent: 'Absent', festiu: 'Festiu',
  no_treballa: '—', sense_horari: 'Sense horari'
}
</script>

<style scoped>
.page-head h1 { margin: 0 0 .25rem; font-size: 1.35rem; }
.page-head .sub { margin: 0 0 1rem; color: #64748b; font-size: .85rem; }

.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 1rem; margin-bottom: 1rem; }
.filtres { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
.filtres .f label { display: block; font-size: .7rem; text-transform: uppercase; color: #64748b; margin-bottom: .2rem; }
.filtres select, .filtres input { border: 1px solid #cbd5e1; border-radius: 6px; padding: .35rem .5rem; font-size: .85rem; }
.setmana { display: flex; gap: .25rem; align-items: center; }
.btn-mini { border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; padding: .3rem .55rem; cursor: pointer; }
.btn-mini:hover { background: #f1f5f9; }
.btn-mini.net { align-self: flex-end; }

.resum-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .6rem; font-size: .9rem; color: #475569; }
.dies { display: grid; grid-template-columns: repeat(7, 1fr); gap: .5rem; }
.dia { border: 1px solid #e2e8f0; border-radius: 8px; padding: .5rem; text-align: center; font-size: .75rem; }
.dia .nom { font-weight: 600; color: #334155; }
.dia .data { color: #94a3b8; }
.dia .n { font-size: 1.2rem; font-weight: 700; color: #047857; }
.dia .et { color: #94a3b8; }
.dia .absents { color: #b45309; }
.dia .festiu { color: #0369a1; margin-top: .3rem; }

.taula-wrap { overflow-x: auto; padding: 0; }
.taula { width: 100%; border-collapse: collapse; font-size: .82rem; }
.taula th { background: #f8fafc; color: #64748b; font-size: .7rem; text-transform: uppercase; padding: .5rem; text-align: left; }
.taula td { padding: .35rem .5rem; border-top: 1px solid #f1f5f9; vertical-align: middle; }
.taula .fixa { position: sticky; left: 0; background: #fff; z-index: 1; }
.taula thead .fixa { background: #f8fafc; }
.centre { text-align: center; }
.dret { text-align: right; }
.nums { font-variant-numeric: tabular-nums; }
.nom-persona { font-weight: 600; color: #0f172a; }
.meta { font-size: .72rem; color: #94a3b8; }
.meta.forta { color: #475569; }
.alerta { color: #be123c; }

.horari-base { color: #475569; }
.horari-compl { margin-top: .15rem; color: #0369a1; font-weight: 600; }
.horari-compl.extra { color: #7c3aed; }
.hores-base { font-variant-numeric: tabular-nums; }
.hores-compl { font-size: .72rem; color: #0369a1; font-weight: 600; font-variant-numeric: tabular-nums; }

.casella { border: 1px solid; border-radius: 5px; padding: .2rem .3rem; text-align: center; font-size: .72rem; line-height: 1.2; }
.casella.treballa     { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.casella.absent       { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.casella.festiu       { background: #f0f9ff; color: #075985; border-color: #bae6fd; }
.casella.no_treballa  { background: #f8fafc; color: #cbd5e1; border-color: #e2e8f0; }
.casella.sense_horari { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.casella-compl { margin-top: 3px; border: 1px dashed #7dd3fc; border-radius: 5px; padding: .12rem .3rem;
                 text-align: center; font-size: .68rem; line-height: 1.2; color: #0369a1; background: #f0f9ff; font-weight: 600; }

.avis { background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; padding: .8rem 1rem; border-radius: 8px; font-size: .85rem; }
.buit { padding: 2rem; text-align: center; color: #94a3b8; }
.peu { font-size: .75rem; color: #94a3b8; margin: 0; }
</style>
