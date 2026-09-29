<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const PAS = 30;            // minuts per casella
const MAX_SLOTS = 6;       // fins a 3 h per costat
const LIM_INI = 6 * 60;
const LIM_FI = 22 * 60 + 30;
const DIES = ['', 'Dilluns', 'Dimarts', 'Dimecres', 'Dijous', 'Divendres'];

const jornada = ref({});
const mesSeguent = ref('');
const declaracions = ref([]);
const sel = ref({});       // sel[dia] = { abans: n, despres: n }
const error = ref('');
const enviant = ref(false);

const m2 = h => { const [a, b] = h.split(':'); return +a * 60 + +b; };
const h2 = m => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

const diesAmbJornada = computed(() =>
    [1, 2, 3, 4, 5].map(d => {
        const j = (jornada.value[d] || [])[0];
        if (!j) return { dia: d, nom: DIES[d], jornada: null, abans: [], despres: [] };
        const jIni = m2(j.ini), jFi = m2(j.fi);
        const abans = [], despres = [];
        for (let k = MAX_SLOTS; k >= 1; k--) {
            const t = jIni - k * PAS;
            if (t >= LIM_INI) abans.push({ k, hora: h2(t) });
        }
        for (let k = 1; k <= MAX_SLOTS; k++) {
            const t = jFi + (k - 1) * PAS;
            if (t + PAS <= LIM_FI) despres.push({ k, hora: h2(t) });
        }
        return { dia: d, nom: DIES[d], jornada: j, jIni, jFi, abans, despres };
    })
);

const horesSetmanals = computed(() => {
    let t = 0;
    for (const d in sel.value) t += (sel.value[d].abans + sel.value[d].despres) * PAS;
    return t / 60;
});

const declaracioMesSeguent = computed(() =>
    declaracions.value.find(x => x.month?.slice(0, 10) === mesSeguent.value && x.status !== 'withdrawn'));

function toggle(dia, costat, k) {
    if (!sel.value[dia]) sel.value[dia] = { abans: 0, despres: 0 };
    sel.value[dia][costat] = sel.value[dia][costat] >= k ? k - 1 : k;
}

function actiu(dia, costat, k) {
    return (sel.value[dia]?.[costat] || 0) >= k;
}

async function carrega() {
    const { data } = await axios.get('/api/v1/franges-complementaries/estat');
    jornada.value = data.jornada || {};
    mesSeguent.value = data.mes_seguent;
    declaracions.value = data.declaracions || [];
}

async function confirma() {
    error.value = '';
    const slots = [];
    for (const d of diesAmbJornada.value) {
        if (!d.jornada) continue;
        const s = sel.value[d.dia];
        if (!s) continue;
        if (s.abans > 0) slots.push({ dia: d.dia, inici: h2(d.jIni - s.abans * PAS), fi: h2(d.jIni) });
        if (s.despres > 0) slots.push({ dia: d.dia, inici: h2(d.jFi), fi: h2(d.jFi + s.despres * PAS) });
    }
    if (!slots.length) { error.value = 'No has marcat cap casella.'; return; }
    if (!confirm(`Confirmes ${horesSetmanals.value.toFixed(1)} h setmanals per al mes del ${mesSeguent.value}?\n\nUn cop confirmat NO es pot retirar unilateralment: s'hi agendaran pacients.`)) return;
    enviant.value = true;
    try {
        await axios.post('/api/v1/franges-complementaries/declara', { month: mesSeguent.value, slots });
        await carrega();
        sel.value = {};
    } catch (e) {
        error.value = e.response?.data?.error || 'No s\'ha pogut desar.';
    } finally {
        enviant.value = false;
    }
}

async function retirada(d) {
    const motiu = prompt('Per què vols retirar-la? Coordinació ho ha d\'aprovar, perquè pot desprogramar pacients.');
    if (!motiu) return;
    try {
        await axios.post('/api/v1/franges-complementaries/retirada', { month: d.month.slice(0, 10), reason: motiu });
        await carrega();
    } catch (e) {
        error.value = e.response?.data?.error || 'Error.';
    }
}

