<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    workLogId: {
        type: [Number, String],
        default: null,
    },
    breakDurationMinutes: {
        type: Number,
        default: 20,
    },
    breakStartTime: {
        type: String,
        default: null,
    },
    breakStatus: {
        type: String,
        default: 'pending',
    },
});

const emit = defineEmits(['break-completed', 'break-skipped']);

const remainingSeconds = ref(props.breakDurationMinutes * 60);
const isActive = ref(props.breakStatus === 'active');
let intervalId = null;

const formattedTime = computed(() => {
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const progressPercent = computed(() => {
    const total = props.breakDurationMinutes * 60;
    const elapsed = total - remainingSeconds.value;
    return Math.min(100, (elapsed / total) * 100);
});

function startCountdown() {
    if (intervalId) clearInterval(intervalId);

    // Calcular tiempo restante basado en el inicio real
    if (props.breakStartTime) {
        const start = new Date(props.breakStartTime).getTime();
        const now = Date.now();
        const elapsedSec = Math.floor((now - start) / 1000);
        remainingSeconds.value = Math.max(0, (props.breakDurationMinutes * 60) - elapsedSec);
    }

    intervalId = setInterval(() => {
        if (remainingSeconds.value > 0) {
            remainingSeconds.value--;
        } else {
            clearInterval(intervalId);
            intervalId = null;
            // Auto-completar cuando llega a cero
            completeBreak();
        }
    }, 1000);
}

async function completeBreak() {
    if (!props.workLogId) return;
    try {
        await axios.post(`/api/v1/work-logs/${props.workLogId}/complete-break`);
        isActive.value = false;
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        emit('break-completed');
    } catch (e) {
        console.error('Error completant pausa:', e);
    }
}

async function skipBreak() {
    if (!props.workLogId) return;
    try {
        await axios.post(`/api/v1/work-logs/${props.workLogId}/skip-break`, {
            reason: 'Pausa omesa manualment',
        });
        isActive.value = false;
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        emit('break-skipped');
    } catch (e) {
        console.error('Error ometer pausa:', e);
    }
}

watch(() => props.breakStatus, (newStatus) => {
    if (newStatus === 'active' && !isActive.value) {
        isActive.value = true;
        startCountdown();
    } else if (newStatus !== 'active') {
        isActive.value = false;
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }
});

onMounted(() => {
    if (isActive.value) {
        startCountdown();
    }
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<template>
    <div v-if="isActive" class="fixed inset-0 z-[100] bg-black bg-opacity-60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
            <div class="text-5xl mb-4">☕</div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Pausa obligatòria</h2>
            <p class="text-gray-500 text-sm mb-6">
                Has superat el llindar d'hores seguides. Has de fer una pausa de
                {{ breakDurationMinutes }} minuts.
            </p>

            <!-- Cuenta atrás -->
            <div class="relative w-48 h-48 mx-auto mb-6">
                <svg class="w-48 h-48 transform -rotate-90" viewBox="0 0 200 200">
                    <circle
                        cx="100" cy="100" r="90"
                        fill="none" stroke="#e5e7eb" stroke-width="12"
                    />
                    <circle
                        cx="100" cy="100" r="90"
                        fill="none" stroke="#3b82f6" stroke-width="12"
                        stroke-linecap="round"
                        :stroke-dasharray="2 * Math.PI * 90"
                        :stroke-dashoffset="2 * Math.PI * 90 * (1 - progressPercent / 100)"
                        class="transition-all duration-1000 ease-linear"
                    />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-4xl font-bold text-gray-800 tabular-nums">{{ formattedTime }}</span>
                </div>
            </div>

            <p class="text-xs text-gray-400 mb-6">
                L'aplicació està bloquejada durant la pausa.
            </p>

            <button
                @click="skipBreak"
                class="text-sm text-gray-400 hover:text-gray-600 underline"
            >
                Ometre pausa (requereix justificació)
            </button>
        </div>
    </div>
</template>
