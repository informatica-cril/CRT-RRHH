<template>
  <div class="tr">
    <div class="tr-cap" data-tour="cap">
      <div>
        <h1 class="page-title">{{ salutacio }}, {{ nomCurt }}</h1>
        <p class="page-subtitle">{{ avui }} · Aquí només surt el que has de fer. La resta és a «Totes les opcions».</p>
      </div>
      <button type="button" class="btn btn-secondary" @click="iniciaRecorregut">🧭 Ensenya'm com funciona</button>
    </div>

    <!-- Recorregut guiat: va marcant cada part del tauler i explica per a què serveix. -->
    <div v-if="pas >= 0" class="tr-guia" role="dialog" aria-live="polite">
      <div class="tr-guia-pas">Recorregut guiat · pas {{ pas + 1 }} de {{ PASSOS.length }}</div>
      <div class="tr-guia-text">{{ PASSOS[pas].text }}</div>
      <div class="tr-guia-botons">
        <button type="button" class="btn btn-secondary" @click="tancaRecorregut">Tancar</button>
        <button v-if="pas > 0" type="button" class="btn btn-secondary" @click="anarA(pas - 1)">← Enrere</button>
        <button type="button" class="btn btn-primary" @click="pas < PASSOS.length - 1 ? anarA(pas + 1) : tancaRecorregut()">
          {{ pas < PASSOS.length - 1 ? 'Següent →' : 'Entesos' }}
        </button>
      </div>
    </div>

    <div v-if="carregant" class="tr-buit">Mirant què tens pendent…</div>
    <div v-else-if="error" class="tr-buit">No s'han pogut carregar els pendents. Torna-ho a provar d'aquí a una estona.</div>

    <template v-else>
      <section data-tour="avui">
        <h2 class="tr-seccio">Cada dia</h2>
        <div class="tr-graella">
          <TargetaTasca v-for="tasca in diaries" :key="tasca.clau" :tasca="tasca" :n="n(tasca)"
            :ajuda-oberta="ajuda === tasca.clau" @ajuda="commutaAjuda(tasca.clau)" />
        </div>
      </section>

      <section data-tour="setmana">
        <h2 class="tr-seccio">Aquesta setmana</h2>
        <div class="tr-graella petita">
          <TargetaTasca v-for="tasca in setmanals" :key="tasca.clau" :tasca="tasca" :n="n(tasca)" petita
            :ajuda-oberta="ajuda === tasca.clau" @ajuda="commutaAjuda(tasca.clau)" />
        </div>
      </section>

      <section data-tour="rapid">
        <h2 class="tr-seccio">Accions ràpides</h2>
        <div class="tr-rapides">
          <router-link v-for="a in RAPIDES" :key="a.nom" :to="a.to" class="tr-rapida" :class="{ discreta: a.discreta }">
            <span class="tr-rapida-ico">{{ a.icona }}</span>
            <span><strong>{{ a.nom }}</strong><small>{{ a.ajuda }}</small></span>
          </router-link>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, h } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../services/apiClient'
import { useAuthStore } from '../stores/auth'