onMounted(carrega);
</script>

<template>
    <Head title="Hores complementàries" />
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3">
            <img src="/assets/crt-logo.png" alt="CRT" class="h-8" onerror="this.style.display='none'">
            <span class="font-bold text-gray-800">CRT RRHH</span>
            <a href="/dashboard" class="ml-auto text-sm text-blue-800 underline">Tornar al panell</a>
        </nav>
        <div class="py-6 max-w-4xl mx-auto px-4 space-y-5">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Disponibilitat complementària · mes del {{ mesSeguent }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Toca les caselles enganxades a la teva jornada per oferir-les, de mitja en mitja hora.
                    Les franges han de començar just quan acabes o acabar just quan comences: és el que permet
                    encadenar-les amb la ruta del dia.
                </p>
            </div>

            <div v-for="d in declaracions" :key="d.id"
                 class="rounded-lg border p-3 text-sm"
                 :class="d.status === 'confirmed' ? 'border-emerald-300 bg-emerald-50' : d.status === 'withdrawal_requested' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-gray-50'">
                <b>Mes del {{ d.month?.slice(0, 10) }}</b> ·
                <span v-if="d.status === 'confirmed'">confirmada ({{ d.hours }} h/setmana).
                    <button class="underline text-emerald-800" @click="retirada(d)">Demanar retirar-la</button></span>
                <span v-else-if="d.status === 'withdrawal_requested'">retirada demanada, pendent de coordinació.</span>
                <span v-else>retirada.</span>
            </div>

            <div v-if="!declaracioMesSeguent" class="bg-white rounded-xl shadow p-5 space-y-4">
                <div v-for="d in diesAmbJornada" :key="d.dia">
                    <div class="text-xs font-bold text-gray-700">{{ d.nom }}</div>
                    <div v-if="!d.jornada" class="text-xs text-gray-400 py-1">
                        Sense jornada: no s'hi poden posar complementàries.
                    </div>
                    <div v-else class="flex gap-0.5 items-stretch mt-1">
                        <button v-for="s in d.abans" :key="'a' + s.k" @click="toggle(d.dia, 'abans', s.k)"
                                class="flex-1 rounded border text-[10px] py-1.5"
                                :class="actiu(d.dia, 'abans', s.k)
                                    ? 'bg-violet-700 text-white border-violet-700'
                                    : 'bg-violet-50 text-violet-700 border-dashed border-violet-300'">
                            {{ s.hora }}
                        </button>
                        <div class="rounded bg-blue-900 text-white text-[11px] flex items-center justify-center px-2"
                             :style="{ flex: Math.max(2, (d.jFi - d.jIni) / PAS) }">
                            jornada {{ d.jornada.ini }}–{{ d.jornada.fi }}
                        </div>
                        <button v-for="s in d.despres" :key="'d' + s.k" @click="toggle(d.dia, 'despres', s.k)"
                                class="flex-1 rounded border text-[10px] py-1.5"
                                :class="actiu(d.dia, 'despres', s.k)
                                    ? 'bg-violet-700 text-white border-violet-700'
                                    : 'bg-violet-50 text-violet-700 border-dashed border-violet-300'">
                            {{ s.hora }}
                        </button>
                    </div>
                </div>

                <div class="font-bold text-gray-800">{{ horesSetmanals.toFixed(1) }} h setmanals oferides</div>

                <div class="rounded border-l-4 border-amber-500 bg-amber-50 p-3 text-xs text-amber-800">
                    Un cop confirmada, la disponibilitat NO es pot retirar unilateralment: caldrà demanar-ho
                    i que coordinació ho aprovi, perquè el sistema hi agendarà pacients i els avisarà.
                </div>

                <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

                <button @click="confirma" :disabled="enviant"
                        class="w-full rounded-lg bg-emerald-600 text-white font-bold py-3 disabled:opacity-50">
                    Confirmar la disponibilitat del mes
                </button>
            </div>
        </div>
    </div>
</template>
