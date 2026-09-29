<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
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

const mostrarModal = ref(false);
const tratamientoEditando = ref(null);

//elementos del form:
//depurar codigo
// onMounted(() => {
//   console.log('ttos_cf:', ttos_cf.value);
// });
// Encabezados para la tabla
const headers = [
    { text: 'Nombre', value: 'nombre', sortable: true },
    { text: 'Apellidos', value: 'apellidos', sortable: true },
    { text: 'Patología', value: 'patologia', sortable: true },
    { text: 'Fecha recepción', value: 'fecha_recepcion_derivacion', sortable: true },
    { text: 'Dias espera', value: 'dias_espera', sortable: true },
    { text: 'Fecha nacimiento', value: 'paciente.fecha_nacimiento', sortable: true },
    { text: 'Teléfono', value: 'telefono', sortable: true },
    { text: 'Dirección', value: 'direccion', sortable: true },
    { text: 'CP', value: 'cp', sortable: true },
    { text: 'Nº asegurado', value: 'numero_asegurado', sortable: true },
    { text: 'Autorización', value: 'autorizacion', sortable: true },
    { text: 'Nº SS auto', value: 'numero_ss_auto', sortable: true },
    { text: 'Fecha recepción autorización', value: 'fecha_recepcion_autorizacion', sortable: true },
    { text: 'Fecha caducidad autorización', value: 'fecha_caducidad_autorizacion', sortable: true },
    { text: 'Estado', value: 'estado_tto', sortable: true },
    { text: 'Fisioterapeuta', value: 'usuario.name', sortable: true },
    { text: 'Fecha inicio tratamiento', value: 'fecha_inicio_tto', sortable: true },
    { text: 'Fecha fin tratamiento', value: 'fecha_fin_tto', sortable: true },
    { text: 'Observaciones', value: 'observaciones', sortable: true },
    { text: 'Acciones', value: 'action', sortable: false },

];

// Items preparados (con apellidos combinados)
const items = computed(() =>
    ttos_cf.value.map(tto => ({
        ...tto,
        apellidos: `${tto.primer_apellido || ''} ${tto.segundo_apellido || ''}`,
        email_fisio: tto.usuario?.email || 'Sin asignar',
        dias_espera: (() => {
            if (!tto.fecha_recepcion_derivacion) return '';
            const fechaRecepcion = new Date(tto.fecha_recepcion_derivacion);
            const hoy = new Date();
            // Limpiar horas para comparar solo fechas
            fechaRecepcion.setHours(0,0,0,0);
            hoy.setHours(0,0,0,0);
            const diffMs = hoy - fechaRecepcion;
            const diffDias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            return diffDias >= 0 ? diffDias : 0;
        })(),
    }))
);

// Función para abrir el modal y cargar el tratamiento seleccionado
function abrirModalEditar(tratamiento) {
//   console.log('Objeto recibido:', JSON.stringify(tratamiento, null, 2));
  tratamientoEditando.value = { ...tratamiento };
  const fechaPaciente = tratamiento?.paciente?.fecha_nacimiento || '';
  tratamientoEditando.value.fecha_nacimiento = tratamiento.fecha_nacimiento || fechaPaciente || '';
  tratamientoEditando.value._fechaNacPreexistente = !!fechaPaciente;
  mostrarModal.value = true;
}


// Función para guardar cambios (aquí solo cierra el modal, deberías hacer petición al backend)
function guardarCambios() {
    const payload = { ...tratamientoEditando.value };
    delete payload._fechaNacPreexistente;
    router.post(route('administracion.edit_tratamientos'), payload, {
        onSuccess: () => {
            mostrarModal.value = false;
            mensajeSuccess.value = 'Tratamiento editado correctamente.';
            Object.keys(tratamientoEditando.value).forEach(key => tratamientoEditando.value[key] = '');
            errores.value = {}; // Limpiar errores
            router.reload({ only: ['tto_cf'] });
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
            mensajeSuccess.value = ''; // <-- Limpia el mensaje de éxito si hay error
        },
    });
    // Aquí deberías hacer la petición al backend para guardar los cambios
    // mostrarModal.value = false;
}


