<template>
    <AdminLayout title="Geolocalización Sucursales">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Geolocalización de Clientes y Sucursales</h1>
                <p class="text-slate-500 text-sm mt-1">
                    Ubicación obtenida de la última venta o visita realizada, con resolución de Sector y Parroquia usando la API de Google.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a :href="exportUrl" target="_blank"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Descargar Excel Profesional (.xlsx)
                </a>
            </div>
        </div>

        <!-- Metrics Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-4 0v-4m0 0h4m-4 0l4-4" />
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Total Sucursales</p>
                    <p class="text-2xl font-bold text-slate-800">{{ summary.total_branches }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Con Geolocalización</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ summary.geolocated_count }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Sin Coordenadas</p>
                    <p class="text-2xl font-bold text-amber-600">{{ summary.missing_geo_count }}</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.5a2.5 2.5 0 002.5-2.5V8.5a.5.5 0 01.5-.5h.5a2.5 2.5 0 002.5-2.5V4.05" />
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium uppercase tracking-wider">Sectores Detectados</p>
                    <p class="text-2xl font-bold text-cyan-600">{{ summary.sectors_count }}</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 mb-6">
            <form @submit.prevent="generateReport" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div class="space-y-1 sm:col-span-2">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Buscar por Cliente, Sucursal, Ruta o Sector</label>
                    <div class="relative">
                        <input v-model="searchForm.search" type="text" placeholder="Ej: Supermercado, Ruta 1, Samborondón..."
                            class="w-full pl-9 pr-3.5 py-2 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/10 text-sm" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado Geolocalización</label>
                    <select v-model="searchForm.has_geo"
                        class="w-full px-3.5 py-2 rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/10 text-sm">
                        <option value="">Todas las sucursales</option>
                        <option value="yes">Con Coordenadas (Geolocalizadas)</option>
                        <option value="no">Sin Coordenadas</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-cyan-600/15 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Generar Reporte
                    </button>
                    <button type="button" @click="resetFilters"
                        class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium text-sm rounded-xl transition-all">
                        Limpiar
                    </button>
                </div>
            </form>
        </div>

        <!-- Initial Un-generated State -->
        <div v-if="!is_generated" class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm mb-6">
            <div class="w-16 h-16 rounded-3xl bg-cyan-50 border border-cyan-100 flex items-center justify-center text-cyan-600 mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Reporte de Geolocalizaciones</h3>
            <p class="text-sm text-slate-500 max-w-md mx-auto mb-6">
                Ingresa un criterio de búsqueda o haz clic en el botón <strong>"Generar Reporte"</strong> para procesar y cargar las ubicaciones más frecuentes de las sucursales.
            </p>
            <button @click="generateReport"
                class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-cyan-600/20 transition-all inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Generar Reporte Ahora
            </button>
        </div>

        <!-- Table (When generated) -->
        <div v-else class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                            <th class="py-4 px-6">Cliente</th>
                            <th class="py-4 px-6">Sucursal</th>
                            <th class="py-4 px-6">Ruta</th>
                            <th class="py-4 px-6">Origen Ubicación</th>
                            <th class="py-4 px-6">Sector / Barrio (Google)</th>
                            <th class="py-4 px-6">Parroquia / Cantón</th>
                            <th class="py-4 px-6 text-center">Acciones / Mapa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="branch in branches.data" :key="branch.id" class="hover:bg-slate-50/70 transition-colors">
                            <!-- Cliente -->
                            <td class="py-4 px-6">
                                <p class="font-semibold text-slate-800">{{ branch.client_name }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ branch.client_identification || 'Sin RUC/Cédula' }}</p>
                            </td>

                            <!-- Sucursal -->
                            <td class="py-4 px-6">
                                <p class="font-medium text-slate-800">
                                    <span v-if="branch.code" class="text-xs font-mono text-cyan-600 bg-cyan-50 px-1.5 py-0.5 rounded mr-1">
                                        {{ branch.code }}
                                    </span>
                                    {{ branch.branch_name }}
                                </p>
                                <p class="text-xs text-slate-500 truncate max-w-xs mt-0.5" :title="branch.address">
                                    {{ branch.address || 'Sin dirección registrada' }}
                                </p>
                            </td>

                            <!-- Ruta -->
                            <td class="py-4 px-6">
                                <span v-if="branch.route" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ branch.route }}
                                </span>
                                <span v-else class="text-xs text-slate-400 italic">N/A</span>
                            </td>

                            <!-- Origen Geolocalización -->
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1.5"
                                    :class="geoSourceBadgeClass(branch.geo_source)">
                                    <span class="w-1.5 h-1.5 rounded-full"
                                        :class="branch.has_geo ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    {{ branch.geo_source }}
                                </span>
                                <p v-if="branch.last_interaction_date" class="text-xs text-slate-400 mt-1">
                                    {{ branch.last_interaction_date }}
                                </p>
                            </td>

                            <!-- Sector / Barrio -->
                            <td class="py-4 px-6">
                                <span v-if="branch.sector !== 'Sin especificar' && branch.sector !== 'Sin coordenadas'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-cyan-50 text-cyan-800 border border-cyan-100">
                                    {{ branch.sector }}
                                </span>
                                <span v-else class="text-xs text-slate-400 italic">
                                    {{ branch.sector }}
                                </span>
                            </td>

                            <!-- Parroquia / Cantón -->
                            <td class="py-4 px-6">
                                <span v-if="branch.parroquia !== 'Sin especificar' && branch.parroquia !== 'Sin coordenadas'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-violet-50 text-violet-800 border border-violet-100">
                                    {{ branch.parroquia }}
                                </span>
                                <span v-else class="text-xs text-slate-400 italic">
                                    {{ branch.parroquia }}
                                </span>
                            </td>

                            <!-- Coordenadas & Link Mapa -->
                            <td class="py-4 px-6 text-center">
                                <div v-if="branch.has_geo && branch.maps_url">
                                    <p class="text-xs font-mono text-slate-500 mb-1">
                                        {{ Number(branch.latitude).toFixed(5) }}, {{ Number(branch.longitude).toFixed(5) }}
                                    </p>
                                    <a :href="branch.maps_url" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-cyan-600 hover:text-cyan-800 hover:underline">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        Ver en Google Maps
                                    </a>
                                </div>
                                <span v-else class="text-xs text-slate-400 italic">
                                    Sin coordenadas
                                </span>
                            </td>
                        </tr>

                        <tr v-if="!branches.data || branches.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No se encontraron sucursales para este filtro de geolocalización.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="branches.links && branches.links.length > 3"
                class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    Mostrando {{ branches.from || 0 }} - {{ branches.to || 0 }} de {{ branches.total }} sucursales
                </p>
                <div class="flex items-center gap-1">
                    <Link v-for="(link, i) in branches.links" :key="i" :href="link.url || '#'"
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
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    branches: Object,
    summary: Object,
    filters: Object,
    is_generated: {
        type: Boolean,
        default: false,
    }
});

const searchForm = ref({
    has_geo: props.filters.has_geo || '',
    search: props.filters.search || '',
    generate: props.filters.generate || '',
});

const exportUrl = computed(() => {
    return route('admin.reports.branches-geolocation.export', { ...searchForm.value, generate: '1' });
});

function generateReport() {
    searchForm.value.generate = '1';
    applyFilters();
}

function applyFilters() {
    router.get(route('admin.reports.branches-geolocation'), searchForm.value, {
        preserveState: true,
        preserveScroll: true
    });
}

function resetFilters() {
    searchForm.value = {
        has_geo: '',
        search: '',
        generate: '',
    };
    applyFilters();
}

function geoSourceBadgeClass(source) {
    if (source.includes('Venta')) {
        return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    } else if (source.includes('Visita')) {
        return 'bg-blue-50 text-blue-700 border border-blue-200';
    } else if (source.includes('Sucursal')) {
        return 'bg-violet-50 text-violet-700 border border-violet-200';
    }
    return 'bg-slate-100 text-slate-600';
}
</script>
