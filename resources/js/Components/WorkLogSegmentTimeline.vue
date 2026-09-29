<script setup>
import { computed } from 'vue';

const props = defineProps({
    segments: {
        type: Array,
        default: () => [],
    },
});

/**
 * Calcula el ancho proporcional de cada tramo respecto al total.
 */
const totalMinutes = computed(() => {
    return props.segments.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
});

const timelineSegments = computed(() => {
    if (totalMinutes.value === 0) return [];
    return props.segments.map(s => ({
        ...s,
        widthPercent: ((s.duration_minutes || 0) / totalMinutes.value) * 100,
    }));
});

function formatTime(dateTimeStr) {
    if (!dateTimeStr) return '--:--';
    const d = new Date(dateTimeStr);
    return d.toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' });
}

function statusColor(status) {
    const colors = {
        approved: 'bg-green-500',
        pending: 'bg-yellow-500',
        rejected: 'bg-red-500',
        modified: 'bg-blue-500',
    };
    return colors[status] || 'bg-gray-400';
}

function zoneBadge(inZone) {
    return inZone
        ? 'bg-green-100 text-green-800'
        : 'bg-red-100 text-red-800';
}

function scheduleBadge(inSchedule) {
    return inSchedule
        ? 'bg-blue-100 text-blue-800'
        : 'bg-orange-100 text-orange-800';
}
</script>

<template>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Timeline de trams</h3>

        <div v-if="segments.length === 0" class="text-gray-500 text-sm py-8 text-center">
            No hi ha trams segmentats per a aquest fichatge.
        </div>

        <template v-else>
            <!-- Barra visual de tramos -->
            <div class="flex w-full h-8 rounded-lg overflow-hidden mb-4 shadow-inner">
                <div
                    v-for="(seg, i) in timelineSegments"
                    :key="seg.id || i"
                    :class="statusColor(seg.status)"
                    :style="{ width: seg.widthPercent + '%' }"
                    :title="`Tram ${seg.segment_number}: ${seg.duration_minutes} min`"
                    class="flex items-center justify-center text-xs text-white font-medium transition-all hover:opacity-80"
                >
                    <span v-if="seg.widthPercent > 10">{{ seg.duration_minutes }}'</span>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="flex flex-wrap gap-4 mb-4 text-xs">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-500"></span> Aprovat</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-yellow-500"></span> Pendent</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500"></span> Rebutjat</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-500"></span> Modificat</span>
            </div>

            <!-- Tabla de tramos -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Inici</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Durada</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Zona</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Horari</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Estat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(seg, i) in segments" :key="seg.id || i" class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-medium text-gray-700">{{ seg.segment_number }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ formatTime(seg.start_time) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ formatTime(seg.end_time) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ seg.duration_minutes }} min</td>
                            <td class="px-3 py-2">
                                <span :class="zoneBadge(seg.in_zone)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ seg.in_zone ? 'Dins' : 'Fora' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span :class="scheduleBadge(seg.in_schedule)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                                    {{ seg.in_schedule ? 'Dins' : 'Fora' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span :class="statusColor(seg.status)" class="px-2 py-0.5 rounded-full text-xs text-white font-medium capitalize">
                                    {{ seg.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