const getRowClass = (row) => {
  if (row.dias_espera > 7) {
    // return 'bg-red-400 text-white px-5 py-2 rounded';
    return 'bg-red-300 text-white px-5 py-2'
  } else if (row.dias_espera >= 4) {
    // return 'bg-orange-300 text-black px-5 py-2 rounded';
    return 'bg-red-300'
  }else{
    return 'bg-orange-300'
    // return 'bg-green-400 text-black px-5 py-2 rounded';
  }

                                        //   bg-red-400 text-white px-5 py-2 rounded ': value.dias_espera > 7,
                                        // 'bg-orange-300 text-black px-5 py-2 rounded ': value.dias_espera >= 4 && value <= 7,
                                        // 'bg-green-400 text-black px-5 py-2 rounded ': value.dias_espera >= 0 && value.dias_espera < 4,



  return '';
};

</script>

<template>

    <Head title="Tratamientos" />
    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tratamientos en espera 
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

                        <Vue3EasyDataTable 
                            :headers="headers" 
                            :items="items" 
                            :search-value="filtroTexto"
                            :search-field="['nombre', 'apellidos', 'patologia', 'fecha_recepcion_derivacion', 'fecha_nacimiento', 'telefono', 'direccion', 'cp', 'numero_asegurado', 'autorizacion', 'numero_ss_auto', 'fecha_recepcion_autorizacion', 'fecha_caducidad_autorizacion', 'estado_tto', 'email_fisio', 'observaciones']"
                            show-index 
                            table-class-name="w-full border border-gray-200 text-sm mb-20"
                            header-text-direction="center"
                            body-text-direction="center" 
                            alternating
                            :body-row-class-name="getRowClass"
                            :sort-by="'dias_espera'"
                            :sort-type="'desc'"
                        >
                            <!-- Clase de fila condicional -->
                            <template #item-dias_espera="value">
                                <span
                                    :class="{
                                        'bg-red-400 text-white px-5 py-2 rounded ': value.dias_espera > 7,
                                        'bg-orange-300 text-black px-5 py-2 rounded ': value.dias_espera >= 4 && value <= 7,
                                        'bg-green-400 text-black px-5 py-2 rounded ': value.dias_espera >= 0 && value.dias_espera < 4,
                                    }"
                                >
                                    {{ value.dias_espera }}
                                </span>
                            </template>
                                
                            <template #item-action="item">
                                <button
                                    class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-1 rounded"
                                    @click="abrirModalEditar(item)">
                                    Editar
                                </button>
                            </template>
                            
                        </Vue3EasyDataTable>

                        <!-- Modal para crear tratamiento -->
                        <div v-if="mostrarModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
                            style="display: grid; place-items: center;">
                            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                                <button @click="mostrarModal = false"
                                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                <h3 class="text-lg font-semibold mb-4">Editar tratamiento</h3>

                                <form @submit.prevent="guardarCambios">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <input v-model="tratamientoEditando.id" type="hidden" />
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha recepción derivación</label>
                                            <input v-model="tratamientoEditando.fecha_recepcion_derivacion" type="date" class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_recepcion_derivacion" class="text-red-600 text-sm">{{ errores.fecha_recepcion_derivacion }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Nombre(*)</label>
                                            <input v-model="tratamientoEditando.nombre" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.nombre" class="text-red-600 text-sm">{{ errores.nombre }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Primer apellido(*)</label>
                                            <input v-model="tratamientoEditando.primer_apellido" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.primer_apellido" class="text-red-600 text-sm">{{
                                                errores.primer_apellido
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Segundo apellido</label>
                                            <input v-model="tratamientoEditando.segundo_apellido" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.segundo_apellido" class="text-red-600 text-sm">{{
                                                errores.segundo_apellido }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha nacimiento(*)</label>
                                            <input v-model="tratamientoEditando.fecha_nacimiento" type="date" min="0"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_nacimiento" class="text-red-600 text-sm">{{ errores.fecha_nacimiento }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Teléfono(*)</label>
                                            <input v-model="tratamientoEditando.telefono" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.telefono" class="text-red-600 text-sm">{{ errores.telefono
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Dirección(*)</label>
                                            <input v-model="tratamientoEditando.direccion" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.direccion" class="text-red-600 text-sm">{{ errores.direccion
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">CP(*)</label>
                                            <input v-model="tratamientoEditando.cp" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.cp" class="text-red-600 text-sm">{{ errores.cp }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Número asegurado(*)</label>
                                            <input v-model="tratamientoEditando.numero_asegurado" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.numero_asegurado" class="text-red-600 text-sm">{{
                                                errores.numero_asegurado }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Autorización(*)</label>
                                            <input v-model="tratamientoEditando.autorizacion" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.autorizacion" class="text-red-600 text-sm">{{
                                                errores.autorizacion
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Número SS auto(*)</label>
                                            <input v-model="tratamientoEditando.numero_ss_auto" type="text"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.numero_ss_auto" class="text-red-600 text-sm">{{
                                                errores.numero_ss_auto
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha recepción
                                                autorización(*)</label>
                                            <input v-model="tratamientoEditando.fecha_recepcion_autorizacion" type="date"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_recepcion_autorizacion" class="text-red-600 text-sm">{{
                                                errores.fecha_recepcion_autorizacion }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha caducidad
                                                autorización(*)</label>
                                            <input v-model="tratamientoEditando.fecha_caducidad_autorizacion" type="date"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_caducidad_autorizacion" class="text-red-600 text-sm">{{
                                                errores.fecha_caducidad_autorizacion }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Estado tratamiento(*)</label>
                                            <select v-model="tratamientoEditando.estado_tto" required
                                                class="mt-1 block w-full border rounded p-2">
                                                <option value="" disabled>Seleccione un estado</option>
                                                <option value="DOMI-CF">CONSIGNADO FISIO</option>
                                                <option value="DOMI-I">PROCESO INICIADO</option>
                                                <option value="DOMI-Z">PROCESO FINALIZADO</option>
                                                <option value="DOMI-X">PROCESO ANULADO</option>
                                            </select>
                                            <span v-if="errores.estado_tto" class="text-red-600 text-sm">{{
                                                errores.estado_tto
                                                }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha inicio tratamiento(*)</label>
                                            <input v-model="tratamientoEditando.fecha_inicio_tto" type="date"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_inicio_tto" class="text-red-600 text-sm">{{
                                                errores.fecha_inicio_tto }}</span>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Fecha fin tratamiento(*)</label>
                                            <input v-model="tratamientoEditando.fecha_fin_tto" type="date"
                                                class="mt-1 block w-full border rounded p-2" />
                                            <span v-if="errores.fecha_fin_tto" class="text-red-600 text-sm">{{
                                                errores.fecha_fin_tto }}</span>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Usuario asignado(*)</label>
                                            <select v-model="tratamientoEditando.fisioterapeuta" required
                                                class="mt-1 block w-full border rounded p-2">
                                                <option value="" disabled>Seleccione un usuario</option>
                                                <option v-for="usuario in usuariosTipo3" :key="usuario.id" :value="usuario.id">
                                                    {{ usuario.name }}
                                                </option>
                                            </select>
                                            <span v-if="errores.fisioterapeuta" class="text-red-600 text-sm">{{
                                                errores.fisioterapeuta
                                                }}</span>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                                            <textarea v-model="tratamientoEditando.observaciones"
                                                class="mt-1 block w-full border rounded p-2"></textarea>
                                            <span v-if="errores.observaciones" class="text-red-600 text-sm">{{
                                                errores.observaciones
                                                }}</span>
                                        </div>
                                        <div class="md:col-span-2 flex justify-end mt-4">
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
    </AdministracionLayout>
