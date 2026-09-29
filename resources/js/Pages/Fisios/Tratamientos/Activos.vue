<script setup>
import FisiosLayout from '@/Layouts/FisiosLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { reactive ,onMounted } from 'vue';

// Easy Data Table
import Vue3EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'

const page = usePage();
// Texto del buscador
const filtroTexto = ref('');
// Datos desde backend
const ttos_cf = computed(() => page.props?.tto_cf || []);
//importar usuarios 
const mensajeExito = computed(() => page.props.flash?.success);
const mensajeError = computed(() => page.props.flash?.error);
//manejo errores
const errores = ref({});

const showModalRegistrarAsistencia = ref(false);
const showModalFinalizarProceso = ref(false);

// Items preparados (con apellidos combinados)
const items = computed(() =>
    ttos_cf.value.map(tto => ({
        ...tto,
        apellidos: `${tto.primer_apellido || ''} ${tto.segundo_apellido || ''}`,
        patologia: tto.patologia ?? 'No establecida',
        fecha_inicio_tto: tto.fecha_inicio_tto ?? 'No establecida',
        fecha_fin_tto: tto.fecha_fin_tto ?? 'No establecida',
        ss_por_semana: tto.ss_por_semana ?? 'No establecida',
        direccion_cp: `<a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(tto.direccion + ', ' + tto.cp)}" target="_blank" rel="noopener noreferrer">${tto.direccion}, ${tto.cp}</a>`,
  
    }))
);

//elementos del form:
//depurar codigo
// onMounted(() => {
//   console.log('ttos_cf:', ttos_cf.value);
// });
// Encabezados para la tabla
const headers = [
    { text: 'Nombre', value: 'nombre', sortable: true },
    { text: 'Apellidos', value: 'apellidos', sortable: true },
    { text: 'Fecha recepción', value: 'fecha_recepcion_derivacion', sortable: true },
    { text: 'Edad', value: 'edad', sortable: true },
    { text: 'Teléfono', value: 'telefono', sortable: true },
    // { text: 'Dirección', value: 'direccion', sortable: true },
      // Agregar columna 'Dirección' con dirección y código postal concatenados
    { text: 'Dirección', value: 'direccion_cp', sortable: false },
    { text: 'CP', value: 'cp', sortable: true },
    { text: 'Nº asegurado', value: 'numero_asegurado', sortable: true },
    { text: 'Autorización', value: 'autorizacion', sortable: true },
    { text: 'Nº SS auto', value: 'numero_ss_auto', sortable: true },
    { text: 'Patología', value: 'patologia', sortable: true },
    { text: 'Fecha inicio (TTO)', value: 'fecha_inicio_tto', sortable: true },
    { text: 'Fecha fin (TTO)', value: 'fecha_fin_tto', sortable: true },
    { text: 'Nº sesiones semanales', value: 'ss_por_semana', sortable: true },
    { text: 'Estado', value: 'estado_tto', sortable: true },
    { text: 'Observaciones', value: 'observaciones', sortable: true },
    { text: 'Acciones', value: 'action', sortable: false },
];

const tratamientoFinalizar = reactive({
    id: '',
    fullname: '',
    fecha_fin_tto: '',
})

const ttoAsistencia = reactive({
    id_tto: '',
    tipo_asistencia: '',
    comentario_asistencia: '',
    tipo_de_firma: '',
    fecha_hora_asistencia: '',
    dia_semana: '',
    usuario_registra: '',
    id_usuario_registra: '',
    fecha_registro: '',
    fullname: '',
})

