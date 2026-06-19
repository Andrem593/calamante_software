<template>
    <AdminLayout title="Reporte por Vendedor">
        <!-- Back to reports -->
        <Link :href="route('admin.reports.index')"
            class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver a Reportes
        </Link>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3 mb-6 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div>
                <label class="block text-xs text-slate-500 font-medium mb-1.5">Desde</label>
                <input type="date" v-model="form.from"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none bg-slate-50 text-slate-700" />
            </div>
            <div>
                <label class="block text-xs text-slate-500 font-medium mb-1.5">Hasta</label>
                <input type="date" v-model="form.to"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none bg-slate-50 text-slate-700" />
            </div>
            <button @click="applyFilters"
                class="px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                Filtrar
            </button>

            <!-- Summaries -->
            <div class="ml-auto flex flex-wrap gap-3 text-right">
                <div class="bg-cyan-50 rounded-xl px-4 py-2.5 border border-cyan-100/50">
                    <p class="text-[10px] text-cyan-500 font-semibold uppercase">Pedidos</p>
                    <p class="text-lg font-bold text-cyan-700 mt-0.5">{{ summary.orders }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl px-4 py-2.5 border border-slate-200/60">
                    <p class="text-[10px] text-slate-500 font-semibold uppercase">Efectivo</p>
                    <p class="text-lg font-bold text-slate-700 mt-0.5">${{ Number(summary.cash).toFixed(2) }}</p>
                </div>
                <div class="bg-violet-50 rounded-xl px-4 py-2.5 border border-violet-100/50">
                    <p class="text-[10px] text-violet-500 font-semibold uppercase">Crédito</p>
                    <p class="text-lg font-bold text-violet-700 mt-0.5">${{ Number(summary.credit).toFixed(2) }}</p>
                </div>
                <div class="bg-emerald-50 rounded-xl px-4 py-2.5 border border-emerald-100/50">
                    <p class="text-[10px] text-emerald-500 font-semibold uppercase">Total Ventas</p>
                    <p class="text-lg font-bold text-emerald-700 mt-0.5">${{ Number(summary.total).toFixed(2) }}</p>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor</th>
                        <th class="text-center text-xs font-semibold text-slate-500 uppercase px-6 py-3">Pedidos</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-6 py-3">Venta Efectivo</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-6 py-3">Venta Crédito</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-6 py-3">Total Ventas</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-6 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="seller in sellers" :key="seller.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">{{ seller.name }}</p>
                            <p class="text-xs text-slate-400">{{ seller.email }}</p>
                        </td>
                        <td class="px-6 py-3.5 text-center text-sm font-medium text-slate-700">
                            {{ seller.total_orders }}
                        </td>
                        <td class="px-6 py-3.5 text-right text-sm text-slate-600 font-medium">
                            ${{ Number(seller.today_cash_sales).toFixed(2) }}
                        </td>
                        <td class="px-6 py-3.5 text-right text-sm text-slate-600 font-medium">
                            ${{ Number(seller.today_credit_sales).toFixed(2) }}
                        </td>
                        <td class="px-6 py-3.5 text-right text-sm font-bold text-slate-800">
                            ${{ Number(seller.total_sales).toFixed(2) }}
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <button @click="showSellerDetail(seller)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-cyan-50 hover:bg-cyan-100 text-cyan-600 rounded-lg transition border border-cyan-100"
                                title="Ver desglose de productos">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Detalles
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!sellers.length">
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">No se encontraron vendedores</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Detail Modal -->
        <div v-if="selectedSeller"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
            @click.self="selectedSeller = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
                <!-- Header -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Detalle de Ventas - {{ selectedSeller.name }}</h3>
                        <p class="text-slate-400 text-xs">Período: {{ form.from }} al {{ form.to }}</p>
                    </div>
                    <button @click="selectedSeller = null" class="text-slate-400 hover:text-slate-600 transition p-1 hover:bg-slate-200 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Summary cards inside modal -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-cyan-50 rounded-xl p-4 border border-cyan-100/50">
                            <p class="text-[10px] text-cyan-500 font-semibold uppercase tracking-wider">Pedidos</p>
                            <p class="text-2xl font-bold text-cyan-700 mt-0.5">{{ selectedSeller.total_orders }}</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100/50">
                            <p class="text-[10px] text-emerald-500 font-semibold uppercase tracking-wider">Total Ventas</p>
                            <p class="text-2xl font-bold text-emerald-700 mt-0.5">${{ Number(selectedSeller.total_sales).toFixed(2) }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200/60">
                            <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Ventas Efectivo</p>
                            <p class="text-xl font-bold text-slate-700 mt-0.5">${{ Number(selectedSeller.today_cash_sales).toFixed(2) }}</p>
                        </div>
                        <div class="bg-violet-50 rounded-xl p-4 border border-violet-100/50">
                            <p class="text-[10px] text-violet-500 font-semibold uppercase tracking-wider">Ventas Crédito</p>
                            <p class="text-xl font-bold text-violet-700 mt-0.5">${{ Number(selectedSeller.today_credit_sales).toFixed(2) }}</p>
                        </div>
                    </div>

                    <!-- Products list -->
                    <div>
                        <h4 class="font-semibold text-slate-800 text-sm mb-3">Productos Vendidos en el período</h4>
                        <div class="border border-slate-100 rounded-xl overflow-hidden bg-slate-50/50">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-100 text-slate-600 font-semibold text-[10px] uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left">Producto</th>
                                        <th class="px-4 py-2.5 text-center">Cant.</th>
                                        <th class="px-4 py-2.5 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="item in selectedSeller.product_breakdown" :key="item.product_sku" class="text-slate-600 hover:bg-slate-50/45 transition-colors">
                                        <td class="px-4 py-2.5">
                                            <p class="font-medium text-slate-700 text-sm">{{ item.product_name }}</p>
                                            <p class="text-[10px] text-slate-400 font-mono">{{ item.product_sku }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-center font-bold text-slate-700 text-sm">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-bold text-slate-800 text-sm">
                                            ${{ Number(item.total).toFixed(2) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!selectedSeller.product_breakdown?.length">
                                        <td colspan="3" class="px-4 py-6 text-center text-slate-400 text-xs">
                                            No se registran productos vendidos en este período.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button @click="selectedSeller = null"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-sm font-semibold transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ sellers: Array, summary: Object, filters: Object });
const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

function applyFilters() {
    router.get(route('admin.reports.sellers'), form, { preserveState: true, replace: true });
}

const selectedSeller = ref(null);
function showSellerDetail(seller) {
    selectedSeller.value = seller;
}
</script>
