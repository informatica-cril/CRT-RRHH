<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    userId: {
        type: Number,
        required: true,
    },
});

const alerts = ref([]);
const loading = ref(false);

const alertIcons = {
    no_clock_in: '⏰',
    no_clock_out: '🚪',
    break_required: '☕',
    segment_rejected: '✂️',
    out_of_zone: '📍',
};

const alertColors = {
    no_clock_in: 'bg-orange-50 border-orange-400 text-orange-800',
    no_clock_out: 'bg-red-50 border-red-400 text-red-800',
    break_required: 'bg-blue-50 border-blue-400 text-blue-800',
    segment_rejected: 'bg-yellow-50 border-yellow-400 text-yellow-800',
    out_of_zone: 'bg-purple-50 border-purple-400 text-purple-800',
};

async function fetchAlerts() {
    if (!props.userId) return;
    loading.value = true;
    try {
        const res = await axios.get(`/api/v1/work-log-alerts/pending/${props.userId}`);
        alerts.value = res.data || [];
    } catch (e) {
        console.error('Error carregant alertes:', e);
    } finally {
        loading.value = false;
    }
}

async function dismiss(alertId) {
    try {
        await axios.post(`/api/v1/work-log-alerts/${alertId}/dismiss`);
        alerts.value = alerts.value.filter(a => a.id !== alertId);
    } catch (e) {
        console.error('Error descartant alerta:', e);
    }
}

let pollInterval = null;

onMounted(() => {
    fetchAlerts();
    // Consulta alertas cada 60 segundos
    pollInterval = setInterval(fetchAlerts, 60000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

defineExpose({ fetchAlerts });
</script>

<template>
    <div v-if="alerts.length > 0" class="space-y-2 mb-4">
        <div
            v-for="alert in alerts"
            :key="alert.id"
            :class="alertColors[alert.type] || 'bg-gray-50 border-gray-400 text-gray-800'"
            class="border-l-4 rounded-r-lg p-4 flex items-start justify-between shadow-sm"
        >
            <div class="flex items-start gap-3">
                <span class="text-2xl">{{ alertIcons[alert.type] || '⚠️' }}</span>
                <div>
                    <p class="font-medium">{{ alert.message }}</p>
                    <p class="text-xs opacity-70 mt-1">
                        Programada: {{ new Date(alert.scheduled_at).toLocaleString('ca-ES') }}
                    </p>
                </div>
            </div>
            <button
                @click="dismiss(alert.id)"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none ml-2"
                title="Descartar"
            >
                &times;
            </button>
        </div>
    </div>
</template>
