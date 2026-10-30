<template>
    <AdminLayout title="Mapa de Rutas y Ventas">
        <!-- Controles y Filtros Superior -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-6">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Rango de Fechas -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Desde</label>
                            <input type="date" v-model="searchForm.from" @change="applyFilters"
                                class="bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-cyan-500 focus:bg-white transition" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Hasta</label>
                            <input type="date" v-model="searchForm.to" @change="applyFilters"
                                class="bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-cyan-500 focus:bg-white transition" />
                        </div>

                        <!-- Buscador -->
                        <div class="min-w-[220px]">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Buscar Cliente</label>
                            <input type="text" v-model="searchForm.search" @input="debounceSearch" placeholder="Nombre o sector..."
                                class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl px-3.5 py-1.5 focus:ring-2 focus:ring-cyan-500 focus:bg-white transition" />
                        </div>
                    </div>

                    <!-- Botón de Exportación Excel -->
                    <div v-if="selectedRoutes.length > 0" class="flex items-center gap-2">
                        <a :href="exportUrl" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold shadow-sm shadow-emerald-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Exportar Excel
                        </a>
                    </div>
                </div>

                <!-- Selección Múltiple de Rutas -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            Seleccionar Rutas ({{ selectedRoutes.length }} elegidas)
                        </label>
                        <div class="flex items-center gap-2 text-xs">
                            <button @click="selectAllRoutes" class="font-semibold text-cyan-600 hover:text-cyan-700 hover:underline">
                                Seleccionar Todas
                            </button>
                            <span class="text-slate-300">|</span>
                            <button @click="clearRoutes" class="font-semibold text-slate-500 hover:text-slate-700 hover:underline">
                                Limpiar Selección
                            </button>
                        </div>
                    </div>

                    <!-- Badges interactivos para cada Ruta -->
                    <div class="flex flex-wrap gap-2">
                        <button v-for="r in available_routes" :key="r" @click="toggleRoute(r)"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold border transition flex items-center gap-1.5"
                            :class="isRouteSelected(r)
                                ? 'bg-cyan-600 text-white border-cyan-600 shadow-sm shadow-cyan-200'
                                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:border-slate-300'">
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: getRouteColor(r) }"></span>
                            📍 {{ r }}
                            <span v-if="isRouteSelected(r)" class="ml-1 text-[10px] bg-white/20 px-1 rounded">✓</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado Inicial Vacío cuando no hay rutas seleccionadas -->
        <div v-if="selectedRoutes.length === 0" class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm mb-6">
            <div class="w-16 h-16 rounded-3xl bg-cyan-50 border border-cyan-100 flex items-center justify-center text-cyan-600 mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Sin Rutas Seleccionadas</h3>
            <p class="text-sm text-slate-500 max-w-md mx-auto mb-5">
                Por favor selecciona una o varias rutas en los botones de arriba para cargar el mapa sectorizado, ventas y clientes.
            </p>
            <div class="flex items-center justify-center gap-3">
                <button @click="selectAllRoutes" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-semibold shadow-sm shadow-cyan-200 transition">
                    Ver Todas las Rutas
                </button>
            </div>
        </div>

        <!-- Vista del Reporte cuando hay rutas seleccionadas -->
        <template v-else>
            <!-- Tarjetas de Métricas KPI -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Ventas Totales en la Ruta -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Ventas en Rutas</p>
                        <p class="text-2xl font-bold text-slate-800">${{ formatCurrency(summary.total_sales) }}</p>
                    </div>
                </div>

                <!-- Pedidos Totales -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-cyan-50 border border-cyan-100 flex items-center justify-center text-cyan-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pedidos</p>
                        <p class="text-2xl font-bold text-slate-800">{{ summary.total_orders }}</p>
                    </div>
                </div>

                <!-- Clientes Geolocalizados -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-violet-50 border border-violet-100 flex items-center justify-center text-violet-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Clientes Ubicados</p>
                        <p class="text-2xl font-bold text-slate-800">{{ summary.geolocated_count }} / {{ summary.total_branches }}</p>
                    </div>
                </div>

                <!-- Producto Estrella -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Producto Estrella</p>
                        <p class="text-base font-bold text-slate-800 truncate" :title="summary.top_product">{{ summary.top_product }}</p>
                    </div>
                </div>
            </div>

            <!-- Sección Mapa Interactivo + Desglose de Productos -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Mapa (2 columnas) -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-3 px-2">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 inline-block"></span>
                            Mapa Sectorizado ({{ selectedRoutes.length }} rutas)
                        </h3>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="inline-flex items-center gap-1 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Con Ventas</span>
                            <span class="inline-flex items-center gap-1 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-cyan-500"></span> Con Registro</span>
                        </div>
                    </div>
                    <div id="map" style="height: 520px; width: 100%;" class="rounded-xl overflow-hidden border border-slate-200"></div>
                </div>

                <!-- Desglose por Productos (1 columna) -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-col">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Ventas por Producto en Rutas
                    </h3>

                    <div class="space-y-4 overflow-y-auto max-h-[460px] pr-1">
                        <div v-for="prod in product_breakdown" :key="prod.id" class="bg-slate-50 rounded-xl p-3.5 border border-slate-100">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-sm font-bold text-slate-800">{{ prod.name }}</span>
                                <span class="text-xs font-extrabold text-cyan-700 bg-cyan-50 px-2 py-0.5 rounded-md border border-cyan-100">
                                    ${{ formatCurrency(prod.total) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                                <span>Unidades: <strong class="text-slate-700">{{ prod.quantity }} u.</strong></span>
                                <span>Participación: <strong class="text-cyan-600">{{ prod.share_percentage }}%</strong></span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-cyan-500 to-cyan-400 rounded-full transition-all duration-500"
                                    :style="{ width: Math.max(prod.share_percentage, 3) + '%' }"></div>
                            </div>
                        </div>

                        <div v-if="!product_breakdown || product_breakdown.length === 0" class="text-center text-slate-400 py-10 text-sm">
                            Sin compras registradas para las rutas seleccionadas.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ventas Mensuales + Lista de Clientes -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Ventas Mensuales -->
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                        Ventas Mensuales Históricas
                    </h3>
                    
                    <div class="h-56 flex items-end gap-3 pt-6">
                        <div v-for="(m, i) in monthly_sales" :key="i" class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
                            <span class="text-[11px] font-bold text-slate-600">${{ formatShortNum(m.total) }}</span>
                            <div class="w-full rounded-t-xl bg-gradient-to-t from-emerald-500 to-emerald-300 transition-all duration-500 min-h-[6px]"
                                :style="{ height: getMonthlyBarHeight(m.total) + '%' }"></div>
                            <span class="text-[11px] text-slate-400 font-medium text-center">{{ m.month }}</span>
                        </div>
                        <div v-if="!monthly_sales || monthly_sales.length === 0" class="w-full text-center text-slate-400 py-10 text-sm">
                            Sin datos de ventas mensuales.
                        </div>
                    </div>
                </div>

                <!-- Tabla de Clientes en Rutas Seleccionadas -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h5m-5 0V9m0 0l2 2m-2-2l-2 2" />
                            </svg>
                            Clientes en Rutas Seleccionadas ({{ branches.length }})
                        </h3>
                    </div>

                    <div class="overflow-x-auto max-h-[320px]">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-bold sticky top-0">
                                <tr>
                                    <th class="py-2.5 px-3">Cliente / Sucursal</th>
                                    <th class="py-2.5 px-3">Ruta</th>
                                    <th class="py-2.5 px-3">Punto Frecuente / Origen</th>
                                    <th class="py-2.5 px-3">Sector</th>
                                    <th class="py-2.5 px-3 text-right">Ventas ($)</th>
                                    <th class="py-2.5 px-3 text-center">Pedidos</th>
                                    <th class="py-2.5 px-3 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="b in branches" :key="b.id" @click="focusBranch(b)"
                                    class="hover:bg-cyan-50/50 transition cursor-pointer"
                                    :class="selectedBranchId === b.id ? 'bg-cyan-50 font-semibold' : ''">
                                    <td class="py-2.5 px-3">
                                        <p class="font-bold text-slate-800">{{ b.client_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ b.branch_name }}</p>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">
                                            {{ b.route || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold"
                                              :class="b.geo_source.includes('Venta') ? 'bg-emerald-100 text-emerald-800' : (b.geo_source.includes('Visita') ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-700')">
                                            📍 {{ b.geo_source }}
                                        </span>
                                        <p v-if="b.last_interaction_date" class="text-[10px] text-slate-400 mt-0.5">🗓️ {{ b.last_interaction_date }}</p>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ b.sector }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-slate-800">
                                        ${{ formatCurrency(b.total_sales) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold">
                                            {{ b.orders_count }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button v-if="b.has_geo" @click.stop="focusBranch(b)"
                                            class="px-2.5 py-1 bg-cyan-100 hover:bg-cyan-200 text-cyan-800 rounded-lg font-bold text-[11px] transition">
                                            📍 Ver Mapa
                                        </button>
                                        <span v-else class="text-slate-400 italic text-[11px]">Sin GPS</span>
                                    </td>
                                </tr>
                                <tr v-if="!branches || branches.length === 0">
                                    <td colspan="7" class="text-center py-6 text-slate-400">Sin sucursales registradas para estas rutas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    branches: Array,
    available_routes: Array,
    selected_routes: Array,
    product_breakdown: Array,
    monthly_sales: Array,
    summary: Object,
    filters: Object,
    google_maps_api_key: String,
});

const selectedRoutes = ref(props.selected_routes || []);

const searchForm = reactive({
    routes: selectedRoutes.value,
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    search: props.filters?.search || '',
});

const selectedBranchId = ref(null);
let mapInstance = null;
let sharedInfo = null;
const markerRegistry = {};
let activePolygons = [];

const routeColors = {
    PUNTILLA: '#06B6D4',  // Cyan
    NORTE: '#3B82F6',     // Blue
    SUR: '#F59E0B',       // Amber/Orange
    CENTRO: '#10B981',    // Emerald/Green
    CEIBOS: '#8B5CF6',    // Purple
    BABAHOYO: '#F43F5E',  // Rose/Red
    MANABI: '#6366F1',    // Indigo
    BUCAY: '#84CC16',     // Lime
    SALINAS: '#0EA5E9',   // Sky
    DEFAULT: '#0284C7',   // Ocean Blue
};

function getRouteColor(routeName) {
    if (!routeName) return routeColors.DEFAULT;
    const key = routeName.toUpperCase().trim();
    return routeColors[key] || routeColors.DEFAULT;
}

function isRouteSelected(rName) {
    return selectedRoutes.value.includes(rName);
}

function toggleRoute(rName) {
    const idx = selectedRoutes.value.indexOf(rName);
    if (idx >= 0) {
        selectedRoutes.value.splice(idx, 1);
    } else {
        selectedRoutes.value.push(rName);
    }
    applyFilters();
}

function selectAllRoutes() {
    selectedRoutes.value = [...props.available_routes];
    applyFilters();
}

function clearRoutes() {
    selectedRoutes.value = [];
    applyFilters();
}

// Algoritmo Convex Hull 2D (Monotone Chain) para el perímetro del área
function getConvexHull(points) {
    if (points.length <= 2) return points;
    const sorted = [...points].sort((a, b) => a.lat === b.lat ? a.lng - b.lng : a.lat - b.lat);
    const crossProduct = (o, a, b) => (a.lng - o.lng) * (b.lat - o.lat) - (a.lat - o.lat) * (b.lng - o.lng);

    const lower = [];
    for (const p of sorted) {
        while (lower.length >= 2 && crossProduct(lower[lower.length - 2], lower[lower.length - 1], p) <= 0) {
            lower.pop();
        }
        lower.push(p);
    }

    const upper = [];
    for (let i = sorted.length - 1; i >= 0; i--) {
        const p = sorted[i];
        while (upper.length >= 2 && crossProduct(upper[upper.length - 2], upper[upper.length - 1], p) <= 0) {
            upper.pop();
        }
        upper.push(p);
    }

    lower.pop();
    upper.pop();
    return lower.concat(upper);
}

function padHull(hull, paddingRatio = 0.08) {
    if (hull.length < 3) return hull;
    let avgLat = 0, avgLng = 0;
    hull.forEach(p => {
        avgLat += p.lat;
        avgLng += p.lng;
    });
    avgLat /= hull.length;
    avgLng /= hull.length;

    return hull.map(p => ({
        lat: p.lat + (p.lat - avgLat) * paddingRatio,
        lng: p.lng + (p.lng - avgLng) * paddingRatio,
    }));
}

const exportUrl = computed(() => {
    return route('admin.reports.route-map.export', {
        routes: selectedRoutes.value.join(','),
        from: searchForm.from,
        to: searchForm.to,
        search: searchForm.search,
    });
});

let searchTimeout = null;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
}

function applyFilters() {
    searchForm.routes = [...selectedRoutes.value];
    router.get(route('admin.reports.route-map'), {
        routes: selectedRoutes.value.join(','),
        from: searchForm.from,
        to: searchForm.to,
        search: searchForm.search,
    }, {
        preserveState: true,
        replace: true,
    });
}

function formatCurrency(val) {
    return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatShortNum(val) {
    const num = Number(val || 0);
    if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
    return num.toFixed(0);
}

const maxMonthlySales = computed(() => {
    return Math.max(...(props.monthly_sales?.map(m => m.total) ?? [1]), 1);
});

function getMonthlyBarHeight(total) {
    return Math.max(((total || 0) / maxMonthlySales.value) * 100, 5);
}

function loadGoogleScript(callback) {
    if (typeof google !== 'undefined') {
        if (callback) callback();
        return;
    }
    window.initGMapRoute = () => {
        if (callback) callback();
    };
    if (!document.getElementById('google-maps-script-route')) {
        const script = document.createElement('script');
        script.id = 'google-maps-script-route';
        script.src = `https://maps.googleapis.com/maps/api/js?key=${props.google_maps_api_key || ''}&callback=initGMapRoute`;
        script.async = true;
        document.head.appendChild(script);
    }
}

function ensureMapInitialized() {
    if (selectedRoutes.value.length === 0) return;
    nextTick(() => {
        if (typeof google === 'undefined') {
            loadGoogleScript(() => {
                ensureMapInitialized();
            });
            return;
        }
        const el = document.getElementById('map');
        if (!el) return;

        if (!mapInstance) {
            initMap();
        } else {
            drawRoutePolygons();
            drawMarkers();
        }
    });
}

onMounted(() => {
    loadGoogleScript(() => {
        ensureMapInitialized();
    });
    ensureMapInitialized();
});

watch(() => props.branches, () => {
    ensureMapInitialized();
}, { deep: true });

function initMap() {
    const el = document.getElementById('map');
    if (!el || typeof google === 'undefined') return;

    const validBranches = (props.branches || []).filter(b => b.has_geo);

    const center = validBranches.length
        ? { lat: parseFloat(validBranches[0].latitude), lng: parseFloat(validBranches[0].longitude) }
        : { lat: -2.18, lng: -79.88 };

    mapInstance = new google.maps.Map(el, {
        zoom: 11,
        center,
        styles: [
            { featureType: 'poi', elementType: 'all', stylers: [{ visibility: 'off' }] },
            { elementType: 'geometry', stylers: [{ color: '#f8fafc' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#e0f2fe' }] },
        ],
    });

    drawRoutePolygons();
    drawMarkers();
}

function clearPolygons() {
    activePolygons.forEach(p => p.setMap(null));
    activePolygons = [];
}

function clearMarkers() {
    Object.values(markerRegistry).forEach(m => m.setMap(null));
    for (const id in markerRegistry) delete markerRegistry[id];
}

function drawRoutePolygons() {
    clearPolygons();
    if (!mapInstance || typeof google === 'undefined') return;

    const routesMap = {};
    (props.branches || []).filter(b => b.has_geo).forEach(b => {
        const rName = b.route || 'OTRO';
        if (!routesMap[rName]) routesMap[rName] = [];
        routesMap[rName].push({ lat: parseFloat(b.latitude), lng: parseFloat(b.longitude) });
    });

    for (const rName in routesMap) {
        const pts = routesMap[rName];
        const color = getRouteColor(rName);

        if (pts.length >= 3) {
            const hull = getConvexHull(pts);
            const paddedPath = padHull(hull, 0.08);

            const polygon = new google.maps.Polygon({
                paths: paddedPath,
                strokeColor: color,
                strokeOpacity: 0.85,
                strokeWeight: 2,
                fillColor: color,
                fillOpacity: 0.20,
                map: mapInstance,
                clickable: false,
            });
            activePolygons.push(polygon);
        } else if (pts.length === 1 || pts.length === 2) {
            pts.forEach(p => {
                const circle = new google.maps.Circle({
                    strokeColor: color,
                    strokeOpacity: 0.8,
                    strokeWeight: 1.5,
                    fillColor: color,
                    fillOpacity: 0.20,
                    map: mapInstance,
                    center: p,
                    radius: 400,
                    clickable: false,
                });
                activePolygons.push(circle);
            });
        }
    }
}

function drawMarkers() {
    if (!mapInstance || typeof google === 'undefined') return;
    clearMarkers();

    if (!sharedInfo) {
        sharedInfo = new google.maps.InfoWindow();
    }

    const validBranches = (props.branches || []).filter(b => b.has_geo);
    if (validBranches.length) {
        const bounds = new google.maps.LatLngBounds();
        validBranches.forEach(b => {
            bounds.extend({ lat: parseFloat(b.latitude), lng: parseFloat(b.longitude) });
        });
        mapInstance.fitBounds(bounds);
        if (validBranches.length === 1) {
            mapInstance.setZoom(14);
        }
    }

    validBranches.forEach(b => {
        const pos = { lat: parseFloat(b.latitude), lng: parseFloat(b.longitude) };
        const hasSales = b.total_sales > 0;
        const color = getRouteColor(b.route);

        const marker = new google.maps.Marker({
            position: pos,
            map: mapInstance,
            title: b.client_name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: hasSales ? 10 : 7,
                fillColor: hasSales ? '#10B981' : color,
                fillOpacity: 0.95,
                strokeColor: '#FFFFFF',
                strokeWeight: 2,
            },
        });

        marker.addListener('click', () => {
            selectedBranchId.value = b.id;
            const content = `
                <div style="font-family: system-ui; padding: 4px; max-width: 250px;">
                    <div style="font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 2px;">${b.client_name}</div>
                    <div style="font-size: 11px; color: #64748b;">${b.branch_name} (${b.route || 'Ruta N/A'})</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">📍 ${b.sector}</div>
                    <div style="margin-top: 6px; padding: 6px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; font-weight: 700; color: #0f172a;">Ventas: $${formatCurrency(b.total_sales)}</div>
                        <div style="font-size: 10px; color: #475569;">Pedidos: ${b.orders_count} | ${b.geo_source}</div>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=${b.latitude},${b.longitude}" target="_blank"
                       style="display: inline-block; margin-top: 6px; font-size: 11px; font-weight: 600; color: #0284c7; text-decoration: none;">
                       🗺️ Abrir en Google Maps &rarr;
                    </a>
                </div>
            `;
            sharedInfo.setContent(content);
            sharedInfo.open(mapInstance, marker);
        });

        markerRegistry[b.id] = marker;
    });
}

function focusBranch(b) {
    if (!b.has_geo) return;
    selectedBranchId.value = b.id;
    const pos = { lat: parseFloat(b.latitude), lng: parseFloat(b.longitude) };
    if (mapInstance) {
        mapInstance.setCenter(pos);
        mapInstance.setZoom(15);
    }
    const m = markerRegistry[b.id];
    if (m && typeof google !== 'undefined') {
        google.maps.event.trigger(m, 'click');
    }
}
</script>
