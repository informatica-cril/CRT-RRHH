<template>
  <div class="hc">
    <div class="hc-head">
      <div>
        <h1 class="page-title">⏱ Control d'hores · complementàries i extraordinàries</h1>
        <p class="page-subtitle">Els dos comptadors per treballador. Les complementàries són pactades (parcials); les extraordinàries (jornada completa) computen a {{ fmtFactor }}× amb un topall de {{ params.extra_cap_h }} h/any (art. 20.1 del conveni). Cada hora és un compromís de pagament autoritzat.</p>
      </div>
      <select v-model="year" @change="load" class="hc-year">
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <!-- Sol·licituds d'extraordinàries forçades des de domi, pendents d'autoritzar -->
    <div v-if="pendents.length" class="hc-pend">
      <h2 style="margin:0 0 8px">⚠ {{ pendents.length }} sol·licitud(s) d'extraordinàries a autoritzar</h2>
      <p class="dim" style="font-size:.84rem;margin:0 0 12px">Coordinació ha forçat aquestes hores per sobre del contracte. Fins que no les autoritzis (compromís de pagament), domi no les programa.</p>
      <div v-for="p in pendents" :key="p.id" class="hc-req">
        <div class="hc-req-info">
          <b>{{ p.user?.name || ('#'+p.user_id) }}</b>
          <span class="pill">{{ p.hours }} h · {{ fmtFactor }}× = {{ (p.hours * params.extra_factor).toFixed(2) }} h pagades</span>
          <span class="dim">{{ p.period_from }} → {{ p.period_to }} · forçat per {{ p.requested_by }}</span>
          <div v-if="p.reason" class="dim" style="font-size:.8rem;margin-top:2px">{{ p.reason }}</div>
        </div>
        <div class="hc-req-acc">
          <button class="hc-b ok" :disabled="p._busy" @click="autoritzar(p)">✔ Autoritzar</button>
          <button class="hc-b no" :disabled="p._busy" @click="denegar(p)">✖ Denegar</button>
        </div>
      </div>
    </div>

    <div v-if="carregant" class="hc-buida">Carregant els comptadors…</div>
    <div v-else class="hc-scroll">
      <table class="hc-t">
        <thead>
          <tr>
            <th>Treballador</th><th>Règim</th>
            <th colspan="2">Complementàries pactades</th>
            <th colspan="2">Extraordinàries ({{ params.extra_cap_h }} h · {{ fmtFactor }}×)</th>
          </tr>
          <tr class="hc-sub">
            <th></th><th></th>
            <th>Usades / topall</th><th style="min-width:160px">Ocupació</th>
            <th>Usades / {{ params.extra_cap_h }}</th><th style="min-width:160px">Ocupació</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.user_id" :class="{ 'hc-nocalc': r.calculable === false }">
            <td><b>{{ r.name }}</b><br><small class="dim">{{ r.dni || '—' }}</small></td>
            <td>
              <span class="hc-pill" :class="r.regim">{{ regimText(r.regim) }}</span>
            </td>

            <!-- Qui no es pot calcular surt LLISTAT i marcat, mai amagat ni en verd -->
            <template v-if="r.calculable === false">
              <td colspan="4" class="hc-avis">⚠ No es pot calcular el topall d'aquesta persona: {{ r.motiu_no_calculable }}</td>
            </template>
            <template v-else>
            <!-- Complementàries: només tenen sentit en parcials -->
            <td v-if="r.regim === 'parcial'" class="num">{{ r.complementaries.consumides ?? 0 }} h fetes
              <small class="dim">· {{ r.complementaries.usades }} compr. / {{ r.complementaries.cap }} h</small></td>
            <td v-else class="dim">— no aplica</td>
            <td v-if="r.regim === 'parcial'">
              <div class="bar"><div class="fill" :class="col(r.complementaries.pct)" :style="{width: Math.min(100, r.complementaries.pct||0)+'%'}"></div></div>
              <small :class="'t-'+col(r.complementaries.pct)">{{ r.complementaries.pct==null ? '—' : r.complementaries.pct+'%' }}</small>
            </td>
            <td v-else></td>

            <!-- Extraordinàries: només en completes -->
            <td v-if="r.regim === 'completa'" class="num">{{ r.extraordinaries.consumides ?? 0 }} h fetes
              <small class="dim">· {{ r.extraordinaries.usades }} autoritzades / {{ r.extraordinaries.cap }} h (={{ r.extraordinaries.cost_equivalent_h }} h pagades)</small></td>
            <td v-else class="dim">— no aplica</td>
            <td v-if="r.regim === 'completa'">
              <div class="bar"><div class="fill" :class="col(r.extraordinaries.pct)" :style="{width: Math.min(100, r.extraordinaries.pct||0)+'%'}"></div></div>
              <small :class="'t-'+col(r.extraordinaries.pct)">{{ r.extraordinaries.pct }}%</small>
            </td>
            <td v-else></td>
            </template>
          </tr>
          <tr v-if="!rows.length"><td colspan="6" class="dim" style="text-align:center;padding:24px">Cap treballador amb control horari.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'

// Els paràmetres arriben amb les dades; aquests valors només eviten una pantalla
// buida mentre carrega i coincideixen amb Support\Jornada del servidor.
const params = ref({ extra_cap_h: 80, extra_factor: 1.25, jornada_anual_h: 1726 })
const fmtFactor = computed(() => String(params.value.extra_factor).replace('.', ','))

