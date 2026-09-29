<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import WorkLogSegmentTimeline from '@/Components/WorkLogSegmentTimeline.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    workLogId: {
        type: [Number, String],
        required: true,
    },
    user: {
        type: Object,
        default: () => ({}),
    },
});

const workLog = ref(null);
const segments = ref([]);
const modifications = ref([]);
const alerts = ref([]);
const loading = ref(true);
const error = ref(null);

// Estado para rechazar tramo
const rejectingSegmentId = ref(null);
const rejectionReason = ref('');

// Estado para modificar tramo
const editingSegment = ref(null);
const editForm = ref({
    start_time: '',
    end_time: '',
    authorization_code_id: '',
});

const canManage = computed(() => {
    const role = props.user?.role || 'worker';
    return role === 'admin' || role === 'coordinator';
});

async function fetchDetail() {
    loading.value = true;
    error.value = null;
    try {
        const res = await axios.get(`/api/v1/work-logs/${props.workLogId}/detail`);
        workLog.value = res.data;
        segments.value = res.data.segments || [];
        modifications.value = res.data.modifications || [];
        alerts.value = res.data.alerts || [];
    } catch (e) {
        error.value = 'No s\'ha pogut carregar el detall del fichatge.';
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function approveSegment(segmentId) {
    try {
        await axios.post(`/api/v1/work-logs/${props.workLogId}/segments/${segmentId}/approve`);
        await fetchDetail();
    } catch (e) {
        console.error('Error aprovant tram:', e);
    }
}

function startReject(segmentId) {
    rejectingSegmentId.value = segmentId;
    rejectionReason.value = '';
}

async function confirmReject() {
    if (!rejectionReason.value.trim()) return;
    try {
        await axios.post(`/api/v1/work-logs/${props.workLogId}/segments/${rejectingSegmentId.value}/reject`, {
            rejection_reason: rejectionReason.value,
        });
        rejectingSegmentId.value = null;
        rejectionReason.value = '';
        await fetchDetail();
    } catch (e) {
        console.error('Error rebutjant tram:', e);
    }
}

function startEdit(segment) {
    editingSegment.value = segment;
    editForm.value = {
        start_time: segment.start_time ? segment.start_time.slice(0, 16) : '',
        end_time: segment.end_time ? segment.end_time.slice(0, 16) : '',
        authorization_code_id: segment.authorization_code_id || '',
    };
}

async function saveEdit() {
    if (!editingSegment.value) return;
    try {
        const data = {};
        if (editForm.value.start_time) data.start_time = editForm.value.start_time;
        if (editForm.value.end_time) data.end_time = editForm.value.end_time;
        if (editForm.value.authorization_code_id) data.authorization_code_id = editForm.value.authorization_code_id;

        await axios.put(`/api/v1/work-logs/${props.workLogId}/segments/${editingSegment.value.id}`, data);
        editingSegment.value = null;
        await fetchDetail();
    } catch (e) {
        console.error('Error modificant tram:', e);
    }
}

function formatDateTime(dt) {
    if (!dt) return '--';
    return new Date(dt).toLocaleString('ca-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function actionLabel(action) {
    const labels = {
        created: 'Creat',
        segmented: 'Segmentat',
        approved: 'Aprovat',
        rejected: 'Rebutjat',
        modified: 'Modificat',
        break_started: 'Pausa iniciada',
        break_completed: 'Pausa completada',
        break_skipped: 'Pausa omesa',
    };
    return labels[action] || action;
}

onMounted(() => {
    fetchDetail();
});
</script>

<template>
    <Head title="Detall de fichatge" />

    <AdministracionLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detall de fichatge
                </h2>
                <Link
                    :href="route('administracion.AsistenciaIndex')"
                    class="text-sm text-blue-600 hover:text-blue-800"
                >
                    ← Tornar
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Loading -->
                <div v-if="loading" class="bg-white rounded-lg shadow p-12 text-center text-gray-400">
                    Carregant...
                </div>

                <!-- Error -->
                <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 text-red-700">
                    {{ error }}
                </div>

                <template v-else-if="workLog">
                    <!-- Resumen del fichaje -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Resum del fichatge</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Treballador</p>
                                <p class="font-medium text-gray-800">{{ workLog.user?.name || '--' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Data</p>
                                <p class="font-medium text-gray-800">{{ formatDateTime(workLog.date) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Entrada</p>
                                <p class="font-medium text-gray-800">{{ formatDateTime(workLog.start_time) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Sortida</p>
                                <p class="font-medium text-gray-800">{{ formatDateTime(workLog.end_time) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Hores totals</p>
                                <p class="font-medium text-gray-800">{{ workLog.total_hours_worked || 0 }}h</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Hores complementàries</p>
                                <p class="font-medium text-gray-800">{{ workLog.complementary_minutes || 0 }} min</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Pausa</p>
                                <p class="font-medium text-gray-800">
                                    <span v-if="workLog.break_required" class="px-2 py-0.5 rounded-full text-xs"
                                        :class="{
                                            'bg-green-100 text-green-800': workLog.break_status === 'completed',
                                            'bg-blue-100 text-blue-800': workLog.break_status === 'active',
                                            'bg-yellow-100 text-yellow-800': workLog.break_status === 'pending',
                                            'bg-gray-100 text-gray-800': workLog.break_status === 'skipped',
                                        }">
                                        {{ workLog.break_status || 'no requerida' }}
                                    </span>
                                    <span v-else class="text-gray-400">No requerida</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Segmentat</p>
                                <p class="font-medium text-gray-800">
                                    <span :class="workLog.segmented ? 'text-green-600' : 'text-gray-400'">
                                        {{ workLog.segmented ? 'Sí' : 'No' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline de tramos -->
                    <WorkLogSegmentTimeline :segments="segments" />

                    <!-- Acciones por tramo -->
                    <div v-if="segments.length > 0" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Accions per tram</h3>
                        <div class="space-y-3">
                            <div
                                v-for="seg in segments"
                                :key="seg.id"
                                class="flex items-center justify-between border border-gray-200 rounded-lg p-3"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="font-medium text-gray-700">Tram {{ seg.segment_number }}</span>
                                    <span class="text-sm text-gray-500">{{ seg.duration_minutes }} min</span>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                        :class="{
                                            'bg-green-100 text-green-800': seg.status === 'approved',
                                            'bg-yellow-100 text-yellow-800': seg.status === 'pending',
                                            'bg-red-100 text-red-800': seg.status === 'rejected',
                                            'bg-blue-100 text-blue-800': seg.status === 'modified',
                                        }"
                                    >
                                        {{ seg.status }}
                                    </span>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        v-if="canManage && seg.status !== 'approved'"
                                        @click="approveSegment(seg.id)"
                                        class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                                    >
                                        Aprovar
                                    </button>
                                    <button
                                        v-if="canManage && seg.status !== 'rejected'"
                                        @click="startReject(seg.id)"
                                        class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded"
                                    >
                                        Rebutjar
                                    </button>
                                    <button
                                        @click="startEdit(seg)"
                                        class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded"
                                    >
                                        Modificar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas del fichaje -->
                    <div v-if="alerts.length > 0" class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Alertes</h3>
                        <div class="space-y-2">
                            <div
                                v-for="alert in alerts"
                                :key="alert.id"
                                class="border-l-4 border-orange-400 bg-orange-50 p-3 rounded-r-lg"
                            >
                                <p class="text-sm text-orange-800">{{ alert.message }}</p>
                                <p class="text-xs text-orange-500 mt-1">{{ formatDateTime(alert.scheduled_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Trazabilidad de modificaciones -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Traçabilitat de canvis</h3>
                        <div v-if="modifications.length === 0" class="text-gray-400 text-sm">
                            No hi ha modificacions registrades.
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Data</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Usuari</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Acció</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600">Comentari</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="mod in modifications" :key="mod.id" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-600">{{ formatDateTime(mod.created_at) }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ mod.user?.name || '--' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ actionLabel(mod.action) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">{{ mod.comment || '--' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

            </div>
        </div>

        <!-- Modal: Rechazar tramo -->
        <div v-if="rejectingSegmentId" class="fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Rebutjar tram</h3>
                <textarea
                    v-model="rejectionReason"
                    placeholder="Motiu del rebuig..."
                    class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none"
                    rows="3"
                ></textarea>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="rejectingSegmentId = null" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                        Cancel·lar
                    </button>
                    <button
                        @click="confirmReject"
                        :disabled="!rejectionReason.trim()"
                        class="px-4 py-2 text-sm bg-red-500 hover:bg-red-600 text-white rounded disabled:opacity-50"
                    >
                        Confirmar rebuig
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Modificar tramo -->
        <div v-if="editingSegment" class="fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    Modificar tram {{ editingSegment.segment_number }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Inici</label>
                        <input
                            v-model="editForm.start_time"
                            type="datetime-local"
                            class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Fi</label>
                        <input
                            v-model="editForm.end_time"
                            type="datetime-local"
                            class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">Codi d'autorització (opcional)</label>
                        <input
                            v-model="editForm.authorization_code_id"
                            type="number"
                            placeholder="ID del codi d'autorització"
                            class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="editingSegment = null" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                        Cancel·lar
                    </button>
                    <button
                        @click="saveEdit"
                        class="px-4 py-2 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded"
                    >
                        Desar
                    </button>
                </div>
            </div>
        </div>

    </AdministracionLayout>
</template>
