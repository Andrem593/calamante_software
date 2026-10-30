<template>
    <AdminLayout title="Reportes">
        <!-- Monthly sales chart -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <h2 class="font-semibold text-slate-800 mb-5">Ventas Mensuales</h2>
                <div class="h-52 flex items-end gap-3">
                    <div v-for="(m, i) in monthlySales" :key="i" class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs text-slate-500">${{ Number(m.total).toFixed(0) }}</span>
                        <div class="w-full rounded-t-xl bg-gradient-to-t from-cyan-500 to-cyan-300 transition-all"
                            :style="{ height: barH(m.total) + '%' }"></div>
                        <span class="text-xs text-slate-400 text-center">{{ m.month }}</span>
                    </div>
                </div>
            </div>

            <!-- Top sellers -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <h2 class="font-semibold text-slate-800 mb-5">Top Vendedores (Total)</h2>
                <div class="space-y-3">
                    <div v-for="(seller, i) in topSellers" :key="seller.id" class="flex items-center gap-3">
                        <span class="w-6 text-slate-400 text-sm font-medium text-right">#{{ i + 1 }}</span>
                        <div
                            class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-white text-xs font-bold">
                            {{ seller.name.charAt(0) }}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ seller.name }}</p>
                            <div class="h-1.5 bg-slate-100 rounded-full mt-1">
                                <div class="h-1.5 bg-cyan-400 rounded-full"
                                    :style="{ width: progressW(seller.total_sales) + '%' }"></div>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-slate-800">${{ Number(seller.total_sales ??
                            0).toFixed(0) }}</span>
                    </div>
                    <div v-if="!topSellers.length" class="text-center text-slate-400 text-sm py-4">Sin datos</div>
                </div>
            </div>
        </div>

        <!-- Quick links -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link :href="route('admin.reports.sales')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-cyan-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center group-hover:bg-cyan-100 transition">
                    <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Reporte de Ventas</p>
                    <p class="text-slate-400 text-sm">Detalle por período</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-cyan-400 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.orders-routes')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-violet-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition">
                    <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Pedidos y Facturas por Ruta</p>
                    <p class="text-slate-400 text-sm">Filtro por fechas, rutas y vendedores</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-violet-500 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.presale-direct-sale')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-blue-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Resumen de Ventas</p>
                    <p class="text-slate-400 text-sm">Resumen por vendedor y productos</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-blue-500 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.active-clients-billing')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-emerald-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Promedio Facturación 6M</p>
                    <p class="text-slate-400 text-sm">Clasificación por rangos ($)</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-emerald-500 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.branches-geolocation')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-cyan-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center group-hover:bg-cyan-100 transition">
                    <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Geolocalización Sucursales</p>
                    <p class="text-slate-400 text-sm">Sector y Parroquia (Google)</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-cyan-500 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.route-map')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-emerald-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Mapa Sectorizado por Rutas</p>
                    <p class="text-slate-400 text-sm">Puntos de clientes y desglose de ventas</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-emerald-500 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.reports.sellers')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-violet-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition">
                    <svg class="w-6 h-6 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Reporte por Vendedor</p>
                    <p class="text-slate-400 text-sm">Detalle de cada vendedor</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-violet-400 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>

            <Link :href="route('admin.orders.map')"
                class="flex items-center gap-4 p-5 bg-white rounded-2xl shadow-sm border border-slate-100 hover:shadow-md hover:border-cyan-200 transition group">
                <div
                    class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4" />
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-800">Mapa en Tiempo Real</p>
                    <p class="text-slate-400 text-sm">Ubicaciones de entregas</p>
                </div>
                <svg class="w-5 h-5 text-slate-300 ml-auto group-hover:text-emerald-400 transition" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ monthlySales: Array, topSellers: Array });
const maxMonth = computed(() => Math.max(...(props.monthlySales?.map(m => m.total) ?? [1])));
const maxSeller = computed(() => Math.max(...(props.topSellers?.map(s => s.total_sales ?? 0) ?? [1])));
function barH(val) { return maxMonth.value > 0 ? Math.max((val / maxMonth.value) * 100, 5) : 5; }
function progressW(val) { return maxSeller.value > 0 ? Math.max(((val ?? 0) / maxSeller.value) * 100, 2) : 2; }
</script>
