<template>
    <AdminLayout title="Dashboard">
        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard label="Pedidos Hoy" :value="stats.today_orders" icon="clipboard" color="cyan" />
            <StatCard label="Entregas Hoy" :value="stats.today_deliveries" icon="truck" color="amber" />
            <StatCard label="Pendientes" :value="stats.pending_orders" icon="clock" color="rose" />
            <StatCard label="Ventas Hoy" :value="'$' + Number(stats.today_sales).toFixed(2)" icon="dollar"
                color="emerald" />
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
                <h2 class="font-semibold text-slate-800 text-base mb-4">Top Vendedores Hoy</h2>
                <div class="space-y-3">
                    <div v-for="(seller, i) in topSellers" :key="seller.id" class="flex items-center gap-3">
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
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ stats: Object, salesByDay: Array, recentOrders: Array, topSellers: Array, ordersByStatus: Array });

const maxSale = computed(() => Math.max(...(props.salesByDay?.map(d => Number(d.total)) ?? [1])));
function barHeight(val) { return maxSale.value > 0 ? Math.max((Number(val) / maxSale.value) * 100, 5) : 5; }
function formatDate(d) { return new Date(d + 'T00:00:00').toLocaleDateString('es-EC', { weekday: 'short', day: 'numeric' }); }
</script>
