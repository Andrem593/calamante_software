<template>
    <AdminLayout title="Promedio Facturación">
        <!-- Overlay Screen while generating report -->
        <div v-if="loading"
            class="fixed inset-0 bg-slate-900/70 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-all duration-300">
            <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl border border-slate-100 text-center space-y-6 animate-pulse-subtle">
                <div class="relative w-20 h-20 mx-auto">
                    <!-- Spinning Rings -->
                    <div class="absolute inset-0 rounded-full border-4 border-cyan-100 border-t-cyan-500 animate-spin"></div>
                    <div class="absolute inset-2 rounded-full border-4 border-emerald-100 border-b-emerald-500 animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-cyan-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14" />
                        </svg>
                    </div>
                </div>

                <div>
                    <h3 class="text-xl font-extrabold text-slate-800">Generando Reporte de Facturación</h3>
                    <p class="text-xs text-slate-500 mt-1">Sincronizando datos y calculando promedios por cliente...</p>
                </div>

                <!-- Animated Step Indicators -->
                <div class="space-y-2 text-left bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs">
                    <div class="flex items-center gap-2.5 text-slate-700 font-medium">
                        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-ping"></span>
                        <span>1. Consultando pedidos e historial de facturas...</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-slate-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>2. Sincronizando documentos con la API de Contifico...</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-slate-600">
                        <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                        <span>3. Calculando promedios y clasificando en rangos ($)...</span>
                    </div>
                </div>

                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-cyan-500 via-blue-500 to-emerald-500 h-full w-full animate-pulse"></div>
                </div>
            </div>
        </div>

        <!-- STATE 1: INITIAL CONFIGURATION FORM (BEFORE GENERATING) -->
        <div v-if="!generated" class="max-w-3xl mx-auto py-6">
            <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
                <!-- Header Banner -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-950 p-8 text-white">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-300">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold">Generador de Reporte de Facturación</h1>
                            <p class="text-cyan-200 text-sm mt-1">Configura el período de evaluación antes de procesar y descargar la información.</p>
                        </div>
                    </div>
                </div>

                <!-- Parameters Form -->
                <form @submit.prevent="generateReport" class="p-8 space-y-6">
                    <div class="space-y-4">
                        <label class="block text-sm font-bold text-slate-700 uppercase tracking-wider">
                            1. Selecciona la Cantidad de Meses Evaluados
                        </label>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <button v-for="mOption in monthOptions" :key="mOption.value" type="button"
                                @click="form.months = mOption.value"
                                class="p-4 rounded-2xl border text-left transition-all relative overflow-hidden flex flex-col justify-between"
                                :class="form.months === mOption.value
                                    ? 'border-cyan-500 bg-cyan-50/50 ring-2 ring-cyan-500/20 text-cyan-950 font-bold shadow-sm'
                                    : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'">
                                <span class="text-lg font-extrabold">{{ mOption.label }}</span>
                                <span class="text-xs text-slate-400 mt-1">{{ mOption.description }}</span>
                                <span v-if="form.months === mOption.value" class="absolute top-2 right-2 w-2 h-2 rounded-full bg-cyan-500"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Optional custom dates -->
                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-sm font-bold text-slate-700 uppercase tracking-wider mb-3">
                            2. O Define un Rango Personalizado de Fechas (Opcional)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <span class="text-xs text-slate-500 font-medium">Fecha Desde</span>
                                <input v-model="form.from" type="date"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 text-sm text-slate-700 bg-slate-50/50" />
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs text-slate-500 font-medium">Fecha Hasta</span>
                                <input v-model="form.to" type="date"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 text-sm text-slate-700 bg-slate-50/50" />
                            </div>
                        </div>
                    </div>

                    <!-- Optional filters -->
                    <div class="pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Filtro por Rango (Opcional)</label>
                            <select v-model="form.range"
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 text-sm text-slate-700 bg-slate-50/50">
                                <option value="">Todos los Rangos</option>
                                <option value="0-50">0 - 50</option>
                                <option value="50-100">50 - 100</option>
                                <option value="100-200">100 - 200</option>
                                <option value="200-500">200 - 500</option>
                                <option value="500-ADELANTE">500+ ADELANTE</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Buscar Cliente (Opcional)</label>
                            <input v-model="form.search" type="text" placeholder="Nombre, RUC o Cédula..."
                                class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 text-sm text-slate-700 bg-slate-50/50" />
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-6 border-t border-slate-100 flex justify-end">
                        <button type="submit"
                            class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-cyan-600 via-blue-600 to-indigo-600 hover:from-cyan-500 hover:to-indigo-500 text-white rounded-2xl font-bold shadow-xl shadow-cyan-600/30 transform hover:-translate-y-0.5 transition-all text-base flex items-center justify-center gap-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Generar Reporte de Facturación
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- STATE 2: GENERATED REPORT VIEW -->
        <div v-else-if="summary && clients">
            <!-- Top Controls Header -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Reporte de Facturación de Clientes Activos</h1>
                    <p class="text-slate-500 text-sm mt-1">
                        Período evaluado: <strong class="text-slate-700">{{ filters.from }}</strong> al <strong class="text-slate-700">{{ filters.to }}</strong> ({{ summary.months_count }} Meses)
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button @click="resetToConfig"
                        class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                        Cambiar Parámetros
                    </button>

                    <a :href="exportExcelUrl" target="_blank"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Descargar Excel Profesional (.xlsx)
                    </a>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Clientes Activos</p>
                        <p class="text-2xl font-bold text-slate-800">{{ summary.active_clients_count }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">Excluidos $0 en promedio</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Promedio Mensual General</p>
                        <p class="text-2xl font-bold text-slate-800">${{ formatMoney(summary.overall_monthly_average) }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">Total Facturado: ${{ formatMoney(summary.total_invoiced_all) }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Período Evaluado</p>
                        <p class="text-xl font-bold text-slate-800">{{ summary.months_count }} Meses</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ filters.from }} al {{ filters.to }}</p>
                    </div>
                </div>
            </div>

            <!-- Range Tabs -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 mb-6 overflow-x-auto">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Filtrar por Rango de Promedio Mensual ($)</p>
                <div class="flex items-center gap-2 min-w-max">
                    <button @click="setRange('')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="!form.range ? 'bg-slate-900 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'">
                        Todos los Rangos
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="!form.range ? 'bg-slate-700 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ summary.active_clients_count }}
                        </span>
                    </button>

                    <button @click="setRange('0-50')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="form.range === '0-50' ? 'bg-slate-700 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'">
                        0 - 50
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="form.range === '0-50' ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ summary.ranges['0-50']?.count ?? 0 }}
                        </span>
                    </button>

                    <button @click="setRange('50-100')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="form.range === '50-100' ? 'bg-amber-600 text-white shadow-md' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'">
                        50 - 100
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="form.range === '50-100' ? 'bg-amber-800 text-white' : 'bg-amber-200/70 text-amber-900'">
                            {{ summary.ranges['50-100']?.count ?? 0 }}
                        </span>
                    </button>

                    <button @click="setRange('100-200')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="form.range === '100-200' ? 'bg-blue-600 text-white shadow-md' : 'bg-blue-50 text-blue-800 hover:bg-blue-100'">
                        100 - 200
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="form.range === '100-200' ? 'bg-blue-800 text-white' : 'bg-blue-200/70 text-blue-900'">
                            {{ summary.ranges['100-200']?.count ?? 0 }}
                        </span>
                    </button>

                    <button @click="setRange('200-500')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="form.range === '200-500' ? 'bg-cyan-600 text-white shadow-md' : 'bg-cyan-50 text-cyan-800 hover:bg-cyan-100'">
                        200 - 500
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="form.range === '200-500' ? 'bg-cyan-800 text-white' : 'bg-cyan-200/70 text-cyan-900'">
                            {{ summary.ranges['200-500']?.count ?? 0 }}
                        </span>
                    </button>

                    <button @click="setRange('500-ADELANTE')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2"
                        :class="form.range === '500-ADELANTE' ? 'bg-emerald-600 text-white shadow-md' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'">
                        500+ ADELANTE
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="form.range === '500-ADELANTE' ? 'bg-emerald-800 text-white' : 'bg-emerald-200/70 text-emerald-900'">
                            {{ summary.ranges['500-ADELANTE']?.count ?? 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-4 px-6">Cliente</th>
                                <th class="py-4 px-6">Identificación</th>
                                <th class="py-4 px-6 text-right">Total Facturado ({{ summary.months_count }}M)</th>
                                <th class="py-4 px-6 text-center">Facturas</th>
                                <th class="py-4 px-6 text-right">Promedio Mensual</th>
                                <th class="py-4 px-6 text-center">Rango Asignado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="client in clients.data" :key="client.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-slate-800">{{ client.name }}</p>
                                    <p v-if="client.nombre_local" class="text-xs text-slate-400 font-medium">Local: {{ client.nombre_local }}</p>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-slate-600">
                                    {{ client.identification || 'N/A' }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-slate-800">
                                    ${{ formatMoney(client.total_invoiced) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                        {{ client.invoice_count }} fac
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-slate-900 text-base">
                                    ${{ formatMoney(client.monthly_average) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold shadow-sm inline-block"
                                        :class="rangeBadgeClass(client.range_key)">
                                        {{ client.range_label }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!clients.data || clients.data.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    No se encontraron clientes activos con facturación en este período/rango.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="clients.links && clients.links.length > 3"
                    class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500">
                        Mostrando {{ clients.from || 0 }} - {{ clients.to || 0 }} de {{ clients.total }} clientes
                    </p>
                    <div class="flex items-center gap-1">
                        <Link v-for="(link, i) in clients.links" :key="i" :href="link.url || '#'"
                            v-html="link.label"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                            :class="{
                                'bg-cyan-600 text-white font-bold': link.active,
                                'text-slate-600 hover:bg-slate-200': !link.active && link.url,
                                'text-slate-300 cursor-not-allowed': !link.url
                            }" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    generated: Boolean,
    clients: Object,
    summary: Object,
    filters: Object
});

const loading = ref(false);

const monthOptions = [
    { value: 1, label: '1 Mes', description: 'Último mes calendario' },
    { value: 3, label: '3 Meses', description: 'Último trimestre' },
    { value: 6, label: '6 Meses', description: 'Recomendado (Semestre)' },
    { value: 12, label: '12 Meses', description: 'Un año completo' },
    { value: 18, label: '18 Meses', description: 'Año y medio' },
    { value: 24, label: '24 Meses', description: 'Dos años evaluados' },
];

const form = ref({
    months: props.filters?.months || 6,
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    range: props.filters?.range || '',
    search: props.filters?.search || '',
    generate: 1,
});

const exportExcelUrl = computed(() => {
    return route('admin.reports.active-clients-billing.export', {
        ...form.value,
        generate: 1
    });
});

function generateReport() {
    loading.value = true;
    form.value.generate = 1;

    router.get(route('admin.reports.active-clients-billing'), form.value, {
        preserveState: false,
        preserveScroll: false,
        onFinish: () => {
            loading.value = false;
        }
    });
}

function setRange(rangeKey) {
    form.value.range = rangeKey;
    generateReport();
}

function resetToConfig() {
    router.get(route('admin.reports.active-clients-billing'), { generate: 0 });
}

function formatMoney(amount) {
    return Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function rangeBadgeClass(key) {
    switch (key) {
        case '0-50':
            return 'bg-slate-100 text-slate-700 border border-slate-200';
        case '50-100':
            return 'bg-amber-100 text-amber-800 border border-amber-200';
        case '100-200':
            return 'bg-blue-100 text-blue-800 border border-blue-200';
        case '200-500':
            return 'bg-cyan-100 text-cyan-800 border border-cyan-200';
        case '500-ADELANTE':
            return 'bg-emerald-500 text-white font-extrabold shadow-emerald-500/20';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}
</script>