// Tauler senzill per a RRHH: les mateixes cues que la Safata (/v1/safata/resum), però ordenades
// per quan toca fer-les i amb una explicació curta a cada una. Les que estan a zero també surten
// («Tot al dia»), perquè la persona sàpiga que hi són i que no ha de fer res.
const DIARIES = [
  { clau: 'fitxatges', cues: ['fitxatges'], icona: '⏱', titol: 'Fitxatges per validar', to: '/work-logs', boto: 'Revisar fitxatges',
    que: 'Jornades acabades que encara ningú ha aprovat.',
    ajuda: "Obre cada fitxatge, mira que l'entrada i la sortida quadrin i prem Aprovar. Si alguna cosa no quadra, prem Rebutjar: l'app avisa la persona i li dona 7 dies per explicar-ho abans de rebutjar-lo del tot." },
  { clau: 'sensesortida', cues: ['sensesortida'], icona: '🚪', titol: 'Sortides oblidades', to: '/safata', boto: 'Veure qui és',
    que: "Persones que van entrar però no van fitxar la sortida.",
    ajuda: "Aquella jornada compta 0 hores fins que es resol. La persona rep un correu cada matí perquè digui a quina hora va sortir. Quan ho hagi dit, obre el fitxatge, prem Corregir hora i després aprova'l." },
  { clau: 'absencies', cues: ['absencies'], icona: '📋', titol: 'Permisos per aprovar', to: '/absences', boto: 'Revisar permisos',
    que: 'Vacances, permisos i dies lliures que han demanat.',
    ajuda: 'Mira les dates i el motiu (i el justificant, si n\'hi ha) i prem Aprovar o Denegar. La persona veu la resposta a la seva app.' },
  { clau: 'horesextra', cues: ['horesextra'], icona: '⏰', titol: 'Hores de més per autoritzar', to: '/auth-codes', boto: 'Revisar hores',
    que: "Hores fetes fora d'horari que algú demana que li comptin.",
    ajuda: "Si les hores estaven justificades, prem Autoritzar; si no, Denegar. Compte: cada hora autoritzada s'ha de pagar, i les extraordinàries tenen un màxim de 80 hores l'any." },
  { clau: 'incidencies', cues: ['incidencies'], icona: '🛎️', titol: 'Incidències', to: '/incidencies', boto: 'Respondre',
    que: 'Problemes o dubtes que t\'han escrit els treballadors.',
    ajuda: "Per exemple, «no em deixa fitxar» o «la meva hora està malament». Obre-la, llegeix-la i respon des d'allà mateix. Quan estigui solucionada, marca-la com a resolta." },
  { clau: 'drets', cues: ['drets'], icona: '🔐', titol: 'Peticions de dades personals', to: '/privacy', boto: 'Respondre', nomesSiN: true,
    que: 'Algú vol veure, corregir o esborrar les seves dades.',
    ajuda: "És un dret per llei (RGPD) i s'ha de respondre en menys d'un mes. Si tens dubtes, consulta-ho amb Informàtica abans de respondre." },
]
const SETMANALS = [
  { clau: 'excedencies', cues: ['excedencies'], icona: '📄', titol: 'Excedències', to: '/excedencies', boto: 'Revisar',
    que: 'Peticions per deixar de treballar una temporada.',
    ajuda: "Revisa la petició i prem ✓ per aprovar-la o ✗ per rebutjar-la." },
  { clau: 'comite', cues: ['comite'], icona: '🤝', titol: 'Hores de comitè', to: '/comite', boto: 'Validar',
    que: 'Hores que els representants dels treballadors diuen que han fet.',
    ajuda: 'Els representants tenen unes hores al mes per a la feina sindical. Comprova-les i valida-les.' },
  { clau: 'sensehorari', cues: ['sensehorari'], icona: '🗓️', titol: 'Persones sense horari', to: '/safata', boto: 'Veure qui és',
    que: "Treballadors que encara no tenen cap horari assignat.",
    ajuda: "Sense horari, l'app no pot calcular les hores de més ni la pausa. Obre la fitxa de la persona i tria-li un horari." },
  { clau: 'disciplinari', cues: ['disciplinari'], icona: '⚖️', titol: 'Avisos per revisar', to: '/disciplinary', boto: 'Revisar',
    que: "Casos que l'app creu que potser cal mirar.",
    ajuda: "L'app només ho suggereix: no obre res sola. Mira-ho i decideix tu si cal fer alguna cosa." },
]
const RAPIDES = [
  { nom: 'Buscar un treballador', ajuda: 'Veure o canviar la seva fitxa', icona: '🔎', to: '/employees' },
  { nom: "Donar d'alta una persona", ajuda: 'Algú nou comença a treballar', icona: '➕', to: { path: '/employees', query: { obre: 'nou' } } },
  { nom: 'Informe del mes', ajuda: 'Les hores de tothom, en PDF o Excel', icona: '📊', to: '/reports' },
  { nom: 'Qui treballa aquesta setmana', ajuda: 'Horaris de tota la plantilla', icona: '👥', to: '/disponibilitat' },
  { nom: 'Totes les opcions', ajuda: 'La resta de pantalles', icona: '☰', to: '/admin/menu', discreta: true },
]
const PASSOS = [
  { on: 'cap', text: "Hola! Aquest és el teu tauler. Aquí només surt el que has de fer, i res més." },
  { on: 'avui', text: "Això és la feina de cada dia. Cada targeta és una tasca, i el número diu quantes coses tens pendents. Si surt «Tot al dia» en verd, no has de fer res." },
  { on: 'boto', text: "Prem el botó de la targeta i aniràs directament a la pantalla on es fa aquella feina." },
  { on: 'ajuda', text: "Si no saps què és alguna cosa, prem el botó ? i t'explicarem què és i què has de fer." },
  { on: 'setmana', text: "Això no corre tant: n'hi ha prou que ho miris un cop a la setmana." },
  { on: 'rapid', text: "I aquí tens les coses que es fan de tant en tant: buscar algú, donar d'alta una persona o treure l'informe del mes. Si busques una altra pantalla, és a «Totes les opcions»." },
]
const CLAU_VIST = 'crt_tauler_recorregut_vist'