// Función para abrir el modal y cargar el tratamiento seleccionado
function modalRegistrarAsistencia(tratamiento){
    showModalRegistrarAsistencia.value = true;
    // ttoAsistencia.value = tratamiento;
    ttoAsistencia.fullname = `${tratamiento.nombre} ${tratamiento.apellidos}`
    ttoAsistencia.id_tto = tratamiento.id;
    ttoAsistencia.tipo_asistencia = 1;
}
function modalFinalizarProceso(tratamiento){
    showModalFinalizarProceso.value = true;
    tratamientoFinalizar.fullname = `${tratamiento.nombre} ${tratamiento.apellidos}`
    tratamientoFinalizar.id = tratamiento.id;

}

// Función para guardar cambios (aquí solo cierra el modal, deberías hacer petición al backend)
function registrarAsistencia() {
    // console.log('Objeto recibido:', JSON.stringify(ttoAinicniar, null, 2));
    router.post(route('fisios.registrarAsistencia'), ttoAsistencia, {
        onSuccess: () => {
            showModalRegistrarAsistencia.value = false;
            Object.keys(ttoAsistencia).forEach(key => ttoAsistencia[key] = ''); //limpiamos info del ttaInicniar por is hay que inicniar otro
            errores.value = {}; // Limpiar errores
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
        },
    });
    // mostrarModal.value = false;
}

function finalizarProceso() {
    // console.log('Objeto recibido:', JSON.stringify(ttoAinicniar, null, 2));
    router.post(route('fisios.finalizarProcesoTto'), tratamientoFinalizar, {
        onSuccess: () => {
            showModalFinalizarProceso.value = false;
            Object.keys(tratamientoFinalizar).forEach(key => tratamientoFinalizar[key] = ''); //limpiamos info del ttaInicniar por is hay que inicniar otro
            errores.value = {}; // Limpiar errores
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
        },
    });
    // mostrarModal.value = false;
}



</script>

