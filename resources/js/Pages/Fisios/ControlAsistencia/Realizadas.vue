<script setup>
import FisiosLayout from '@/Layouts/FisiosLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { reactive ,onMounted } from 'vue';
import axios from 'axios';

// Easy Data Table
import Vue3EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'

const page = usePage();
// Flash message
const mensajeSuccess = ref('');
// Texto del buscador
const filtroTexto = ref('');
// Datos desde backend
const ttos_cf = computed(() => page.props?.tto_cf || []);
//variable modal:
const showDeleteModal = ref(false); 

const mensajeExito = computed(() => page.props.flash?.success);
const mensajeError = computed(() => page.props.flash?.error);

//manejo errores
const errores = ref({});

const asisToDel = reactive({
    id:'',
    nombre_completo_paciente: '',
})
//cabezados para la tabla
const headers = [
    { text: 'Nombre paciente', value: 'nombre_completo_paciente', sortable: true },
    { text: 'Fisio', value: 'usuario.name', sortable: true },
    { text: 'Fisio email', value: 'usuario_registra', sortable: true },
    // { text: 'Fecha asistencia', value: 'fecha_asis_formated', sortable: true },
    { text: 'Fecha asistencia', value: 'fecha_asis_raw', sortable: true }, // 👈 ordenar por timestamp
    { text: 'Dia de la semana', value: 'dia_semana', sortable: true },
    { text: 'Estado tratamiento', value: 'tratamiento.estado_tto', sortable: true },
    { text: 'Comentario asistencia ', value: 'comentario_asistencia', sortable: true },
    { text: 'Eliminar', value: 'accion', sortable: true },

];

// Estado que guarda la data cruda
const asistenciasRaw = ref(page.props?.asistencias ?? []);

const items = computed(() =>
    asistenciasRaw.value.map(asistencias => {
    const rawDate = new Date(asistencias.fecha_hora_asistencia);
    return {
      ...asistencias,
      nombre_completo_paciente: `${asistencias.tratamiento.nombre} ${asistencias.tratamiento.primer_apellido} ${asistencias.tratamiento.segundo_apellido}`,
      fecha_asis_formated: rawDate.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' }),
      fecha_asis_raw: rawDate.getTime(), // 👈 este es el campo real para ordenar
    };
  })
);

//depurar codigo
// onMounted(() => {
//   console.log('asistenciasRaw:', asistenciasRaw.value);
// });

function openDeleteModal(asisTable){
    asisToDel.id = asisTable.id
    asisToDel.nombre_completo_paciente = asisTable.nombre_completo_paciente
    showDeleteModal.value = true;

}

function deleteModalRequest(){

    router.post(route('fisios.eliminarAsistencia'), asisToDel, {
        onSuccess: () => {
            showDeleteModal.value = false;
            Object.keys(asisToDel).forEach(key => asisToDel[key] = ''); //limpiamos info del ttaInicniar por is hay que inicniar otro
            errores.value = {}; // Limpiar errores
            console.log(mensajeExito);
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
        },
    });
}

</script>

<template>

    <Head title="Asistencias" />
    <FisiosLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Asistencias realizadas 🗓️
            </h2>
        </template>

        <div class="py-6 min-h-screen bg-gray-50">
            <!-- <div class="w-full h-full px-4"> -->
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm rounded-lg h-full">
                    <div class="p-6">

                        <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                            {{ mensajeExito }}
                        </div>
                        <div v-if="mensajeError" class="bg-red-100 text-red-800 p-4 rounded mb-4">
                            {{ mensajeError }}
                        </div>

                        <input v-model="filtroTexto" type="text"
                            placeholder="🔍 Buscar por nombre, apellidos o email..."
                            class="border border-gray-300 shadow-sm px-4 py-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" />

                        
                        <span  class="text-red-600 text-sm"></span>

                        <Vue3EasyDataTable
                            :headers="headers"
                            :items="items"
                            :search-value="filtroTexto"
                            :search-field=" ['nombre_completo_paciente','usuario.name','usuario_registra','fecha_asis_formated','dia_semana','tratamiento.estado_tto','comentario_asistencia']"
                            show-index
                            table-class-name="w-full border border-gray-200 text-sm mb-20"
                            header-text-direction="center"
                            body-text-direction="center"
                            alternating
                        >
                            <template #item-fecha_asis_raw="item">
                                {{ item.fecha_asis_formated }}
                            </template>

                            <template #item-accion="item">
                                <button
                                    class="bg-red-500 hover:bg-red-700 text-white px-3 py-1 rounded text-xs"
                                    @click="openDeleteModal(item)"
                                >
                                    Eliminar asistencia
                                </button>
                            </template>
                            
                        </Vue3EasyDataTable>
                        
                        
                        <!-- modal para finalizar el proceso terapeutico -->
                        <div v-if="showDeleteModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
                            style="display: grid; place-items: center;">
                            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                                <button @click="showDeleteModal = false"
                                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                <h3 class="text-lg font-semibold mb-4">Eliminar asistencia</h3>
                       
                                <form @submit.prevent="deleteModalRequest">
                                    <h2>¿Seguro que desea eliminar la asistencia del paciente <b>{{ asisToDel.nombre_completo_paciente }}</b>?</h2>
                                    <div class="md:col-span-2 flex justify-end mt-4">
                                        <button @click="showDeleteModal = false"
                                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 mx-2 px-4 rounded">
                                            Cerrar
                                        </button>
                                        <button type="submit"
                                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                            Guardar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        
                     



                    </div>
                      
                </div>
            </div>
        </div>
    </FisiosLayout>
</template>
