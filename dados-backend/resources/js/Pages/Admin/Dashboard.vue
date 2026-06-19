<template>
    <AdminLayout title="Dashboard">
        <!-- Date range filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6 bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
            <span class="text-sm font-semibold text-slate-700">Filtrar por Rango:</span>
            <div class="flex items-center gap-2">
                <input type="date" v-model="form.from" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none bg-slate-50 text-slate-700" />
                <span class="text-slate-400 text-xs">hasta</span>
                <input type="date" v-model="form.to" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none bg-slate-50 text-slate-700" />
            </div>
            <button @click="clearFilters" v-if="hasFilters"
                class="text-xs text-rose-500 hover:text-rose-600 font-semibold px-2 py-1 rounded-lg hover:bg-rose-50 transition ml-auto">
                Limpiar Filtro
            </button>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
            <StatCard :label="'Pedidos ' + rangeLabel" :value="stats.today_orders" icon="clipboard" color="cyan" />
            <StatCard :label="'Entregas ' + rangeLabel" :value="stats.today_deliveries" icon="truck" color="amber" />
            <StatCard label="Pendientes" :value="stats.pending_orders" icon="clock" color="rose" />
            <StatCard :label="'Ventas ' + rangeLabel" :value="'$' + Number(stats.today_sales).toFixed(2)" icon="dollar"
                color="emerald" />
            <StatCard :label="'Efectivo ' + rangeLabel" :value="'$' + Number(stats.today_cash_sales).toFixed(2)" icon="dollar"
                color="cyan" />
            <StatCard :label="'Crédito ' + rangeLabel" :value="'$' + Number(stats.today_credit_sales).toFixed(2)" icon="dollar"
                color="violet" />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
            <!-- Sales chart -->
            <div class="xl:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h2 class="font-semibold text-slate-800 text-base">Ventas Últimos 7 Días</h2>
                        <p class="text-slate-400 text-xs">Tendencia de facturación</p>
                    </div>
                </div>
                <div class="h-48 flex justify-between items-end gap-3">
                    <div v-for="(d, i) in salesByDay" :key="i"
                        class="flex-1 h-full flex flex-col justify-end items-center gap-1 group">
                        <!-- Tooltip/Price -->
                        <span
                            class="text-[10px] sm:text-xs font-semibold text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity mb-1">
                            ${{ Number(d.total).toLocaleString() }}
                        </span>

                        <!-- Bar Container -->
                        <div class="w-full bg-slate-50 rounded-t-lg relative overflow-hidden flex flex-col justify-end"
                            :style="{ height: 'calc(100% - 40px)' }">
                            <div class="w-full bg-gradient-to-t from-cyan-500 to-cyan-400 transition-all duration-700 ease-out hover:from-cyan-400 hover:to-cyan-300"
                                :style="{ height: barHeight(d.total) + '%' }">
                                <!-- Subtle reflection effect -->
                                <div class="absolute inset-0 bg-white/10 w-1/2"></div>
                            </div>
                        </div>

                        <!-- Date Label -->
                        <span class="text-[10px] sm:text-xs text-slate-400 mt-1 whitespace-nowrap">
                            {{ formatDate(d.date) }}
                        </span>
                    </div>
                    <div v-if="!salesByDay.length" class="w-full text-center text-slate-400 text-sm py-10">
                        Sin datos de ventas en los últimos 7 días
                    </div>
                </div>
            </div>

            <!-- Top sellers -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <h2 class="font-semibold text-slate-800 text-base mb-4">Top Vendedores ({{ rangeLabel }})</h2>
                <div class="space-y-3">
                    <div v-for="(seller, i) in topSellers" :key="seller.id"
                        @click="showSellerDetail(seller)"
                        class="flex items-center gap-3 cursor-pointer hover:bg-slate-50 p-2 -mx-2 rounded-xl transition"
                        title="Ver detalle del vendedor">
                        <div
                            class="w-7 h-7 rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-white text-xs font-bold">
                            {{ i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ seller.name }}</p>
                            <p class="text-xs text-slate-400">{{ seller.total_orders }} pedidos</p>
                        </div>
                        <span class="text-sm font-semibold text-emerald-500">${{ Number(seller.today_sales ??
                            0).toFixed(2)
                        }}</span>
                    </div>
                    <div v-if="!topSellers.length" class="text-center text-slate-400 text-sm py-4">Sin actividad hoy
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="flex justify-between items-center px-6 py-4 border-b border-slate-50">
                <h2 class="font-semibold text-slate-800">Últimos Pedidos</h2>
                <Link :href="route('admin.orders.index')"
                    class="text-cyan-500 hover:text-cyan-600 text-sm font-medium transition">Ver todos →</Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-xs text-slate-400 uppercase text-left">
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Vendedor</th>
                            <th class="px-6 py-3">Cliente</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in recentOrders" :key="order.id"
                            class="border-t border-slate-50 hover:bg-slate-50 transition">
                            <td class="px-6 py-3 text-sm font-medium text-slate-700">#{{ order.id }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600">{{ order.user?.name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600">{{ order.client?.name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm font-semibold">${{ Number(order.total).toFixed(2) }}</td>
                            <td class="px-6 py-3">
                                <StatusBadge :status="order.status" />
                            </td>
                        </tr>
                        <tr v-if="!recentOrders.length">
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">Sin pedidos recientes
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seller Detail Modal -->
        <div v-if="selectedSeller"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
            @click.self="selectedSeller = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
                <!-- Header -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Detalle de Ventas - {{ selectedSeller.name }}</h3>
                        <p class="text-slate-400 text-xs">Resumen de ventas ({{ rangeLabel }})</p>
                    </div>
                    <button @click="selectedSeller = null" class="text-slate-400 hover:text-slate-600 transition p-1 hover:bg-slate-200 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body (Scrollable) -->
                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Metrics Summary -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-cyan-50 rounded-xl p-4 border border-cyan-100/50">
                            <p class="text-[10px] text-cyan-500 font-semibold uppercase tracking-wider">Pedidos</p>
                            <p class="text-2xl font-bold text-cyan-700 mt-0.5">{{ selectedSeller.total_orders }}</p>
                        </div>
                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100/50">
                            <p class="text-[10px] text-emerald-500 font-semibold uppercase tracking-wider">Total Facturado</p>
                            <p class="text-2xl font-bold text-emerald-700 mt-0.5">${{ Number(selectedSeller.today_sales ?? 0).toFixed(2) }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200/60">
                            <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Venta en Efectivo</p>
                            <p class="text-xl font-bold text-slate-700 mt-0.5">${{ Number(selectedSeller.today_cash_sales ?? 0).toFixed(2) }}</p>
                        </div>
                        <div class="bg-violet-50 rounded-xl p-4 border border-violet-100/50">
                            <p class="text-[10px] text-violet-500 font-semibold uppercase tracking-wider">Venta a Crédito</p>
                            <p class="text-xl font-bold text-violet-700 mt-0.5">${{ Number(selectedSeller.today_credit_sales ?? 0).toFixed(2) }}</p>
                        </div>
                    </div>

                    <!-- Products Breakdown -->
                    <div>
                        <h4 class="font-semibold text-slate-800 text-sm mb-3">Detalle de Productos Vendidos</h4>
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
                                            No se registran productos vendidos hoy.
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
import { computed, ref, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ stats: Object, salesByDay: Array, recentOrders: Array, topSellers: Array, ordersByStatus: Array, filters: Object });

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

