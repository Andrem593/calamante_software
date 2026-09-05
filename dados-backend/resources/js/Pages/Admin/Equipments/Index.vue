<template>
    <AdminLayout title="Catálogo de Equipos">
        <div class="flex items-center gap-3 mb-6">
            <div class="relative">
                <input v-model="search" @input="doSearch" placeholder="Buscar equipo..."
                    class="pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white w-72" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <Link :href="route('admin.equipments.create')"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Equipo
                </Link>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Modelo</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Capacidad</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Fecha de Registro</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="equipment in equipments.data" :key="equipment.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5 text-sm font-semibold text-slate-800">
                            {{ equipment.model }}
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-600">
                            {{ equipment.capacity }}
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-400">
                            {{ new Date(equipment.created_at).toLocaleDateString('es-EC') }}
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.equipments.edit', equipment.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="del(equipment.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!equipments.data.length">
                        <td colspan="4" class="px-6 py-10 text-center text-slate-400 text-sm">No hay equipos en el catálogo</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :data="equipments" />
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({ equipments: Object, filters: Object });
const search = ref(props.filters?.search ?? '');

let timeout = null;
function doSearch() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('admin.equipments.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 300);
}

function del(id) {
    if (confirm('¿Está seguro de que desea eliminar este equipo del catálogo? Esta acción no afectará a los registros históricos pero desasociará el equipo de los clientes.')) {
        router.delete(route('admin.equipments.destroy', id));
    }
}
</script>

<style scoped>
@reference "../../../../css/app.css";
</style>
