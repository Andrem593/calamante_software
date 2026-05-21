<template>
    <AdminLayout title="Detalles del Pedido">
        <div class="max-w-4xl mx-auto">
            <!-- Header Actions -->
            <div class="flex items-center justify-between mb-6 no-print">
                <Link :href="route('admin.orders.index')" class="flex items-center gap-2 text-slate-500 hover:text-slate-800 transition text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver a la lista
                </Link>
                <div class="flex gap-3">
                    <a :href="route('admin.orders.export-pdf', order.id)" target="_blank"
                        class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-medium transition shadow-lg shadow-red-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Descargar PDF
                    </a>
                    <button @click="printPage"
                        class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-medium transition shadow-lg shadow-slate-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir
                    </button>
                </div>
            </div>

            <!-- Order Card -->
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden print:shadow-none print:border-none">
                <!-- Top Banner -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-8 py-10 text-white flex justify-between items-start">
                    <div>
                        <p class="text-cyan-400 font-bold uppercase tracking-widest text-xs mb-2">Comprobante de Pedido</p>
                        <h2 class="text-3xl font-extrabold">Pedido #{{ order.id }}</h2>
                        <div class="mt-4 flex items-center gap-4">
                            <StatusBadge :status="order.status" />
                            <span class="text-slate-400 text-sm">Fecha: {{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="w-16 h-16 bg-white rounded-2xl mb-3 ml-auto flex items-center justify-center p-2">
                             <img src="/img/logo.jpeg" alt="Logo" class="max-w-full max-h-full object-contain">
                        </div>
                        <p class="text-sm font-medium text-slate-300">Dados App</p>
                    </div>
                </div>

                <div class="p-8">
                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-12 mb-10">
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Información del Cliente</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-slate-500 text-xs">Cliente</p>
                                    <p class="text-slate-900 font-semibold">{{ order.client?.name ?? 'Cliente Genérico' }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500 text-xs">Sucursal</p>
                                    <p class="text-slate-800">{{ order.branch?.name ?? 'Principal' }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500 text-xs">Dirección de Entrega</p>
                                    <p class="text-slate-800 text-sm italic">{{ order.address ?? 'No especificada' }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Detalles del Vendedor</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-slate-500 text-xs">Asesor Comercial</p>
                                    <p class="text-slate-900 font-semibold">{{ order.user?.name ?? 'Sistema' }}</p>
                                </div>
                                <div class="flex justify-between border-t border-slate-50 pt-3">
                                    <span class="text-slate-500 text-sm">Método de Pago:</span>
                                    <span class="text-slate-900 font-medium capitalize">{{ order.payment_method?.replace('_', ' ') ?? 'Pendiente' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 text-sm">Fecha de Entrega:</span>
                                    <span class="text-slate-900 font-medium">{{ formatDate(order.delivery_date) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="mb-10">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Detalle de Productos</h3>
                        <div class="border border-slate-100 rounded-2xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600 font-semibold">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Producto</th>
                                        <th class="px-6 py-3 text-center">Cant.</th>
                                        <th class="px-6 py-3 text-right">Precio Unit.</th>
                                        <th class="px-6 py-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in order.items" :key="item.id">
                                        <td class="px-6 py-4 font-medium text-slate-800">{{ item.product?.name ?? 'Producto' }}</td>
                                        <td class="px-6 py-4 text-center text-slate-600">{{ item.quantity }}</td>
                                        <td class="px-6 py-4 text-right text-slate-600">${{ Number(item.price).toFixed(2) }}</td>
                                        <td class="px-6 py-4 text-right font-bold text-slate-900">${{ Number(item.subtotal).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-slate-50/50">
                                    <tr>
                                        <td colspan="3" class="px-6 py-3 text-right font-medium text-slate-500 uppercase text-xs tracking-wider">Total General</td>
                                        <td class="px-6 py-3 text-right text-xl font-black text-slate-900">${{ Number(order.total).toFixed(2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Validation / Signature -->
                    <div class="grid grid-cols-2 gap-12 pt-8 border-t border-dash border-slate-200">
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Notas / Observaciones</h3>
                            <p class="text-slate-600 text-sm italic bg-slate-50 p-4 rounded-xl min-h-[80px]">
                                {{ order.notes || 'Sin observaciones adicionales.' }}
                            </p>
                        </div>
                        <div class="text-center">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Validación del Solicitante</h3>
                            <div class="border border-slate-100 rounded-2xl p-4 bg-slate-50 inline-block min-w-[200px]">
                                <img v-if="order.signature" :src="order.signature" alt="Firma" class="max-h-24 mx-auto mb-2 grayscale opacity-80 mix-blend-multiply">
                                <div v-else class="h-24 flex items-center justify-center text-slate-300 italic text-xs">Sin firma registrada</div>
                                <div class="border-t border-slate-200 pt-2 mt-2">
                                    <p class="text-slate-900 font-bold text-sm">{{ order.requested_by_name || 'Nombre no disponible' }}</p>
                                    <p class="text-slate-500 text-xs">{{ order.requested_by_id }}</p>
                                    <p class="text-slate-400 text-[10px] uppercase font-bold tracking-tighter mt-1">Firma Digital del Solicitante</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-slate-50 px-8 py-4 text-center border-t border-slate-100">
                    <p class="text-slate-400 text-xs font-medium">Este es un documento generado por el sistema de gestión DADOS. Gracias por su preferencia.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    order: Object
});

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('es-EC', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function printPage() {
    window.print();
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    
    :deep(aside), 
    :deep(header) {
        display: none !important;
    }

    :deep(main) {
        padding: 0 !important;
        margin: 0 !important;
    }

    body {
        background-color: white !important;
    }

    .max-w-4xl {
        max-width: 100% !important;
        width: 100% !important;
    }
}
</style>
