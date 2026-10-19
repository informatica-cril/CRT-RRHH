<template>
  <div class="qd" @mouseup="arrossegant = false" @mouseleave="arrossegant = false">
    <div class="page-header">
      <div>
        <h1 class="page-title">Quadrant</h1>
        <p class="page-subtitle">L'horari de cada persona dia a dia. Els dies que no canvies segueixen la plantilla de la seva fitxa.</p>
      </div>
    </div>

    <!-- Guia curta: es pot amagar i tornar a obrir amb «Com funciona». -->
    <div v-if="mostraGuia" class="qd-guia">
      <div class="qd-guia-cap">
        <strong>Com es posa un horari</strong>
        <button type="button" class="icon-btn" @click="amagaGuia" title="Amagar la guia">✕</button>
      </div>
      <ol>
        <li><b>Tria els dies.</b> Clica'ls o arrossega amb el ratolí. Clica el nom d'una persona per triar-li tot el mes.</li>
        <li><b>Tria l'horari</b> al panell que surt a baix: una plantilla, un horari a mida (també partit) o dia lliure.</li>
        <li><b>Prem «Aplicar».</b> Els dies canviats queden marcats amb una ratlla fosca a l'esquerra.</li>
      </ol>
    </div>

    <div class="card qd-barra">
      <div class="qd-mes">
        <button type="button" class="btn btn-outline btn-sm" @click="mouMes(-1)" title="Mes anterior">‹</button>
        <input type="month" class="form-input" v-model="mes" />
        <button type="button" class="btn btn-outline btn-sm" @click="mouMes(1)" title="Mes següent">›</button>
      </div>
      <select class="form-select" v-model="departament" title="Departament">
        <option value="">Tots els departaments</option>
        <option v-for="d in dades.departaments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <input class="form-input qd-cerca" v-model="cerca" placeholder="Cerca una persona…" />
      <button type="button" class="btn btn-outline btn-sm" :disabled="!personesVisibles.length || desant" @click="copiaMesAnterior"
        title="Les persones que es veuen tindran aquest mes igual que l'anterior, dia de la setmana per dia de la setmana">
        📋 Copiar del mes anterior
      </button>
      <button v-if="!mostraGuia" type="button" class="btn btn-outline btn-sm" @click="mostraGuia = true">❔ Com funciona</button>
    </div>

    <div class="qd-llegenda">
      <span v-for="h in horesPresents" :key="h" class="qd-mostra"><i :style="colorHores(h)">{{ textHores(h) }}</i></span>
      <span class="qd-mostra"><i class="d-canviat">9-15</i> Canviat al quadrant</span>
      <span class="qd-mostra"><i class="d-amida">9-14</i> A mida</span>
      <span class="qd-mostra"><i class="d-lliure">—</i> No treballa</span>
      <span class="qd-mostra"><i class="d-abs">Perm.</i> Permís</span>
      <span class="qd-mostra"><i class="qd-m-festiu">&nbsp;</i> Festiu</span>
    </div>

    <div class="card qd-taula-card">
      <div v-if="carregant" class="text-muted qd-buit">Carregant…</div>
      <div v-else-if="!personesVisibles.length" class="text-muted qd-buit">No hi ha ningú amb aquest filtre.</div>
      <div v-else class="qd-scroll">
        <table class="qd-taula">
          <thead>
            <tr>
              <th class="qd-nom">Persona</th>
              <th v-for="d in dades.dies" :key="d" class="qd-dia-cap"
                :class="{ cap: esCapDeSetmana(d), festiu: dades.festius[d], avui: d === avui }"
                :title="dades.festius[d] ? 'Festiu: ' + dades.festius[d] : 'Triar aquest dia per a totes les persones que es veuen'"
                @click="triaColumna(d)">
                <div>{{ Number(d.slice(8)) }}</div>
                <div class="qd-ds">{{ inicialDia(d) }}</div>
              </th>
              <th class="qd-hores">Hores</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in personesVisibles" :key="p.id">
              <th class="qd-nom">
                <button type="button" class="qd-nom-btn" @click="triaFila(p)" :title="'Triar tot el mes de ' + p.name">{{ p.name }}</button>
                <div class="qd-nom-sub">
                  <span class="qd-plantilla" :title="p.plantilla?.name || 'Sense plantilla'"><b v-if="p.plantilla && horesDe(p.plantilla.id)" class="qd-h-xip" :style="colorHores(horesDe(p.plantilla.id))">{{ textHores(horesDe(p.plantilla.id)) }}</b> {{ p.plantilla?.name || 'Sense plantilla' }}</span>
                  <button type="button" class="qd-any-btn" @click="obreAny(p)" title="Veure tot l'any">📅 Any</button>
                </div>
              </th>
              <td v-for="d in dades.dies" :key="d" class="qd-cel"
                :class="[classeDia(p.dies[d]), { sel: seleccio.has(clau(p.id, d)), cap: esCapDeSetmana(d), festiu: dades.festius[d] }]"
                :style="estilDia(p.dies[d])"
                :title="titolDia(p, d)"
                @mousedown.prevent="iniciaArrossega(p.id, d, $event)" @mouseenter="continuaArrossega(p.id, d)">
                {{ p.dies[d].absencia && p.dies[d].trams.length ? 'Perm.' : textDia(p.dies[d]) }}
              </td>
              <td class="qd-hores">{{ formatHM(p.minuts / 60) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Accions sobre la selecció -->
    <div v-if="seleccio.size" class="qd-accions">
      <div class="qd-accions-cap">
        <strong>{{ seleccio.size }} dia{{ seleccio.size === 1 ? '' : 's' }} triat{{ seleccio.size === 1 ? '' : 's' }}</strong>
        <span class="text-small">de {{ personesTriades }} persona{{ personesTriades === 1 ? '' : 'es' }}</span>
        <button type="button" class="btn btn-outline btn-sm" @click="seleccio = new Set()">Treure la selecció</button>
      </div>
      <div class="qd-accions-cos">
        <div class="qd-accio">
          <label class="text-small">Posar una plantilla</label>
          <div class="qd-fila">
            <select class="form-select" v-model="plantillaTriada">
              <option :value="null" disabled>Tria una plantilla…</option>
              <option v-for="pl in dades.plantilles" :key="pl.id" :value="pl.id">{{ pl.name }}</option>
            </select>
            <button type="button" class="btn btn-primary btn-sm" :disabled="!plantillaTriada || desant" @click="aplica('plantilla')">Aplicar</button>
          </div>
        </div>
        <div class="qd-accio">
          <label class="text-small">Horari a mida (fins a 3 trams, per a jornada partida)</label>
          <div v-for="(t, i) in tramsAMida" :key="i" class="qd-fila">
            <input type="time" class="form-input" v-model="t.start" />
            <span>a</span>
            <input type="time" class="form-input" v-model="t.end" />
            <button v-if="tramsAMida.length > 1" type="button" class="btn btn-outline btn-sm" @click="tramsAMida.splice(i, 1)" title="Treure aquest tram">✕</button>
          </div>
          <div class="qd-fila">
            <button v-if="tramsAMida.length < 3" type="button" class="btn btn-outline btn-sm" @click="tramsAMida.push({ start: '', end: '' })">+ Afegir tram</button>
            <button type="button" class="btn btn-primary btn-sm" :disabled="!tramsValids || desant" @click="aplica('trams')">Aplicar</button>
          </div>
        </div>
        <div class="qd-accio">
          <label class="text-small">Altres</label>
          <div class="qd-fila">
            <button type="button" class="btn btn-outline btn-sm" :disabled="desant" @click="aplica('lliure')">Dia lliure</button>
            <button type="button" class="btn btn-outline btn-sm" :disabled="desant" @click="aplica('esborra')" title="Els dies tornen a seguir la plantilla de la fitxa">↺ Tornar a la plantilla</button>
          </div>
          <input class="form-input" v-model="nota" maxlength="255" placeholder="Nota (opcional): p. ex. canvi amb una companya" />
        </div>
      </div>
    </div>

    <!-- Vista anual d'una persona -->
    <div v-if="anual" class="modal-overlay" @click.self="anual = null">
      <div class="modal qd-modal-any">
        <div class="modal-header">
          <h3 class="modal-title">{{ anual.nom }} · {{ anual.any }}</h3>
          <div class="qd-fila">
            <button type="button" class="btn btn-outline btn-sm" @click="carregaAny(anual.id, anual.nom, anual.any - 1)">‹</button>
            <button type="button" class="btn btn-outline btn-sm" @click="carregaAny(anual.id, anual.nom, anual.any + 1)">›</button>
            <button class="icon-btn" @click="anual = null">✕</button>
          </div>
        </div>
        <div v-if="!anual.dades" class="text-muted">Carregant…</div>
        <div v-else class="qd-any">
          <div v-for="m in mesosAny" :key="m.mes" class="qd-mini">
            <button type="button" class="qd-mini-titol" @click="vesAlMes(m.mes)" title="Obrir aquest mes">{{ m.nom }} <span class="text-muted">· {{ formatHM(m.minuts / 60) }}</span></button>
            <div class="qd-mini-grid">
              <span v-for="l in ['Dl','Dt','Dc','Dj','Dv','Ds','Dg']" :key="l" class="qd-mini-cap">{{ l }}</span>
              <span v-for="n in m.buits" :key="'b' + n"></span>
              <span v-for="d in m.dies" :key="d" class="qd-mini-dia"
                :class="[classeDia(anual.dades.persona.dies[d]), { festiu: anual.dades.festius[d] }]"
                :style="estilDia(anual.dades.persona.dies[d])" :title="d.split('-').reverse().join('/') + ' · ' + textLlarg(anual.dades.persona.dies[d])">
                {{ Number(d.slice(8)) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '../services/apiClient'
import { confirma } from '../utils/dialegs'
import { avisa } from '../utils/avisos'
import { formatHM } from '../utils/formatHours'
import { useEstatUrl, idOText } from '../composables/useEstatUrl'

const ara = new Date()
const dosDigits = n => String(n).padStart(2, '0')
const avui = `${ara.getFullYear()}-${dosDigits(ara.getMonth() + 1)}-${dosDigits(ara.getDate())}`
const mes = ref(avui.slice(0, 7))
const departament = ref('')
const cerca = ref('')
useEstatUrl('mes', mes)
useEstatUrl('departament', departament, idOText)

const dades = ref({ dies: [], festius: {}, plantilles: [], departaments: [], persones: [] })

// Guia de 3 passos: es recorda en aquest navegador si s'ha amagat.
const CLAU_GUIA = 'qd-guia-amagada'
const mostraGuia = ref((() => { try { return localStorage.getItem(CLAU_GUIA) !== '1' } catch { return true } })())
function amagaGuia() {
  mostraGuia.value = false
  try { localStorage.setItem(CLAU_GUIA, '1') } catch { /* sense emmagatzematge: només aquesta visita */ }
}
const carregant = ref(false)
const desant = ref(false)

async function carrega() {
  if (!/^\d{4}-\d{2}$/.test(mes.value)) return
  carregant.value = true
  try {
    const q = new URLSearchParams({ mes: mes.value })
    if (departament.value) q.set('department_id', departament.value)
    const r = await api.get(`/v1/quadrant?${q}`)
    dades.value = { ...r, festius: Array.isArray(r.festius) ? {} : (r.festius || {}) }
  } catch (e) {
    avisa(e?.message || "No s'ha pogut carregar el quadrant.", 'error')
  } finally {
    carregant.value = false
  }
}
onMounted(carrega)
watch([mes, departament], () => { seleccio.value = new Set(); carrega() })

function mouMes(n) {
  const [a, m] = mes.value.split('-').map(Number)
  const d = new Date(a, m - 1 + n, 1)
  mes.value = `${d.getFullYear()}-${dosDigits(d.getMonth() + 1)}`
}

const normalitza = s => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const personesVisibles = computed(() => {
  const c = normalitza(cerca.value.trim())
  return c ? dades.value.persones.filter(p => normalitza(p.name).includes(c)) : dades.value.persones
})

// ── Aspecte de cada dia ──
// El color és el de les hores setmanals de la plantilla (totes les de 35 h, iguals; les de 20 h, d'un altre…).
// El dia canviat al quadrant porta una ratlla fosca a l'esquerra; l'horari a mida, ratllat.
const PALETA = [['#DBEAFE', '#1E3A8A'], ['#DCFCE7', '#166534'], ['#FFEDD5', '#9A3412'], ['#EDE9FE', '#5B21B6'],
  ['#FCE7F3', '#9D174D'], ['#CFFAFE', '#155E75'], ['#FEF9C3', '#854D0E'], ['#FEE2E2', '#991B1B'], ['#E2E8F0', '#334155']]
const minutsDe = h => { const [a, b] = String(h).split(':').map(Number); return a * 60 + (b || 0) }
function horesDe(plantillaId) {
  const p = dades.value.plantilles.find(x => x.id === plantillaId)
  if (!p) return 0
  let h = Number(p.total_hours_weekly) || 0
  if (!h && Array.isArray(p.days)) {
    h = p.days.filter(d => d && d.start && d.end && d.active !== false).reduce((s, d) => s + minutsDe(d.end) - minutsDe(d.start), 0) / 60
  }
  return Math.round(h * 2) / 2
}
// Cada nombre d'hores té sempre el mateix color: de més a menys hores, en l'ordre de la paleta.
const totesLesHores = computed(() => [...new Set(dades.value.plantilles.map(p => horesDe(p.id)).filter(h => h > 0))].sort((a, b) => b - a))
function colorHores(h) {
  const i = totesLesHores.value.indexOf(h)
  const [bg, fg] = PALETA[(i < 0 ? PALETA.length - 1 : i) % PALETA.length]
  return { background: bg, color: fg }
}
const textHores = h => `${String(h).replace('.', ',')} h`
const horesPresents = computed(() => {
  const s = new Set()
  for (const p of dades.value.persones) {
    for (const d of Object.values(p.dies)) if (d.trams.length && d.work_schedule_id) s.add(horesDe(d.work_schedule_id))
  }
  return [...s].filter(h => h > 0).sort((a, b) => b - a)
})
function classeDia(d) {
  if (!d) return ''
  const canviat = d.origen === 'quadrant' ? ' d-canviat' : ''
  if (d.absencia) return 'd-abs'
  if (!d.trams.length) return 'd-lliure' + canviat
  if (d.origen === 'quadrant' && !d.work_schedule_id) return 'd-amida d-canviat'
  return 'd-plantilla' + canviat
}
function estilDia(d) {
  if (!d || d.absencia || !d.trams.length || !d.work_schedule_id) return {}
  const h = horesDe(d.work_schedule_id)
  return h ? colorHores(h) : {}
}
const hora = h => h.replace(/^0/, '').replace(/:00$/, '')
function textDia(d) {
  if (!d.trams.length) return '—'
  if (d.trams.length > 1) return 'Partit'
  return `${hora(d.trams[0].start)}-${hora(d.trams[0].end)}`
}
function textLlarg(d) {
  if (!d) return ''
  const h = d.trams.length ? d.trams.map(t => `${t.start}–${t.end}`).join(' i ') : 'Lliure'
  return h + (d.origen === 'quadrant' ? ' (canviat al quadrant)' : ' (plantilla)') + (d.absencia ? ' · Absència aprovada' : '') + (d.nota ? ` · ${d.nota}` : '')
}
function titolDia(p, d) {
  const fest = dades.value.festius[d] ? ` · Festiu: ${dades.value.festius[d]}` : ''
  return `${p.name} · ${d.split('-').reverse().join('/')}\n${textLlarg(p.dies[d])}${fest}`
}
const esCapDeSetmana = d => [0, 6].includes(new Date(`${d}T12:00:00`).getDay())
const inicialDia = d => ['dg', 'dl', 'dt', 'dc', 'dj', 'dv', 'ds'][new Date(`${d}T12:00:00`).getDay()]

// ── Selecció ──
const seleccio = ref(new Set())
const clau = (uid, d) => `${uid}|${d}`
const arrossegant = ref(false)
let modeArrossega = true
function commuta(uid, d, posar) {
  const s = new Set(seleccio.value)
  posar ? s.add(clau(uid, d)) : s.delete(clau(uid, d))
  seleccio.value = s
}
function iniciaArrossega(uid, d) {
  arrossegant.value = true
  modeArrossega = !seleccio.value.has(clau(uid, d))
  commuta(uid, d, modeArrossega)
}
function continuaArrossega(uid, d) {
  if (arrossegant.value) commuta(uid, d, modeArrossega)
}
function triaGrup(claus) {
  const s = new Set(seleccio.value)
  const totes = claus.every(k => s.has(k))
  claus.forEach(k => (totes ? s.delete(k) : s.add(k)))
  seleccio.value = s
}
const triaFila = p => triaGrup(dades.value.dies.map(d => clau(p.id, d)))
const triaColumna = d => triaGrup(personesVisibles.value.map(p => clau(p.id, d)))
const personesTriades = computed(() => new Set([...seleccio.value].map(k => k.split('|')[0])).size)

// ── Accions ──
const plantillaTriada = ref(null)
const tramsAMida = ref([{ start: '', end: '' }])
const nota = ref('')
const tramsValids = computed(() => tramsAMida.value.every(t => t.start && t.end && t.end > t.start))

async function aplica(accio) {
  // Una crida per a cada grup de persones amb els mateixos dies triats.
  const perPersona = {}
  for (const k of seleccio.value) {
    const [uid, d] = k.split('|')
    ;(perPersona[uid] ||= []).push(d)
  }
  const grups = {}
  for (const [uid, dies] of Object.entries(perPersona)) {
    const g = dies.sort().join(',')
    ;(grups[g] ||= { dies: dies, user_ids: [] }).user_ids.push(Number(uid))
  }
  const cos = { accio, nota: nota.value.trim() || null }
  if (accio === 'plantilla') cos.work_schedule_id = plantillaTriada.value
  if (accio === 'trams') cos.trams = tramsAMida.value.map(t => ({ start: t.start, end: t.end }))
  if (accio === 'esborra' && !await confirma(`Tornar ${seleccio.value.size} dia${seleccio.value.size === 1 ? '' : 's'} a la plantilla de la fitxa?`)) return

  desant.value = true
  let recalculats = 0
  try {
    for (const g of Object.values(grups)) {
      const r = await api.put('/v1/quadrant', { ...cos, user_ids: g.user_ids, dies: g.dies })
      recalculats += r?.trams_recalculats || 0
    }
    avisa(`✅ Quadrant desat.${recalculats ? ` ${recalculats} fitxatge${recalculats === 1 ? '' : 's'} pendent${recalculats === 1 ? '' : 's'} s'han tornat a revisar amb l'horari nou.` : ''}`)
    seleccio.value = new Set()
    nota.value = ''
    await carrega()
  } catch (e) {
    avisa(e?.message || "No s'ha pogut desar el quadrant.", 'error')
  } finally {
    desant.value = false
  }
}

async function copiaMesAnterior() {
  const [a, m] = mes.value.split('-').map(Number)
  const d = new Date(a, m - 2, 1)
  const de = `${d.getFullYear()}-${dosDigits(d.getMonth() + 1)}`
  const n = personesVisibles.value.length
  if (!await confirma(`Copiar el quadrant de ${nomMes(de)} a ${nomMes(mes.value)} per a ${n} persona${n === 1 ? '' : 'es'}? Els canvis que ja hi hagi aquest mes es substituiran.`)) return
  desant.value = true
  try {
    await api.post('/v1/quadrant/copia', { user_ids: personesVisibles.value.map(p => p.id), de, a: mes.value })
    avisa('✅ Mes copiat.')
    await carrega()
  } catch (e) {
    avisa(e?.message || "No s'ha pogut copiar el mes.", 'error')
  } finally {
    desant.value = false
  }
}
function nomMes(m) {
  const [a, mm] = m.split('-').map(Number)
  return new Date(a, mm - 1, 1).toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' })
}

// ── Vista anual ──
const anual = ref(null)
const obreAny = p => carregaAny(p.id, p.name, Number(mes.value.slice(0, 4)))
async function carregaAny(id, nom, any) {
  anual.value = { id, nom, any, dades: null }
  try {
    const r = await api.get(`/v1/quadrant/any?user_id=${id}&any=${any}`)
    if (anual.value?.id === id && anual.value.any === any) {
      anual.value.dades = { ...r, festius: Array.isArray(r.festius) ? {} : (r.festius || {}) }
    }
  } catch (e) {
    avisa(e?.message || "No s'ha pogut carregar l'any.", 'error')
    anual.value = null
  }
}
const mesosAny = computed(() => {
  if (!anual.value?.dades) return []
  const dies = anual.value.dades.persona.dies
  return Array.from({ length: 12 }, (_, i) => {
    const m = `${anual.value.any}-${dosDigits(i + 1)}`
    const delMes = Object.keys(dies).filter(d => d.startsWith(m))
    const primer = new Date(`${m}-01T12:00:00`).getDay()
    const minuts = delMes.reduce((s, d) => s + dies[d].trams.reduce((x, t) => x + minutsDe(t.end) - minutsDe(t.start), 0), 0)
    const nom = nomMes(m).replace(/ de \d{4}$| \d{4}$/, '')
    return { mes: m, nom: nom.charAt(0).toUpperCase() + nom.slice(1), dies: delMes, buits: (primer + 6) % 7, minuts }
  })
})
function vesAlMes(m) {
  anual.value = null
  mes.value = m
}
</script>

<style scoped>
.qd-barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 12px; margin-bottom: 10px; }
.qd-mes { display: flex; gap: 6px; align-items: center; }
.qd-mes input { width: 170px; }
.qd-cerca { max-width: 240px; }
.qd-llegenda { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 10px; }
.qd-llegenda { gap: 14px; }
.qd-mostra { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; color: var(--color-text-secondary, #475569); }
.qd-mostra i { font-style: normal; display: inline-block; min-width: 38px; padding: 3px 6px; border-radius: 4px; text-align: center; font-size: .7rem; border: 1px solid var(--color-border-light, #eef2f7); }
.d-plantilla { font-weight: 500; }
.d-canviat { font-weight: 800; box-shadow: inset 3px 0 0 #0f172a; }
.d-amida { background: repeating-linear-gradient(45deg, #fff, #fff 4px, #f1f5f9 4px, #f1f5f9 8px); color: #0f172a; }
.d-lliure { color: #cbd5e1; }
.d-abs { background: #FDECEA; color: #B3352F; font-weight: 600; }
.qd-h-xip { display: inline-block; padding: 0 5px; border-radius: 4px; font-size: .64rem; margin-right: 2px; }
.qd-guia { margin-bottom: 10px; padding: 10px 14px; border-radius: 12px; background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; font-size: .84rem; }
.qd-guia-cap { display: flex; justify-content: space-between; align-items: center; }
.qd-guia ol { margin: 6px 0 0; padding-left: 20px; display: flex; flex-direction: column; gap: 3px; }
.qd-m-festiu { background: #fdecea; }
.qd-taula-card { padding: 0; overflow: hidden; }
.qd-buit { padding: 32px; text-align: center; }
.qd-scroll { overflow: auto; max-height: calc(100vh - 290px); }
.qd-taula { border-collapse: separate; border-spacing: 0; font-size: .72rem; user-select: none; }
.qd-taula th, .qd-taula td { border-bottom: 1px solid var(--color-border-light, #eef2f7); border-right: 1px solid var(--color-border-light, #eef2f7); }
.qd-taula thead th { position: sticky; top: 0; z-index: 2; background: var(--color-surface, #fff); }
.qd-nom { position: sticky; left: 0; z-index: 1; background: var(--color-surface, #fff); text-align: left; min-width: 190px; max-width: 220px; padding: 6px 10px; font-weight: 500; }
thead .qd-nom { z-index: 3; }
.qd-nom-btn { border: 0; background: none; padding: 0; font: inherit; font-weight: 700; font-size: .8rem; color: var(--color-text, #1e293b); cursor: pointer; text-align: left; }
.qd-nom-btn:hover { color: var(--color-primary, #094E8C); }
.qd-nom-sub { display: flex; justify-content: space-between; gap: 6px; color: var(--color-text-muted, #64748b); font-size: .68rem; }
.qd-plantilla { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.qd-any-btn { border: 0; background: none; padding: 0; cursor: pointer; font-size: .68rem; color: var(--color-primary, #094E8C); white-space: nowrap; }
.qd-dia-cap { min-width: 44px; padding: 4px 2px; text-align: center; cursor: pointer; font-weight: 700; }
.qd-dia-cap:hover { background: #f1f5f9; }
.qd-ds { font-weight: 500; font-size: .62rem; color: var(--color-text-muted, #64748b); }
.qd-dia-cap.avui { color: var(--color-primary, #094E8C); box-shadow: inset 0 -3px 0 var(--color-primary, #094E8C); }
.cap { background-color: #f8fafc; }
.festiu { background-color: #fdecea; }
.qd-cel { height: 34px; text-align: center; cursor: pointer; white-space: nowrap; padding: 0 3px; font-size: .7rem; }
.qd-cel.cap.d-lliure, .qd-cel.festiu.d-lliure { color: transparent; }
.qd-cel.sel { outline: 2px solid var(--color-primary, #094E8C); outline-offset: -2px; box-shadow: inset 0 0 0 40px rgba(9, 78, 140, .14); }
.qd-hores { position: sticky; right: 0; background: var(--color-surface, #fff); padding: 0 8px; font-weight: 700; white-space: nowrap; text-align: right; }
.qd-accions { position: sticky; bottom: 8px; z-index: 6; margin-top: 12px; padding: 12px 14px; border-radius: 12px; background: var(--color-surface, #fff); border: 1px solid var(--color-primary, #094E8C); box-shadow: 0 8px 24px rgba(15, 35, 60, .18); }
.qd-accions-cap { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px; }
.qd-accions-cos { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
.qd-accio { display: flex; flex-direction: column; gap: 6px; }
.qd-fila { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.qd-fila input[type="time"] { width: 110px; }
.qd-modal-any { max-width: 980px; width: 100%; }
.qd-any { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; max-height: 70vh; overflow: auto; }
.qd-mini-titol { border: 0; background: none; padding: 0 0 4px; font: inherit; font-weight: 700; font-size: .82rem; cursor: pointer; color: var(--color-text, #1e293b); }
.qd-mini-titol:hover { color: var(--color-primary, #094E8C); }
.qd-mini-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.qd-mini-cap { font-size: .6rem; text-align: center; color: var(--color-text-muted, #64748b); }
.qd-mini-dia { font-size: .66rem; text-align: center; border-radius: 4px; padding: 3px 0; }
@media (max-width: 640px) {
  .qd-nom { min-width: 130px; }
  .qd-cerca { max-width: none; flex: 1; }
}
</style>
