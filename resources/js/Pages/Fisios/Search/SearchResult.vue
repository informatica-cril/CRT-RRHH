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
const mensajeSuccess = ref('');
// Texto del buscador
const filtroTexto = ref('');
// Datos desde backend
const pacientes = computed(() => page.props?.pacientes || []);
const mensajeExito = computed(() => page.props.mensajeExito);

//manejo errores
const errores = ref({});

const mostrarModal = ref(false);
const tratamientoEditando = ref(null);

//elementos del form:
//depurar codigo
// onMounted(() => {
//   console.log('ttos_cf:', ttos_cf.value);
// });

// Items preparados (con apellidos combinados)
const items = computed(() =>
    pacientes.value.map(paciente => ({
        ...paciente,
        iniciales: `${paciente.nombre?.charAt(0) || ''}${paciente.primer_apellido?.charAt(0) || ''}`, 
        nombreCompleto: `${paciente.nombre} ${paciente.primer_apellido} ${paciente.segundo_apellido}`,
    }))
);
</script>

<template>

    <Head title="Resultaod busqueda" />
    <FisiosLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Resultados de la búsqueda
            </h2>
        </template>

        <div class="py-6 bg-gray-50 flex justify-center h-screen">
            <div class="w-full max-w-7xl px-10">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4">
                        <ul v-if="items && items.length > 0">
                            <!-- <li class="py-4 sm:py-5" > -->
                            <li class="py-4 sm:py-5 border-b rounded-xl hover:bg-slate-200 border-slate-200 bg-slate-100 px-2" v-for="paciente in items" :key="paciente.id"  >
                                <a :href="route('fisios.perfilPaciente', paciente.id)" class="text-base font-semibold   text-gray-900  dark:text-white">
                                    <div class="flex items-center space-x-4" >
                                        <div class="flex-shrink-0 ml-5 border-2 border-gray-300 rounded-full p-4">
                                            {{ paciente.iniciales }}
                                        </div>
                                        <div class="flex-1 min-w-0 pl-1">
                                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                                <p  class="text-blue-600 hover:underline">
                                                    {{ paciente.nombreCompleto }}
                                                </p>
                                            </span>
                                            <p class="text-sm  text-gray-500 dark:text-gray-400">
                                                {{ paciente.telefono }} |   {{ paciente.direccion }}, {{ paciente.cp }}
                                            </p>
                                        </div>
                                        <span>Numero asegurado:</span>
                                        <div
                                            class="inline-flex items-center text-lg font-bold text-gray-900 dark:text-white">
                                            {{ paciente.numero_asegurado }}
                                        </div>
                                    </div>
                                </a>
                            </li>

                        </ul>
                        <div v-if="items.length === 0" class="text-gray-500">
                            No se encontraron resultados.
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </FisiosLayout>
</template>