const authStore = useAuthStore()
const carregant = ref(true)
const error = ref(false)
const cues = ref({})
const ajuda = ref(null)
const pas = ref(-1)

const nomCurt = computed(() => (authStore.user?.name || '').split(' ')[0])
const salutacio = computed(() => { const h = new Date().getHours(); return h < 14 ? 'Bon dia' : h < 21 ? 'Bona tarda' : 'Bona nit' })
const avui = computed(() => {
  const s = new Date().toLocaleDateString('ca-ES', { weekday: 'long', day: 'numeric', month: 'long' })
  return s.charAt(0).toUpperCase() + s.slice(1)
})
const n = (tasca) => tasca.cues.reduce((s, c) => s + (cues.value[c] || 0), 0)
const diaries = computed(() => DIARIES.filter(t => !t.nomesSiN || n(t) > 0))
const setmanals = computed(() => SETMANALS)

function commutaAjuda(clau) { ajuda.value = ajuda.value === clau ? null : clau }

async function carrega() {
  try {
    const r = await api.get('/v1/safata/resum')
    cues.value = Object.fromEntries((r?.cues || []).map(c => [c.clau, Number(c.n) || 0]))
    error.value = false
  } catch (e) {
    console.error(e)
    error.value = true
  } finally {
    carregant.value = false
  }
}

