<template>
    <div class="min-h-screen bg-slate-100 py-8 px-4 print:bg-white print:p-0">
        <!-- Action Header (Hidden when printing) -->
        <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
            <div class="flex items-center gap-3">
                <span class="text-sm font-bold text-slate-700">Impresión en Lote</span>
                <span class="bg-cyan-50 text-cyan-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                    {{ orders.length }} Pedidos
                </span>
            </div>
            <div class="flex gap-2">
                <button @click="goBack" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                    Volver al Listado
                </button>
                <button @click="printPage" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-semibold transition shadow-md shadow-slate-200">
                    Imprimir Lote
                </button>
            </div>
        </div>

        <!-- Orders Print Loop -->
        <div class="max-w-4xl mx-auto space-y-8 print:space-y-0 print:max-w-full">
            <div v-for="(order, index) in orders" :key="order.id" 
                class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden print:shadow-none print:border-none print:rounded-none page-card"
                :class="{ 'page-break': index < orders.length - 1 }">
                
                <!-- Top Banner -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-8 py-10 text-white flex justify-between items-start print:bg-slate-900 print:text-white">
                    <div>
                        <p class="text-cyan-400 font-bold uppercase tracking-widest text-xs mb-2">Comprobante de Pedido</p>
                        <h2 class="text-3xl font-extrabold">Pedido #{{ order.id }}</h2>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                :class="{
                                    'bg-amber-400/20 text-amber-400 border border-amber-500/30': order.status === 'pending',
                                    'bg-blue-500/20 text-blue-400 border border-blue-500/30': order.status === 'invoiced',
                                    'bg-violet-500/20 text-violet-400 border border-violet-500/30': order.status === 'on_the_way',
                                    'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30': order.status === 'delivered',
                                    'bg-slate-500/20 text-slate-400 border border-slate-500/30': order.status === 'cancelled',
                                }">
                                {{ getStatusLabel(order.status) }}
                            </span>
                            <span v-if="order.is_invoiced"
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                Factura
                            </span>
                            <span v-else
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                                Prefactura
                            </span>
                            <span v-if="order.status !== 'cancelled' && (order.is_preinvoiced || order.contifico_id)" 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                Contifico: Sincronizado ({{ order.contifico_id ?? 'N/A' }})
                            </span>
                            <span class="text-slate-400 text-sm">Fecha: {{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="w-16 h-16 bg-white rounded-2xl mb-3 ml-auto flex items-center justify-center p-2 print:hidden">
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
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    orders: Array
});

onMounted(() => {
    // Automatically trigger browser print dialog once the view is loaded
    setTimeout(() => {
        window.print();
    }, 500);
});

function printPage() {
    window.print();
}

function goBack() {
    router.visit(route('admin.orders.index'));
}

function getStatusLabel(status) {
    return {
        pending: 'En Proceso',
        invoiced: 'Facturado',
        on_the_way: 'En Camino',
        delivered: 'Entregado',
        cancelled: 'Cancelado',
    }[status] ?? status;
}

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('es-EC', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background-color: white !important;
    }

    .page-card {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }

    .page-break {
        page-break-after: always;
        break-after: page;
    }
}
</style>