<template>

    <Head title="Tratamientos" />
    <FisiosLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tratamientos en activos 
            </h2>
        </template>

        <div class="py-6 min-h-screen bg-gray-50">
            <div class="w-full h-full px-4">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg h-full">
                    <div class="p-6">

                        <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                            {{ mensajeExito }}
                        </div>
                        <div v-if="mensajeError" class="bg-red-100 text-red-800 p-4 rounded mb-4">
                            {{ mensajeError }}
                        </div>

                        <input v-model="filtroTexto" type="text"
                            placeholder="🔍 Buscar por nombre, apellidos o patología..."
                            class="border border-gray-300 shadow-sm px-4 py-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" />

                        <Vue3EasyDataTable :headers="headers" :items="items" :search-value="filtroTexto"
                            :search-field="['nombre', 'apellidos', 'patologia', 'fecha_recepcion_derivacion', 'edad', 'telefono', 'direccion', 'cp', 'numero_asegurado', 'autorizacion', 'numero_ss_auto', 'fecha_recepcion_autorizacion', 'fecha_caducidad_autorizacion', 'estado_tto', 'email_fisio', 'observaciones']"
                            show-index table-class-name="w-full border border-gray-200 text-sm mb-20"
                            header-text-direction="center" body-text-direction="center" alternating>

                            <template #item-direccion_cp="item">
                                <a
                                    :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(item.direccion + ', ' + item.cp)}`"
                                    class="text-blue-600 underline hover:text-blue-800 transition-colors"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ item.direccion }}, {{ item.cp }}
                                </a>
                            </template>
                            <template #item-action="item">
                                <div class="flex justify-center">
                                    <button
                                        class="bg-green-500 hover:bg-green-700 text-black  px-2 my-2 rounded"
                                        @click="modalRegistrarAsistencia(item)">
                                        Registrar asistencia
                                    </button>
                                    <button
                                        class="bg-amber-500 hover:bg-amber-700 text-black  px-2 mx-2 my-2 rounded"
                                        @click="modalFinalizarProceso(item)">
                                        Finalizar proceso
                                    </button>
                                </div>
                            </template>
                        </Vue3EasyDataTable>

                        <!-- Modal para registrar asistencia al tratamiento -->
                        <div v-if="showModalRegistrarAsistencia" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
                            style="display: grid; place-items: center;">
                            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                                <button @click="showModalRegistrarAsistencia = false"
                                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                <h3 class="text-lg font-semibold mb-4">Registrar asistencia</h3>
                       
                                <form @submit.prevent="registrarAsistencia">
                                    
                                    <h2>Registrar asistencia para el paciente <b>{{ ttoAsistencia.fullname }}</b></h2>
                                    <div class="grid grid-cols-1 md:grid-cols-1 gap-2">
                                            
                                       
                                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"> 
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Duracion</label>
                                                <select v-model="ttoAsistencia.tipo_de_firma" class="mt-1 block w-full border rounded p-2">
                                                    <option value="" disabled selected>Selecciona la duracion</option>
                                                    <option value="30">30 min</option>
                                                    <option value="45" disabled>45 min</option>
                                                </select>
                                                <span v-if="errores.tipo_de_firma" class="text-red-600 text-sm">{{ errores.tipo_de_firma }}</span>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Fecha y hora de la asistencia</label>
                                                <input v-model="ttoAsistencia.fecha_hora_asistencia" type="datetime-local" class="mt-1 block w-full border rounded p-2" />
                                                <span v-if="errores.fecha_hora_asistencia" class="text-red-600 text-sm">{{ errores.fecha_hora_asistencia }}</span>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2 grid grid-cols-1  gap-4 mt-4"> 
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Día de la semana</label>
                                                <select v-model="ttoAsistencia.dia_semana" class="mt-1 block w-full border rounded p-2">
                                                    <option value="" disabled selected>Selecciona dia de la semana</option>
                                                    <option value="1">Lunes</option>
                                                    <option value="2">Martes</option>
                                                    <option value="3">Miercoles</option>
                                                    <option value="4">Jueves</option>
                                                    <option value="5">Viernes</option>
                                                    <option value="6">Sabado</option>
                                                    <option value="0">Domingo</option>
                                                </select>
                                                <span v-if="errores.dia_semana" class="text-red-600 text-sm">{{ errores.dia_semana }}</span>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                                                <textarea v-model="ttoAsistencia.comentario_asistencia" class="mt-1 block w-full border rounded p-2" placeholder="Añade observaciones"></textarea>
                                                <span v-if="errores.comentario_asistencia" class="text-red-600 text-sm">{{ errores.comentario_asistencia }}</span>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2 flex justify-end mt-4">
                                            <button @click="showModalRegistrarAsistencia = false"
                                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 mx-2 px-4 rounded">
                                                Cerrar
                                            </button>
                                            <button type="submit"
                                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                Guardar
                                            </button>
                                           
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>

                        <!-- modal para finalizar el proceso terapeutico -->
                        <div v-if="showModalFinalizarProceso" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
                            style="display: grid; place-items: center;">
                            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                                <button @click="showModalFinalizarProceso = false"
                                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                <h3 class="text-lg font-semibold mb-4">Finalizar proceso</h3>
                       
                                <form @submit.prevent="finalizarProceso">
                                    
                                    <h2>Finalizar el proceso del paciente <b>{{ tratamientoFinalizar.fullname }}</b></h2>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-1 gap-2">
                                        <div class="md:col-span-2 grid grid-cols-1  gap-4 mt-4"> 
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Fecha de finalizacion tratamiento <span class="text-red-600">*</span></label>
                                                <input v-model="tratamientoFinalizar.fecha_fin_tto" type="date" class="mt-1 block w-full border rounded p-2" />
                                                <span v-if="errores.fecha_fin_tto" class="text-red-600 text-sm">{{ errores.fecha_fin_tto }}</span>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2 flex justify-end mt-4">
                                            <button @click="showModalFinalizarProceso = false"
                                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 mx-2 px-4 rounded">
                                                Cerrar
                                            </button>
                                            <button type="submit"
                                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                Guardar
                                            </button>
                                           
                                        </div>
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
