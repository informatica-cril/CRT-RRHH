<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
                

</script>

<template>
    <Head title="Dashboard" />
    
    <AdministracionLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <!-- Bienvenido  {{ $page.props.auth.user.name }} -->
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <h2 class="font-semibold text-xl p-6 text-gray-900 mb-1">Control de asistencia</h2>
            
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-6 pb-6">
                        <!-- Card Tratamientos Activos -->
                        <!-- Card Asistencias Realizadas -->
                        <!-- <Link :href="route('fisio.asistenciasRealizadas')" class="block"> -->
                            
                        <Link :href="route('administracion.AsistenciasRealizadas')" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-blue-50 hover:bg-blue-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">🗓️</span>
                                <span class="font-semibold text-lg text-gray-800">Asistencias realizadas</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Consulta y gestiona las asistencias que has realizado.</span>
                            </div>
                        </Link>
                        <!-- Card Asistencias Eliminadas -->
                        <!-- <Link :href="route('fisio.asistenciasEliminadas')" class="block"> -->
                        <Link :href="route('administracion.AsistenciasAnuladas')" class="block">
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


    </AdministracionLayout>
</template>
