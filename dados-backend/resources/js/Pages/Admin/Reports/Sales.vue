<template>
    <AdminLayout title="Reporte de Ventas">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600 font-bold text-xl">
                    $
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Ventas</p>
                    <p class="text-2xl font-extrabold text-slate-800">${{ Number(summary.total ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xl">
                    📦
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Pedidos</p>
                    <p class="text-2xl font-extrabold text-slate-800">{{ summary.count ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold text-xl">
                    💵
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Ventas Efectivo</p>
                    <p class="text-xl font-bold text-emerald-700">${{ Number(summary.cash ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 font-bold text-xl">
                    💳
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Ventas a Crédito</p>
                    <p class="text-xl font-bold text-amber-700">${{ Number(summary.credit ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 font-bold text-xl">
                    🧾
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Facturados SRI</p>
                    <p class="text-xl font-bold text-purple-700">{{ summary.invoiced_count ?? 0 }} <span class="text-xs text-slate-400 font-normal">({{ summary.preinvoiced_count ?? 0 }} pre)</span></p>
                </div>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
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
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Forma de Pago</label>
                    <select v-model="form.payment_method"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="">Todas</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="credito">Crédito</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Estado Pedido</label>
                    <select v-model="form.status"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="">Todos (Excl. cancelados)</option>
                        <option value="pending">En Proceso</option>
                        <option value="delivered">Entregado</option>
                        <option value="invoiced">Facturado</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 font-medium mb-1.5">Buscar</label>
                    <input type="text" v-model="form.search" placeholder="Cliente, RUC, # ID..."
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
                    Exportar Excel (CSV)
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">#</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Fecha</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Vendedor</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Cliente</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Sucursal</th>
                        <th class="text-center text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Pago / Crédito</th>
                        <th class="text-center text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Estado</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-5 py-3.5">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="order in sales.data" :key="order.id"
                        class="hover:bg-slate-50/60 transition cursor-pointer"
                        @click="router.visit(route('admin.orders.show', order.id))">
                        <td class="px-5 py-3.5 text-sm font-bold text-cyan-600">#{{ order.id }}</td>
                        <td class="px-5 py-3.5 text-sm text-slate-600">
                            {{ new Date(order.created_at).toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-700 font-medium">{{ order.user?.name ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">{{ order.client?.name ?? '—' }}</p>
                            <p v-if="order.client?.identification" class="text-xs text-slate-400 font-mono">{{ order.client.identification }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-600">{{ order.branch?.name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                :class="order.payment_method?.toLowerCase().includes('cred') ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
                                {{ order.payment_method ?? 'Efectivo' }}
                                <span v-if="order.credit_days && order.credit_days > 0" class="text-[10px] opacity-80">({{ order.credit_days }}d)</span>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <StatusBadge :status="order.status" />
                        </td>
                        <td class="px-5 py-3.5 text-right font-extrabold text-slate-800 text-sm">
                            ${{ Number(order.total).toFixed(2) }}
                        </td>
                    </tr>
                    <tr v-if="!sales.data.length">
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400 text-sm">
                            No se encontraron pedidos con los filtros aplicados.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :data="sales" />
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    sales: Object,
    summary: Object,
    sellers: Array,
    filters: Object,
});

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    user_id: props.filters?.user_id ?? '',
    payment_method: props.filters?.payment_method ?? '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
});

function applyFilters() {
    router.get(route('admin.reports.sales'), form, { preserveState: true, replace: true });
}

function clearFilters() {
    form.from = '';
    form.to = '';
    form.user_id = '';
    form.payment_method = '';
    form.status = '';
    form.search = '';
    applyFilters();
}

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (form.from) params.append('from', form.from);
    if (form.to) params.append('to', form.to);
    if (form.user_id) params.append('user_id', form.user_id);
    if (form.payment_method) params.append('payment_method', form.payment_method);
    if (form.status) params.append('status', form.status);
    if (form.search) params.append('search', form.search);
    return route('admin.reports.sales.export') + '?' + params.toString();
});
</script>
