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
                    <button v-if="order.status !== 'delivered' && order.status !== 'cancelled'" 
                        @click="showDeliverModal = true"
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-medium transition shadow-lg shadow-emerald-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Confirmar Entrega
                    </button>
                    <Link v-if="order.status !== 'delivered' && order.status !== 'cancelled' && !order.is_invoiced" 
                        :href="route('admin.orders.edit', order.id)"
                        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition shadow-lg shadow-blue-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Editar Pedido
                    </Link>
                    <button v-if="order.status !== 'cancelled'" @click="syncWithContifico" :disabled="syncing"
                        class="flex items-center gap-2 px-4 py-2 text-white rounded-xl text-sm font-medium transition shadow-lg"
                        :class="order.is_preinvoiced || order.contifico_id ? 'bg-slate-600 hover:bg-slate-700 shadow-slate-200' : 'bg-cyan-600 hover:bg-cyan-700 shadow-cyan-200'">
                        <svg v-if="syncing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.23" />
                        </svg>
                        {{ syncing ? 'Sincronizando...' : (order.is_preinvoiced || order.contifico_id ? 'Re-sincronizar Contifico' : 'Sincronizar Contifico') }}
                    </button>
                    <button v-if="order.status !== 'cancelled' && order.contifico_id && order.is_invoiced" 
                        @click="showAuthorizeModal = true" :disabled="authorizing"
                        class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-medium transition shadow-lg shadow-amber-200">
                        <svg v-if="authorizing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        {{ authorizing ? 'Autorizando...' : 'Autorizar SRI' }}
                    </button>
                    <button v-if="order.status !== 'cancelled'" 
                        @click="showCancelModal = true"
                        class="flex items-center gap-2 px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-sm font-semibold transition border border-rose-200 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Anular Pedido
                    </button>
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
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <StatusBadge :status="order.status" />
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
                                <span v-if="contifico_status" class="ml-1.5 pl-1.5 border-l border-emerald-500/30">
                                    Estado SRI: {{ contifico_status }}
                                </span>
                            </span>
                            <span v-else-if="order.status !== 'cancelled'" 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-400 border border-rose-500/30 animate-pulse">
                                Contifico: Pendiente
                            </span>
                            <span v-else 
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-500/20 text-slate-400 border border-slate-500/30">
                                Contifico: Bloqueado (Anulado)
                            </span>
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

            <!-- Modern Confirmation Modal -->
            <div v-if="showCancelModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all duration-300 scale-100">
                    <div class="flex items-center gap-4 mb-4 text-red-600">
                        <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Anular Pedido #{{ order.id }}</h3>
                            <p class="text-sm text-slate-500">Esta acción es irreversible.</p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                        ¿Está seguro de que desea anular este pedido? Una vez anulado, no contará para las estadísticas de ventas y se bloqueará toda sincronización posterior con Contifico.
                    </p>

                    <div class="flex justify-end gap-3">
                        <button @click="showCancelModal = false" :disabled="cancelling"
                            class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button @click="cancelOrder" :disabled="cancelling"
                            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-red-200">
                            <svg v-if="cancelling" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ cancelling ? 'Anulando...' : 'Confirmar Anulación' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- SRI Authorization Confirmation Modal -->
            <div v-if="showAuthorizeModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all duration-300 scale-100">
                    <div class="flex items-center gap-4 mb-4 text-amber-500">
                        <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Autorizar SRI</h3>
                            <p class="text-sm text-slate-500">Enviar documento a autorizar</p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                        ¿Está seguro de que desea enviar este documento a autorizar al SRI a través de Contifico? Esta acción procesará el comprobante electrónico ante el servicio de rentas internas.
                    </p>

                    <div class="flex justify-end gap-3">
                        <button @click="showAuthorizeModal = false" :disabled="authorizing"
                            class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button @click="authorizeSri" :disabled="authorizing"
                            class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-amber-200">
                            <svg v-if="authorizing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ authorizing ? 'Enviando...' : 'Confirmar Envío' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delivery Confirmation Modal -->
            <div v-if="showDeliverModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 transform transition-all duration-300 scale-100">
                    <div class="flex items-center gap-4 mb-4 text-emerald-600">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Confirmar Entrega</h3>
                            <p class="text-sm text-slate-500">Marcar pedido como entregado</p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                        ¿Está seguro de que desea marcar este pedido como entregado? Se registrará la entrega del producto y se actualizará el estado del pedido.
                    </p>

                    <div class="flex justify-end gap-3">
                        <button @click="showDeliverModal = false" :disabled="delivering"
                            class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button @click="deliverOrder" :disabled="delivering"
                            class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-emerald-200">
                            <svg v-if="delivering" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ delivering ? 'Procesando...' : 'Confirmar Entrega' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    order: Object,
    contifico_status: String
});

const syncing = ref(false);
const cancelling = ref(false);
const authorizing = ref(false);
const showCancelModal = ref(false);
const showAuthorizeModal = ref(false);
const showDeliverModal = ref(false);
const delivering = ref(false);

function syncWithContifico() {
    if (syncing.value) return;
    syncing.value = true;
    router.post(route('admin.orders.sync-contifico', props.order.id), {}, {
        onFinish: () => {
            syncing.value = false;
        }
    });
}

function authorizeSri() {
    if (authorizing.value) return;
    authorizing.value = true;
    router.post(route('admin.orders.authorize-sri', props.order.id), {}, {
        onSuccess: () => {
            showAuthorizeModal.value = false;
        },
        onFinish: () => {
            authorizing.value = false;
        }
    });
}

function cancelOrder() {
    if (cancelling.value) return;
    cancelling.value = true;
    router.post(route('admin.orders.cancel', props.order.id), {}, {
        onSuccess: () => {
            showCancelModal.value = false;
        },
        onFinish: () => {
            cancelling.value = false;
        }
    });
}

function deliverOrder() {
    if (delivering.value) return;
    delivering.value = true;
    router.post(route('admin.orders.deliver', props.order.id), {}, {
        onSuccess: () => {
            showDeliverModal.value = false;
        },
        onFinish: () => {
            delivering.value = false;
        }
    });
}

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
