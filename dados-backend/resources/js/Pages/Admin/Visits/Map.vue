<template>
    <AdminLayout title="Recorrido de Visitas">
        <!-- Filters Card -->
        <div class="flex flex-wrap gap-4 mb-6 items-end bg-white p-5 rounded-2xl shadow-sm border border-slate-100 no-print">
            <div class="flex flex-col gap-1.5 min-w-[200px]">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Vendedor</span>
                <select v-model="filters.seller_id" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white">
                    <option value="">Seleccione un vendedor...</option>
                    <option v-for="seller in sellers" :key="seller.id" :value="seller.id">
                        {{ seller.name }}
                    </option>
                </select>
            </div>
            
            <div class="flex flex-col gap-1.5">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Desde</span>
                <input type="date" v-model="filters.from" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
            </div>
            
            <div class="flex flex-col gap-1.5">
                <span class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Hasta</span>
                <input type="date" v-model="filters.to" @change="applyFilters"
                    class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
            </div>

            <button v-if="filters.seller_id && visits.length > 0" @click="printMap"
                class="ml-auto flex items-center gap-2 px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-sm font-medium transition shadow-sm shadow-rose-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Imprimir / PDF
            </button>
            <Link :href="route('admin.visits.index')"
                class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-medium transition"
                :class="{ 'ml-auto': !filters.seller_id || visits.length === 0, 'ml-2': filters.seller_id && visits.length > 0 }">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Ver Listado
            </Link>
        </div>

        <!-- No Seller Selected Banner -->
        <div v-if="!filters.seller_id" class="bg-cyan-50 border border-cyan-200 rounded-2xl p-8 text-center text-cyan-800 mb-6">
            <svg class="w-12 h-12 mx-auto mb-3 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <h3 class="text-lg font-bold mb-1">Seleccione un Vendedor</h3>
            <p class="text-sm text-cyan-600 max-w-md mx-auto">
                Para ver el recorrido cronológico del día en el mapa, seleccione a un vendedor en el menú superior.
            </p>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Map Card (3 cols) -->
            <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative">
                <div id="visit-map" style="height: 580px; width: 100%;"></div>
                
                <!-- Resumen de Recorrido Overlay -->
                <div v-if="visits.length > 0" 
                     class="absolute top-4 left-4 z-10 bg-white/95 backdrop-blur-md p-4 rounded-2xl shadow-xl border border-slate-200/60 w-72 transition-all hover:shadow-2xl">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Resumen del Recorrido
                    </h4>
                    
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span class="text-[10px] text-slate-500 block font-medium">Total Visitas</span>
                            <span class="text-lg font-extrabold text-slate-800">{{ stats.totalVisits }}</span>
                        </div>
                        <div class="bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100">
                            <span class="text-[10px] text-emerald-600 block font-medium">Pedidos Exitosos</span>
                            <span class="text-lg font-extrabold text-emerald-700">{{ stats.correctOrders }}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-2 border-t border-slate-100 pt-3">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Efectivo:
                            </span>
                            <span class="font-bold text-slate-800">${{ formatCurrency(stats.cashSales) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                Crédito:
                            </span>
                            <span class="font-bold text-slate-800">${{ formatCurrency(stats.creditSales) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs border-t border-dashed border-slate-100 pt-2 font-semibold">
                            <span class="text-slate-700">Total Venta:</span>
                            <span class="text-cyan-600 font-extrabold">${{ formatCurrency(stats.cashSales + stats.creditSales) }}</span>
                        </div>
                    </div>
                </div>
                
                <div v-if="visits.length === 0" class="absolute inset-0 bg-slate-50/90 flex flex-col items-center justify-center text-center p-6 z-10">
                    <svg class="w-16 h-16 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    <h4 class="text-base font-bold text-slate-700 mb-1">Sin visitas registradas</h4>
                    <p class="text-sm text-slate-400 max-w-xs">
                        Este vendedor no registró visitas en la fecha seleccionada.
                    </p>
                </div>
            </div>

            <!-- Chronology/Timeline Card (1 col) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col h-[580px] overflow-hidden print-hide-chronology">
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 mb-4 flex items-center justify-between">
                    <span>Cronología</span>
                    <span class="px-2 py-0.5 text-xs font-semibold bg-cyan-50 text-cyan-600 rounded-full">
                        {{ visits.length }} parada(s)
                    </span>
                </h3>

                <!-- Timeline Scrollable -->
                <div class="flex-1 overflow-y-auto space-y-4 pr-1 relative">
                    <div v-if="visits.length === 0" class="h-full flex items-center justify-center text-center text-slate-400 text-xs py-8">
                        No hay paradas registradas
                    </div>
                    
                    <div v-else class="relative border-l-2 border-slate-100 ml-4 py-2 space-y-6">
                        <div v-for="(visit, idx) in visits" :key="visit.id" @click="focusVisit(visit, idx)"
                            class="relative pl-6 cursor-pointer group"
                            :class="selectedIdx === idx ? 'text-cyan-500' : 'text-slate-600'">
                            
                            <!-- Timeline dot -->
                            <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 border-white flex items-center justify-center text-[9px] font-bold text-white transition-all shadow-sm"
                                :class="selectedIdx === idx
                                    ? (visit.type === 'order' ? 'bg-orange-500 scale-125 ring-4 ring-orange-100' : 'bg-cyan-500 scale-125 ring-4 ring-cyan-100')
                                    : (visit.type === 'order' ? 'bg-orange-400 group-hover:bg-orange-500' : 'bg-slate-400 group-hover:bg-slate-500')">
                                {{ idx + 1 }}
                            </div>

                            <!-- Timeline Item Content -->
                            <div class="p-3 rounded-xl border transition-all text-xs bg-slate-50/50 hover:bg-slate-50"
                                :class="selectedIdx === idx
                                    ? (visit.type === 'order' ? 'border-orange-200 bg-orange-50/20 ring-1 ring-orange-100 shadow-sm' : 'border-cyan-200 bg-cyan-50/20 ring-1 ring-cyan-100 shadow-sm')
                                    : 'border-slate-100'">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-bold text-slate-800 truncate block max-w-[120px]">{{ visit.client }}</span>
                                    <span class="font-semibold text-slate-400 text-[10px]">{{ visit.time }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span v-if="visit.type === 'order'" class="px-1.5 py-0.5 text-[9px] font-bold bg-orange-50 text-orange-600 rounded border border-orange-100 uppercase tracking-wider">
                                        Pedido
                                    </span>
                                    <span v-else class="px-1.5 py-0.5 text-[9px] font-bold bg-cyan-50 text-cyan-600 rounded border border-cyan-100 uppercase tracking-wider">
                                        Visita
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-500 italic mt-1.5 line-clamp-2" :title="visit.comment">
                                    {{ visit.comment || 'Sin comentario' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, onUnmounted, ref, reactive, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    visits: Array,
    sellers: Array,
    google_maps_api_key: String,
    filters: Object
});

const stats = computed(() => {
    const list = props.visits || [];
    let totalVisits = list.length;
    let correctOrders = 0;
    let cashSales = 0;
    let creditSales = 0;

    list.forEach(v => {
        if (v.type === 'order') {
            if (v.status !== 'cancelled') {
                correctOrders++;
                const method = (v.payment_method || '').toLowerCase();
                const isCredit = method.includes('cred') || method.includes('créd');
                if (isCredit) {
                    creditSales += parseFloat(v.total || 0);
                } else {
                    cashSales += parseFloat(v.total || 0);
                }
            }
        }
    });

    return {
        totalVisits,
        correctOrders,
        cashSales,
        creditSales
    };
});

function formatCurrency(value) {
    return parseFloat(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

const selectedIdx = ref(null);
const markerRegistry = [];
const polylineRegistry = [];
let mapInstance = null;
let sharedInfo = null;

const filters = reactive({
    seller_id: props.filters?.seller_id ?? '',
    from: props.filters?.from ?? new Date().toISOString().split('T')[0],
    to: props.filters?.to ?? new Date().toISOString().split('T')[0],
});

watch(() => props.filters, (newFilters) => {
    Object.assign(filters, newFilters);
}, { deep: true });

function applyFilters() {
    router.get(route('admin.visits.map'), filters, {
        preserveState: true,
        replace: true
    });
}

onMounted(() => {
    if (filters.seller_id) {
        if (typeof google !== 'undefined') {
            initMap();
        } else {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${props.google_maps_api_key || ''}&callback=initGMap`;
            script.async = true;
            window.initGMap = initMap;
            document.head.appendChild(script);
        }
    }

    // Global callback for InfoWindow clicks or links
    window.focusSingleVisitIdx = (idx) => {
        if (props.visits[idx]) {
            focusVisit(props.visits[idx], idx);
        }
    };
});

onUnmounted(() => {
    if (window.focusSingleVisitIdx) {
        delete window.focusSingleVisitIdx;
    }
});

function initMap() {
    const visitsWithCoords = props.visits.filter(v => v.lat && v.lng);

    const center = visitsWithCoords.length
        ? { lat: parseFloat(visitsWithCoords[0].lat), lng: parseFloat(visitsWithCoords[0].lng) }
        : { lat: -0.1807, lng: -78.4678 }; // Default to Quito

    mapInstance = new google.maps.Map(document.getElementById('visit-map'), {
        zoom: 13,
        center,
        styles: [
            { featureType: 'poi', elementType: 'all', stylers: [{ visibility: 'off' }] },
            { elementType: 'geometry', stylers: [{ color: '#f8fafc' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#f0f9ff' }] },
        ],
    });

    drawRoute();
}

function clearRoute() {
    // Clear markers
    markerRegistry.forEach(m => m.setMap(null));
    markerRegistry.length = 0;

    // Clear polylines
    polylineRegistry.forEach(p => p.setMap(null));
    polylineRegistry.length = 0;
}

function drawSegmentPolyline(pathCoordinates, color) {
    const polyline = new google.maps.Polyline({
        path: pathCoordinates,
        geodesic: true,
        strokeColor: color,
        strokeOpacity: 0.85,
        strokeWeight: 4.5,
        map: mapInstance,
        icons: [{
            icon: {
                path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                strokeColor: color,
                fillColor: color,
                fillOpacity: 1,
                scale: 3
            },
            offset: '50%',
            repeat: '120px'
        }]
    });
    polylineRegistry.push(polyline);
}

function drawSegmentStraightLine(origin, destination, color) {
    drawSegmentPolyline([origin, destination], color);
}

function trySegmentOSRM(origin, destination, color) {
    const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${origin.lng},${origin.lat};${destination.lng},${destination.lat}?overview=full&geometries=geojson`;

    fetch(osrmUrl)
        .then(response => response.json())
        .then(data => {
            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                const routeCoords = data.routes[0].geometry.coordinates.map(coord => {
                    return { lat: coord[1], lng: coord[0] };
                });
                drawSegmentPolyline(routeCoords, color);
            } else {
                drawSegmentStraightLine(origin, destination, color);
            }
        })
        .catch(err => {
            console.warn("OSRM segment routing failed:", err);
            drawSegmentStraightLine(origin, destination, color);
        });
}

function drawSegmentRoute(origin, destination, color, index) {
    if (typeof google !== 'undefined' && google.maps.DirectionsService) {
        const directionsService = new google.maps.DirectionsService();
        const request = {
            origin: origin,
            destination: destination,
            travelMode: google.maps.TravelMode.DRIVING,
        };

        directionsService.route(request, (result, status) => {
            if (status === google.maps.DirectionsStatus.OK) {
                const routePath = result.routes[0].overview_path;
                drawSegmentPolyline(routePath, color);
            } else {
                console.warn(`Google Directions segment ${index} failed: ${status}. Trying OSRM fallback...`);
                trySegmentOSRM(origin, destination, color);
            }
        });
    } else {
        trySegmentOSRM(origin, destination, color);
    }
}

function drawRoute() {
    if (!mapInstance) return;
    clearRoute();

    if (!sharedInfo && typeof google !== 'undefined') {
        sharedInfo = new google.maps.InfoWindow();
    }

    const visitsWithCoords = props.visits.filter(v => v.lat && v.lng);
    if (!visitsWithCoords.length) return;

    const pathCoordinates = [];
    const bounds = new google.maps.LatLngBounds();

    visitsWithCoords.forEach((visit, index) => {
        const position = { lat: parseFloat(visit.lat), lng: parseFloat(visit.lng) };
        pathCoordinates.push(position);
        bounds.extend(position);

        // Marker for each visit with a sequence number inside (SVG)
        const sequenceNumber = index + 1;
        const color = visit.type === 'order' ? '#f97316' : '#06b6d4'; // Orange for order, Cyan for visit
        
        // Define Custom Marker SVG containing sequence number
        const marker = new google.maps.Marker({
            position,
            map: mapInstance,
            title: visit.client,
            label: {
                text: String(sequenceNumber),
                color: '#ffffff',
                fontSize: '11px',
                fontWeight: 'bold'
            },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 12,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
            }
        });

        marker.addListener('click', () => {
            const content = buildInfoWindowContent(visit, index);
            sharedInfo.setContent(content);
            sharedInfo.open(mapInstance, marker);
            selectedIdx.value = index;
        });

        markerRegistry.push(marker);
    });

    // Segment colors for alternating path: Cyan, Indigo, Magenta
    const segmentColors = ['#0891b2', '#4f46e5', '#db2777'];

    // Draw segment routes between consecutive visits
    for (let i = 0; i < pathCoordinates.length - 1; i++) {
        const origin = pathCoordinates[i];
        const destination = pathCoordinates[i + 1];
        const color = segmentColors[i % segmentColors.length];
        drawSegmentRoute(origin, destination, color, i);
    }

    mapInstance.fitBounds(bounds);
    if (visitsWithCoords.length === 1) {
        mapInstance.setZoom(15);
    }
}

// Watch data updates to redraw
watch(() => props.visits, () => {
    if (filters.seller_id) {
        if (!mapInstance) {
            initMap();
        } else {
            drawRoute();
        }
    }
}, { deep: true });

function focusVisit(visit, index) {
    if (!visit.lat || !visit.lng || !mapInstance) return;
    selectedIdx.value = index;

    const marker = markerRegistry[index];
    if (!marker) return;

    // Pan to coordinate
    mapInstance.panTo(marker.getPosition());
    mapInstance.setZoom(16);

    // Open InfoWindow
    const content = buildInfoWindowContent(visit, index);
    sharedInfo.setContent(content);
    sharedInfo.open(mapInstance, marker);
}

function buildInfoWindowContent(visit, index) {
    const isOrder = visit.type === 'order';
    const typeLabel = isOrder ? 'Pedido 🛒' : 'Visita 📌';
    const typeColor = isOrder ? '#f97316' : '#06b6d4';
    return `
    <div style="font-family:Inter,sans-serif;padding:6px;max-width:240px">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
            <span style="font-size:9px;font-weight:800;color:${typeColor};background:${isOrder ? '#fff7ed' : '#ecfeff'};border:1px solid ${isOrder ? '#ffedd5' : '#cffafe'};padding:2px 6px;border-radius:4px;text-transform:uppercase">
                ${typeLabel}
            </span>
        </div>
        <h4 style="font-weight:800;margin:0 0 4px;font-size:12px;color:#0f172a">
            Parada #${index + 1} — ${visit.client}
        </h4>
        <p style="color:#64748b;font-size:10px;margin:0 0 6px">Fecha y Hora: <strong>${visit.time}</strong></p>
        <div style="background:#f8fafc;border-radius:6px;padding:6px;border:1px solid #f1f5f9;margin-bottom:6px">
            <p style="color:#334155;font-size:10px;margin:0;line-height:1.4">
                <strong>Detalles:</strong><br/>
                ${visit.comment || '<i>Sin detalles registrados</i>'}
            </p>
        </div>
        <div style="background:#f8fafc;border-radius:6px;padding:6px;border:1px solid #f1f5f9">
            <p style="color:#334155;font-size:10px;margin:0;line-height:1.4">
                <strong>Equipos del Cliente:</strong><br/>
                ${visit.equipments || 'Ninguno'}
            </p>
        </div>
    </div>`;
}

function printMap() {
    window.print();
}
</script>

<style>
@media print {
    /* Ocultar elementos de navegación y de control */
    aside,
    header,
    .no-print,
    .flex.items-center.justify-between.mb-6,
    a,
    button {
        display: none !important;
    }

    /* Quitar márgenes de maquetación lateral */
    .ml-64 {
        margin-left: 0 !important;
        padding-left: 0 !important;
    }

    body, html, .min-h-screen, #app {
        background-color: white !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }

    /* Convertir el grid a vertical */
    .grid {
        display: block !important;
    }

    /* Contenedor del Mapa */
    .lg\:col-span-3 {
        width: 100% !important;
        page-break-inside: avoid;
        margin-bottom: 20px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        position: relative !important;
        box-shadow: none !important;
    }

    #visit-map {
        height: 480px !important;
        width: 100% !important;
    }

    /* Mantener flotando el Resumen del Recorrido */
    .lg\:col-span-3 .absolute {
        position: absolute !important;
        top: 15px !important;
        left: 15px !important;
        background-color: rgba(255, 255, 255, 0.95) !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 16px !important;
        box-shadow: none !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Ocultar la columna de la Cronología */
    .print-hide-chronology {
        display: none !important;
    }

    /* Forzar fondo blanco en listados */
    .bg-white {
        background-color: white !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* Ocultar los controles de Zoom/Satelite de Google */
    .gm-style-cc, .gmnoprint, .gm-fullscreen-control, .gm-svpc {
        display: none !important;
    }
}
</style>