</template>
<style>
.customize-table {
  --easy-table-border: 1px solid #445269;
  --easy-table-row-border: 1px solid #445269;

  --easy-table-header-font-size: 14px;
  --easy-table-header-height: 50px;
  --easy-table-header-font-color: #c1cad4;
  --easy-table-header-background-color: #2d3a4f;

  --easy-table-header-item-padding: 10px 15px;

  --easy-table-body-even-row-font-color: #fff;
  --easy-table-body-even-row-background-color: #4c5d7a;

  --easy-table-body-row-font-color: #c0c7d2;
  --easy-table-body-row-background-color: #2d3a4f;
  --easy-table-body-row-height: 50px;
  --easy-table-body-row-font-size: 14px;

  --easy-table-body-row-hover-font-color: #2d3a4f;
  --easy-table-body-row-hover-background-color: #eee;

  --easy-table-body-item-padding: 10px 15px;

  --easy-table-footer-background-color: #2d3a4f;
  --easy-table-footer-font-color: #c0c7d2;
  --easy-table-footer-font-size: 14px;
  --easy-table-footer-padding: 0px 10px;
  --easy-table-footer-height: 50px;

  --easy-table-rows-per-page-selector-width: 70px;
  --easy-table-rows-per-page-selector-option-padding: 10px;
  --easy-table-rows-per-page-selector-z-index: 1;


  --easy-table-scrollbar-track-color: #2d3a4f;
  --easy-table-scrollbar-color: #2d3a4f;
  --easy-table-scrollbar-thumb-color: #4c5d7a;;
  --easy-table-scrollbar-corner-color: #2d3a4f;

  --easy-table-loading-mask-background-color: #2d3a4f;
}
</style>