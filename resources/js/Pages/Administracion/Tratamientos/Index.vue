<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
//easytable
import Vue3EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'

//importamos el page para poder usar los elementos del backend
const page = usePage();
// Estado para mostrar/ocultar el modal
const mostrarModal = ref(false);
const mostrarModalConfirmacion = ref(false);
//importar errores y mensajes de exito
const mensajeExito =  computed(() => page.props.flash?.success);
const mensajeError =  computed(() => page.props.flash?.error);
const errores = ref({});
//importar usuarios 
const usuariosTipo3 = computed(() => page.props.usuariosTipo3);
//formularios hospital bcn
const formularios = computed(() => page.props.formularios);
//const formulario a eliminar
const formToDel = reactive({
    id:'',
    fullname: '',
});
//headers de la tabla
const headers = [
    { text: 'Nombre', value: 'nombre', sortable: true },
    { text: 'Primer apellido', value: 'primer_apellido', sortable: true },
    { text: 'Segundo apellido', value: 'segundo_apellido', sortable: true },
    { text: 'Telefono', value: 'telefono', sortable: true },
    { text: 'Numero asegurado', value: 'numero_asegurado', sortable: true },
    { text: 'Dia previsto alta ', value: 'dia_previsto_alta', sortable: true },
    { text: 'Fisio registra', value: 'fisio_registra', sortable: true },
    { text: 'Acciones', value: 'actions', sortable: false },
]
const filtroTexto = ref('');




// Estado reactivo para el formulario de tratamiento
const form = reactive({
    id: '',
    fecha_recepcion_derivacion: '',
    nombre: '',
    primer_apellido: '',
    segundo_apellido: '',
    fecha_nacimiento: '',
    telefono: '',
    direccion: '',
    cp: '',
    numero_asegurado: '',
    autorizacion: '',
    numero_ss_auto: '',
    fecha_recepcion_autorizacion: '',
    fecha_caducidad_autorizacion: '',
    situacion: '',
    fisioterapeuta: '',
    patologia: '',
    fecha_inicio_tto: '',
    dias_espera: '',
    fecha_fin_tto: '',
    estado_tto: '',
    observaciones: '',
    ss_por_semana: '',
    fisioterapeuta: '',
});

// Función para enviar el formulario
function crearTratamiento() {
    router.post('add_tratamientos', form, {
        onSuccess: () => {
            mostrarModal.value = false;
            Object.keys(form).forEach(key => form[key] = '');
            errores.value = {}; // Limpiar errores
            mensajeError.value = '';
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
            mensajeExito.value = ''; // <-- Limpia el mensaje de éxito si hay error
        },
        onFinish: () => {
            // Opcional: cualquier cosa que quieras hacer al finalizar la petición
        }
    });
}

function modalCrearTratamiento (itemTto){
    form.nombre = itemTto.nombre;
    form.primer_apellido = itemTto.primer_apellido;
    form.segundo_apellido = itemTto.segundo_apellido;
    form.telefono = itemTto.telefono;
    form.numero_asegurado = itemTto.numero_asegurado;
    form.fecha_recepcion_derivacion = itemTto.dia_previsto_alta;
    mostrarModal.value = true;

}

function modalEliminarFormulario(itemTto){
    mostrarModalConfirmacion.value = true;
    formToDel.id = itemTto.id
    formToDel.fullname = `${itemTto.nombre} ${itemTto.primer_apellido} ${itemTto.segundo_apellido}`
}


function eliminarPacienteFormulario() {
    router.delete(route('administracion.eliminarFormulario',formToDel.id), formToDel, {
        onSuccess: () => {
            mostrarModalConfirmacion.value = false;
            Object.keys(form).forEach(key => formToDel[key] = '');
            errores.value = {}; // Limpiar errores
        },
        onError: (err) => {
            errores.value = err; // Aquí Inertia te pasa los errores de validación
            mensajeExito.value = ''; // <-- Limpia el mensaje de éxito si hay error
        },
    });
}

