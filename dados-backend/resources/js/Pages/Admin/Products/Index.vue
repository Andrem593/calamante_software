<template>
    <AdminLayout title="Productos">
        <div class="flex items-center gap-3 mb-6">
            <div class="relative">
                <input v-model="search" @input="doSearch" placeholder="Buscar producto..."
                    class="pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white w-72" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <!-- Sync Contifico -->
                <button @click="syncProducts" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-600 rounded-xl text-sm font-medium transition disabled:opacity-50 border border-cyan-100">
                    <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ syncing ? 'Sincronizando...' : 'Sincronizar Contifico' }}
                </button>
                <Link :href="route('admin.products.create')"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Producto
                </Link>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Producto</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Categoría</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Descripción</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Precio / IVA</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Estado</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">App</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products.data" :key="product.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-50 to-cyan-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ product.name }}</p>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ product.sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3.5">
                            <span v-if="product.category"
                                class="text-xs font-medium px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md">
                                {{ product.category.name }}
                            </span>
                            <span v-else class="text-xs text-slate-400">Sin categoría</span>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-500 max-w-xs truncate">{{ product.description || '—'
                            }}</td>
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">${{ Number(product.price).toFixed(2) }}</p>
                            <p class="text-[10px] text-emerald-600 font-medium">{{ product.tax_percentage }}% IVA</p>
                        </td>
                        <td class="px-6 py-3.5">
                            <span
                                :class="product.status === 'A' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ product.status === 'A' ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <button @click="toggleVisibility(product.id)"
                                :class="product.is_visible ? 'bg-cyan-500 text-white' : 'bg-slate-200 text-slate-500'"
                                class="w-10 h-5 rounded-full relative transition-colors duration-200 focus:outline-none">
                                <div :class="product.is_visible ? 'translate-x-5' : 'translate-x-0'"
                                    class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200 shadow-sm">
                                </div>
                            </button>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.products.edit', product.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="del(product.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!products.data.length">
                        <td colspan="4" class="px-6 py-10 text-center text-slate-400 text-sm">No hay productos</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="products.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>Mostrando {{ products.from }}–{{ products.to }} de {{ products.total }}</span>
                <div class="flex gap-1">
                    <template v-for="(link, index) in products.links" :key="index">
                        <span v-if="link.url === null"
                            class="px-3 py-1.5 rounded-lg text-slate-400 cursor-not-allowed select-none text-xs"
                            v-html="link.label">
                        </span>
                        <Link v-else
                            :href="link.url"
                            class="px-3 py-1.5 rounded-lg transition text-xs"
                            :class="link.active ? 'bg-cyan-500 text-white font-medium' : 'hover:bg-slate-100 text-slate-600'"
                            v-html="link.label">
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ products: Object, filters: Object });
const search = ref(props.filters?.search ?? '');
const syncing = ref(false);

let searchTimer;
function doSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.products.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 400);
}

function syncProducts() {
    syncing.value = true;
    router.post(route('admin.products.sync'), {}, {
        onFinish: () => { syncing.value = false; }
    });
}

function toggleVisibility(id) {
    router.post(route('admin.products.toggle-visibility', id), {}, {
        preserveScroll: true
    });
}

function del(id) {
    if (confirm('¿Eliminar este producto?')) router.delete(route('admin.products.destroy', id));
}
</script>
