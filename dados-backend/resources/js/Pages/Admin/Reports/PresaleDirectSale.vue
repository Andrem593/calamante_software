<template>
    <AdminLayout title="Resumen de Preventa y Venta Directa">
        <!-- Header & Action Buttons -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
            <div>
                <Link :href="route('admin.reports.index')" class="text-sm font-medium text-slate-500 hover:text-cyan-600 flex items-center gap-1 mb-1 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Volver a Reportes
                </Link>
                <h1 class="text-2xl font-bold text-slate-800">Resumen de Preventa y Venta Directa</h1>
                <p class="text-sm text-slate-500">Resumen de visitas, ventas por facturación y método de pago, e ítems vendidos por vendedor.</p>
            </div>
            <div class="flex items-center gap-3">
                <a :href="exportUrl" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar a Excel / CSV
                </a>
                <button @click="triggerPrint"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-semibold transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir / PDF
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-6 print:hidden">
            <form @submit.prevent="applyFilter" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <!-- Mode Selector -->
                    <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl">
                        <button type="button" @click="setFilterMode('date')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', form.mode === 'date' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-900']">
                            Día Específico
                        </button>
                        <button type="button" @click="setFilterMode('range')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', form.mode === 'range' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-900']">
                            Rango de Fechas
                        </button>
                    </div>

                    <!-- Quick Presets -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-slate-400 font-medium mr-1">Rápido:</span>
                        <button type="button" @click="setPreset('today')" class="px-2.5 py-1 bg-slate-100 hover:bg-cyan-50 hover:text-cyan-600 rounded-lg text-slate-600 transition">Hoy</button>
                        <button type="button" @click="setPreset('yesterday')" class="px-2.5 py-1 bg-slate-100 hover:bg-cyan-50 hover:text-cyan-600 rounded-lg text-slate-600 transition">Ayer</button>
                        <button type="button" @click="setPreset('thisWeek')" class="px-2.5 py-1 bg-slate-100 hover:bg-cyan-50 hover:text-cyan-600 rounded-lg text-slate-600 transition">Esta Semana</button>
                        <button type="button" @click="setPreset('thisMonth')" class="px-2.5 py-1 bg-slate-100 hover:bg-cyan-50 hover:text-cyan-600 rounded-lg text-slate-600 transition">Este Mes</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-4 pt-2 border-t border-slate-100">
                    <!-- Date Input (Single Date Mode) -->
                    <div v-if="form.mode === 'date'" class="w-full sm:w-auto">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha</label>
                        <input v-model="form.date" type="date"
                               class="border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition" />
                    </div>

                    <!-- Date Range Inputs (Range Mode) -->
                    <template v-else>
                        <div class="w-full sm:w-auto">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Desde</label>
                            <input v-model="form.date_from" type="date"
                                   class="border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition" />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Hasta</label>
                            <input v-model="form.date_to" type="date"
                                   class="border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition" />
                        </div>
                    </template>

                    <!-- Filter & Reset Buttons -->
                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-semibold transition shadow-sm">
                            Filtrar
                        </button>
                        <button type="button" @click="resetFilter"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-medium transition">
                            Limpiar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Printable Report Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden print:shadow-none print:border-none">
            <!-- Printable Header Banner -->
            <div class="bg-blue-100 border-b border-blue-200 p-3 flex items-center justify-between text-slate-900 font-bold text-sm sm:text-base print:bg-blue-100 print:text-black">
                <div class="bg-white/80 px-3 py-1 rounded-md border border-blue-300 font-semibold text-xs sm:text-sm">
                    FECHA: {{ formattedDateLabel }}
                </div>
                <div class="uppercase tracking-wide text-center flex-1 font-extrabold text-slate-900 text-sm sm:text-lg">
                    RESUMEN DE PREVENTA Y VENTA DIRECTA
                </div>
            </div>

            <!-- Report Main Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-slate-800 border-collapse border border-slate-300">
                    <thead>
                        <!-- Row 1: High Level Categories -->
                        <tr class="bg-slate-100 text-center font-bold text-slate-800 border-b border-slate-300">
                            <th rowspan="2" class="border border-slate-300 px-3 py-2 text-left bg-slate-100 min-w-[140px]">VENDEDOR</th>
                            <th rowspan="2" class="border border-slate-300 px-2 py-2 w-16">VISITAS</th>
                            <th rowspan="2" class="border border-slate-300 px-2 py-2 bg-yellow-300 text-slate-900 w-20">TOTAL PEDIDOS</th>
                            <th rowspan="2" class="border border-slate-300 px-3 py-2 w-24">VENTAS FACTURA</th>
                            <th rowspan="2" class="border border-slate-300 px-3 py-2 w-24">VENTAS PREFACTUR.</th>
                            <th colspan="3" class="border border-slate-300 px-3 py-1 bg-slate-200">VENTAS TOTALES</th>
                            <th rowspan="2" class="border border-slate-300 px-3 py-2 bg-yellow-300 text-slate-900 w-28">TOTAL ($)</th>
                            <th :colspan="products.length" class="border border-slate-300 px-3 py-1 bg-slate-100">ITEMS VENDIDOS</th>
                            <th rowspan="2" class="border border-slate-300 px-3 py-2 bg-yellow-300 text-slate-900 w-24">TOTAL ITEMS</th>
                        </tr>
                        <!-- Row 2: Subcategories -->
                        <tr class="bg-slate-100 text-center font-bold text-slate-800 border-b border-slate-300">
                            <th class="border border-slate-300 px-2 py-1 bg-slate-50 min-w-[70px]">CONTADO</th>
                            <th class="border border-slate-300 px-2 py-1 bg-slate-50 min-w-[70px]">CREDITO</th>
                            <th class="border border-slate-300 px-2 py-1 bg-slate-50 min-w-[70px]">TRANSFER.</th>
                            <th v-for="product in products" :key="product.id" class="border border-slate-300 px-2 py-1 min-w-[80px]">
                                {{ formatProductName(product.name) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Seller Rows -->
                        <tr v-for="row in reportData" :key="row.user_id" class="hover:bg-slate-50 border-b border-slate-200 text-right">
                            <td class="border border-slate-300 px-3 py-2 text-left font-medium text-slate-900">{{ row.seller_name }}</td>
                            <td class="border border-slate-300 px-2 py-2">{{ row.visits || '' }}</td>
                            <td class="border border-slate-300 px-2 py-2 bg-yellow-100/70 font-semibold text-slate-900">{{ row.orders_count || '' }}</td>
                            <td class="border border-slate-300 px-3 py-2">{{ formatCurrency(row.invoiced_amount) }}</td>
                            <td class="border border-slate-300 px-3 py-2">{{ formatCurrency(row.preinvoiced_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2">{{ formatCurrency(row.cash_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2">{{ formatCurrency(row.credit_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2">{{ formatCurrency(row.transfer_amount) }}</td>
                            <td class="border border-slate-300 px-3 py-2 bg-yellow-200/90 font-bold text-slate-900">{{ formatCurrency(row.total_amount) }}</td>
                            
                            <!-- Dynamic Product Item Quantities -->
                            <td v-for="product in products" :key="product.id" class="border border-slate-300 px-2 py-2">
                                {{ row.product_quantities[product.id] || '' }}
                            </td>

                            <td class="border border-slate-300 px-3 py-2 bg-yellow-200/90 font-bold text-slate-900">{{ row.total_items || '' }}</td>
                        </tr>

                        <!-- Empty State if no sellers -->
                        <tr v-if="!reportData || !reportData.length">
                            <td :colspan="10 + products.length" class="text-center py-6 text-slate-400 italic">
                                No hay registros para la fecha o rango seleccionado.
                            </td>
                        </tr>
                    </tbody>

                    <!-- Total General Row -->
                    <tfoot>
                        <tr class="bg-blue-100/80 font-bold text-slate-900 text-right border-t-2 border-slate-400">
                            <td class="border border-slate-300 px-3 py-2 text-left font-extrabold text-slate-900 bg-blue-100">Total general</td>
                            <td class="border border-slate-300 px-2 py-2 font-bold">{{ totals.visits }}</td>
                            <td class="border border-slate-300 px-2 py-2 bg-yellow-300 font-extrabold text-slate-900">{{ totals.orders_count }}</td>
                            <td class="border border-slate-300 px-3 py-2 font-bold">{{ formatCurrency(totals.invoiced_amount) }}</td>
                            <td class="border border-slate-300 px-3 py-2 font-bold">{{ formatCurrency(totals.preinvoiced_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2 font-bold">{{ formatCurrency(totals.cash_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2 font-bold">{{ formatCurrency(totals.credit_amount) }}</td>
                            <td class="border border-slate-300 px-2 py-2 font-bold">{{ formatCurrency(totals.transfer_amount) }}</td>
                            <td class="border border-slate-300 px-3 py-2 bg-yellow-300 font-extrabold text-slate-900">{{ formatCurrency(totals.total_amount) }}</td>
                            
                            <!-- Product Totals -->
                            <td v-for="product in products" :key="product.id" class="border border-slate-300 px-2 py-2 font-bold">
                                {{ totals.product_quantities[product.id] || 0 }}
                            </td>

                            <td class="border border-slate-300 px-3 py-2 bg-yellow-300 font-extrabold text-slate-900">{{ totals.total_items }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    reportData: Array,
    products: Array,
    totals: Object,
    filters: Object
});

const todayStr = new Date().toISOString().split('T')[0];

const form = ref({
    mode: props.filters?.mode || 'date',
    date: props.filters?.date || todayStr,
    date_from: props.filters?.date_from || todayStr,
    date_to: props.filters?.date_to || todayStr,
});

function setFilterMode(mode) {
    form.value.mode = mode;
}

function setPreset(preset) {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        form.value.mode = 'date';
        form.value.date = formatDate(today);
        form.value.date_from = formatDate(today);
        form.value.date_to = formatDate(today);
    } else if (preset === 'yesterday') {
        form.value.mode = 'date';
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        form.value.date = formatDate(y);
        form.value.date_from = formatDate(y);
        form.value.date_to = formatDate(y);
    } else if (preset === 'thisWeek') {
        form.value.mode = 'range';
        const first = new Date(today);
        const day = first.getDay() || 7;
        first.setDate(first.getDate() - day + 1);
        form.value.date_from = formatDate(first);
        form.value.date_to = formatDate(today);
    } else if (preset === 'thisMonth') {
        form.value.mode = 'range';
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        form.value.date_from = formatDate(first);
        form.value.date_to = formatDate(today);
    }
    applyFilter();
}

function applyFilter() {
    const params = { mode: form.value.mode };
    if (form.value.mode === 'range') {
        params.date_from = form.value.date_from;
        params.date_to = form.value.date_to;
    } else {
        params.date = form.value.date;
    }
    router.get(route('admin.reports.presale-direct-sale'), params, {
        preserveState: true,
        replace: true
    });
}

function resetFilter() {
    form.value.mode = 'date';
    form.value.date = todayStr;
    form.value.date_from = todayStr;
    form.value.date_to = todayStr;
    applyFilter();
}

const formattedDateLabel = computed(() => {
    if (form.value.mode === 'range' && form.value.date_from !== form.value.date_to) {
        return `${formatDisplayDate(form.value.date_from)} al ${formatDisplayDate(form.value.date_to)}`;
    }
    return formatDisplayDate(form.value.date);
});

function formatDisplayDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
}

function formatCurrency(val) {
    if (!val || Number(val) === 0) return '$0,00';
    return '$' + Number(val).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatProductName(name) {
    if (!name) return '';
    let n = name.toUpperCase();
    n = n.replace('FUNDAS DE HIELO LISTO', 'FD. LISTO');
    n = n.replace('FUNDAS DE HIELO', 'FD.');
    n = n.replace('FUNDA DE HIELO', 'FD.');
    return n;
}

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.append('mode', form.value.mode);
    if (form.value.mode === 'range') {
        params.append('date_from', form.value.date_from);
        params.append('date_to', form.value.date_to);
    } else {
        params.append('date', form.value.date);
    }
    return route('admin.reports.presale-direct-sale.export') + '?' + params.toString();
});

function triggerPrint() {
    window.print();
}
</script>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }
    body {
        background: white !important;
        color: black !important;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>
