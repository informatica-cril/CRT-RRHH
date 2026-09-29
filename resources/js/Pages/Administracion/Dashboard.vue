<script setup>
// import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
                

</script>

<template>
    <Head title="Dashboard" />
    
    <AdministracionLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Bienvenido  {{ $page.props.auth.user.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <h2 class="font-semibold text-xl p-6 text-gray-900 mb-1">¿Qué quieres hacer hoy?</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 p-6">
                        <!-- Card Tratamientos -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-gray-50 hover:bg-gray-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">💊</span>
                                <span class="font-semibold text-lg text-gray-800">Tratamientos</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Gestiona los tratamientos.</span>
                            </div>
                        </Link>
                        <!-- Card Control de Asistencia -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-gray-50 hover:bg-gray-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">📝</span>
                                <span class="font-semibold text-lg text-gray-800">Control de asistencia</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Revisa la asistencia del personal.</span>
                            </div>
                        </Link>
                        <!-- Card Registrar Fisioterapeuta -->
                        <!-- <Link :href="route('fisioterapeutas.create')" class="block"> -->
                        <!-- <Link :href="'#'" class="block"> -->
                        <Link href="#" class="block">
                            <div class="border rounded-lg shadow hover:shadow-lg transition p-6 bg-gray-50 hover:bg-gray-100 h-full flex flex-col items-center justify-center">
                                <span class="text-2xl mb-2">🧑‍⚕️</span>
                                <span class="font-semibold text-lg text-gray-800">Registrar fisioterapeuta</span>
                                <span class="text-gray-500 text-sm mt-2 text-center">Agrega un nuevo fisioterapeuta al sistema.</span>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

    </AdministracionLayout>
</template>
