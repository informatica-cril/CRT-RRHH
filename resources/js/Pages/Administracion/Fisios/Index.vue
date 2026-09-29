<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, watch} from 'vue';

import Vue3EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'
// Importamos el page para poder usar los elementos del backend
const page = usePage();
// Estado para mostrar/ocultar el modal
const mostrarModal = ref(false);
const errores = ref({});

// Estado para mostrar/ocultar el modal de edición
const mostrarModalEditar = ref(false);
// Importar usuarios tipo 3 (fisioterapeutas)
const usuariosTipo3 = computed(() => page.props.usuariosTipo3);


//capturamos mensaje de exito
const mensajeExito = computed(() => page.props.flash?.success);
const mensajeError = computed(() => page.props.flash?.error);
// Buscador
const filtroTexto = ref('');

// Configuración de la tabla
const headers = [
    { text: 'Nombre', value: 'name' },
    { text: 'Email', value: 'email' },
    // { text: 'Tipo de cuenta', value: 'tipo_cuenta' },
    { text: 'Tipo', value: 'tipo_cuenta_texto', sortable: true },
    { text: 'Acciones', value: 'action', sortable: false }
];

// Los items de la tabla son los usuarios tipo 3

// const items = computed(() => usuariosTipo3.value);
const tipoCuentaTexto = {
    1: 'Administrador',
    2: 'Recepción',
    3: 'Fisio',
};
const items = computed(() =>
    usuariosTipo3.value.map(users => ({
        ...users,
        tipo_cuenta_texto: tipoCuentaTexto[users.tipo_cuenta] || 'Desconocido',
    }))
);

// Estado reactivo para el formulario de usuario (según modelo User.php)
const form = reactive({
    name: '',
    email: '',
    // password: '',
    tipo_cuenta: '', // Por defecto fisioterapeuta
});

// Estado para edición de usuario
const usuarioEditando = reactive({
    id: '',
    name: '',
    email: '',
    tipo_cuenta: '',
});

// depurar codigo
onMounted(() => {
//   console.log('ttos_cf:', usuariosTipo3.value);
});


// Función para enviar el formulario de creación de usuario
function crearUsuario() {
    router.post('add_usuario', form, {
        onSuccess: () => {
            mostrarModal.value = false;
            Object.keys(form).forEach(key => form[key] = '');
            errores.value = {};
        },
        onError: (err) => {
            errores.value = err;
            mensajeExito.value = '';
        },
        onFinish: () => {}
    });
}

// Función para abrir el modal de edición y cargar datos del usuario
function abrirModalEditar(item) {
    usuarioEditando.id = item.id;
    usuarioEditando.name = item.name;
    usuarioEditando.email = item.email;
    usuarioEditando.tipo_cuenta = item.tipo_cuenta;
    mostrarModalEditar.value = true;
    errores.value = {};
}

// Función para cerrar el modal de edición
function cerrarModalEditar() {
    mostrarModalEditar.value = false;
    usuarioEditando.id = null;
    usuarioEditando.name = '';
    usuarioEditando.email = '';
    usuarioEditando.tipo_cuenta = 3;
    errores.value = {};
}

// Función para guardar cambios de usuario editado
function guardarCambiosUsuario() {
    router.put(`update_usuario/${usuarioEditando.id}`, usuarioEditando, {
        onSuccess: () => {
            mostrarModalEditar.value = false;
            errores.value = {};
        },
        onError: (err) => {
            errores.value = err;
            mensajeExito.value = '';
        },
        onFinish: () => {}
    });
}

// Función para eliminar usuario
function eliminarUsuario(item) {
    if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
        router.delete(`delete_usuario/${item.id}`, {
            onError: (err) => {
                errores.value = err;
            }
        });
    }
}


</script>

