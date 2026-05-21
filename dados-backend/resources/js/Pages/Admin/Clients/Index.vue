<template>
    <AdminLayout title="Clientes y Sucursales">
        <!-- Header actions -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="relative flex-1">
                <input v-model="search" @input="doSearch" placeholder="Buscar por nombre o RUC..."
                    class="w-full sm:w-72 pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-2 ml-auto">
                <!-- Sync Contifico -->
                <button @click="syncClients" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-600 rounded-xl text-sm font-medium transition disabled:opacity-50">
                    <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ syncing ? 'Sincronizando...' : 'Sincronizar Contifico' }}
                </button>
                <!-- Import Excel -->
                <button @click="showImport = true"
                    class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 rounded-xl text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Importar Excel
                </button>
                <Link :href="route('admin.clients.create')"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Cliente
                </Link>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Razón Social /
                            Nombre</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Nombre Local</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">RUC /
                            Identificación</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Sucursales</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Email</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in clients.data" :key="client.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-medium text-slate-800">{{ client.name }}</p>
                        </td>
                        <td class="px-6 py-3.5">
                            <p class="text-sm text-slate-600">{{ client.nombre_local ?? '—' }}</p>
                        </td>
                        <td class="px-6 py-3.5 text-sm">
                            <span class="text-slate-800 font-medium">{{ client.identification ?? '—' }}</span>
                            <span v-if="client.identification_type" class="ml-2 text-xs text-slate-400 capitalize">
                                ({{ client.identification_type }})
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-600 text-xs font-semibold">
                                {{ client.branches_count }} local(es)
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ client.email ?? '—' }}</td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.clients.edit', client.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition"
                                    title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="deleteClient(client.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                    title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!clients.data.length">
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">No hay clientes</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="clients.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>Mostrando {{ clients.from }}–{{ clients.to }} de {{ clients.total }}</span>
                <div class="flex gap-1">
                    <Link v-for="link in clients.links" :key="link.label" :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-lg transition text-xs"
                        :class="link.active ? 'bg-cyan-500 text-white' : 'hover:bg-slate-100 text-slate-600'">
                        <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Import modal -->
        <div v-if="showImport"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Importar Clientes desde Excel</h3>
                <p class="text-slate-500 text-sm mb-4">El archivo debe tener las columnas: <strong>TIPO, IDENTIFICACION,
                        CODIGO, Persona, Nombre Local, SUCURSALES, Ruta</strong></p>
                <form @submit.prevent="submitImport">
                    <input type="file" ref="fileInput" accept=".xlsx,.xls,.csv"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm mb-4" required />
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="showImport = false"
                            class="px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-100 rounded-xl transition">Cancelar</button>
                        <button type="submit" :disabled="importForm.processing"
                            class="px-4 py-2.5 text-sm bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition">
                            {{ importForm.processing ? 'Importando...' : 'Importar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ clients: Object, filters: Object });
const search = ref(props.filters?.search ?? '');
const showImport = ref(false);
const syncing = ref(false);
const fileInput = ref(null);
const importForm = useForm({ file: null });

let searchTimer;
function doSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.clients.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 400);
}

function syncClients() {
    syncing.value = true;
    router.post(route('admin.clients.sync'), {}, {
        onFinish: () => { syncing.value = false; }
    });
}

function deleteClient(id) {
    if (confirm('¿Eliminar este cliente y todas sus sucursales?')) {
        router.delete(route('admin.clients.destroy', id));
    }
}

function submitImport() {
    importForm.file = fileInput.value.files[0];
    importForm.post(route('admin.clients.import'), {
        onSuccess: () => { showImport.value = false; }
    });
}
</script>
