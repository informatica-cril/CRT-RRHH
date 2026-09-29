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
// Flash message
const mensajeSuccess = ref('');
// Texto del buscador
const filtroTexto = ref('');
// Datos desde backend
const ttos_cf = computed(() => page.props?.tto_cf || []);
//importar usuarios 
const usuariosTipo3 = computed(() => page.props.usuariosTipo3);
//manejo errores
const errores = ref({});

const modalInfo = ref(false);
const tratamientoInfo = reactive({});

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

// Función para abrir el modal y cargar el tratamiento seleccionado
function abrirModalVerInfo(tratamiento) {
    // tratamientoInfo = tratamiento;
    //reasinga el tratamiento a la variable V-model tratamineto info
    Object.assign(tratamientoInfo, tratamiento);
    tratamientoInfo.fullname = `${tratamiento.nombre} ${tratamiento.apellidos}`

    console.log(tratamientoInfo)
    modalInfo.value = true;

}


// Función para guardar cambios (aquí solo cierra el modal, deberías hacer petición al backend)
function guardarCambios() {
    router.post(route('administracion.edit_tratamientos'), tratamientoEditando.value, {
        onSuccess: () => {
            mostrarModal.value = false;
            mensajeSuccess.value = 'Tratamiento editado correctamente.';
            Object.keys(tratamientoEditando.value).forEach(key => tratamientoEditando.value[key] = '');
            errores.value = {}; // Limpiar errores
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
            mensajeSuccess.value = ''; // <-- Limpia el mensaje de éxito si hay error
        },
    });
    // Aquí deberías hacer la petición al backend para guardar los cambios
    // mostrarModal.value = false;
}

</script>

<template>

    <Head title="Tratamientos" />
    <FisiosLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tratamientos finalizados ✔️
            </h2>
        </template>

        <div class="py-6 min-h-screen bg-gray-50">
            <div class="w-full h-full px-4">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg h-full">
                    <div class="p-6">

                        <div v-if="mensajeSuccess" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                            {{ mensajeSuccess }}
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
                                <button
                                    class="bg-yellow-500 hover:bg-yellow-700 text-black hover:text-white px-3 py-1 rounded"
                                    @click="abrirModalVerInfo(item)">
                                    Ver
                                </button>
                            </template>
                        </Vue3EasyDataTable>

                        <!-- Modal para ver informacion del tto  -->
                        <div v-if="modalInfo" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
                            style="display: grid; place-items: center;">
                            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                                <button @click="modalInfo = false"
                                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                <h3 class="text-lg font-semibold mb-4">Ver informacion del proceso de {{ tratamientoInfo.fullname }} </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2">

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Nombre completo</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.fullname }}</p>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Edat</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.edad }}</p>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Telefono</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.telefono }}</p>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Direccion</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200">  
                                            <a
                                                :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(tratamientoInfo.direccion + ', ' + tratamientoInfo.cp)}`"
                                                class="text-blue-600 underline hover:text-blue-800 transition-colors"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {{ tratamientoInfo.direccion }}, {{ tratamientoInfo.cp }}
                                            </a>
                                        </p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Fecha recepcion derivacion</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ new Date(tratamientoInfo.fecha_recepcion_derivacion).toLocaleDateString('es-ES') }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Nº Autorizacion</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.autorizacion }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Dias de espera</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.dias_espera }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Estado tratamiento</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.estado_tto }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Fecha caducidad autorizacion</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ new Date(tratamientoInfo.fecha_caducidad_autorizacion).toLocaleDateString('es-ES') }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Fecha recepcion autorizacion</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200">{{ new Date(tratamientoInfo.fecha_recepcion_autorizacion).toLocaleDateString('es-ES') }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Fecha inicio tratamiento</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200">{{ new Date(tratamientoInfo.fecha_fin_tto).toLocaleDateString('es-ES') }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Fecha fin tratamiento</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200">{{ new Date(tratamientoInfo.fecha_inicio_tto).toLocaleDateString('es-ES') }}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Numero asegurado</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.numero_asegurado}}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Numero sesiones autorizadas</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.numero_ss_auto}}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Sesiones por semana</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.ss_por_semana}}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Patologia</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.patologia}}</p>
                                    </div>

                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Usuario asignado</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.usuario.name}}</p>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Email usuario asignado</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.usuario.email}}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1">
                                    <div>
                                        <span class="font-semibold text-sm text-slate-500 font-mono mb-3 dark:text-slate-400">Observaciones</span>
                                        <p class="not-italic text-base font-medium text-slate-900 dark:text-slate-200"> {{ tratamientoInfo.observaciones}}</p>
                                    </div>
                                </div>

                                <hr class="my-4 ">

                                <div class="md:col-span-2 flex justify-end mt-4">
                                    <button @click="modalInfo = false"
                                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 mx-2 px-4 rounded">
                                        Cerrar
                                    </button>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FisiosLayout>
</template>
