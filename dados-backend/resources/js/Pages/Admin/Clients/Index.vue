<template>
    <AdminLayout title="Clientes y Sucursales">
        <!-- Header actions -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="relative flex-1">
                <input v-model="search" @input="doSearch" placeholder="Buscar por nombre o RUC..."
                    class="w-full sm:w-72 pl-9 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-2 ml-auto">
                <!-- Sync Contifico -->
                <button @click="syncClients(false)" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-600 rounded-xl text-sm font-medium transition disabled:opacity-50 border border-cyan-100">
                    <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ syncing ? 'Sincronizando...' : 'Sincronizar Contifico' }}
                </button>
                <!-- Sync Crédito -->
                <button @click="syncClients(false, true)" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-violet-50 hover:bg-violet-100 text-violet-600 rounded-xl text-sm font-medium transition disabled:opacity-50 border border-violet-100"
                    title="Sincronizar días y cupo de crédito desde Contifico">
                    <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Sincronizar Crédito
                </button>
                <!-- Sync Vendedores -->
                <button @click="syncClients(false, false, true)" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl text-sm font-medium transition disabled:opacity-50 border border-amber-200"
                    title="Sincronizar vendedores asignados desde Contifico">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Sincronizar Vendedores
                </button>
                <!-- Sync Inactivos -->
                <button @click="syncClients(true, false)" :disabled="syncing"
                    class="flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-medium transition disabled:opacity-50 border border-slate-200"
                    title="Sincronizar únicamente clientes inactivos desde Contifico">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Sincronizar Inactivos
                </button>
                <!-- Import Excel -->
                <button @click="showImport = true"
                    class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 rounded-xl text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Importar Excel
                </button>
                <!-- Fusionar Clientes -->
                <button @click="openMergeModal"
                    class="flex items-center gap-2 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-xl text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Fusionar Clientes
                </button>
                <Link :href="route('admin.clients.create')"
                    class="flex items-center gap-2 px-4 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-cyan-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo Cliente
                </Link>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Razón Social /
                            Nombre</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Nombre Local</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">RUC /
                            Identificación</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Vendedor Asignado</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Sucursales</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Crédito</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Estado</th>
                        <th class="text-left text-xs font-semibold text-slate-500 uppercase px-6 py-3">Email</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in clients.data" :key="client.id"
                        class="border-t border-slate-50 hover:bg-slate-50/50 transition">
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-medium text-slate-800">{{ client.name }}</p>
                        </td>
                        <td class="px-6 py-3.5">
                            <p class="text-sm text-slate-600">{{ client.nombre_local ?? '—' }}</p>
                        </td>
                        <td class="px-6 py-3.5 text-sm">
                            <span class="text-slate-800 font-medium">{{ client.identification ?? '—' }}</span>
                            <span v-if="client.identification_type" class="ml-2 text-xs text-slate-400 capitalize">
                                ({{ client.identification_type }})
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-sm">
                            <span v-if="client.vendedor_nombre" class="font-medium text-slate-700">
                                {{ client.vendedor_nombre }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-6 py-3.5">
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-600 text-xs font-semibold">
                                {{ client.branches_count }} local(es)
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-sm">
                            <div v-if="client.credit_limit || client.credit_days">
                                <p class="font-medium text-slate-800">${{ Number(client.credit_limit || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                                <p class="text-xs text-slate-400">{{ client.credit_days || 0 }} días</p>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-6 py-3.5">
                            <div class="flex flex-col gap-1 items-start">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                    :class="client.is_active !== false ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="client.is_active !== false ? 'bg-emerald-500' : 'bg-red-500'"></span>
                                    {{ client.is_active !== false ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                                <span v-if="client.unblock_time_remaining" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[11px] font-medium border border-amber-200" :title="'Desbloqueado temporalmente até ' + client.unblocked_until">
                                    🔓 Desbloqueado ({{ client.unblock_time_remaining }})
                                </span>
                                <span v-else-if="client.is_blocked || client.is_blocked_for_sales" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-100 text-red-800 text-[11px] font-bold border border-red-200">
                                    🔒 BLOQUEADO POR VENTAS
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 text-sm text-slate-500">{{ client.email ?? '—' }}</td>
                        <td class="px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button @click="openUnblockModal(client)"
                                    class="p-2 text-amber-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition"
                                    title="Gestión de Bloqueo / Desbloqueo por Días">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <Link :href="route('admin.clients.edit', client.id)"
                                    class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition"
                                    title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="deleteClient(client.id)"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                    title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!clients.data.length">
                        <td colspan="9" class="px-6 py-10 text-center text-slate-400 text-sm">No hay clientes</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :data="clients" />
        </div>

        <!-- Import modal -->
        <div v-if="showImport"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Importar Clientes desde Excel</h3>
                <p class="text-slate-500 text-sm mb-4">
                    Formatos de columna soportados: <strong>RUC, Cédula, Razón Social, Nombre Comercial, Monto Crédito, Días Crédito, CLI_Nombre Loca, CLI_Correo Sucu</strong> (o formato estándar: IDENTIFICACION, Persona, SUCURSALES).
                </p>
                <form @submit.prevent="submitImport">
                    <input type="file" ref="fileInput" accept=".xlsx,.xls,.csv"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm mb-4" required />
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="showImport = false"
                            class="px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-100 rounded-xl transition">Cancelar</button>
                        <button type="submit" :disabled="importForm.processing"
                            class="px-4 py-2.5 text-sm bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition">
                            {{ importForm.processing ? 'Importando...' : 'Importar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Merge modal -->
        <div v-if="showMerge"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Fusionar Clientes Duplicados</h3>
                    <button @click="closeMergeModal" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p class="text-slate-500 text-sm mb-6">
                    Esta acción unirá dos registros de clientes. Se transferirán todas las <strong>sucursales, pedidos y precios especiales</strong> del cliente origen al cliente destino, y luego se <strong>eliminará</strong> el cliente origen. También se conservará el correo del origen si el destino no lo tiene.
                </p>

                <div class="space-y-4 mb-6">
                    <!-- Source Client (to be deleted) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Cliente Origen (Se eliminará)</label>
                        <div class="relative">
                            <input v-model="sourceSearch" @input="searchSourceClients" placeholder="Buscar por nombre o identificación..."
                                class="w-full pl-3 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white" />
                            
                            <!-- Search Results Dropdown -->
                            <div v-if="sourceResults.length" class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto z-10">
                                <div v-for="c in sourceResults" :key="c.id" @click="selectSourceClient(c)"
                                    class="px-4 py-2 text-sm hover:bg-slate-50 cursor-pointer flex justify-between">
                                    <span class="font-medium text-slate-700">{{ c.name }}</span>
                                    <span class="text-slate-400 text-xs">{{ c.identification }} ({{ c.identification_type }})</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedSource" class="mt-2 text-xs bg-red-50 text-red-700 px-3 py-1.5 rounded-lg flex items-center justify-between">
                            <span>Seleccionado: <strong>{{ selectedSource.name }}</strong> ({{ selectedSource.identification }})</span>
                            <button @click="selectedSource = null" class="font-bold ml-2">×</button>
                        </div>
                    </div>

                    <!-- Target Client (to be preserved) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Cliente Destino (Se conservará)</label>
                        <div class="relative">
                            <input v-model="targetSearch" @input="searchTargetClients" placeholder="Buscar por nombre o identificación..."
                                class="w-full pl-3 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white" />
                            
                            <!-- Search Results Dropdown -->
                            <div v-if="targetResults.length" class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto z-10">
                                <div v-for="c in targetResults" :key="c.id" @click="selectTargetClient(c)"
                                    class="px-4 py-2 text-sm hover:bg-slate-50 cursor-pointer flex justify-between">
                                    <span class="font-medium text-slate-700">{{ c.name }}</span>
                                    <span class="text-slate-400 text-xs">{{ c.identification }} ({{ c.identification_type }})</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedTarget" class="mt-2 text-xs bg-green-50 text-green-700 px-3 py-1.5 rounded-lg flex items-center justify-between">
                            <span>Seleccionado: <strong>{{ selectedTarget.name }}</strong> ({{ selectedTarget.identification }})</span>
                            <button @click="selectedTarget = null" class="font-bold ml-2">×</button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    <button type="button" @click="closeMergeModal"
                        class="px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-100 rounded-xl transition">
                        Cancelar
                    </button>
                    <button type="button" @click="submitMerge" :disabled="submittingMerge || !selectedSource || !selectedTarget"
                        class="px-4 py-2.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition disabled:opacity-50 flex items-center gap-2">
                        <svg v-if="submittingMerge" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Fusionar
                    </button>
                </div>
            </div>
        </div>

        <!-- Unblock Modal -->
        <div v-if="showUnblockModal"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Desbloqueo de Cliente</h3>
                    <button @click="showUnblockModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedClientUnblock" class="mb-4">
                    <p class="text-sm text-slate-800 font-bold">{{ selectedClientUnblock.name }}</p>
                    <p class="text-xs text-slate-500">RUC/Cédula: {{ selectedClientUnblock.identification ?? 'N/A' }}</p>
                    <div class="mt-2 text-xs p-2.5 rounded-xl border" :class="selectedClientUnblock.unblock_time_remaining ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-slate-50 border-slate-200 text-slate-700'">
                        <span v-if="selectedClientUnblock.unblock_time_remaining">
                            🔓 Desbloqueado activo hasta: <strong>{{ selectedClientUnblock.unblock_time_remaining }}</strong>
                        </span>
                        <span v-else-if="selectedClientUnblock.is_blocked || selectedClientUnblock.is_blocked_for_sales">
                            🔒 Estado actual: <strong>BLOQUEADO POR VENTAS</strong>
                        </span>
                        <span v-else>
                            ✅ Estado actual: <strong>ACTIVO / AL DÍA</strong>
                        </span>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Seleccionar Días de Desbloqueo</label>
                    <div class="grid grid-cols-4 gap-2 mb-3">
                        <button type="button" @click="unblockDays = 1"
                            class="py-2 text-xs font-bold rounded-xl border transition"
                            :class="unblockDays === 1 ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                            1 Día
                        </button>
                        <button type="button" @click="unblockDays = 2"
                            class="py-2 text-xs font-bold rounded-xl border transition"
                            :class="unblockDays === 2 ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                            2 Días
                        </button>
                        <button type="button" @click="unblockDays = 3"
                            class="py-2 text-xs font-bold rounded-xl border transition"
                            :class="unblockDays === 3 ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                            3 Días
                        </button>
                        <button type="button" @click="unblockDays = 7"
                            class="py-2 text-xs font-bold rounded-xl border transition"
                            :class="unblockDays === 7 ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                            7 Días
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs text-slate-500 font-medium">Días personalizados:</span>
                        <input type="number" min="1" max="365" v-model.number="unblockDays"
                            class="w-24 px-3 py-1.5 border border-slate-200 rounded-xl text-sm font-bold text-center focus:ring-2 focus:ring-amber-400 focus:outline-none" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Motivo / Observación (Opcional):</label>
                        <input type="text" v-model="unblockReason" placeholder="Ej. Solicitud de gerencia / Compromiso de pago"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="button" @click="submitUnblock" :disabled="submittingUnblock || unblockDays < 1"
                        class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm rounded-xl transition shadow-md shadow-amber-200 disabled:opacity-50 flex items-center justify-center gap-2">
                        <svg v-if="submittingUnblock" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Otorgar Desbloqueo por {{ unblockDays }} día(s)
                    </button>

                    <div class="flex gap-2 mt-1">
                        <button type="button" @click="toggleManualBlock(true)"
                            class="flex-1 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold rounded-xl border border-red-200 transition">
                            🔒 Bloquear Manualmente
                        </button>
                        <button type="button" @click="toggleManualBlock(false)"
                            class="flex-1 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-xl border border-emerald-200 transition">
                            ✅ Quitar Bloqueo Manual
                        </button>
                    </div>

                    <button type="button" @click="showUnblockModal = false"
                        class="w-full py-2 text-slate-500 hover:text-slate-700 text-xs font-medium transition mt-1">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({ clients: Object, filters: Object });
const search = ref(props.filters?.search ?? '');
const showImport = ref(false);
const syncing = ref(false);
const fileInput = ref(null);
const importForm = useForm({ file: null });

let searchTimer;
function doSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.clients.index'), { search: search.value }, { preserveState: true, replace: true });
    }, 400);
}

function syncClients(onlyInactive = false, onlyCredit = false, onlyVendor = false) {
    syncing.value = true;
    router.post(route('admin.clients.sync'), { inactive: onlyInactive, credit: onlyCredit, vendedor: onlyVendor }, {
        onFinish: () => { syncing.value = false; }
    });
}

function deleteClient(id) {
    if (confirm('¿Eliminar este cliente y todas sus sucursales?')) {
        router.delete(route('admin.clients.destroy', id));
    }
}

function submitImport() {
    importForm.file = fileInput.value.files[0];
    importForm.post(route('admin.clients.import'), {
        onSuccess: () => { showImport.value = false; }
    });
}

const showMerge = ref(false);
const submittingMerge = ref(false);
const sourceSearch = ref('');
const targetSearch = ref('');
const sourceResults = ref([]);
const targetResults = ref([]);
const selectedSource = ref(null);
const selectedTarget = ref(null);

let sourceTimer;
function searchSourceClients() {
    clearTimeout(sourceTimer);
    if (!sourceSearch.value) {
        sourceResults.value = [];
        return;
    }
    sourceTimer = setTimeout(async () => {
        try {
            const response = await fetch(route('admin.clients.search-json', { search: sourceSearch.value }));
            sourceResults.value = await response.json();
        } catch (e) {
            console.error(e);
        }
    }, 300);
}

let targetTimer;
function searchTargetClients() {
    clearTimeout(targetTimer);
    if (!targetSearch.value) {
        targetResults.value = [];
        return;
    }
    targetTimer = setTimeout(async () => {
        try {
            const response = await fetch(route('admin.clients.search-json', { search: targetSearch.value }));
            targetResults.value = await response.json();
        } catch (e) {
            console.error(e);
        }
    }, 300);
}

function selectSourceClient(client) {
    selectedSource.value = client;
    sourceResults.value = [];
    sourceSearch.value = '';
}

function selectTargetClient(client) {
    selectedTarget.value = client;
    targetResults.value = [];
    targetSearch.value = '';
}

function openMergeModal() {
    showMerge.value = true;
}

function closeMergeModal() {
    showMerge.value = false;
    selectedSource.value = null;
    selectedTarget.value = null;
    sourceSearch.value = '';
    targetSearch.value = '';
    sourceResults.value = [];
    targetResults.value = [];
}

function submitMerge() {
    if (!selectedSource.value || !selectedTarget.value) return;
    if (selectedSource.value.id === selectedTarget.value.id) {
        alert('El cliente origen y destino no pueden ser el mismo.');
        return;
    }
    if (!confirm(`¿Estás seguro de fusionar a ${selectedSource.value.name} dentro de ${selectedTarget.value.name}?\n\nEsta acción NO se puede deshacer y eliminará al cliente de origen.`)) {
        return;
    }
    
    submittingMerge.value = true;
    router.post(route('admin.clients.merge'), {
        source_client_id: selectedSource.value.id,
        target_client_id: selectedTarget.value.id
    }, {
        onSuccess: () => {
            closeMergeModal();
        },
        onFinish: () => {
            submittingMerge.value = false;
        }
    });
}

// Unblock feature
const showUnblockModal = ref(false);
const selectedClientUnblock = ref(null);
const unblockDays = ref(1);
const unblockReason = ref('');
const submittingUnblock = ref(false);

function openUnblockModal(client) {
    selectedClientUnblock.value = client;
    unblockDays.value = 1;
    unblockReason.value = '';
    showUnblockModal.value = true;
}

function submitUnblock() {
    if (!selectedClientUnblock.value || unblockDays.value < 1) return;
    submittingUnblock.value = true;

    router.post(route('admin.clients.unblock', selectedClientUnblock.value.id), {
        days: unblockDays.value,
        reason: unblockReason.value
    }, {
        onSuccess: () => {
            showUnblockModal.value = false;
        },
        onFinish: () => {
            submittingUnblock.value = false;
        }
    });
}

function toggleManualBlock(isBlocked) {
    if (!selectedClientUnblock.value) return;
    const actionText = isBlocked ? 'bloquear' : 'quitar el bloqueo manual a';
    if (!confirm(`¿Estás seguro de ${actionText} ${selectedClientUnblock.value.name}?`)) return;

    router.post(route('admin.clients.block', selectedClientUnblock.value.id), {
        is_blocked: isBlocked,
        reason: unblockReason.value
    }, {
        onSuccess: () => {
            showUnblockModal.value = false;
        }
    });
}
</script>

