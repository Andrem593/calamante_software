<template>
    <AdminLayout title="Reporte de Pedidos y Facturas por Ruta">
        <!-- Encabezado con título y botón de exportar -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                    <span class="p-2 bg-gradient-to-tr from-cyan-500 to-blue-600 text-white rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2" />
                        </svg>
                    </span>
                    Pedidos y Facturas con Rutas
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Control consolidado por fecha de emisión o entrega, tipo de comprobante, rutas y múltiples vendedores.
                </p>
            </div>

            <a :href="exportUrl" target="_blank"
                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition shadow-sm flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Exportar Excel (CSV)
            </a>
        </div>

        <!-- Tarjetas de Resumen KPI -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600 font-bold text-xl">
                    $
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Filtrado</p>
                    <p class="text-2xl font-extrabold text-slate-800">
                        ${{ formatMoney(summary.total_sales) }}
                    </p>
                    <p class="text-[11px] text-slate-400">{{ summary.total_count }} comprobantes</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold text-xl">
                    🧾
                </div>
                <div>
                    <p class="text-xs text-emerald-600 font-semibold uppercase tracking-wider">Facturas SRI</p>
                    <p class="text-xl font-bold text-slate-800">
                        ${{ formatMoney(summary.invoiced_total) }}
                    </p>
                    <p class="text-[11px] text-emerald-600 font-medium">{{ summary.invoiced_count }} facturadas</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 font-bold text-xl">
                    📑
                </div>
                <div>
                    <p class="text-xs text-purple-600 font-semibold uppercase tracking-wider">Prefacturas</p>
                    <p class="text-xl font-bold text-slate-800">
                        ${{ formatMoney(summary.preinvoiced_total) }}
                    </p>
                    <p class="text-[11px] text-purple-600 font-medium">{{ summary.preinvoiced_count }} prefacturadas</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 font-bold text-xl">
                    ⏳
                </div>
                <div>
                    <p class="text-xs text-amber-600 font-semibold uppercase tracking-wider">Pendientes</p>
                    <p class="text-xl font-bold text-slate-800">
                        ${{ formatMoney(summary.pending_total) }}
                    </p>
                    <p class="text-[11px] text-amber-600 font-medium">{{ summary.pending_count }} pedidos pendientes</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xl">
                    🗺️
                </div>
                <div>
                    <p class="text-xs text-blue-600 font-semibold uppercase tracking-wider">Rutas Activas</p>
                    <p class="text-2xl font-extrabold text-slate-800">
                        {{ summary.route_breakdown ? summary.route_breakdown.length : 0 }}
                    </p>
                    <p class="text-[11px] text-slate-400">sectores en este período</p>
                </div>
            </div>
        </div>

        <!-- Barra rápida de Rutas (Chips) -->
        <div v-if="summary.route_breakdown && summary.route_breakdown.length"
            class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 mb-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span>📍</span> Resumen por Ruta:
                </span>
                <span class="text-xs text-slate-400">Haz clic en una ruta para filtrar</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-for="rb in summary.route_breakdown" :key="rb.route"
                    @click="toggleRouteChip(rb.route)"
                    type="button"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold transition border flex items-center gap-2"
                    :class="isRouteSelected(rb.route)
                        ? 'bg-cyan-500 text-white border-cyan-600 shadow-sm'
                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                    <span>{{ rb.route }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                        :class="isRouteSelected(rb.route) ? 'bg-cyan-700 text-cyan-100' : 'bg-slate-200 text-slate-600'">
                        {{ rb.orders_count }} | ${{ formatMoney(rb.sales_sum) }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Panel de Filtros -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-6">
            <!-- Selector de Tipo de Fecha -->
            <div class="flex flex-wrap items-center gap-3 pb-4 mb-4 border-b border-slate-100">
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Filtrar fecha por:</span>
                <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200">
                    <button type="button"
                        @click="form.date_type = 'created_at'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        :class="form.date_type === 'created_at' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-800'">
                        <span>📅</span> Fecha Pedido / Factura
                    </button>
                    <button type="button"
                        @click="form.date_type = 'delivery_date'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        :class="form.date_type === 'delivery_date' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-800'">
                        <span>🚚</span> Fecha de Entrega
                    </button>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="button" @click="setPresetRange('today')" class="px-2.5 py-1 text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition font-medium">Hoy</button>
                    <button type="button" @click="setPresetRange('week')" class="px-2.5 py-1 text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition font-medium">Esta Semana</button>
                    <button type="button" @click="setPresetRange('month')" class="px-2.5 py-1 text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition font-medium">Este Mes</button>
                    <button type="button" @click="setPresetRange('last_month')" class="px-2.5 py-1 text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition font-medium">Mes Anterior</button>
                </div>
            </div>

            <!-- Fila 1 de Campos de Filtro -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Desde -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">
                        Desde {{ form.date_type === 'delivery_date' ? '(Entrega)' : '(Emisión)' }}
                    </label>
                    <input type="date" v-model="form.from"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                </div>

                <!-- Hasta -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">
                        Hasta {{ form.date_type === 'delivery_date' ? '(Entrega)' : '(Emisión)' }}
                    </label>
                    <input type="date" v-model="form.to"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                </div>

                <!-- Tipo de Documento -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">Tipo de Comprobante</label>
                    <select v-model="form.doc_type"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="all">Todos los Comprobantes</option>
                        <option value="invoiced">🧾 Solo Facturas SRI (Facturados)</option>
                        <option value="preinvoiced">📑 Solo Prefacturas</option>
                        <option value="not_invoiced">⏳ Pendientes (Sin facturar)</option>
                    </select>
                </div>

                <!-- Rutas Selector Dropdown -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">Ruta</label>
                    <select v-model="selectedSingleRoute" @change="onSingleRouteChange"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="all">Todas las rutas</option>
                        <option v-for="r in available_routes" :key="r" :value="r">{{ r }}</option>
                        <option value="none">Sin ruta asignada</option>
                    </select>
                </div>
            </div>

            <!-- Fila 2: Multi-vendedor y búsqueda -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <!-- Selector Múltiple de Vendedores -->
                <div class="relative">
                    <label class="block text-xs text-slate-500 font-semibold mb-1 flex justify-between items-center">
                        <span>Vendedores</span>
                        <span v-if="form.user_ids.length" class="text-[11px] font-bold text-cyan-600">
                            {{ form.user_ids.length }} seleccionado(s)
                        </span>
                        <span v-else class="text-[11px] text-slate-400">Todos</span>
                    </label>

                    <!-- Botón para abrir el panel desplegable de vendedores -->
                    <div @click="toggleSellerDropdown"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm bg-white cursor-pointer flex justify-between items-center hover:border-slate-300">
                        <span class="truncate text-slate-700">
                            {{ sellerButtonLabel }}
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition" :class="{'rotate-180': sellerDropdownOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <!-- Dropdown de vendedores -->
                    <div v-if="sellerDropdownOpen"
                        class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-2xl shadow-xl z-30 p-3 max-h-72 overflow-hidden flex flex-col">
                        <div class="flex justify-between items-center pb-2 mb-2 border-b border-slate-100 text-xs">
                            <button type="button" @click="selectAllSellers" class="text-cyan-600 font-semibold hover:underline">
                                Seleccionar todos
                            </button>
                            <button type="button" @click="clearSellers" class="text-slate-400 hover:text-slate-600">
                                Desmarcar todos
                            </button>
                        </div>

                        <!-- Buscador interno de vendedor -->
                        <input type="text" v-model="sellerSearch" placeholder="Buscar vendedor..."
                            class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs mb-2 focus:ring-1 focus:ring-cyan-500 focus:outline-none" />

                        <!-- Lista con checkboxes -->
                        <div class="overflow-y-auto space-y-1 pr-1 flex-1">
                            <label v-for="seller in filteredSellers" :key="seller.id"
                                class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-xs text-slate-700">
                                <input type="checkbox" :value="seller.id" v-model="form.user_ids"
                                    class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-400" />
                                <span>{{ seller.name }}</span>
                            </label>
                            <div v-if="!filteredSellers.length" class="text-center text-slate-400 py-3 text-xs">
                                No se encontraron vendedores
                            </div>
                        </div>

                        <div class="pt-2 mt-2 border-t border-slate-100 flex justify-end">
                            <button type="button" @click="sellerDropdownOpen = false"
                                class="px-3 py-1 bg-cyan-500 text-white rounded-lg text-xs font-semibold hover:bg-cyan-600 transition">
                                Listo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Búsqueda rápida de texto -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">Buscar</label>
                    <div class="relative">
                        <input type="text" v-model="form.search" placeholder="Cliente, RUC, Sucursal, # ID..."
                            @keyup.enter="applyFilters"
                            class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Estado del Pedido -->
                <div>
                    <label class="block text-xs text-slate-500 font-semibold mb-1">Estado del Pedido</label>
                    <select v-model="form.status"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-400 focus:outline-none">
                        <option value="">Todos los Estados (Excl. Cancelados)</option>
                        <option value="pending">En Proceso</option>
                        <option value="invoiced">Facturado</option>
                        <option value="delivered">Entregado</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
            </div>

            <!-- Chips de Vendedores Seleccionados (para eliminar rápidamente) -->
            <div v-if="form.user_ids.length > 0 && form.user_ids.length < sellers.length" class="flex flex-wrap items-center gap-1.5 mb-4 pt-2 border-t border-slate-100">
                <span class="text-[11px] text-slate-400">Vendedores activos en filtro:</span>
                <span v-for="sid in form.user_ids" :key="sid"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-cyan-50 text-cyan-700 border border-cyan-200">
                    {{ getSellerName(sid) }}
                    <button type="button" @click="removeSeller(sid)" class="hover:text-red-500">×</button>
                </span>
                <button type="button" @click="clearSellers" class="text-[11px] text-slate-400 hover:text-red-500 underline ml-1">
                    Limpiar todos
                </button>
            </div>

            <!-- Botones de Acción -->
            <div class="flex flex-wrap justify-between items-center gap-3 pt-3 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <button @click="applyFilters"
                        class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Aplicar Filtros
                    </button>
                    <button @click="clearFilters"
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-medium transition">
                        Limpiar Filtros
                    </button>
                </div>

                <div class="text-xs text-slate-400">
                    Mostrando <strong class="text-slate-700">{{ orders.total }}</strong> pedidos / facturas encontrados
                </div>
            </div>
        </div>

        <!-- Tabla de Datos -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider"># Pedido</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Fecha Pedido</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Fecha Entrega</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Tipo Doc.</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Ruta</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Sucursal</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Vendedor</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Pago</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="order in orders.data" :key="order.id"
                        class="hover:bg-slate-50/70 transition cursor-pointer"
                        @click="router.visit(route('admin.orders.show', order.id))">
                        <!-- # Pedido -->
                        <td class="px-4 py-3.5 text-sm font-bold text-cyan-600">
                            #{{ order.id }}
                        </td>

                        <!-- Fecha Pedido -->
                        <td class="px-4 py-3.5 text-xs text-slate-600 whitespace-nowrap">
                            {{ formatDate(order.created_at) }}
                        </td>

                        <!-- Fecha Entrega -->
                        <td class="px-4 py-3.5 text-xs whitespace-nowrap">
                            <span v-if="order.delivery_date" class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md font-medium border border-blue-100">
                                {{ formatDateOnly(order.delivery_date) }}
                            </span>
                            <span v-else class="text-slate-400 italic text-[11px]">—</span>
                        </td>

                        <!-- Tipo de Documento -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <span v-if="order.is_invoiced || order.status === 'invoiced'"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span>🧾</span> FACTURA SRI
                            </span>
                            <span v-else-if="order.is_preinvoiced"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                <span>📑</span> PREFACTURA
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                <span>⏳</span> PEDIDO
                            </span>
                        </td>

                        <!-- Ruta -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider"
                                :class="getRouteBadgeClass(order.computed_route)">
                                <span>📍</span> {{ order.computed_route || 'SIN RUTA' }}
                            </span>
                        </td>

                        <!-- Cliente -->
                        <td class="px-4 py-3.5">
                            <p class="text-sm font-semibold text-slate-800 line-clamp-1" :title="order.client?.name">
                                {{ order.client?.name ?? '—' }}
                            </p>
                            <p v-if="order.client?.identification" class="text-xs text-slate-400 font-mono">
                                {{ order.client.identification }}
                            </p>
                        </td>

                        <!-- Sucursal -->
                        <td class="px-4 py-3.5 text-xs text-slate-600">
                            <p class="line-clamp-1" :title="order.branch?.name">
                                {{ order.branch?.name ?? 'Matriz / Principal' }}
                            </p>
                            <p v-if="order.branch?.address" class="text-[11px] text-slate-400 truncate max-w-[150px]">
                                {{ order.branch.address }}
                            </p>
                        </td>

                        <!-- Vendedor -->
                        <td class="px-4 py-3.5 text-xs text-slate-700 font-medium whitespace-nowrap">
                            {{ order.user?.name ?? '—' }}
                        </td>

                        <!-- Pago -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium"
                                :class="order.payment_method?.toLowerCase().includes('cred') ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
                                {{ order.payment_method ?? 'Efectivo' }}
                                <span v-if="order.credit_days && order.credit_days > 0" class="text-[10px] font-bold">({{ order.credit_days }}d)</span>
                            </span>
                        </td>

                        <!-- Estado -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <StatusBadge :status="order.status" />
                        </td>

                        <!-- Total -->
                        <td class="px-4 py-3.5 text-right font-black text-slate-800 text-sm whitespace-nowrap">
                            ${{ formatMoney(order.total) }}
                        </td>
                    </tr>

                    <tr v-if="!orders.data.length">
                        <td colspan="11" class="px-6 py-16 text-center text-slate-400">
                            <div class="max-w-xs mx-auto space-y-2">
                                <span class="text-4xl">🔍</span>
                                <p class="text-base font-semibold text-slate-700">No se encontraron comprobantes</p>
                                <p class="text-xs text-slate-400">Intenta modificando los filtros de fecha, tipo de comprobante, ruta o vendedor.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginación -->
            <Pagination :data="orders" />
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    orders: Object,
    summary: Object,
    sellers: Array,
    available_routes: Array,
    filters: Object,
});

// Estado de los filtros reactivos
const form = reactive({
    date_type: props.filters?.date_type ?? 'created_at',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    doc_type: props.filters?.doc_type ?? 'all',
    user_ids: Array.isArray(props.filters?.user_ids) ? [...props.filters.user_ids] : [],
    routes: Array.isArray(props.filters?.routes) ? [...props.filters.routes] : [],
    payment_method: props.filters?.payment_method ?? '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
});

// Dropdown de vendedores
const sellerDropdownOpen = ref(false);
const sellerSearch = ref('');

function toggleSellerDropdown() {
    sellerDropdownOpen.value = !sellerDropdownOpen.value;
}

const filteredSellers = computed(() => {
    if (!sellerSearch.value) return props.sellers;
    const s = sellerSearch.value.toLowerCase();
    return props.sellers.filter(v => v.name.toLowerCase().includes(s));
});

const sellerButtonLabel = computed(() => {
    if (!form.user_ids.length) return 'Todos los vendedores';
    if (form.user_ids.length === 1) {
        return getSellerName(form.user_ids[0]);
    }
    return `${form.user_ids.length} vendedores seleccionados`;
});

function getSellerName(id) {
    const s = props.sellers.find(v => v.id === id);
    return s ? s.name : `#${id}`;
}

function selectAllSellers() {
    form.user_ids = props.sellers.map(s => s.id);
}

function clearSellers() {
    form.user_ids = [];
}

function removeSeller(id) {
    form.user_ids = form.user_ids.filter(sid => sid !== id);
}

// Control de selección única / múltiple de ruta
const selectedSingleRoute = ref(
    form.routes.length === 1 ? form.routes[0] : (form.routes.length === 0 ? 'all' : form.routes[0])
);

function onSingleRouteChange() {
    if (selectedSingleRoute.value === 'all') {
        form.routes = [];
    } else {
        form.routes = [selectedSingleRoute.value];
    }
}

function toggleRouteChip(route) {
    if (isRouteSelected(route)) {
        form.routes = form.routes.filter(r => r !== route);
        if (selectedSingleRoute.value === route) {
            selectedSingleRoute.value = 'all';
        }
    } else {
        form.routes = [route];
        selectedSingleRoute.value = route;
    }
    applyFilters();
}

function isRouteSelected(route) {
    return form.routes.includes(route);
}

// Rangos de fecha predefinidos
function setPresetRange(preset) {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const toYMD = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    if (preset === 'today') {
        const todayStr = toYMD(now);
        form.from = todayStr;
        form.to = todayStr;
    } else if (preset === 'week') {
        const day = now.getDay();
        const diffToMonday = (day === 0 ? -6 : 1) - day;
        const monday = new Date(now);
        monday.setDate(now.getDate() + diffToMonday);
        form.from = toYMD(monday);
        form.to = toYMD(now);
    } else if (preset === 'month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        form.from = toYMD(firstDay);
        form.to = toYMD(now);
    } else if (preset === 'last_month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
        form.from = toYMD(firstDay);
        form.to = toYMD(lastDay);
    }
    applyFilters();
}

function applyFilters() {
    sellerDropdownOpen.value = false;
    router.get(route('admin.reports.orders-routes'), form, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    form.date_type = 'created_at';
    form.from = '';
    form.to = '';
    form.doc_type = 'all';
    form.user_ids = [];
    form.routes = [];
    form.payment_method = '';
    form.status = '';
    form.search = '';
    selectedSingleRoute.value = 'all';
    applyFilters();
}

// URL dinámica para exportación con todos los filtros activos
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (form.date_type) params.append('date_type', form.date_type);
    if (form.from) params.append('from', form.from);
    if (form.to) params.append('to', form.to);
    if (form.doc_type && form.doc_type !== 'all') params.append('doc_type', form.doc_type);
    if (form.user_ids && form.user_ids.length) {
        params.append('user_ids', form.user_ids.join(','));
    }
    if (form.routes && form.routes.length) {
        params.append('routes', form.routes.join(','));
    }
    if (form.payment_method) params.append('payment_method', form.payment_method);
    if (form.status) params.append('status', form.status);
    if (form.search) params.append('search', form.search);

    return route('admin.reports.orders-routes.export') + '?' + params.toString();
});

// Estilos de badges por ruta
function getRouteBadgeClass(route) {
    if (!route || route === 'SIN RUTA') {
        return 'bg-slate-100 text-slate-500 border border-slate-200';
    }
    const r = route.toUpperCase();
    if (r.includes('NORTE')) return 'bg-sky-50 text-sky-700 border border-sky-200';
    if (r.includes('CENTRO')) return 'bg-violet-50 text-violet-700 border border-violet-200';
    if (r.includes('SUR')) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if (r.includes('COBERTURA')) return 'bg-amber-50 text-amber-700 border border-amber-200';
    if (r.includes('PUNTILLA')) return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    if (r.includes('CEIBOS')) return 'bg-teal-50 text-teal-700 border border-teal-200';
    if (r.includes('BUCAY') || r.includes('BABAHOYO') || r.includes('MILAGRO')) return 'bg-orange-50 text-orange-700 border border-orange-200';
    return 'bg-cyan-50 text-cyan-700 border border-cyan-200';
}

function formatMoney(amount) {
    return Number(amount ?? 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-EC', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateOnly(dateStr) {
    if (!dateStr) return '—';
    const parts = dateStr.split('T')[0].split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return new Date(dateStr).toLocaleDateString('es-EC');
}
</script>
