<template>
    <AdminLayout title="Usuarios">
        <div class="flex items-center gap-3 mb-6">
            <div class="relative flex-1">
                <input v-model="search" @input="doSearch" placeholder="Buscar usuario..."
                    class="pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white w-72" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <Link :href="route('admin.users.create')"
                class="ml-auto flex items-center gap-2 px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Usuario
            </Link>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Nombre</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Email</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Rol</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Pedidos</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-white text-sm font-bold">
                                    {{ user.name.charAt(0) }}
                                </div>
                                <span class="text-sm font-medium text-slate-800">{{ user.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ user.email }}</td>
                        <td class="px-6 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                :class="{ 'bg-violet-50 text-violet-600': user.role === 'admin', 'bg-amber-50 text-amber-600': user.role === 'supervisor', 'bg-cyan-50 text-cyan-600': user.role === 'seller' || !user.role }">
                                {{ user.role === 'admin' ? 'Administrador' : user.role === 'supervisor' ? 'Supervisor' :
                                'Vendedor' }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-700 font-medium">{{ user.orders_count }}</td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.users.edit', user.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="deleteUser(user.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="5" class="px-6 py-10 text-center text-slate-400 text-sm">No hay usuarios</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ users: Object, filters: Object });
const search = ref(props.filters?.search ?? '');

let searchTimer;
function doSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.users.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 400);
}

function deleteUser(id) {
    if (confirm('¿Eliminar este usuario?')) router.delete(route('admin.users.destroy', id));
}
</script>
