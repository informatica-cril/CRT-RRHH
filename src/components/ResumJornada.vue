<template>
  <!-- Resum del saldo de jornada de l'any (dades de /users/{id}/jornada): el saldo a la vista,
       què vol dir en paraules, el progrés cap al màxim, la diferència per mes i el detall per trams. -->
  <div class="resum-jornada">
    <div class="rj-capcalera">
      <div class="text-small text-muted">Dades a {{ fmtData(avui) }} · el còmput es tanca el 31/12</div>
      <div v-if="trams.length" class="rj-xips">
        <span v-for="(t, ti) in trams" :key="ti" class="rj-xip" :class="{ vigent: t.des <= avui && avui <= t.fins }">
          Període {{ ti + 1 }} · {{ fmtPct(t.pct) }} % · {{ curt(t.des) }}–{{ curt(t.fins) }}
        </span>
      </div>
    </div>

    <template v-if="trams.length">
      <div class="rj-targetes">
        <div class="rj-targeta">
          <div class="rj-etiqueta">Saldo a dia d'avui</div>
          <div class="rj-saldo" :class="color(saldo)">{{ fmtSaldo(saldo) }}</div>
          <div class="rj-frase" :class="color(saldo)">{{ fraseSaldo }}</div>
          <div class="text-small text-muted">{{ Math.abs(saldo).toLocaleString('ca-ES') }} minuts</div>
        </div>

        <div class="rj-targeta">
          <div class="rj-parella">
            <span>A retornar al treballador</span>
            <strong class="positiu">{{ saldo > 0 ? fmtHm(saldo) : '0 min' }}</strong>
          </div>
          <div class="rj-separador"></div>
          <div class="rj-parella">
            <span>Pendent de fer pel treballador</span>
            <strong class="negatiu">{{ saldo < 0 ? fmtHm(saldo) : '0 min' }}</strong>
          </div>
          <div class="text-small text-muted">Si l'any acabés avui. Complementàries i extres autoritzades van a part.</div>
        </div>

        <div class="rj-targeta">
          <div class="rj-etiqueta">Progrés de l'any</div>
          <div><strong>{{ fmtCurt(fet) }}</strong> fetes de <strong>{{ fmtCurt(maxim) }}</strong> màximes</div>
          <div class="rj-progres" role="img" :aria-label="`Fet ${pctFet} % del màxim; tocava ${pctToc} %`">
            <div class="rj-progres-fet" :style="{ width: Math.min(pctFet, 100) + '%' }"></div>
            <div class="rj-progres-marca" :style="{ left: Math.min(pctToc, 100) + '%' }"></div>
          </div>
          <div class="rj-peu-progres text-small text-muted">
            <span>Fet {{ Math.round(pctFet) }} % del màxim</span>
            <span>Marca: tocava fins avui ({{ fmtCurt(tocava) }})</span>
          </div>
        </div>

        <div class="rj-targeta">
          <div class="rj-etiqueta">Per tancar l'any a zero</div>
          <template v-if="restaTocar > 0">
            <div>Queden <strong>{{ fmtHm(restaTocar) }}</strong> previstes fins al 31/12.</div>
            <div v-if="saldo < 0">Hauria de fer-ne <strong>{{ fmtHm(restaFer) }}</strong>, és a dir, <strong class="negatiu">{{ fmtHm(saldo) }}</strong> més del previst.</div>
            <div v-else-if="saldo > 0">En té prou amb <strong>{{ fmtHm(restaFer) }}</strong>, és a dir, <strong class="positiu">{{ fmtHm(saldo) }}</strong> menys del previst.</div>
            <div v-else>Fent el previst, l'any tanca a zero.</div>
          </template>
          <div v-else>Ja no queden hores previstes aquest any.</div>
        </div>
      </div>
    </template>

    <div v-if="avisos.length" class="rj-revisa">
      <div class="rj-revisa-titol">Revisa abans de tancar el saldo</div>
      <div v-for="(a, i) in avisos" :key="i" class="rj-avis">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
        </svg>
        <span><strong>{{ a.titol }}</strong> {{ a.text }}</span>
      </div>
    </div>

    <template v-if="trams.length">
      <div v-if="grafic.length" class="rj-bloc">
        <div class="rj-bloc-cap">
          <h3>Diferència per mes</h3>
          <div class="rj-llegenda text-small text-muted">
            <span><i class="positiu-fons"></i>Ha fet més del que tocava</span>
            <span><i class="negatiu-fons"></i>Ha fet menys</span>
          </div>
        </div>
        <div class="rj-desplaca">
          <div class="rj-grafic">
            <div v-for="m in grafic" :key="m.mes" class="rj-col" :title="`${NOMS_MES[m.mes - 1]}: ${fmtSaldo(m.dif)}`">
              <div class="rj-valor positiu">{{ m.dif > 0 ? fmtSaldo(m.dif) : '' }}</div>
              <div class="rj-meitat amunt"><div class="positiu-fons" :style="{ height: m.dif > 0 ? m.h + 'px' : '0' }"></div></div>
              <div class="rj-eix"></div>
              <div class="rj-meitat avall"><div class="negatiu-fons" :style="{ height: m.dif < 0 ? m.h + 'px' : '0' }"></div></div>
              <div class="rj-valor negatiu">{{ m.dif < 0 ? fmtSaldo(m.dif) : '' }}</div>
              <div class="rj-mes">{{ NOMS_MES[m.mes - 1].slice(0, 3) }}</div>
              <div class="rj-acum text-muted">acum. {{ fmtSaldo(m.acum) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rj-bloc">
        <h3>Detall per períodes de contracte</h3>
        <div class="rj-desplaca">
          <table class="rj-taula">
            <thead>
              <tr>
                <th>Mes</th><th>Tocava</th><th>Fet</th><th>Compliment</th><th>Diferència</th><th>Acumulat</th>
                <th v-if="ambCompl">Complem.</th><th v-if="ambExtres">Extres</th>
              </tr>
            </thead>
            <tbody v-for="(t, ti) in trams" :key="ti">
              <tr class="rj-fila-tram">
                <td :colspan="columnes">
                  <span class="rj-tram-nom">
                    Període {{ ti + 1 }} · {{ fmtPct(t.pct) }} % · {{ fmtHores(t.hores_setmanals) }}/setmana<template v-if="t.motiu"> · {{ t.motiu }}</template>
                    · {{ curt(t.des) }}–{{ curt(t.fins) }}
                  </span>
                  <span class="text-small text-muted">
                    Màxim {{ fmtCurt(t.maxim_min) }} · mòdul {{ fmtCurt(t.modul_diari_min) }} ·
                    saldo <strong :class="color(t.totals.saldo_min)">{{ fmtSaldo(t.totals.saldo_min) }}</strong>
                  </span>
                </td>
              </tr>
              <tr v-for="m in filesTram(t)" :key="m.mes">
                <td class="rj-nom-mes">{{ NOMS_MES[m.mes - 1] }}</td>
                <td>{{ fmtCurt(m.esperat_min) }}</td>
                <td>{{ fmtCurt(m.ordinari_min) }}<span v-if="m.pendents_validar_min" class="text-muted" title="Inclou fitxatges pendents de validar"> *</span></td>
                <td>
                  <span v-if="m.esperat_min > 0" class="rj-compliment">
                    <span class="rj-barreta"><span :class="m.ordinari_min >= m.esperat_min ? 'positiu-fons' : 'negatiu-suau'" :style="{ width: Math.min(m.ordinari_min / m.esperat_min, 1) * 100 + '%' }"></span></span>
                    <span class="rj-pct">{{ Math.round(m.ordinari_min / m.esperat_min * 100) }} %</span>
                  </span>
                </td>
                <td><span v-if="m.diferencia_min != null" class="rj-etiq" :class="m.diferencia_min >= 0 ? 'etiq-positiu' : 'etiq-negatiu'">{{ fmtSaldo(m.diferencia_min) }}</span></td>
                <td :class="color(m.acumulat_min)" style="font-weight:600;">{{ m.acumulat_min == null ? '' : fmtSaldo(m.acumulat_min) }}</td>
                <td v-if="ambCompl">{{ m.complementaries_min ? fmtCurt(m.complementaries_min) : '' }}</td>
                <td v-if="ambExtres">{{ m.extres_min ? fmtCurt(m.extres_min) : '' }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td>Total {{ jornada.any }}</td>
                <td>{{ fmtCurt(tocava) }}</td>
                <td>{{ fmtCurt(fet) }}</td>
                <td class="text-small text-muted" style="font-weight:400;">màxim {{ fmtCurt(maxim) }}</td>
                <td :class="color(saldo)">{{ fmtSaldo(saldo) }}</td>
                <td></td>
                <td v-if="ambCompl">{{ fmtCurt(jornada.totals.complementaries_min) }}</td>
                <td v-if="ambExtres">{{ fmtCurt(jornada.totals.extres_min) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="text-small text-muted">
          «Tocava» = mòdul diari × dies laborables sense absències aprovades, fins avui. La diferència s'acumula: el còmput és a final d'any.
          Complementàries i extres autoritzades van a part<template v-if="!ambCompl && !ambExtres"> (aquest any no n'hi ha cap)</template>.
          * inclou fitxatges pendents de validar.
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  jornada: { type: Object, required: true },
})

const NOMS_MES = ['Gener', 'Febrer', 'Març', 'Abril', 'Maig', 'Juny', 'Juliol', 'Agost', 'Setembre', 'Octubre', 'Novembre', 'Desembre']
const avui = new Date().toLocaleDateString('en-CA', { timeZone: 'Europe/Madrid' })

const fmtCurt = (m) => `${Math.floor(Math.abs(m) / 60)}:${String(Math.abs(m) % 60).padStart(2, '0')}`
const fmtSaldo = (m) => (m > 0 ? '+' : m < 0 ? '−' : '') + fmtCurt(m)
const fmtHm = (m) => `${Math.floor(Math.abs(m) / 60)} h ${String(Math.abs(m) % 60).padStart(2, '0')} min`
const fmtPct = (p) => Number(p).toLocaleString('ca-ES', { maximumFractionDigits: 2 })
const fmtHores = (h) => `${Number(h).toLocaleString('ca-ES', { maximumFractionDigits: 2 })} h`
const fmtData = (d) => (d ? d.split('-').reverse().join('/') : '')
const curt = (d) => fmtData(d).slice(0, 5)
const color = (m) => (m == null || m === 0 ? '' : m > 0 ? 'positiu' : 'negatiu')

const trams = computed(() => props.jornada.trams || [])
const saldo = computed(() => props.jornada.totals?.acumulat_min || 0)
const tocava = computed(() => props.jornada.totals?.esperat_min || 0)
const fet = computed(() => props.jornada.totals?.ordinari_min || 0)
const maxim = computed(() => props.jornada.maxim_anual_min || 0)
const pctFet = computed(() => (maxim.value ? fet.value / maxim.value * 100 : 0))
const pctToc = computed(() => (maxim.value ? tocava.value / maxim.value * 100 : 0))
// El que queda previst és el màxim menys el que tocava fins avui; per tancar a zero cal
// fer-ho i, a més, recuperar el saldo negatiu (o es pot fer menys si és positiu).
const restaTocar = computed(() => Math.max(0, maxim.value - tocava.value))
const restaFer = computed(() => Math.max(0, restaTocar.value - saldo.value))
const fraseSaldo = computed(() => saldo.value > 0 ? `L'empresa ha de retornar ${fmtHm(saldo.value)}`
  : saldo.value < 0 ? `El treballador ha de recuperar ${fmtHm(saldo.value)}` : 'Al dia: no hi ha res a recuperar ni a retornar')

// Mesos de l'any amb dades fins avui (els que encara no han arribat no surten)
const grafic = computed(() => {
  const mesos = (props.jornada.mesos || []).filter(m => m.diferencia_min != null && (m.esperat_min > 0 || m.ordinari_min > 0))
  const escala = Math.max(1, ...mesos.map(m => Math.abs(m.diferencia_min)))
  return mesos.map(m => ({ mes: m.mes, dif: m.diferencia_min, acum: m.acumulat_min, h: Math.max(2, Math.round(Math.abs(m.diferencia_min) / escala * 90)) }))
})

const filesTram = (t) => t.mesos.filter(m => m.diferencia_min != null || m.ordinari_min > 0)
const ambCompl = computed(() => (props.jornada.totals?.complementaries_min || 0) > 0)
const ambExtres = computed(() => (props.jornada.totals?.extres_min || 0) > 0)
const columnes = computed(() => 6 + (ambCompl.value ? 1 : 0) + (ambExtres.value ? 1 : 0))

// Avisos del que sol estar malament abans de donar el saldo per bo
const avisos = computed(() => {
  const llista = []
  const j = props.jornada

  // Mesos seguits sense cap fitxatge (amb almenys 3 dies que tocaven, per no avisar el dia 1)
  const buits = (j.mesos || []).map(m => m.diferencia_min != null && m.esperat_min > 0 && !m.ordinari_min
    && !m.complementaries_min && !m.extres_min && !m.pendents_validar_min && (m.dies_comptats == null || m.dies_comptats >= 3))
  for (let i = 0; i < 12; i++) {
    if (!buits[i]) continue
    let f = i
    while (f + 1 < 12 && buits[f + 1]) f++
    const dif = j.mesos.slice(i, f + 1).reduce((s, m) => s + m.diferencia_min, 0)
    const nom = i === f ? NOMS_MES[i] : `${NOMS_MES[i]}–${NOMS_MES[f].toLowerCase()}`
    llista.push({ titol: `${nom} sense cap fitxatge (${fmtSaldo(dif)}).`,
      text: "Si fitxava al Portal de l'Empleat, cal importar-ho; si no, reviseu la data d'inici del % de contracte." })
    i = f
  }

  // Dies laborables que no compten per absències de dia sencer (camps nous del servidor)
  const varisTrams = (j.trams || []).length > 1
  const dies = []
  ;(j.trams || []).forEach((t, ti) => t.mesos.forEach(m => {
    if (m.dies_laborables == null || m.dies_comptats >= m.dies_laborables) return
    dies.push(`${NOMS_MES[m.mes - 1].toLowerCase()}${varisTrams ? ` (període ${ti + 1})` : ''}: ${m.dies_laborables} laborables, se'n compten ${m.dies_comptats}`)
  }))
  if (dies.length) {
    llista.push({ titol: 'Dies que no compten per absències aprovades.',
      text: `${dies.join('; ')}. Comproveu que les absències siguin correctes.` })
  }

  if (j.sense_contracte) {
    llista.push({ titol: `${fmtHm(j.sense_contracte.ordinari_min)} fitxades sense % de contracte`,
      text: `entre el ${fmtData(j.sense_contracte.des)} i el ${fmtData(j.sense_contracte.fins)}: no compten al saldo. Afegiu el període d'aquelles dates a «Canviar el %».` })
  }
  return llista
})
</script>

<style scoped>
.resum-jornada {
  --rj-positiu: #1d5fbf; --rj-positiu-fons: #e3edfb; --rj-positiu-text: #123f80;
  --rj-negatiu: #b4530a; --rj-negatiu-barra: #c2570c; --rj-negatiu-suau: #e08a4a; --rj-negatiu-fons: #fdeadb; --rj-negatiu-text: #7a3606;
  --rj-revisa-fons: #fff7ed; --rj-revisa-vora: #f3c99b; --rj-revisa-text: #3d2410;
  display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px; font-variant-numeric: tabular-nums;
}
.positiu { color: var(--rj-positiu); }
.negatiu { color: var(--rj-negatiu); }
.positiu-fons { background: var(--rj-positiu); }
.negatiu-fons { background: var(--rj-negatiu-barra); }
.negatiu-suau { background: var(--rj-negatiu-suau); }

.rj-capcalera { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; }
.rj-xips { display: flex; flex-wrap: wrap; gap: 6px; }
.rj-xip { font-size: 0.78rem; padding: 4px 10px; border-radius: 999px; background: var(--color-bg-hover, #edf2f7); color: var(--color-text); }
.rj-xip.vigent { background: var(--color-text); color: var(--color-bg-card, #fff); }

.rj-targetes { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
.rj-targeta { border: 1px solid var(--color-border, #e2e8f0); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; background: var(--color-bg-card, #fff); font-size: 0.9rem; }
.rj-etiqueta { font-size: 0.82rem; font-weight: 600; color: var(--color-text-secondary); }
.rj-saldo { font-size: 2.3rem; font-weight: 700; line-height: 1; }
.rj-frase { font-weight: 600; }
.rj-parella { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.rj-parella strong { font-size: 1.1rem; white-space: nowrap; }
.rj-separador { height: 1px; background: var(--color-border, #e2e8f0); }
.rj-progres { position: relative; height: 12px; border-radius: 999px; background: var(--color-bg-hover, #edf2f7); margin: 4px 0; }
.rj-progres-fet { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: var(--rj-positiu); }
.rj-progres-marca { position: absolute; top: -4px; bottom: -4px; width: 2px; background: var(--color-text); }
.rj-peu-progres { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px 8px; }

.rj-revisa { background: var(--rj-revisa-fons); border: 1px solid var(--rj-revisa-vora); border-radius: 12px; padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; color: var(--rj-revisa-text); font-size: 0.86rem; }
.rj-revisa-titol { font-weight: 700; color: var(--rj-negatiu-text); }
.rj-avis { display: flex; gap: 8px; align-items: flex-start; }
.rj-avis svg { flex: none; margin-top: 1px; color: var(--rj-negatiu); }

.rj-bloc { border: 1px solid var(--color-border, #e2e8f0); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; background: var(--color-bg-card, #fff); }
.rj-bloc h3 { margin: 0; font-size: 1rem; font-weight: 700; }
.rj-bloc-cap { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 6px; }
.rj-llegenda { display: flex; flex-wrap: wrap; gap: 12px; }
.rj-llegenda span { display: flex; align-items: center; gap: 5px; }
.rj-llegenda i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
.rj-desplaca { overflow-x: auto; }

.rj-grafic { display: flex; min-width: 540px; }
.rj-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; }
.rj-valor { font-size: 0.7rem; font-weight: 600; height: 16px; white-space: nowrap; }
.rj-meitat { height: 90px; width: 100%; display: flex; justify-content: center; }
.rj-meitat.amunt { align-items: flex-end; }
.rj-meitat.avall { align-items: flex-start; }
.rj-meitat > div { width: 60%; max-width: 34px; }
.rj-meitat.amunt > div { border-radius: 4px 4px 0 0; }
.rj-meitat.avall > div { border-radius: 0 0 4px 4px; }
.rj-eix { height: 2px; width: 100%; background: var(--color-text-light, #a0aec0); }
.rj-mes { font-size: 0.8rem; font-weight: 600; margin-top: 4px; }
.rj-acum { font-size: 0.66rem; white-space: nowrap; }

.rj-taula { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 0.84rem; }
.rj-taula th { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--color-text-secondary); text-align: left; padding: 0 8px 6px; }
.rj-taula td { padding: 7px 8px; border-top: 1px solid var(--color-border, #e2e8f0); white-space: nowrap; }
.rj-fila-tram td { border-top: 2px solid var(--color-text); padding-top: 10px; white-space: normal; }
.rj-tram-nom { font-weight: 700; margin-right: 10px; }
.rj-nom-mes { font-weight: 600; }
.rj-compliment { display: flex; align-items: center; gap: 6px; min-width: 110px; }
.rj-barreta { flex: 1; height: 7px; border-radius: 999px; background: var(--color-bg-hover, #edf2f7); overflow: hidden; }
.rj-barreta > span { display: block; height: 100%; border-radius: 999px; }
.rj-pct { width: 38px; text-align: right; font-size: 0.78rem; }
.rj-etiq { display: inline-block; padding: 2px 9px; border-radius: 999px; font-weight: 600; font-size: 0.8rem; }
.etiq-positiu { background: var(--rj-positiu-fons); color: var(--rj-positiu-text); }
.etiq-negatiu { background: var(--rj-negatiu-fons); color: var(--rj-negatiu-text); }
.rj-taula tfoot td { border-top: 2px solid var(--color-text); font-weight: 700; padding-top: 10px; }
</style>

<style>
/* Fora de l'scoped: :global() amb més selector es perdia */
.dark-mode .resum-jornada {
  --rj-positiu: #7fb0ff; --rj-positiu-fons: #1c3354; --rj-positiu-text: #cfe0ff;
  --rj-negatiu: #f2a35e; --rj-negatiu-barra: #e07b2e; --rj-negatiu-suau: #c9773a; --rj-negatiu-fons: #4a2c14; --rj-negatiu-text: #ffd9b8;
  --rj-revisa-fons: #2e2114; --rj-revisa-vora: #6b4a26; --rj-revisa-text: #f5e1cc;
}
</style>