const now = new Date().getFullYear()
const years = [now, now - 1, now - 2]
const year = ref(now)
const rows = ref([])
const pendents = ref([])
const carregant = ref(true)

async function loadPendents() {
  try {
    const r = await db.getOvertimeRequests('pending')
    pendents.value = (Array.isArray(r) ? r : (r?.data ?? [])).map(p => ({ ...p, _busy: false }))
  } catch (e) { pendents.value = [] }
}
async function autoritzar(p) {
  p._busy = true
  try { await db.authorizeOvertime(p.id); await loadPendents(); await load() }
  catch (e) { p._busy = false; alert(e?.message || 'No s\'ha pogut autoritzar.') }
}
async function denegar(p) {
  // El motiu és OBLIGATORI: qui rep la denegació ha de saber per què.
  const reason = await demana('Motiu de la denegació (obligatori):')
  if (reason === null) return
  if (!reason.trim()) { alert('Cal escriure el motiu de la denegació.'); return }
  p._busy = true
  try { await db.denyOvertime(p.id, reason); await loadPendents() }
  catch (e) { p._busy = false; alert(e?.message || 'No s\'ha pogut denegar.') }
}

// El verd és per al que s'ha pogut calcular i està per sota del llindar. Un percentatge
// desconegut NO és «tot correcte»: es marca com a desconegut i es veu gris.
function col(pct) {
  if (pct == null) return 'nocalc'
  if (pct >= 100) return 'alta'
  if (pct >= 85) return 'mitjana'
  return 'ok'
}

function regimText(regim) {
  if (regim === 'completa') return 'Jornada completa'
  if (regim === 'parcial') return 'Parcial'
  return '? Sense jornada'
}

async function load() {
  carregant.value = true
  try {
    const r = await db.getHoursControl(year.value)
    rows.value = r?.rows ?? r?.data?.rows ?? []
    // Els paràmetres de jornada els mana el servidor (font única): no es reescriuen aquí.
    const p = r?.parametres ?? r?.data?.parametres
    if (p) params.value = p
  } catch (e) { rows.value = [] }
  carregant.value = false
}
onMounted(() => { load(); loadPendents() })
</script>

<style scoped>
.hc-head { display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 10px; }
.hc-head > div { flex: 1; }
.hc-year { border: 1px solid var(--color-border, #DCE4EE); border-radius: 8px; padding: 8px 12px; font-size: .9rem; }
.hc-scroll { overflow-x: auto; border: 1px solid var(--color-border, #DCE4EE); border-radius: 12px; background: var(--color-surface, #fff); }
.hc-t { border-collapse: collapse; width: 100%; font-size: .84rem; }
.hc-t th { background: var(--color-bg-subtle, #EEF5FC); padding: 8px 12px; text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-muted, #5D6B7E); }
.hc-t th[colspan] { text-align: center; border-left: 1px solid var(--color-border, #DCE4EE); }
.hc-sub th { text-transform: none; font-weight: 600; }
.hc-t td { padding: 9px 12px; border-top: 1px solid var(--color-border, #DCE4EE); vertical-align: middle; }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.dim { color: var(--color-text-muted, #7a8aa0); }
.hc-pill { display: inline-block; border-radius: 99px; padding: 2px 10px; font-size: .72rem; font-weight: 700; }
.hc-pill.completa { background: #eef5fc; color: #00806C; }
.hc-pill.parcial { background: #fff7ec; color: #96600F; }
.hc-pill.desconegut { background: #f2e6e6; color: #B3352F; }
.hc-nocalc { background: #fdf6f5; }
.hc-avis { color: #B3352F; font-weight: 600; }
.bar { height: 9px; border-radius: 5px; background: #eef2f7; overflow: hidden; }
.fill { height: 100%; border-radius: 5px; }
.fill.ok { background: #0DAF83; } .fill.mitjana { background: #D9A400; } .fill.alta { background: #B3352F; }
.fill.nocalc { background: #b6c2d1; }
.t-ok { color: #0A7C5E; } .t-mitjana { color: #96600F; } .t-alta { color: #B3352F; font-weight: 700; }
.t-nocalc { color: #7a8aa0; font-weight: 700; }
.hc-buida { padding: 26px; text-align: center; color: var(--color-text-muted, #7a8aa0); }
.hc-pend { background: #fff7ec; border: 1px solid #f0d9a8; border-left: 5px solid #D9A400; border-radius: 13px; padding: 14px 18px; margin: 8px 0 18px; }
.hc-req { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; background: var(--color-surface, #fff); border: 1px solid var(--color-border, #DCE4EE); border-radius: 10px; padding: 10px 14px; margin-top: 8px; }
.hc-req-info { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 3px; }
.hc-req-info .pill { margin: 0; }
.hc-req-acc { display: flex; gap: 8px; }
.hc-b { border: 1.5px solid var(--color-border, #DCE4EE); background: var(--color-surface, #fff); border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: .82rem; cursor: pointer; min-height: 40px; }
.hc-b.ok { border-color: #0DAF83; color: #0A7C5E; }
.hc-b.no { border-color: #e0a9a5; color: #B3352F; }
.hc-b:disabled { opacity: .5; cursor: not-allowed; }
</style>