<template>
    <Head title="Fisioterapeutas" />
    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de fisioterapeutas
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Botón para abrir el modal -->
                    <div class="flex justify-end p-4">
                        <button
                            @click="mostrarModal = true"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                        >
                            + Nuevo usuario
                        </button>
                    </div>

                    <div v-if="mensajeExito" class="bg-green-100 text-green-800 p-4 rounded mb-4">
                        {{ mensajeExito }}
                    </div>
                    <div v-if="mensajeError" class="bg-red-100 text-red-800 p-4 rounded mb-4">
                        {{ mensajeError }}
                    </div>

                    <!-- Tabla de fisioterapeutas (usuarios tipo 3) -->
                    <div class="p-6">
                        <!-- Buscador -->
                        <input
                            v-model="filtroTexto"
                            type="text"
                            placeholder="🔍 Buscar por nombre, email..."
                            class="border border-gray-300 shadow-sm px-4 mb-2 rounded w-full max-w-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        
                        <Vue3EasyDataTable
                            :headers="headers"
                            :items="items"
                            :search-value="filtroTexto"
                            :search-field="['name', 'email', 'tipo_cuenta']"
                            show-index
                            table-class-name="w-full border border-gray-200 text-sm"
                            header-text-direction="center"
                            alternating
                            body-text-direction="center"
                        >
                            <template #item-action="item">
                                <div class="flex justify-center">
                                    <button
                                        class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-1 rounded mr-2"
                                        @click="abrirModalEditar(item)"
                                    >
                                        Editar
                                    </button>
                                    
                                    <button
                                        class="bg-red-500 hover:bg-red-700 text-white px-3 py-1 rounded"
                                        @click="eliminarUsuario(item)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </template>
                        </Vue3EasyDataTable>
                    </div>

                    <!-- Modal para editar fisioterapeuta -->
                    <div v-if="mostrarModalEditar" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto" style="display: grid; place-items: center;">
                        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
                            <button @click="cerrarModalEditar" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                            <h3 class="text-lg font-semibold mb-4">Editar fisioterapeuta</h3>
                            <form @submit.prevent="guardarCambiosUsuario" class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <input v-model="usuarioEditando.name" type="text" class="mt-1 block w-full border rounded p-2" />
                                    <span v-if="errores.name" class="text-red-600 text-sm">{{ errores.name }}</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input v-model="usuarioEditando.email" type="email" class="mt-1 block w-full border rounded p-2" />
                                    <span v-if="errores.email" class="text-red-600 text-sm">{{ errores.email }}</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tipo de cuenta</label>
                                    <select v-model="usuarioEditando.tipo_cuenta" class="mt-1 block w-full border rounded p-2">
                                        <option value="3">Fisioterapeuta</option>
                                        <option value="2">Gestión/Recepcion</option>
                                        <!-- <option value="1">Administrador</option> -->
                                    </select>
                                    <span v-if="errores.tipo_cuenta" class="text-red-600 text-sm">{{ errores.tipo_cuenta }}</span>
                                </div>
                                <div class="flex justify-end mt-4">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para crear usuario -->
        <div v-if="mostrarModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 overflow-y-auto" style="display: grid; place-items: center;">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
                <button @click="mostrarModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                <h3 class="text-lg font-semibold mb-4">Crear nuevo usuario</h3>
                <form @submit.prevent="crearUsuario" class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.name" class="text-red-600 text-sm">{{ errores.name }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input v-model="form.email" type="email" class="mt-1 block w-full border rounded p-2" />
                        <span v-if="errores.email" class="text-red-600 text-sm">{{ errores.email }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo de cuenta</label>
                        <select v-model="form.tipo_cuenta" class="mt-1 block w-full border rounded p-2">
                            <option value="" disabled selected>Seleccione una opcion</option>
                            <option value="3">Fisioterapeuta</option>
                            <option value="2">Gestión/Recepcion</option>
                            <!-- <option value="1">Administrador</option> -->
                        </select>
                        <span v-if="errores.tipo_cuenta" class="text-red-600 text-sm">{{ errores.tipo_cuenta }}</span>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdministracionLayout>
</template>
