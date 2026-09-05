<template>
    <AdminLayout title="Reporte de Cartera de Clientes">
        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600 font-bold text-xl">
                    💳
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Cartera a Crédito</p>
                    <p class="text-2xl font-extrabold text-slate-800">${{ Number(summary.total_receivables ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-red-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-600 font-bold text-xl">
                    🚨
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Cartera Vencida</p>
                    <p class="text-2xl font-extrabold text-red-600">${{ Number(summary.overdue_receivables ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                    <p class="text-[11px] text-red-400 font-medium">{{ summary.overdue_count ?? 0 }} pedido(s) vencido(s)</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-emerald-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold text-xl">
                    🟢
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Cartera Al Día (Vigente)</p>
                    <p class="text-2xl font-extrabold text-emerald-700">${{ Number(summary.current_receivables ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600 font-bold text-xl">
                    👥
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Clientes Activos a Crédito</p>
                    <p class="text-2xl font-extrabold text-slate-800">{{ summary.clients_count ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Desde</label>
                    <input type="date" v-model="form.from"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Hasta</label>
                    <input type="date" v-model="form.to"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Vendedor</label>
                    <select v-model="form.user_id"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="">Todos los vendedores</option>
                        <option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Estado Cartera</label>
                    <select v-model="form.status"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="">Todos (Vencidos y Vigentes)</option>
                        <option value="overdue">🔴 Solo Vencidos</option>
                        <option value="current">🟢 Solo Al Día (Vigentes)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Buscar Cliente</label>
                    <input type="text" v-model="form.search" placeholder="Cliente, RUC, cédula..."
                        @keyup.enter="applyFilters"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                </div>
            </div>

            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-4 border-t border-slate-100">
                <div class="flex gap-2">
                    <button @click="applyFilters"
                        class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Filtrar
                    </button>
                    <button @click="clearFilters"
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-medium transition">
                        Limpiar
                    </button>
                </div>

                <a :href="exportUrl" target="_blank"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar Cartera Excel (CSV)
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-xs font-semibold text-slate-500 uppercase px-5 py-3.5"># Pedido</th>
                        <th class="text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Cliente & RUC</th>
                        <th class="text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Vendedor / Sucursal</th>
                        <th class="text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Emisión</th>
                        <th class="text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Vencimiento</th>
                        <th class="text-center text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Días Crédito</th>
                        <th class="text-center text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Estado Mora</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Monto Crédito</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="order in orders.data" :key="order.id"
                        class="hover:bg-slate-50/60 transition cursor-pointer"
                        @click="router.visit(route('admin.orders.show', order.id))">
                        <td class="px-5 py-3.5 text-sm font-bold text-cyan-600">#{{ order.id }}</td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">{{ order.client?.name ?? '—' }}</p>
                            <p v-if="order.client?.identification" class="text-xs text-slate-400 font-mono">{{ order.client.identification }}</p>
                            <p v-if="order.client?.credit_limit" class="text-[11px] text-emerald-600 font-medium">Cupo asignado: ${{ Number(order.client.credit_limit).toFixed(2) }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            <p class="font-medium text-slate-700">{{ order.user?.name ?? '—' }}</p>
                            <p class="text-xs text-slate-400">{{ order.branch?.name ?? 'Sucursal Principal' }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            {{ new Date(order.created_at).toLocaleDateString('es-EC') }}
                        </td>
                        <td class="px-5 py-3.5 text-sm font-medium" :class="order.is_overdue ? 'text-red-600' : 'text-slate-700'">
                            {{ new Date(order.due_date).toLocaleDateString('es-EC') }}
                        </td>
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-slate-700">
                            {{ order.credit_days }} días
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span v-if="order.is_overdue"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                🚨 Vencido ({{ order.days_overdue }} días)
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                🟢 Al Día (vence en {{ order.days_remaining }}d)
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right font-extrabold text-slate-800 text-sm">
                            ${{ Number(order.total).toFixed(2) }}
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400 text-sm">
                            No se encontraron pedidos en cartera con los filtros aplicados.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :data="orders" />
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    orders: Object,
    summary: Object,
    sellers: Array,
    filters: Object,
});

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    user_id: props.filters?.user_id ?? '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
});

function applyFilters() {
    router.get(route('admin.reports.receivables'), form, { preserveState: true, replace: true });
}

function clearFilters() {
    form.from = '';
    form.to = '';
    form.user_id = '';
    form.status = '';
    form.search = '';
    applyFilters();
}

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (form.from) params.append('from', form.from);
    if (form.to) params.append('to', form.to);
    if (form.user_id) params.append('user_id', form.user_id);
    if (form.status) params.append('status', form.status);
    if (form.search) params.append('search', form.search);
    return route('admin.reports.receivables.export') + '?' + params.toString();
});
</script>
