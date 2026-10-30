<template>
    <AdminLayout title="Configuración de Contifico">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-50 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">API de Contifico</h2>
                        <p class="text-sm text-slate-500">Administra las credenciales para la sincronización automática.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                                API Key
                                <span class="text-xs font-normal text-slate-400">(Requerido para la
                                    autenticación)</span>
                            </label>
                            <input v-model="form.CONTIFICO_API_KEY" type="password"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                placeholder="Ingresa tu API Key de Contifico" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                                API Token
                                <span class="text-xs font-normal text-slate-400">(Opcional, según el uso)</span>
                            </label>
                            <input v-model="form.CONTIFICO_API_TOKEN" type="text"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                placeholder="Ingresa tu Token si es necesario" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                                Frecuencia de Sincronización / Verificación con Contifico
                                <span class="text-xs font-normal text-slate-400">(Evita saturar el sistema)</span>
                            </label>
                            <select v-model="form.CONTIFICO_SYNC_INTERVAL"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50">
                                <option value="1">Cada 1 Hora</option>
                                <option value="12">Cada 12 Horas (Recomendado)</option>
                                <option value="24">Cada 24 Horas</option>
                            </select>
                            <p class="text-xs text-slate-500">
                                Controla la frecuencia de verificación del estado de facturas vencidas para no saturar las consultas externas a Contifico.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                                Google Maps API Key
                                <span class="text-xs font-normal text-slate-400">(Requerido para Reverse Geocoding: Sector y Parroquia)</span>
                            </label>
                            <input v-model="form.GOOGLE_MAPS_API_KEY" type="password"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                placeholder="AIzaSy..." />
                        </div>
                    </div>

                    <!-- Sección de Actualización de la App -->
                    <div class="pt-6 border-t border-slate-100">
                        <h3 class="text-md font-bold text-slate-800 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Actualizaciones de la Aplicación Móvil
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-700">
                                    Última Versión (Ej: 1.0.5)
                                </label>
                                <input v-model="form.LATEST_APP_VERSION" type="text"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                    placeholder="Ej: 1.0.5" />
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-slate-700">
                                    Versión Mínima Requerida (Ej: 1.0.4)
                                </label>
                                <input v-model="form.MIN_REQUIRED_APP_VERSION" type="text"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                    placeholder="Ej: 1.0.4" />
                            </div>
                        </div>

                        <div class="space-y-2 mt-6">
                            <label class="text-sm font-semibold text-slate-700">
                                Enlace de Descarga de APK (URL)
                            </label>
                            <input v-model="form.APP_APK_URL" type="text"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 transition-all text-slate-600 bg-slate-50/50"
                                placeholder="Ej: https://app.grupo-dados.com/downloads/dados-app.apk" />
                        </div>

                        <div v-if="userRole === 'superadmin'" class="space-y-2 mt-6">
                            <label class="text-sm font-semibold text-slate-700 block">
                                Subir Archivo APK (Reemplazará la URL de descarga)
                            </label>
                            <div class="flex items-center gap-4">
                                <input type="file" ref="apkFileInput" @change="handleFileChange" accept=".apk"
                                    class="hidden" />
                                <button type="button" @click="$refs.apkFileInput.click()"
                                    class="px-4 py-2 border border-slate-300 hover:border-cyan-500 rounded-xl text-sm font-medium text-slate-700 hover:text-cyan-600 bg-white transition-all flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    {{ form.apk_file ? 'Archivo: ' + form.apk_file.name : 'Seleccionar Archivo .apk' }}
                                </button>
                                <button v-if="form.apk_file" type="button" @click="clearFile" class="text-xs text-red-500 hover:underline">
                                    Quitar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" :disabled="form.processing"
                            class="px-8 py-3 bg-gradient-to-r from-cyan-500 to-blue-600 text-white rounded-xl font-bold shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 transform hover:-translate-y-0.5 active:translate-y-0 transition-all disabled:opacity-50 flex items-center gap-2">
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: Object
});

const page = usePage();
const userRole = page.props.auth.user?.role;
const apkFileInput = ref(null);

const form = useForm({
    CONTIFICO_API_KEY: props.settings.CONTIFICO_API_KEY || '',
    CONTIFICO_API_TOKEN: props.settings.CONTIFICO_API_TOKEN || '',
    CONTIFICO_SYNC_INTERVAL: String(props.settings.CONTIFICO_SYNC_INTERVAL || '12'),
    GOOGLE_MAPS_API_KEY: props.settings.GOOGLE_MAPS_API_KEY || '',
    LATEST_APP_VERSION: props.settings.LATEST_APP_VERSION || '',
    MIN_REQUIRED_APP_VERSION: props.settings.MIN_REQUIRED_APP_VERSION || '',
    APP_APK_URL: props.settings.APP_APK_URL || '',
    apk_file: null,
});

function handleFileChange(event) {
    form.apk_file = event.target.files[0];
}

function clearFile() {
    form.apk_file = null;
    if (apkFileInput.value) {
        apkFileInput.value.value = '';
    }
}

function submit() {
    form.post(route('admin.settings.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.apk_file = null;
            if (apkFileInput.value) {
                apkFileInput.value.value = '';
            }
        }
    });
}
</script>
