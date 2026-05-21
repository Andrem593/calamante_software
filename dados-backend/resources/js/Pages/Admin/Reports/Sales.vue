<template>
    <AdminLayout title="Reporte de Ventas">
        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3 mb-6 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <div>
                <label class="block text-xs text-slate-500 font-medium mb-1.5">Desde</label>
                <input type="date" v-model="form.from"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
            </div>
            <div>
                <label class="block text-xs text-slate-500 font-medium mb-1.5">Hasta</label>
                <input type="date" v-model="form.to"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
            </div>
            <button @click="applyFilters"
                class="px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition">Filtrar</button>

            <div class="ml-auto flex gap-4 text-right">
                <div class="bg-cyan-50 rounded-xl px-5 py-3">
                    <p class="text-xs text-cyan-500 font-medium">Total Ventas</p>
                    <p class="text-xl font-bold text-cyan-700">${{ Number(summary.total ?? 0).toFixed(2) }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl px-5 py-3">
                    <p class="text-xs text-slate-500 font-medium">Pedidos</p>
                    <p class="text-xl font-bold text-slate-700">{{ summary.count ?? 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">#</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Fecha</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Cliente</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Sucursal</th>
                        <th class="text-right text-xs font-semibold text-slate-500 uppercase px-6 py-3">Total</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in sales.data" :key="order.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3 text-sm font-medium text-slate-600">#{{ order.id }}</td>
                        <td class="px-6 py-3 text-sm text-slate-500">{{ new
                            Date(order.created_at).toLocaleDateString('es-EC') }}</td>
                        <td class="px-6 py-3 text-sm text-slate-600">{{ order.user?.name ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-slate-700 font-medium">{{ order.client?.name ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-slate-500">{{ order.branch?.name ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm font-bold text-slate-800 text-right">${{
                            Number(order.total).toFixed(2) }}</td>
                        <td class="px-6 py-3">
                            <StatusBadge :status="order.status" />
                        </td>
                    </tr>
                    <tr v-if="!sales.data.length">
                        <td colspan="7" class="px-6 py-10 text-center text-slate-400 text-sm">Sin resultados para este
                            período</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="sales.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>{{ sales.from }}–{{ sales.to }} de {{ sales.total }}</span>
                <div class="flex gap-1">
                    <Link v-for="link in sales.links" :key="link.label" :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-lg transition"
                        :class="link.active ? 'bg-cyan-500 text-white' : 'hover:bg-slate-100 text-slate-600'"
                        v-html="link.label" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ sales: Object, summary: Object, filters: Object });
const form = reactive({ from: props.filters?.from ?? '', to: props.filters?.to ?? '' });

function applyFilters() {
    router.get(route('admin.reports.sales'), form, { preserveState: true, replace: true });
}
</script>
