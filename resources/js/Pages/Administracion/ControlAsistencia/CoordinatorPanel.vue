<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import WorkLogSegmentTimeline from '@/Components/WorkLogSegmentTimeline.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
});

// Estado
const activeTab = ref('pending');
const workLogs = ref([]);
const loading = ref(true);
const selectedLog = ref(null);
const selectedSegments = ref([]);
const selectedModifications = ref([]);

const tabs = [
    { id: 'pending', label: 'Pendents de revisió', icon: '⏳' },
    { id: 'segmented', label: 'Segmentats', icon: '✂️' },
    { id: 'out_of_zone', label: 'Fora de zona', icon: '📍' },
    { id: 'all', label: 'Tots', icon: '📋' },
];

async function fetchWorkLogs() {
    loading.value = true;
    try {
        const res = await axios.get('/api/v1/work-logs');
        let logs = res.data || [];

        // Filtrar según el tab activo
        if (activeTab.value === 'pending') {
            logs = logs.filter(l => l.segmented && l.status === 'pending');
        } else if (activeTab.value === 'segmented') {
            logs = logs.filter(l => l.segmented);
        } else if (activeTab.value === 'out_of_zone') {
            logs = logs.filter(l => l.start_location_match === false || l.end_location_match === false);
        }

        workLogs.value = logs;
    } catch (e) {
        console.error('Error carregant fichatges:', e);
    } finally {
        loading.value = false;
    }
}

async function viewDetail(log) {
    selectedLog.value = log;
    selectedSegments.value = [];
    selectedModifications.value = [];
    try {
        const [segRes, modRes] = await Promise.all([
            axios.get(`/api/v1/work-logs/${log.id}/segments`),
            axios.get(`/api/v1/work-logs/${log.id}/modifications`),
        ]);
        selectedSegments.value = segRes.data || [];
        selectedModifications.value = modRes.data || [];
    } catch (e) {
        console.error('Error carregant detall:', e);
    }
}

async function approveSegment(segmentId) {
    if (!selectedLog.value) return;
    try {
        await axios.post(`/api/v1/work-logs/${selectedLog.value.id}/segments/${segmentId}/approve`);
        await viewDetail(selectedLog.value);
    } catch (e) {
        console.error('Error aprovant tram:', e);
    }
}

function formatDateTime(dt) {
    if (!dt) return '--';
    return new Date(dt).toLocaleString('ca-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function switchTab(tabId) {
    activeTab.value = tabId;
    selectedLog.value = null;
    fetchWorkLogs();
}

onMounted(() => {
    fetchWorkLogs();
});
</script>

<template>
    <Head title="Panel de coordinació" />

    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Panel de coordinació
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Tabs -->
                <div class="flex gap-2 mb-6 border-b border-gray-200">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="switchTab(tab.id)"
                        :class="activeTab === tab.id
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                    >
                        {{ tab.icon }} {{ tab.label }}
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Lista de fichajes -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-600 mb-3">Fichatges</h3>

                        <div v-if="loading" class="text-center text-gray-400 py-8">
                            Carregant...
                        </div>

                        <div v-else-if="workLogs.length === 0" class="text-center text-gray-400 py-8">
                            No hi ha fichatges per mostrar.
                        </div>

                        <div v-else class="space-y-2 max-h-[600px] overflow-y-auto">
                            <button
                                v-for="log in workLogs"
                                :key="log.id"
                                @click="viewDetail(log)"
                                :class="selectedLog?.id === log.id
                                    ? 'border-blue-500 bg-blue-50'
                                    : 'border-gray-200 hover:bg-gray-50'"
                                class="w-full text-left border rounded-lg p-3 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-800 text-sm">{{ log.user?.name || '--' }}</p>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(log.date) }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="log.segmented"
                                            class="px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-800"
                                        >
                                            Segmentat
                                        </span>
                                        <span
                                            v-if="log.start_location_match === false || log.end_location_match === false"
                                            class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800"
                                        >
                                            Fora de zona
                                        </span>
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': log.status === 'pending',
                                                'bg-green-100 text-green-800': log.status === 'approved',
                                                'bg-red-100 text-red-800': log.status === 'rejected',
                                            }"
                                        >
                                            {{ log.status }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex gap-4 mt-2 text-xs text-gray-500">
                                    <span>⏱️ {{ log.total_hours_worked || 0 }}h</span>
                                    <span>📋 {{ log.complementary_minutes || 0 }} min complementaris</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Detalle del fichaje seleccionado -->
                    <div class="space-y-4">
                        <div v-if="!selectedLog" class="bg-white rounded-lg shadow p-12 text-center text-gray-400">
                            Selecciona un fichatge per veure el detall.
                        </div>

                        <template v-else>
                            <!-- Resumen -->
                            <div class="bg-white rounded-lg shadow p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-600">
                                        {{ selectedLog.user?.name || '--' }}
                                    </h3>
                                    <Link
                                        :href="route('administracion.workLogDetail', { workLogId: selectedLog.id })"
                                        class="text-xs text-blue-600 hover:text-blue-800"
                                    >
                                        Veure detall complet →
                                    </Link>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-gray-400">Entrada</p>
                                        <p class="text-gray-700">{{ formatDateTime(selectedLog.start_time) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400">Sortida</p>
                                        <p class="text-gray-700">{{ formatDateTime(selectedLog.end_time) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400">Hores complementàries</p>
                                        <p class="text-gray-700">{{ selectedLog.complementary_minutes || 0 }} min</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400">Estat</p>
                                        <p class="text-gray-700 capitalize">{{ selectedLog.status }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeline de tramos -->
                            <WorkLogSegmentTimeline :segments="selectedSegments" />

                            <!-- Acciones rápidas por tramo -->
                            <div v-if="selectedSegments.length > 0" class="bg-white rounded-lg shadow p-4">
                                <h3 class="text-sm font-semibold text-gray-600 mb-3">Accions ràpides</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="seg in selectedSegments"
                                        :key="seg.id"
                                        class="flex items-center justify-between border border-gray-200 rounded p-2"
                                    >
                                        <span class="text-sm text-gray-700">Tram {{ seg.segment_number }} ({{ seg.duration_minutes }} min)</span>
                                        <button
                                            v-if="seg.status !== 'approved'"
                                            @click="approveSegment(seg.id)"
                                            class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                                        >
                                            Aprovar
                                        </button>
                                        <span v-else class="text-xs text-green-600">✓ Aprovat</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AdministracionLayout>
</template>
