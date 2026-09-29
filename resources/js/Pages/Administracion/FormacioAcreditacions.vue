<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(true);
const error = ref('');
const data = ref({ ok: false, campanya: null, empleats: [] });

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await axios.get('/api/v1/formacio/acreditacions');
        data.value = res.data;
    } catch (e) {
        error.value = 'No s\'ha pogut carregar (revisa que el servei de domi respon).';
    } finally {
        loading.value = false;
    }
}

const counts = computed(() => {
    const c = { total: 0, pendent: 0, test: 0, firmat: 0 };
    (data.value.empleats || []).forEach((x) => { c.total++; c[x.estat] = (c[x.estat] || 0) + 1; });
    return c;
});

const estatLabel = (s) => ({ pendent: 'Pendent', test: 'Test superat · falta firma', firmat: 'Acreditat' }[s] || s);
const badgeClass = (s) => ({
    pendent: 'bg-orange-100 text-orange-700',
    test: 'bg-amber-100 text-amber-700',
    firmat: 'bg-emerald-100 text-emerald-700',
}[s] || 'bg-gray-100 text-gray-600');

const docUrl = (e, tipus) =>
    `/api/v1/formacio/doc/${tipus}/${encodeURIComponent(e.usuari)}/${encodeURIComponent(e.rol)}?campanya=${data.value.campanya?.id || 0}`;

onMounted(load);
</script>

<template>
    <Head title="Formació · acreditacions" />
    <AdministracionLayout>
        <div class="max-w-6xl mx-auto px-4 py-6">
            <h1 class="text-xl font-semibold text-gray-800">Formació obligatòria · acreditacions</h1>
            <p class="text-sm text-gray-500 mt-1">
                Estat de la formació de plataforma de cada persona. Els treballadors la fan a CRT
                Domiciliària; aquí en veus l'acreditació i descarregues el diploma i el rebut. Les firmes
                entren al segell FNMT diari compartit del llibre d'integritat.
            </p>

            <div v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>
            <div v-else-if="loading" class="mt-6 text-sm text-gray-500">Carregant…</div>
            <div v-else-if="!data.ok || !data.campanya" class="mt-6 rounded-lg border border-gray-200 bg-white p-5 text-gray-600">
                Ara mateix no hi ha cap campanya de formació activa (o el servei de domi no respon).
            </div>

            <template v-else>
                <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="font-medium text-gray-800">{{ data.campanya.nom }}</div>
                    <div class="text-xs text-gray-500">Llindar {{ data.campanya.llindar }}%<span v-if="data.campanya.data_limit"> · límit {{ data.campanya.data_limit }}</span></div>
                    <div class="flex flex-wrap gap-3 mt-3">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2 min-w-[104px]"><div class="text-2xl font-bold text-gray-800">{{ counts.total }}</div><div class="text-xs text-gray-500">assignats</div></div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2 min-w-[104px]"><div class="text-2xl font-bold text-orange-600">{{ counts.pendent }}</div><div class="text-xs text-gray-500">pendents</div></div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2 min-w-[104px]"><div class="text-2xl font-bold text-amber-600">{{ counts.test }}</div><div class="text-xs text-gray-500">falta firma</div></div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-2 min-w-[104px]"><div class="text-2xl font-bold text-emerald-600">{{ counts.firmat }}</div><div class="text-xs text-gray-500">acreditats</div></div>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 bg-white overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-100">
                                <th class="px-4 py-2">Treballador</th><th class="px-4 py-2">Perfil</th>
                                <th class="px-4 py-2">Estat</th><th class="px-4 py-2">Test</th><th class="px-4 py-2">Documents</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in data.empleats" :key="e.usuari + '-' + e.rol" class="border-b border-gray-50">
                                <td class="px-4 py-2 text-gray-800">{{ e.usuari }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ e.rol }}</td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badgeClass(e.estat)">{{ estatLabel(e.estat) }}<span v-if="e.estat === 'firmat'"> ✓</span></span></td>
                                <td class="px-4 py-2 text-gray-600"><span v-if="e.percent !== null">{{ e.percent }}%<span v-if="e.aprovat"> ✓</span></span><span v-else>—</span></td>
                                <td class="px-4 py-2">
                                    <template v-if="e.estat === 'firmat'">
                                        <a v-if="e.te_diploma" :href="docUrl(e, 'diploma')" target="_blank" class="inline-block rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50 mr-1">Diploma</a>
                                        <a v-if="e.te_rebut" :href="docUrl(e, 'rebut')" target="_blank" class="inline-block rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50">Rebut</a>
                                    </template>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AdministracionLayout>
</template>
