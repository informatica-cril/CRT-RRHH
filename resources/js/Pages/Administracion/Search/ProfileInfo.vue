<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
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

// Estado para edición de usuario
const pacienteEditado = reactive({
    id: pacienteToEdit.value.id,
    numero_asegurado: pacienteToEdit.value.numero_asegurado,
    nombre: pacienteToEdit.value.nombre,
    primer_apellido: pacienteToEdit.value.primer_apellido,
    segundo_apellido: pacienteToEdit.value.segundo_apellido,
    telefono: pacienteToEdit.value.telefono,
    fecha_nacimiento: pacienteToEdit.value.fecha_nacimiento,
    direccion: pacienteToEdit.value.direccion,
    cp: pacienteToEdit.value.cp,
});


const tratamientoToCreate = reactive({
    fecha_recepcion_derivacion: '',
    numero_asegurado: pacienteToEdit.value.numero_asegurado,
    nombre: pacienteToEdit.value.nombre,
    primer_apellido: pacienteToEdit.value.primer_apellido,
    segundo_apellido: pacienteToEdit.value.segundo_apellido,
    telefono: pacienteToEdit.value.telefono,
    fecha_nacimiento: pacienteToEdit.value.fecha_nacimiento,
    direccion: pacienteToEdit.value.direccion,
    cp: pacienteToEdit.value.cp,
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

// Función para guardar cambios de usuario editado

function guardarCambiosPerfil() {
    router.put(route("administracion.pacientes.update", pacienteEditado.id), pacienteEditado, {
        onSuccess: () => {
            mostrarModal.value = false;
            errores.value = {};
        },
        onError: (err) => {
            console.log(err);
            errores.value = err;
            mensajeExito.value = '';
        },
        onFinish: () => { }
    });
}

function crearTratamiento() {
    router.post(route("administracion.add_tratamientos"), tratamientoToCreate, {
        onSuccess: () => {
            mostrarModalTratamiento.value = false;
            errores.value = {};
        },
        onError: (err) => {
            errores.value = err;
            mensajeExito.value = '';
        },
        onFinish: () => { }
    });
}

</script>

<template>

    <Head title="Perfil paciente" />
    <AdministracionLayout>

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
                    <div class="flex flex-col sm:flex-row justify-between items-center p-4 sm:p-8 gap-2">
                        <button
                            class="bg-blue-500 hover:bg-blue-600 text-white rounded-xl p-3 shadow focus:outline-none transition"
                            @click="mostrarModal = true" title="Editar perfil">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
                                <!-- Icono de bolígrafo diferente (por ejemplo, un lápiz simple) -->
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L7.75 18.963a1 1 0 01-.414.242l-4 1.25a.5.5 0 01-.632-.632l1.25-4a1 1 0 01.242-.414l12.666-12.666zM15 6l3 3" />
                            </svg>
                        </button>
                        <button
                            class="bg-green-500 hover:bg-green-600 text-white rounded-xl p-3 shadow focus:outline-none transition"
                            @click="mostrarModalTratamiento = true" title="Editar perfil">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>

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

        <!-- Modal para editar paciente -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto"
            style="display: grid; place-items: center;">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
                <button @click="mostrarModal = false"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                <h3 class="text-lg font-semibold mb-4">Editar paciente</h3>

                <form @submit.prevent="guardarCambiosPerfil">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Nombre</label>
                        <input v-model="pacienteEditado.nombre" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            required>
                        <span v-if="errores.nombre" class="text-red-600 text-sm">{{ errores.nombre }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Primer Apellido</label>
                        <input v-model="pacienteEditado.primer_apellido" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            required>
                        <span v-if="errores.primer_apellido" class="text-red-600 text-sm">{{ errores.primer_apellido
                        }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Segundo Apellido</label>
                        <input v-model="pacienteEditado.segundo_apellido" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.segundo_apellido" class="text-red-600 text-sm">{{ errores.segundo_apellido
                        }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Teléfono</label>
                        <input v-model="pacienteEditado.telefono" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.telefono" class="text-red-600 text-sm">{{ errores.telefono }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Número de asegurado</label>
                        <input v-model="pacienteEditado.numero_asegurado" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.numero_asegurado" class="text-red-600 text-sm">{{ errores.numero_asegurado
                        }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Fecha de nacimiento</label>
                        <input v-model="pacienteEditado.fecha_nacimiento" type="date"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.fecha_nacimiento" class="text-red-600 text-sm">{{ errores.fecha_nacimiento
                        }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Dirección</label>
                        <input v-model="pacienteEditado.direccion" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.direccion" class="text-red-600 text-sm">{{ errores.direccion }}</span>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">CP</label>
                        <input v-model="pacienteEditado.cp" type="text"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <span v-if="errores.cp" class="text-red-600 text-sm">{{ errores.cp }}</span>
                    </div>


                    <!-- Puedes agregar más campos según sea necesario -->
                    <div class="flex justify-end">
                        <button type="button" @click="mostrarModal = false"
                            class="hover:bg-red-300 bg-red-600 text-white hover:text-black font-bold py-2 px-4 rounded mr-2">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="hover:bg-green-500 bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal para agregar tratamiento -->  <!-- Modal para crear tratamiento -->
        <div v-if="mostrarModalTratamiento" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto" style="display: grid; place-items: center;">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
                <button @click="mostrarModalTratamiento = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                <h3 class="text-lg font-semibold mb-4">Crear nuevo tratamiento</h3>
                <form @submit.prevent="crearTratamiento" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha recepción derivación</label>
                        <input v-model="tratamientoToCreate.fecha_recepcion_derivacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_recepcion_derivacion" class="text-red-600 text-sm">{{ errores.fecha_recepcion_derivacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input v-model="tratamientoToCreate.nombre" type="text" class="mt-1 block w-full border rounded p-2"  />
                        <span v-if="errores.nombre" class="text-red-600 text-sm">{{ errores.nombre }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Primer apellido</label>
                        <input v-model="tratamientoToCreate.primer_apellido" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.primer_apellido" class="text-red-600 text-sm">{{ errores.primer_apellido }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Segundo apellido</label>
                        <input v-model="tratamientoToCreate.segundo_apellido" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.segundo_apellido" class="text-red-600 text-sm">{{ errores.segundo_apellido }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha nacimiento</label>
                        <input v-model="tratamientoToCreate.fecha_nacimiento" type="date" min="0" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_nacimiento" class="text-red-600 text-sm">{{ errores.fecha_nacimiento }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                        <input v-model="tratamientoToCreate.telefono" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.telefono" class="text-red-600 text-sm">{{ errores.telefono }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dirección</label>
                        <input v-model="tratamientoToCreate.direccion" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.direccion" class="text-red-600 text-sm">{{ errores.direccion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CP</label>
                        <input v-model="tratamientoToCreate.cp" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.cp" class="text-red-600 text-sm">{{ errores.cp }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Número asegurado</label>
                        <input v-model="tratamientoToCreate.numero_asegurado" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.numero_asegurado" class="text-red-600 text-sm">{{ errores.numero_asegurado }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Autorización</label>
                        <input v-model="tratamientoToCreate.autorizacion" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.autorizacion" class="text-red-600 text-sm">{{ errores.autorizacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Número SS auto</label>
                        <input v-model="tratamientoToCreate.numero_ss_auto" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.numero_ss_auto" class="text-red-600 text-sm">{{ errores.numero_ss_auto }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha recepción autorización</label>
                        <input v-model="tratamientoToCreate.fecha_recepcion_autorizacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_recepcion_autorizacion" class="text-red-600 text-sm">{{ errores.fecha_recepcion_autorizacion }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha caducidad autorización</label>
                        <input v-model="tratamientoToCreate.fecha_caducidad_autorizacion" type="date" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.fecha_caducidad_autorizacion" class="text-red-600 text-sm">{{ errores.fecha_caducidad_autorizacion }}</span>
                    </div>
                  
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Usuario asignado</label>
                        <select v-model="tratamientoToCreate.fisioterapeuta" required class="mt-1 block w-full border rounded p-2">
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
                        <textarea v-model="tratamientoToCreate.observaciones" class="mt-1 block w-full border rounded p-2"></textarea>
                        <span v-if="errores.observaciones" class="text-red-600 text-sm">{{ errores.observaciones }}</span>
                    </div>
                    
                    <!-- Puedes agregar más campos según sea necesario -->
                    <div class="md:col-span-2 flex justify-end mt-4">
                        <button type="button" @click="mostrarModalTratamiento = false"
                            class="hover:bg-red-300 bg-red-600 text-white hover:text-black font-bold py-2 px-4 rounded mr-2">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="hover:bg-green-500 bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>

    </AdministracionLayout>
</template>
