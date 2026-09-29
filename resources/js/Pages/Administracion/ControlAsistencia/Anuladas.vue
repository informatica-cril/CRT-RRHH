<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
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
//importar usuarios 
const usuariosTipo3 = computed(() => page.props?.usuariosTipo3);

const mensajeExito = computed(() => page.props.falsh?.success);
const mensajeError = computed(() => page.props.falsh?.error);

//manejo errores
const errores = ref({});

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

];

// Estado que guarda la data cruda
const asistenciasRaw = ref(page.props?.asistencias ?? []);
const items = computed(() =>
    asistenciasRaw  .value.map(asistencias => {
    const rawDate = new Date(asistencias.fecha_hora_asistencia);
    return {
      ...asistencias,
      nombre_completo_paciente: `${asistencias.tratamiento.nombre} ${asistencias.tratamiento.primer_apellido} ${asistencias.tratamiento.segundo_apellido}`,
      fecha_asis_formated: rawDate.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' }),
      fecha_asis_raw: rawDate.getTime(), // 👈 este es el campo real para ordenar
    };
  })
);


//elementos del form:
//depurar codigo
// onMounted(() => {
//   console.log('asistenciasRaw:', asistenciasRaw.value);
// });


const filterItems = async (filterID) => {
  try {
        const response = await axios.post(route('administracion.consultarFisioAsistencia'), {
            idfisio: filterID,
            tipo_asistencia: 0,
        })
        // Suponiendo que la respuesta es { asistencias: [...] }
        asistenciasRaw.value = response.data ?? []
        // console.log( asistenciasRaw.value );

  } catch (error) {
        console.error('Error al obtener asistencias:', error)
        // Opcional: mostrar alerta o notificación
  }
}

</script>

<template>

    <Head title="Asistencias" />
    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Asistencias anuladas 🗑️
            </h2>
        </template>

        <div class="py-6 min-h-screen bg-gray-50">
            <!-- <div class="w-full h-full px-4"> -->
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white overflow-hidden shadow-sm rounded-lg h-full">
                    <div class="p-6">

                        <div v-if="mensajeSuccess" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                            {{ mensajeSuccess }}
                        </div>

                        <input v-model="filtroTexto" type="text"
                            placeholder="🔍 Buscar por nombre, apellidos o email"
                            class="border border-gray-300 shadow-sm px-4 py-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" />

                            <select class="border border-gray-300 shadow-sm px-4 mx-2 py-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
                                @change="filterItems($event.target.value)"
                            >
                                <option value="" readonly selected>Selecciona un fisioterapeuta</option>
                                <option v-for="usuario in usuariosTipo3" :key="usuario.id" :value="usuario.id">
                                    {{ usuario.name }}
                                </option>
                            </select>
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
                                
                            </Vue3EasyDataTable>

                    
                    </div>
                </div>
            </div>
        </div>
    </AdministracionLayout>
</template>
