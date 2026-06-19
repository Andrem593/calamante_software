<template>
    <AdminLayout title="Pedidos del Día">
        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 font-medium">Desde:</span>
                <input type="date" v-model="filters.from" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 font-medium">Hasta:</span>
                <input type="date" v-model="filters.to" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
            </div>
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
                        <th class="px-6 py-3 text-left w-12">
                            <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll"
                                class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                        </th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Pedido</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Documento</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Cliente / Sucursal</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Total</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Entrega</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Contifico</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Estado</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition"
                        :class="{ 'bg-cyan-50/20': selectedOrderIds.includes(order.id) }">
                        <td class="px-6 py-3.5 w-12">
                            <input type="checkbox" v-model="selectedOrderIds" :value="order.id"
                                class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                        </td>
                        <td class="px-6 py-3.5 text-sm font-medium text-slate-700">#{{ order.id }}</td>
                        <td class="px-6 py-3.5">
                            <span v-if="order.is_invoiced"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs font-semibold border border-blue-100">
                                Factura
                            </span>
                            <span v-else
                                class="inline-flex items-center px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs font-semibold border border-amber-100">
                                Prefactura
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-600">{{ order.user?.name ?? '—' }}</td>
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-medium text-slate-700">{{ order.client?.name ?? '—' }}</p>
                            <p class="text-xs text-slate-400">{{ order.branch?.name }}</p>
                        </td>
                        <td class="px-6 py-3.5 text-sm font-semibold">${{ Number(order.total).toFixed(2) }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ order.delivery_date ? new Date(order.delivery_date).toLocaleDateString('es-EC') : '—' }}</td>
                        <!-- Contifico status -->
                        <td class="px-6 py-3.5">
                            <span v-if="order.is_preinvoiced || order.contifico_id"
                                class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-100">
                                Sincronizado
                            </span>
                            <span v-else
                                class="inline-flex items-center px-2 py-0.5 rounded bg-rose-50 text-rose-700 text-xs font-semibold border border-rose-100">
                                Pendiente
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <StatusBadge :status="order.status" />
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex justify-end gap-2">
                                <button v-if="order.status !== 'delivered' && order.status !== 'cancelled'"
                                    @click="confirmDelivery(order)"
                                    class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                    title="Confirmar Entrega">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
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
                        <td colspan="10" class="px-6 py-10 text-center text-slate-400 text-sm">No hay pedidos</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1"
                class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
                <span>Mostrando {{ orders.from }}–{{ orders.to }} de {{ orders.total }}</span>
                <div class="flex gap-1">
                    <template v-for="(link, index) in orders.links" :key="index">
                        <span v-if="link.url === null"
                            class="px-3 py-1.5 rounded-lg text-slate-400 cursor-not-allowed select-none"
                            v-html="link.label">
                        </span>
                        <Link v-else
                            :href="link.url"
                            class="px-3 py-1.5 rounded-lg transition"
                            :class="link.active ? 'bg-cyan-500 text-white font-medium' : 'hover:bg-slate-100 text-slate-600'"
                            v-html="link.label">
                        </Link>
                    </template>
                </div>
            </div>
        </div>

        <!-- Floating Actions Bar -->
        <Transition
            enter-active-class="transform transition ease-out duration-300"
            enter-from-class="translate-y-10 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transform transition ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-10 opacity-0"
        >
            <div v-if="selectedOrderIds.length" 
                class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-slate-900/90 text-white rounded-2xl px-6 py-4 shadow-2xl flex items-center gap-6 z-50 backdrop-blur border border-slate-800">
                <span class="text-sm font-medium">{{ selectedOrderIds.length }} seleccionados</span>
                <div class="h-5 w-px bg-slate-800"></div>
                <button type="button" @click="bulkDeliver"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Confirmar Entrega
                </button>
                <button type="button" @click="bulkPrint"
                    class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-semibold transition border border-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir
                </button>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({ orders: Object, filters: Object });

const selectedOrderIds = ref([]);

const filters = reactive({
    status: props.filters?.status ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    seller: props.filters?.seller ?? '',
    search: props.filters?.search ?? '',
});

// select all logic
const isAllSelected = computed(() => {
    return props.orders.data.length > 0 && props.orders.data.every(order => selectedOrderIds.value.includes(order.id));
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedOrderIds.value = [];
    } else {
        selectedOrderIds.value = props.orders.data.map(order => order.id);
    }
}

// Watch props.orders to clear selection on page navigate
watch(() => props.orders.data, () => {
    selectedOrderIds.value = [];
});

watch(() => props.filters, (newFilters) => {
    Object.assign(filters, newFilters);
}, { deep: true });

let timer;
function applyFilters() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.orders.index'), filters, { preserveState: true, replace: true });
    }, 400);
}

function confirmDelivery(order) {
    if (confirm(`¿Está seguro de que desea marcar el pedido #${order.id} como entregado?`)) {
        router.post(route('admin.orders.deliver', order.id), {}, {
            preserveScroll: true
        });
    }
}

function bulkDeliver() {
    if (confirm(`¿Está seguro de que desea marcar los ${selectedOrderIds.value.length} pedidos seleccionados como entregados?`)) {
        router.post(route('admin.orders.bulk-deliver'), {
            order_ids: selectedOrderIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedOrderIds.value = [];
            }
        });
    }
}

function bulkPrint() {
    const ids = selectedOrderIds.value.join(',');
    window.open(route('admin.orders.bulk-print') + `?ids=${ids}`, '_blank');
}
</script>
