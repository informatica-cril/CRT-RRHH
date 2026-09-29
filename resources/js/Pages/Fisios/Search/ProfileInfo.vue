<script setup>
import FisiosLayout from '@/Layouts/FisiosLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { reactive, onMounted } from 'vue';

// Easy Data Table
import Vue3EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'

const page = usePage();

// Flash message
const mensajeExito = computed(() => page.props.flash?.success);
const usuariosTipo3 = computed(() => page.props.usuariosTipo3);

//manejo errores
const errores = ref({});

const mostrarModal = ref(false);
const mostrarModalTratamiento = ref(false);
const pacienteToEdit = computed(() => page.props?.paciente);
const tratamientos = computed(() => page.props?.tratamientos);

pacienteToEdit.value.nombreCompleto = `${pacienteToEdit.value.nombre} ${pacienteToEdit.value.primer_apellido} ${pacienteToEdit.value.segundo_apellido}`;
pacienteToEdit.value.iniciales = `${pacienteToEdit.value.nombre?.charAt(0) || ''}${pacienteToEdit.value.primer_apellido?.charAt(0) || ''}`;






const header_tratamientos = ref(
    [
        {title: 'fecha_recepcion_derivacion', field: 'fecha_recepcion_derivacion'},
        {title: 'autorizacion', field: 'autorizacion'},
        {title: 'numero_ss_auto', field: 'numero_ss_auto'},
        {title: 'fecha_recepcion_autorizacion', field: 'fecha_recepcion_autorizacion'},
        {title: 'fecha_caducidad_autorizacion', field: 'fecha_caducidad_autorizacion'},
        {title: 'fecha_inicio_tto', field: 'fecha_inicio_tto'},
        {title: 'fecha_fin_tto', field: 'fecha_fin_tto'},
        {title: 'dias_espera', field: 'dias_espera'},
        {title: 'estado_tto', field: 'estado_tto'},
        {title: 'ss_por_semana', field: 'ss_por_semana'},
        {title: 'observaciones', field: 'observaciones'},
    ]
);



</script>

<template>

    <Head title="Perfil paciente" />
    <FisiosLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Perfil paciente
            </h2>
        </template>

        <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4 mt-3">
            {{ mensajeExito }}
        </div>

        <div class="py-10 bg-gray-50 flex flex-col md:flex-row justify-center min-h-screen gap-6">
            <!-- Perfil del paciente -->
            <div class="w-full md:w-[40%] max-w-full md:max-w-2xl px-2 sm:px-4 md:px-6 mb-6 md:mb-0">
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="flex flex-col items-center p-4 sm:p-8">
                        <!-- Avatar del paciente -->
                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-gray-200 flex items-center justify-center mb-4">
                            <!-- Aquí puedes poner una imagen de avatar si tienes la url -->
                            <span class="text-4xl sm:text-5xl text-gray-400">
                                <i class="fas fa-user"></i>
                                {{ pacienteToEdit.iniciales }}
                            </span>
                        </div>
                        <!-- Nombre completo -->
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2">
                            {{ $page.props.paciente.nombre }} {{ $page.props.paciente.primer_apellido }} {{
                                $page.props.paciente.segundo_apellido }}
                        </h2>
                        <!-- Iniciales o identificador -->
                        <p class="text-gray-500 mb-4 text-center">
                            <span class="font-semibold">Nº asegurado:</span> {{ $page.props.paciente.numero_asegurado }}
                        </p>
                    </div>

                    <div class="border-t px-4 sm:px-8 py-4 sm:py-6 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <p class="text-gray-600 font-semibold mb-1">Teléfono</p>
                            <p class="text-gray-800 break-words">{{ $page.props.paciente.telefono }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 font-semibold mb-1">Fecha de nacimiento</p>
                            <p class="text-gray-800 break-words">{{ $page.props.paciente.fecha_nacimiento }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 font-semibold mb-1">Dirección</p>
                            <p class="text-gray-800 break-words">{{ $page.props.paciente.direccion }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 font-semibold mb-1">CP</p>
                            <p class="text-gray-800 break-words">{{ $page.props.paciente.cp }}</p>
                        </div>
                        <!-- Puedes agregar más campos según la información disponible -->
                    </div>
                </div>
            </div>
            <!-- Tabla de tratamientos -->
            <div class="w-full md:w-[60%] max-w-full md:max-w-6xl px-2 sm:px-4 md:px-6 mt-0">
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="border-t px-2 sm:px-8 py-4 sm:py-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th v-for="header in header_tratamientos" :key="header.title" class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider whitespace-nowrap">{{ header.title }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="bg-white even:bg-gray-50" v-for="tratamiento in tratamientos" :key="tratamiento.id">
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.fecha_recepcion_derivacion }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.autorizacion }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.numero_ss_auto }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.fecha_recepcion_autorizacion }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.fecha_caducidad_autorizacion }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.fecha_fin_tto }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.fecha_fin_tto }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.dias_espera }}</td>
                                        <td class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">
                                            <span
                                                :class="{
                                                    'bg-green-200 text-green-800 px-2 py-1 rounded': tratamiento.estado_tto === 'DOMI-I',
                                                    'bg-yellow-200 text-yellow-800 px-2 py-1 rounded': tratamiento.estado_tto === 'DOMI-CF',
                                                    'bg-blue-200 text-blue-800 px-2 py-1 rounded': tratamiento.estado_tto === 'DOMI-Z',
                                                    'bg-red-300 text-red-800 px-2 py-1 rounded': tratamiento.estado_tto === 'DOMI-Z',
                                                    'bg-gray-200 text-gray-800 px-2 py-1 rounded': ['DOMI-CF', 'DOMI-I', 'DOMI-Z'].indexOf(tratamiento.estado_tto) === -1
                                                }"
                                            >
                                                {{ tratamiento.estado_tto }}
                                            </span>
                                        </td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.ss_por_semana }}</td>
                                        <td  class="px-2 sm:px-6 py-4 whitespace-nowrap text-gray-800">{{ tratamiento.observaciones }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </FisiosLayout>
</template>