watch(() => props.filters, (newFilters) => {
    Object.assign(form, newFilters);
}, { deep: true });

function applyFilters() {
    router.get(route('admin.dashboard'), form, { preserveState: true, replace: true });
}

function clearFilters() {
    const todayStr = new Date().toLocaleDateString('sv-SE');
    form.from = todayStr;
    form.to = todayStr;
    applyFilters();
}

const hasFilters = computed(() => {
    const todayStr = new Date().toLocaleDateString('sv-SE');
    return form.from !== todayStr || form.to !== todayStr;
});

const rangeLabel = computed(() => {
    const todayStr = new Date().toLocaleDateString('sv-SE');
    if (form.from === todayStr && form.to === todayStr) {
        return 'Hoy';
    }
    return 'Rango';
});

const selectedSeller = ref(null);
function showSellerDetail(seller) {
    selectedSeller.value = seller;
}

const maxSale = computed(() => Math.max(...(props.salesByDay?.map(d => Number(d.total)) ?? [1])));
function barHeight(val) { return maxSale.value > 0 ? Math.max((Number(val) / maxSale.value) * 100, 5) : 5; }
function formatDate(d) { return new Date(d + 'T00:00:00').toLocaleDateString('es-EC', { weekday: 'short', day: 'numeric' }); }
</script>
