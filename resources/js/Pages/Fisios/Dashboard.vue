<script setup>
import FisiosLayout from '@/Layouts/FisiosLayout.vue';
import WorkLogAlertBanner from '@/Components/WorkLogAlertBanner.vue';
import BreakCountdown from '@/Components/BreakCountdown.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// ── Estado de pausa obligatoria ──
const activeWorkLog = ref(null);
const breakSettings = ref({ enabled: true, break_duration_minutes: 20 });

const userId = computed(() => page.props.auth.user.id);

async function fetchActiveWorkLog() {
    try {
        const res = await axios.get(`/api/v1/work-logs/user/${userId.value}`);
        const logs = res.data || [];
        // Buscar fichaje abierto (sin end_time)
        activeWorkLog.value = logs.find(l => !l.end_time) || null;
    } catch (e) {
        console.error('Error carregant fichatge actiu:', e);
    }
}

async function fetchBreakSettings() {
    try {
        const res = await axios.get('/api/v1/break-settings');
        breakSettings.value = res.data;
    } catch (e) {
        console.error('Error carregant configuració de pausa:', e);
    }
}

onMounted(() => {
    fetchActiveWorkLog();
    fetchBreakSettings();
});

</script>

<template>
    <Head title="Dashboard" />
    
    <FisiosLayout>

        <!-- Alertas molestas -->
        <WorkLogAlertBanner :user-id="userId" />

        <!-- Modal de pausa obligatoria -->
        <BreakCountdown
            v-if="activeWorkLog && activeWorkLog.break_required"
            :work-log-id="activeWorkLog.id"
            :break-duration-minutes="breakSettings.break_duration_minutes"
            :break-start-time="activeWorkLog.break_start_time"
            :break-status="activeWorkLog.break_status"
            @break-completed="fetchActiveWorkLog"
            @break-skipped="fetchActiveWorkLog"
        />

        <template #header>
            <div class="w-full flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight w-full sm:w-auto">
                    Bienvenido  {{ $page.props.auth.user.name }}
                </h2>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

               


                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <h2 class="font-semibold text-xl p-6 text-gray-900 mb-1">¿Qué quieres hacer hoy?</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 p-6">
                        <!-- Card Tratamientos Activos -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-green-50 hover:bg-green-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">✅</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos activos</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Visualiza y gestiona los tratamientos en curso.</span>
                            </div>
                        </Link>
                        <!-- Card Tratamientos en Espera -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-yellow-50 hover:bg-yellow-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">⏳</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos en espera</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Consulta los tratamientos pendientes de inicio.</span>
                            </div>
                        </Link>
                        <!-- Card Tratamientos Finalizados -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-red-50 hover:bg-red-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">✔️</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos finalizados</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Revisa el historial de tratamientos concluidos.</span>
                            </div>
                        </Link>
                    </div>
                    <hr class="my-4 mx-8 p-4">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-6 pb-6">
                        <!-- Card Tratamientos Activos -->
                        <!-- Card Asistencias Realizadas -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-blue-50 hover:bg-blue-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">🗓️</span>
                                <span class="font-semibold text-lg text-gray-800">Asistencias realizadas</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Consulta y gestiona las asistencias que has realizado.</span>
                            </div>
                        </Link>
                        <!-- Card Asistencias Eliminadas -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-gray-50 hover:bg-gray-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">🗑️</span>
                                <span class="font-semibold text-lg text-gray-800">Asistencias eliminadas</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Revisa el historial de asistencias eliminadas.</span>
                            </div>
                        </Link>
                        <!-- Card Próximas Asistencias -->
                        <!-- <Link :href="route('fisio.proximasAsistencias')" class="block"> -->
                        <!-- <Link href="#" class="block">

                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-green-50 hover:bg-green-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">⏰</span>
                                <span class="font-semibold text-lg text-gray-800">Próximas asistencias</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Visualiza las asistencias programadas para los próximos días.</span>
                            </div>
                        </Link> -->
                    </div>
                </div>
            </div>
        </div>

    </FisiosLayout>
</template>
