<script setup>
import AdministracionLayout from '@/Layouts/AdministracionLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const settings = ref({
    enabled: true,
    threshold_hours: 5,
    break_duration_minutes: 20,
    auto_start: false,
    grace_period_minutes: 0,
});

const loading = ref(true);
const saving = ref(false);
const message = ref('');
const messageType = ref('');

async function fetchSettings() {
    loading.value = true;
    try {
        const res = await axios.get('/api/v1/break-settings');
        settings.value = res.data;
    } catch (e) {
        console.error('Error carregant configuració:', e);
    } finally {
        loading.value = false;
    }
}

async function saveSettings() {
    saving.value = true;
    message.value = '';
    try {
        const res = await axios.put('/api/v1/break-settings', settings.value);
        settings.value = res.data;
        message.value = 'Configuració desada correctament.';
        messageType.value = 'success';
    } catch (e) {
        message.value = 'Error desant la configuració.';
        messageType.value = 'error';
        console.error(e);
    } finally {
        saving.value = false;
        setTimeout(() => { message.value = ''; }, 3000);
    }
}

onMounted(() => {
    fetchSettings();
});
</script>

<template>
    <Head title="Configuració de pausa" />

    <AdministracionLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Configuració de pausa obligatòria
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div v-if="loading" class="text-center text-gray-400 py-12">
                        Carregant...
                    </div>

                    <form v-else @submit.prevent="saveSettings" class="space-y-6">
                        <!-- Mensaje -->
                        <div
                            v-if="message"
                            :class="messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                            class="p-3 rounded-lg text-sm"
                        >
                            {{ message }}
                        </div>

                        <!-- Activar/desactivar -->
                        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                            <div>
                                <label class="font-medium text-gray-800">Pausa obligatòria activada</label>
                                <p class="text-sm text-gray-500">Activa o desactiva la pausa obligatòria per a tots els treballadors.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    v-model="settings.enabled"
                                    type="checkbox"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <!-- Umbral de horas -->
                        <div>
                            <label class="block font-medium text-gray-800 mb-1">
                                Llindar d'hores seguides
                            </label>
                            <p class="text-sm text-gray-500 mb-2">
                                Nombre d'hores seguides treballades abans de requerir la pausa.
                            </p>
                            <input
                                v-model.number="settings.threshold_hours"
                                type="number"
                                min="1"
                                max="12"
                                class="w-32 border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                            <span class="ml-2 text-gray-500 text-sm">hores</span>
                        </div>

                        <!-- Duración de pausa -->
                        <div>
                            <label class="block font-medium text-gray-800 mb-1">
                                Durada de la pausa
                            </label>
                            <p class="text-sm text-gray-500 mb-2">
                                Minuts que durarà la pausa obligatòria.
                            </p>
                            <input
                                v-model.number="settings.break_duration_minutes"
                                type="number"
                                min="5"
                                max="60"
                                class="w-32 border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                            <span class="ml-2 text-gray-500 text-sm">minuts</span>
                        </div>

                        <!-- Auto-start -->
                        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                            <div>
                                <label class="font-medium text-gray-800">Inici automàtic</label>
                                <p class="text-sm text-gray-500">
                                    Si està activat, la pausa comença automàticament quan se supera el llindar.
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    v-model="settings.auto_start"
                                    type="checkbox"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <!-- Período de gracia -->
                        <div>
                            <label class="block font-medium text-gray-800 mb-1">
                                Període de gràcia
                            </label>
                            <p class="text-sm text-gray-500 mb-2">
                                Minuts de tolerància abans de forçar la pausa.
                            </p>
                            <input
                                v-model.number="settings.grace_period_minutes"
                                type="number"
                                min="0"
                                max="30"
                                class="w-32 border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                            <span class="ml-2 text-gray-500 text-sm">minuts</span>
                        </div>

                        <!-- Botón guardar -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm disabled:opacity-50"
                            >
                                {{ saving ? 'Desant...' : 'Desar configuració' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdministracionLayout>
</template>
