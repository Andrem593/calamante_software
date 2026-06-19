<template>
    <AdminLayout title="Precios Especiales y Descuentos">
        <!-- Acciones principales -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <!-- Buscador -->
            <div class="relative flex-1">
                <input v-model="search" @input="doSearch" placeholder="Buscar por cliente o producto..."
                    class="w-full sm:w-80 pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <!-- Botón de Importar -->
            <div class="flex gap-2 ml-auto">
                <button @click="showImport = true"
                    class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 rounded-xl text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Importar Excel
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Cliente</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">RUC / Identificación</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Producto</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Precio Especial Base</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Descuento</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Precio Neto Final</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="rule in specialPrices.data" :key="rule.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <!-- Cliente -->
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-medium text-slate-800">{{ rule.client?.name }}</p>
                            <p v-if="rule.client?.nombre_local" class="text-xs text-slate-500">{{ rule.client?.nombre_local }}</p>
                        </td>
                        <!-- RUC -->
                        <td class="px-6 py-3.5 text-sm text-slate-600">
                            {{ rule.client?.identification ?? '—' }}
                        </td>
                        <!-- Producto -->
                        <td class="px-6 py-3.5 text-sm font-medium text-slate-700">
                            {{ rule.product?.name }}
                        </td>
                        <!-- Precio Especial Base -->
                        <td class="px-6 py-3.5 text-sm text-slate-800">
                            {{ rule.price !== null ? `$${parseFloat(rule.price).toFixed(4)}` : 'Precio Normal' }}
                        </td>
                        <!-- Descuento -->
                        <td class="px-6 py-3.5">
                            <span v-if="parseFloat(rule.discount_percentage) > 0"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-amber-50 text-amber-600 text-xs font-semibold">
                                {{ parseFloat(rule.discount_percentage) }}% desc.
                            </span>
                            <span v-else class="text-slate-400 text-xs">0%</span>
                        </td>
                        <!-- Precio Neto Final -->
                        <td class="px-6 py-3.5 text-sm font-semibold text-slate-800">
                            ${{ calculateNetPrice(rule).toFixed(4) }}
                        </td>
                        <!-- Acciones -->
                        <td class="px-6 py-3.5 text-right">
                            <button @click="deleteRule(rule.id)"
                                class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                title="Eliminar regla">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!specialPrices.data.length">
                        <td colspan="7" class="px-6 py-10 text-center text-slate-400 text-sm">No se encontraron precios especiales</td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginación -->
            <div v-if="specialPrices.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>Mostrando {{ specialPrices.from }}–{{ specialPrices.to }} de {{ specialPrices.total }}</span>
                <div class="flex gap-1">
                    <template v-for="(link, index) in specialPrices.links" :key="index">
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

        <!-- Modal de Importación -->
        <div v-if="showImport"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Importar Precios y Descuentos</h3>
                    <button @click="showImport = false" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <p class="text-slate-500 text-xs mb-4">
                    El archivo Excel o CSV debe contener exactamente las siguientes columnas:<br>
                    <strong>IDENTIFICAC, Nombre Local, Precio, PRODUCTO, % Descuen</strong>
                </p>

                <form @submit.prevent="submitImport">
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Seleccionar Archivo</label>
                        <input type="file" ref="fileInput" accept=".xlsx,.xls,.csv"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400" required />
                    </div>

                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="showImport = false"
                            class="px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-100 rounded-xl transition">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="importForm.processing"
                            class="px-4 py-2.5 text-sm bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition shadow-sm shadow-emerald-200">
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

const props = defineProps({ specialPrices: Object, filters: Object });
const search = ref(props.filters?.search ?? '');
const showImport = ref(false);
const fileInput = ref(null);
const importForm = useForm({ file: null });

let searchTimer;
function doSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.special-prices.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 400);
}

function calculateNetPrice(rule) {
    const basePrice = rule.price !== null ? parseFloat(rule.price) : parseFloat(rule.product?.price ?? 0);
    const discount = parseFloat(rule.discount_percentage ?? 0);
    return basePrice * (1 - discount / 100);
}

function deleteRule(id) {
    if (confirm('¿Eliminar esta regla de precio especial?')) {
        router.delete(route('admin.special-prices.destroy', id));
    }
}

function submitImport() {
    importForm.file = fileInput.value.files[0];
    importForm.post(route('admin.special-prices.import'), {
        onSuccess: () => {
            showImport.value = false;
        }
    });
}
</script>
