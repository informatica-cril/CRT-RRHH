<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router, usePage} from '@inertiajs/vue3';
import { reactive, ref, computed} from 'vue';

const errores = ref({});
const mensajeExito = computed(() => usePage().props.flash?.success);
const mensajeError = computed(() => usePage().props.flash?.error);

// Estado para edición de usuario
const searchItem = reactive({
    textToSearch : '',
});

const formulario = reactive({
    nombre : '',
    primer_apellido : '',
    segundo_apellido : '',
    telefono : '',
    numero_asegurado : '',
    patologia : '',
    dia_previsto_alta : '',
    fisio_registra : '',
    fisio_password: '',
});
          
// Función para enviar el formulario
function enviarFormulario() {
    router.post(route('addPacienteHospital'), formulario, {
        onSuccess: () => {
            Object.keys(formulario).forEach(key => formulario[key] = '');
            errores.value = {}; // Limpiar errores
            mensajeError.value = '';
            console.log(mensajeExito.value);
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

</script>

<template>
    <Head title="Dashboard" />
    
        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 bg-gray-50  py-5">
                <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                    {{ mensajeExito }}
                </div>
                <div v-if="mensajeError" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                    {{ mensajeError }}
                </div>
                <!-- <div class="bg-green-100 text-green-800 p-4 rounded mb-4">
                    Formulario registrado correctamente
                </div> -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg ">
               
                    <h2 class="font-semibold text-2xl p-6 text-gray-900 mb-1">Formulario pacientes pendiente de altas</h2>
                    <form @submit.prevent="enviarFormulario" class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input v-model="formulario.nombre" type="text" id="nombre" name="nombre" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <span v-if="errores.nombre" class="text-red-500 text-xs">{{ errores.nombre }}</span>
                            </div>
                            <!-- Primer Apellido -->
                            <div>
                                <label for="primer_apellido" class="block text-sm font-medium text-gray-700 mb-1">Primer Apellido</label>
                                <input v-model="formulario.primer_apellido" type="text" id="primer_apellido" name="primer_apellido" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <span v-if="errores.primer_apellido" class="text-red-500 text-xs">{{ errores.primer_apellido }}</span>
                            </div>
                            <!-- Segundo Apellido -->
                            <div>
                                <label for="segundo_apellido" class="block text-sm font-medium text-gray-700 mb-1">Segundo Apellido</label>
                                <input v-model="formulario.segundo_apellido" type="text" id="segundo_apellido" name="segundo_apellido" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.segundo_apellido" class="text-red-500 text-xs">{{ errores.segundo_apellido }}</span>
                            </div>
                            <!-- Teléfono -->
                            <div>
                                <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input v-model="formulario.telefono" type="tel" id="telefono" name="telefono" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.telefono" class="text-red-500 text-xs">{{ errores.telefono }}</span>
                            </div>
                            <!-- Número Asegurado -->
                            <div>
                                <label for="numero_asegurado" class="block text-sm font-medium text-gray-700 mb-1">Número de asegurado</label>
                                <input v-model="formulario.numero_asegurado" type="text" id="numero_asegurado" name="numero_asegurado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.numero_asegurado" class="text-red-500 text-xs">{{ errores.numero_asegurado }}</span>
                            </div>
                            <!-- Patología -->
                            <div>
                                <label for="patologia" class="block text-sm font-medium text-gray-700 mb-1">Patología</label>
                                <input v-model="formulario.patologia" type="text" id="patologia" name="patologia" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.patologia" class="text-red-500 text-xs">{{ errores.patologia }}</span>
                            </div>
                            <!-- Día previsto de alta -->
                            <div>
                                <label for="dia_previsto_alta" class="block text-sm font-medium text-gray-700 mb-1">Día previsto de alta</label>
                                <input v-model="formulario.dia_previsto_alta" type="date" id="dia_previsto_alta" name="dia_previsto_alta" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.dia_previsto_alta" class="text-red-500 text-xs">{{ errores.dia_previsto_alta }}</span>
                            </div>
                            <!-- Fisio que registra -->
                            <div>
                                <label for="fisio_registra" class="block text-sm font-medium text-gray-700 mb-1">Fisio que registra</label>
                                <input v-model="formulario.fisio_registra" type="text" id="fisio_registra" name="fisio_registra" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span v-if="errores.fisio_registra" class="text-red-500 text-xs">{{ errores.fisio_registra }}</span>
                            </div>
                            <!-- Fisio contraseña -->
                            <div>
                                <label for="fisio_password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña del fisio</label>
                                <input v-model="formulario.fisio_password" type="password" id="fisio_password" name="fisio_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <span v-if="errores.fisio_password" class="text-red-500 text-xs">{{ errores.fisio_password }}</span>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-emerald-200 text-black rounded-md shadow hover:bg-emerald-400 transition">Guardar</button>
                        </div>
                    </form>

                   
                </div>
            </div>
        </div>
</template>