</script>
<template>
    <Head title="Tratamientos" />
    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Índice de Tratamientos
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Botón para abrir el modal -->
                    <div class="flex justify-end p-6">
                        <button
                            @click="mostrarModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                        >
                            + Crear Tratamiento
                        </button>
                    </div>

                    <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4 ">
                        {{ mensajeExito }}
                    </div>

                    <!-- <div v-if="Object.keys(errores).length" class="bg-red-100 text-red-800 p-4 rounded mb-4">
                        <ul>
                            <li v-for="(mensaje, campo) in errores" :key="campo">{{ mensaje }}</li>
                        </ul>
                    </div> -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 p-6">
                        <!-- Card Tratamientos Activos -->
                        <Link :href="route('administracion.tratamientosActivos')" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-green-50 hover:bg-green-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">✅</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos activos</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Visualiza y gestiona los tratamientos en curso.</span>
                            </div>
                        </Link>
                        <!-- Card Tratamientos en Espera -->
                        <Link :href="route('administracion.tratamientosEspera')" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-yellow-50 hover:bg-yellow-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">⏳</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos en espera</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Consulta los tratamientos pendientes de inicio.</span>
                            </div>
                        </Link>
                        <!-- Card Tratamientos Finalizados -->
                        <Link :href="route('administracion.tratamientosFinalizados')" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-red-50 hover:bg-red-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">✔️</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos finalizados</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Revisa el historial de tratamientos concluidos.</span>
                            </div>
                        </Link>
                    </div>

                    <div class="mt-10 p-5">

                        <input v-model="filtroTexto" type="text"
                            placeholder="🔍 Buscar por nombre, apellidos o patología..."
                            class="border border-gray-300 shadow-sm px-4 py-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" />

                        <Vue3EasyDataTable 
                            :headers="headers" 
                            :items="formularios" 
                            :search-value="filtroTexto"
                            :search-field="['nombre', 'primer_apellido', 'segundo_apellido', 'telefono', 'numero_asegurado', 'fisio_registra']"
                            show-index 
                            table-class-name="w-full border border-gray-200 text-sm mb-20"
                            header-text-direction="center"
                            body-text-direction="center" 
                            alternating
                            :body-row-class-name="getRowClass"
                            :sort-by="'dias_espera'"
                            :sort-type="'desc'"
                        >

                            <template #item-actions="item">
                                <div class="flex flex-col space-y-2">
                                    <button
                                        class="bg-blue-500 hover:bg-blue-700 text-white mt-2  rounded"
                                        @click="modalCrearTratamiento(item)">
                                        Crear tratamiento
                                    </button>
                                    <button
                                        class="bg-rose-500 hover:bg-rose-700 text-white mb-3  rounded"
                                        @click="modalEliminarFormulario(item)">
                                        Eliminar de la lista
                                    </button>
                                </div>
                            </template>
                            
                        </Vue3EasyDataTable>

                    </div>
                </div>


            </div>
        </div>

        <!-- Modal para crear tratamiento -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto" style="display: grid; place-items: center;">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                <button @click="mostrarModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                <h3 class="text-lg font-semibold mb-4">Crear nuevo tratamiento</h3>
                <form @submit.prevent="crearTratamiento" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha recepción derivación</label>
                        <input v-model="form.fecha_recepcion_derivacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_recepcion_derivacion" class="text-red-600 text-sm">{{ errores.fecha_recepcion_derivacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input v-model="form.nombre" type="text" class="mt-1 block w-full border rounded p-2"  />
                        <span v-if="errores.nombre" class="text-red-600 text-sm">{{ errores.nombre }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Primer apellido</label>
                        <input v-model="form.primer_apellido" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.primer_apellido" class="text-red-600 text-sm">{{ errores.primer_apellido }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Segundo apellido</label>
                        <input v-model="form.segundo_apellido" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.segundo_apellido" class="text-red-600 text-sm">{{ errores.segundo_apellido }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha nacimiento</label>
                        <input v-model="form.fecha_nacimiento" type="date" min="0" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_nacimiento" class="text-red-600 text-sm">{{ errores.fecha_nacimiento }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input v-model="form.telefono" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.telefono" class="text-red-600 text-sm">{{ errores.telefono }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dirección</label>
                        <input v-model="form.direccion" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.direccion" class="text-red-600 text-sm">{{ errores.direccion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CP</label>
                        <input v-model="form.cp" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.cp" class="text-red-600 text-sm">{{ errores.cp }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Número asegurado</label>
                        <input v-model="form.numero_asegurado" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.numero_asegurado" class="text-red-600 text-sm">{{ errores.numero_asegurado }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Autorización</label>
                        <input v-model="form.autorizacion" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.autorizacion" class="text-red-600 text-sm">{{ errores.autorizacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Número SS auto</label>
                        <input v-model="form.numero_ss_auto" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.numero_ss_auto" class="text-red-600 text-sm">{{ errores.numero_ss_auto }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha recepción autorización</label>
                        <input v-model="form.fecha_recepcion_autorizacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_recepcion_autorizacion" class="text-red-600 text-sm">{{ errores.fecha_recepcion_autorizacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha caducidad autorización</label>
                        <input v-model="form.fecha_caducidad_autorizacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_caducidad_autorizacion" class="text-red-600 text-sm">{{ errores.fecha_caducidad_autorizacion }}</span>
                    </div>
                    
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700">Fisioterapeuta</label>
                        <input v-model="form.fisioterapeuta" type="text" class="mt-1 block w-full border rounded p-2" />
                    </div> -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha inicio tratamiento</label>
                        <input v-model="form.fecha_inicio_tto" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_inicio_tto" class="text-red-600 text-sm">{{ errores.fecha_inicio_tto }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha fin tratamiento</label>
                        <input v-model="form.fecha_fin_tto" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_fin_tto" class="text-red-600 text-sm">{{ errores.fecha_fin_tto }}</span>
                    </div>
              
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Usuario asignado</label>
                        <select v-model="form.fisioterapeuta" required class="mt-1 block w-full border rounded p-2">
                            <option value="" >Seleccione un usuario</option>
                            <option 
                                v-for="usuario in usuariosTipo3" 
                                :key="usuario.id" 
                                :value="usuario.id"
                            >
                                {{ usuario.name }}
                            </option>
                        </select>
                        <span v-if="errores.fisioterapeuta" class="text-red-600 text-sm">{{ errores.fisioterapeuta }}</span>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                        <textarea v-model="form.observaciones" class="mt-1 block w-full border rounded p-2"></textarea>
                        <span v-if="errores.observaciones" class="text-red-600 text-sm">{{ errores.observaciones }}</span>
                    </div>
               
                    <div class="md:col-span-2 flex justify-end mt-4">
                        <button type="submit" @click="mostrarModal = false" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mx-2">
                            Cerrar
                        </button>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal confirmacion limpiar de la lista -->
        <div v-if="mostrarModalConfirmacion" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto" style="display: grid; place-items: center;">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                <button @click="mostrarModalConfirmacion = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                <h3 class="text-lg font-semibold mb-4">Eliminar paciente de la lista</h3>
                <form @submit.prevent="eliminarFormulario" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <p class="text-red-600 font-semibold mb-4">
                           ¿Seguro que quieres eliminar al paciente  <b>{{ formToDel.fullname }}</b> de la lista? <br>
                        </p>
                        <p>
                            <span class="font-normal text-gray-800 mt-10">Recuerda que luego no podrás crearle un tratamiento.</span>
                            <br>
                            <span class="font-normal text-gray-800">Hazlo, si no lo has hecho ya.</span>
                        </p>
                    </div>
               
                    <div class="md:col-span-2 flex justify-end mt-4">
                        <button type="submit" @click="mostrarModalConfirmacion = false" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mx-2">
                            Cerrar
                        </button>
                        <button type="submit" @click="eliminarPacienteFormulario" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Tratamiento creado, eliminar paciente
                        </button>
                    </div>
                </form>
            </div>
        </div>



       
    </AdministracionLayout>
</template>