// ── Recorregut guiat ─────────────────────────────────────────────────────────
function marca(on) {
  document.querySelectorAll('.tr-marcat').forEach(el => el.classList.remove('tr-marcat'))
  const el = document.querySelector(`[data-tour="${on}"]`)
  if (!el) return
  el.classList.add('tr-marcat')
  el.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
async function anarA(i) {
  pas.value = i
  // Els passos del botó i de l'ajuda marquen la primera targeta; l'ajuda només és oberta al seu pas.
  ajuda.value = PASSOS[i].on === 'ajuda' ? diaries.value[0]?.clau : null
  await nextTick()
  marca(PASSOS[i].on)
}
function iniciaRecorregut() { ajuda.value = null; anarA(0) }
function tancaRecorregut() {
  pas.value = -1
  ajuda.value = null
  document.querySelectorAll('.tr-marcat').forEach(el => el.classList.remove('tr-marcat'))
  try { localStorage.setItem(CLAU_VIST, '1') } catch (e) {}
}

let interval = null
const enCanviar = () => carrega()
onMounted(async () => {
  await carrega()
  interval = setInterval(carrega, 60000)
  window.addEventListener('crt:canvi-dades', enCanviar)
  // La primera vegada que s'entra, el recorregut comença sol.
  let vist = true
  try { vist = !!localStorage.getItem(CLAU_VIST) } catch (e) {}
  if (!vist && !error.value) anarA(0)
})
onUnmounted(() => {
  clearInterval(interval)
  window.removeEventListener('crt:canvi-dades', enCanviar)
  document.querySelectorAll('.tr-marcat').forEach(el => el.classList.remove('tr-marcat'))
})

// Targeta d'una tasca: número gran, què és, botó per anar-hi i «?» amb l'explicació.
const TargetaTasca = {
  props: { tasca: Object, n: Number, petita: Boolean, ajudaOberta: Boolean },
  emits: ['ajuda'],
  setup(props, { emit }) {
    return () => {
      const t = props.tasca
      const alDia = props.n === 0
      const primera = t === DIARIES[0]
      return h('div', { class: ['tr-targeta', { petita: props.petita, aldia: alDia }] }, [
        h('button', { type: 'button', class: 'tr-q', 'aria-label': `Què és «${t.titol}»?`, title: 'Què és això?',
          'data-tour': primera ? 'ajuda' : undefined, onClick: () => emit('ajuda') }, '?'),
        h('div', { class: 'tr-t-cap' }, [h('span', { class: 'tr-t-ico' }, t.icona), h('span', { class: 'tr-t-titol' }, t.titol)]),
        alDia
          ? h('div', { class: 'tr-t-ok' }, '✓ Tot al dia')
          : h('div', { class: 'tr-t-n' }, [String(props.n), h('small', props.n === 1 ? ' pendent' : ' pendents')]),
        h('div', { class: 'tr-t-que' }, t.que),
        props.ajudaOberta ? h('div', { class: 'tr-t-ajuda' }, t.ajuda) : null,
        h(RouterLink, { to: t.to, class: ['btn', alDia ? 'btn-secondary' : 'btn-primary', 'tr-t-boto'],
          'data-tour': primera ? 'boto' : undefined }, () => (alDia ? 'Obrir' : `${t.boto} →`)),
      ])
    }
  },
}
</script>

<style scoped>
.tr { padding-bottom: 100px; }
.tr-cap { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
.tr-seccio { font-size: 1.05rem; font-weight: 700; margin: 24px 0 12px; color: var(--color-text); }
.tr-graella { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.tr-graella.petita { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
.tr-buit { padding: 40px 0; text-align: center; color: var(--color-text-secondary); }

.tr :deep(.tr-targeta) { position: relative; display: flex; flex-direction: column; gap: 8px; padding: 18px 20px;
  background: var(--color-bg-card); border: 1px solid var(--color-border); border-left: 5px solid var(--color-warning); border-radius: 12px; }
.tr :deep(.tr-targeta.aldia) { border-left-color: var(--color-success); }
.tr :deep(.tr-targeta.petita) { padding: 14px 16px; }
.tr :deep(.tr-q) { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border-radius: 50%;
  border: 1px solid var(--color-border); background: var(--color-bg); color: var(--color-text-secondary);
  font-weight: 700; cursor: pointer; }
.tr :deep(.tr-q:hover) { border-color: var(--color-primary); color: var(--color-primary); }
.tr :deep(.tr-t-cap) { display: flex; align-items: center; gap: 10px; padding-right: 36px; }
.tr :deep(.tr-t-ico) { font-size: 1.4rem; }
.tr :deep(.tr-t-titol) { font-weight: 700; font-size: 1rem; }
.tr :deep(.tr-t-n) { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--color-warning); }
.tr :deep(.tr-t-n small) { font-size: .85rem; font-weight: 600; color: var(--color-text-secondary); }
.tr :deep(.tr-targeta.petita .tr-t-n) { font-size: 1.5rem; }
.tr :deep(.tr-t-ok) { font-size: 1.1rem; font-weight: 700; color: var(--color-success); }
.tr :deep(.tr-t-que) { font-size: .88rem; color: var(--color-text-secondary); }
.tr :deep(.tr-t-ajuda) { font-size: .88rem; line-height: 1.5; padding: 10px 12px; border-radius: 8px;
  background: var(--color-primary-bg); color: var(--color-text); }
.tr :deep(.tr-t-boto) { margin-top: auto; justify-content: center; min-height: 42px; text-decoration: none; }

.tr-rapides { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.tr-rapida { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; text-decoration: none;
  background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text); }
.tr-rapida:hover { border-color: var(--color-primary); }
.tr-rapida.discreta { background: transparent; }
.tr-rapida-ico { font-size: 1.3rem; }
.tr-rapida strong { display: block; font-size: .92rem; }
.tr-rapida small { display: block; font-size: .8rem; color: var(--color-text-secondary); }

.tr-guia { position: sticky; top: 8px; z-index: 20; margin: 12px 0; padding: 14px 16px; border-radius: 12px;
  background: var(--color-bg-card); border: 2px solid var(--color-primary); box-shadow: var(--shadow-md); }
.tr-guia-pas { font-size: .78rem; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 4px; }
.tr-guia-text { font-size: .98rem; line-height: 1.5; }
.tr-guia-botons { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; margin-top: 12px; }

.tr :deep(.tr-marcat) { outline: 3px solid var(--color-primary); outline-offset: 6px; border-radius: 12px; }
.tr section.tr-marcat .tr-seccio { margin-top: 12px; }
</style>
