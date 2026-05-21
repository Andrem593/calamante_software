<template>
    <AdminLayout title="Pedidos del Día">
        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6">
            <input type="date" v-model="filters.date" @change="applyFilters"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
            <select v-model="filters.status" @change="applyFilters"
                class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white">
                <option value="">Todos los estados</option>
                <option value="pending">En Proceso</option>
                <option value="invoiced">Facturado</option>
                <option value="on_the_way">En Camino</option>
                <option value="delivered">Entregado</option>
                <option value="cancelled">Cancelado</option>
            </select>
            <div class="relative">
                <input v-model="filters.search" @input="applyFilters" placeholder="Buscar pedido o cliente..."
                    class="pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white w-64" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <Link :href="route('admin.orders.map')"
                class="ml-auto flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4" />
                </svg>
                Ver en Mapa
            </Link>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Pedido</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Cliente /
                            Sucursal</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Total</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Entrega</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3 text-right">
                            Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5 text-sm font-medium text-slate-700">#{{ order.id }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-600">{{ order.user?.name ?? '—' }}</td>
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-medium text-slate-700">{{ order.client?.name ?? '—' }}</p>
                            <p class="text-xs text-slate-400">{{ order.branch?.name }}</p>
                        </td>
                        <td class="px-6 py-3.5 text-sm font-semibold">${{ Number(order.total).toFixed(2) }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ order.delivery_date ? new
                            Date(order.delivery_date).toLocaleDateString('es-EC') : '—' }}</td>
                        <td class="px-6 py-3.5">
                            <StatusBadge :status="order.status" />
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex justify-end gap-2">
                                <Link :href="route('admin.orders.show', order.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg transition"
                                    title="Ver Detalles">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <a :href="route('admin.orders.export-pdf', order.id)" target="_blank"
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                    title="Descargar PDF">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">No hay pedidos</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>Mostrando {{ orders.from }}–{{ orders.to }} de {{ orders.total }}</span>
                <div class="flex gap-1">
                    <Link v-for="(link, index) in orders.links" :key="index" :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-lg transition" :class="link.active ? 'bg-cyan-500 text-white'
                            : 'hover:bg-slate-100 text-slate-600'">
                    {{ link.label.replace('&laquo;', '«').replace('&raquo;', '»') }}
                    </Link>
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

const props = defineProps({ orders: Object, filters: Object });
const filters = reactive({ ...props.filters });

let timer;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.orders.index'), filters, { preserveState: true, replace: true });
    }, 400);
}
</script>
