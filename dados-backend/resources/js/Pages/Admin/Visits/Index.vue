<template>
    <AdminLayout title="Reporte de Visitas y Resumen de Ventas">
        <!-- Header / Filters -->
        <div class="flex flex-col gap-4 mb-6 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <!-- Search Client -->
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Cliente</span>
                    <div class="relative">
                        <input v-model="filters.search" @input="doSearch" placeholder="Buscar por cliente..."
                            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Seller Select -->
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Vendedor</span>
                    <select v-model="filters.seller_id" @change="applyFilters"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white">
                        <option value="">Todos los vendedores</option>
                        <option v-for="seller in sellers" :key="seller.id" :value="seller.id">
                            {{ seller.name }}
                        </option>
                    </select>
                </div>

                <!-- From Date -->
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Desde</span>
                    <input type="date" v-model="filters.from" @change="applyFilters"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                </div>

                <!-- To Date -->
                <div class="flex flex-col gap-1.5">
                    <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Hasta</span>
                    <input type="date" v-model="filters.to" @change="applyFilters"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                </div>
            </div>

            <!-- Additional Quick Actions -->
            <div class="flex flex-wrap items-center justify-between border-t border-slate-50 pt-4 mt-2 gap-2">
                <button @click="clearFilters"
                    class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-xl transition">
                    Limpiar Filtros
                </button>
                <div class="flex items-center gap-2 flex-wrap">
                    <Link :href="route('admin.visits.map', mapRedirectParams)"
                        class="flex items-center gap-2 px-4 py-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Ver Mapa
                    </Link>
                    <a :href="route('admin.visits.export-pdf', filters)" target="_blank"
                        class="flex items-center gap-2 px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-rose-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar PDF
                    </a>
                    <a :href="route('admin.visits.export-excel', filters)" target="_blank"
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-emerald-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exportar Excel
                    </a>
                </div>
            </div>
        </div>

        <!-- Sales & Activity Executive Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <!-- Total Visits -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Total Visitas</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-slate-900">{{ visits.total }}</span>
                    <span class="text-xs text-slate-400 font-medium">Registradas</span>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Total Pedidos</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-slate-900">{{ salesSummary?.totalOrders || 0 }}</span>
                    <span class="text-xs text-slate-400 font-medium">Activos</span>
                </div>
            </div>

            <!-- Total Sales Amount -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Ventas Totales</span>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-slate-900">${{ formatCurrency(salesSummary?.totalAmount) }}</span>
                    <p class="text-xs text-slate-400 mt-0.5">Crédito: ${{ formatCurrency(salesSummary?.totalCredit) }} | Contado: ${{ formatCurrency(salesSummary?.totalCash) }}</p>
                </div>
            </div>

            <!-- Factura Sales -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-emerald-100 bg-emerald-50/20 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-emerald-700 font-semibold uppercase tracking-wider">Ventas Factura</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-800 rounded-full">
                        {{ salesSummary?.totalFacturaCount || 0 }} ped.
                    </span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-emerald-700">${{ formatCurrency(salesSummary?.totalFacturaAmount) }}</span>
                    <p class="text-xs text-emerald-600/70 mt-0.5">Facturadas SRI / Contifico</p>
                </div>
            </div>

            <!-- Prefactura Sales -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-amber-100 bg-amber-50/20 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-amber-700 font-semibold uppercase tracking-wider">Ventas Prefactura</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded-full">
                        {{ salesSummary?.totalPrefacturaCount || 0 }} ped.
                    </span>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-bold text-amber-700">${{ formatCurrency(salesSummary?.totalPrefacturaAmount) }}</span>
                    <p class="text-xs text-amber-600/70 mt-0.5">Documento Prefactura</p>
                </div>
            </div>
        </div>

        <!-- Sales by Product Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Ventas por Producto</h3>
                    <p class="text-xs text-slate-500">Desglose de productos vendidos en los pedidos del período seleccionado</p>
                </div>
                <button @click="showProducts = !showProducts"
                    class="text-xs font-semibold text-cyan-600 hover:text-cyan-800 transition flex items-center gap-1">
                    <span>{{ showProducts ? 'Ocultar Productos' : 'Mostrar Productos (' + (productSales?.length || 0) + ')' }}</span>
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showProducts }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <div v-if="showProducts" class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase">
                        <tr>
                            <th class="px-6 py-3">Producto</th>
                            <th class="px-6 py-3">Código / SKU</th>
                            <th class="px-6 py-3 text-center">Cant. Total</th>
                            <th class="px-6 py-3 text-right">Total Ventas ($)</th>
                            <th class="px-6 py-3 text-center">Cant. Factura</th>
                            <th class="px-6 py-3 text-right">Total Factura ($)</th>
                            <th class="px-6 py-3 text-center">Cant. Prefactura</th>
                            <th class="px-6 py-3 text-right">Total Prefactura ($)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="prod in productSales" :key="prod.id" class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-3.5 font-medium text-slate-800">{{ prod.name }}</td>
                            <td class="px-6 py-3.5 text-xs text-slate-500 font-mono">{{ prod.code }}</td>
                            <td class="px-6 py-3.5 text-center font-bold text-slate-800">{{ prod.total_qty }}</td>
                            <td class="px-6 py-3.5 text-right font-bold text-slate-900">${{ formatCurrency(prod.total_amount) }}</td>
                            <td class="px-6 py-3.5 text-center text-emerald-700 font-semibold">{{ prod.invoiced_qty }}</td>
                            <td class="px-6 py-3.5 text-right text-emerald-700 font-semibold">${{ formatCurrency(prod.invoiced_amount) }}</td>
                            <td class="px-6 py-3.5 text-center text-amber-700 font-semibold">{{ prod.preinvoiced_qty }}</td>
                            <td class="px-6 py-3.5 text-right text-amber-700 font-semibold">${{ formatCurrency(prod.preinvoiced_amount) }}</td>
                        </tr>
                        <tr v-if="!productSales || !productSales.length">
                            <td colspan="8" class="px-6 py-8 text-center text-slate-400 text-sm">
                                No hay ventas de productos registradas en este período.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Visits Table Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-800">Detalle de Visitas Registradas</h3>
                <span class="text-xs font-medium text-slate-400">Página {{ visits.current_page }} de {{ visits.last_page }}</span>
            </div>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Fecha y Hora</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Cliente</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Ubicación GPS</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Comentario</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="visit in visits.data" :key="visit.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <!-- Date & Time -->
                        <td class="px-6 py-4 text-sm text-slate-800 whitespace-nowrap">
                            <p class="font-medium">{{ formatDate(visit.created_at) }}</p>
                            <p class="text-xs text-slate-400">{{ formatTime(visit.created_at) }}</p>
                        </td>

                        <!-- Seller -->
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">
                            {{ visit.user?.name ?? '—' }}
                        </td>

                        <!-- Client -->
                        <td class="px-6 py-4">
                            <p class="text-sm font-semibold text-slate-800">{{ visit.client?.name ?? '—' }}</p>
                            <p class="text-xs text-slate-400 flex items-center gap-1.5 flex-wrap">
                                <span>{{ visit.client?.nombre_local ?? '' }}</span>
                                <span v-if="visit.branch?.name" class="px-1.5 py-0.5 text-[10px] font-medium bg-slate-100 text-slate-600 rounded">
                                    {{ visit.branch.name }}
                                </span>
                            </p>
                        </td>

                        <!-- GPS Location -->
                        <td class="px-6 py-4 text-sm text-slate-500 whitespace-nowrap">
                            <div v-if="visit.latitude && visit.longitude" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-cyan-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                </svg>
                                <span>{{ Number(visit.latitude).toFixed(5) }}, {{ Number(visit.longitude).toFixed(5) }}</span>
                            </div>
                            <span v-else class="text-xs text-slate-300 italic">Sin coordenadas</span>
                        </td>

                        <!-- Comment -->
                        <td class="px-6 py-4 text-sm text-slate-600 max-w-xs">
                            <p class="truncate" :title="visit.comment">{{ visit.comment ?? '—' }}</p>
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <!-- View Route on Map -->
                                <Link :href="route('admin.visits.map', { from: formatDateForMap(visit.created_at), to: formatDateForMap(visit.created_at), seller_id: visit.user_id })"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition"
                                    title="Ver recorrido de este día">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                </Link>

                                <!-- Delete Visit -->
                                <button @click="deleteVisit(visit.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                    title="Eliminar registro">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!visits.data.length">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-sm">
                            No se encontraron registros de visitas en este rango de fechas.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :data="visits" preserve-scroll preserve-state />
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    visits: Object,
    sellers: Array,
    salesSummary: Object,
    productSales: Array,
    filters: Object
});

const showProducts = ref(true);

const filters = reactive({
    search: props.filters?.search ?? '',
    seller_id: props.filters?.seller_id ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

// Redirect parameters for map button
const mapRedirectParams = computed(() => {
    return {
        from: filters.from || new Date().toISOString().split('T')[0],
        to: filters.to || new Date().toISOString().split('T')[0],
        seller_id: filters.seller_id || ''
    };
});

let debounceTimer = null;
function doSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 450);
}

function applyFilters() {
    router.get(route('admin.visits.index'), filters, {
        preserveState: true,
        replace: true
    });
}

function clearFilters() {
    filters.search = '';
    filters.seller_id = '';
    
    const todayStr = new Date().toISOString().split('T')[0];
    filters.from = todayStr;
    filters.to = todayStr;
    applyFilters();
}

function deleteVisit(id) {
    if (confirm('¿Está seguro de que desea eliminar este registro de visita?')) {
        router.delete(route('admin.visits.destroy', id), {
            preserveScroll: true
        });
    }
}

// Formatting helpers
function formatCurrency(amount) {
    if (amount === undefined || amount === null) return '0.00';
    return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit', hour12: true });
}

function formatDateForMap(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toISOString().split('T')[0];
}
</script>
