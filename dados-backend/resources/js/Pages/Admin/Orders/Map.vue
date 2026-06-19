<template>
    <AdminLayout title="Mapa de Entregas">
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
            
            <Link :href="route('admin.orders.index')"
                class="ml-auto flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Ver Lista
            </Link>
        </div>

        <!-- Legend -->
        <div class="mb-4 flex items-center gap-3 text-sm">
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
                En Proceso</div>
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span>
                Facturado</div>
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-violet-500 inline-block"></span>
                En Camino</div>
            <div class="flex items-center gap-1.5"><span
                    class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span> Entregado</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div id="map" style="height: 550px; width: 100%;"></div>
        </div>

        <!-- Orders below map — clickable -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="order in orders" :key="order.id" @click="focusOrder(order)"
                class="bg-white rounded-xl p-4 shadow-sm border border-slate-100 flex items-start gap-3 transition cursor-pointer"
                :class="[
                    selectedId === order.id ? 'ring-2 ring-cyan-400 border-cyan-300 shadow-md' : 'hover:border-slate-300 hover:shadow',
                    !order.lat || !order.lng ? 'opacity-50' : '',
                ]" :title="!order.lat || !order.lng ? 'Sin coordenadas registradas' : `Ver en mapa: ${order.client}`">
                <div class="w-3 h-3 rounded-full mt-1.5 flex-shrink-0" :class="{
                    'bg-amber-400': order.status === 'pending',
                    'bg-blue-500': order.status === 'invoiced',
                    'bg-violet-500': order.status === 'on_the_way',
                    'bg-emerald-500': order.status === 'delivered',
                }"></div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ order.client ?? '—' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ order.branch }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Vendedor: {{ order.seller }}</p>
                    <p class="text-sm font-bold text-slate-700 mt-1">${{ Number(order.total).toFixed(2) }}</p>
                </div>
                <!-- location pin indicator -->
                <div class="flex-shrink-0 mt-0.5">
                    <svg v-if="order.lat && order.lng" class="w-4 h-4 text-cyan-400" fill="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                    <svg v-else class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M9 10h.01M15 10h.01" />
                    </svg>
                </div>
            </div>
            <div v-if="!orders.length" class="col-span-3 text-center text-slate-400 text-sm py-8">Sin entregas para el rango seleccionado
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, onUnmounted, ref, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ orders: Array, filters: Object });

const selectedId = ref(null);

// map and markers registry: { [orderId]: { marker, group, sharedInfo } }
const markerRegistry = {};
let mapInstance = null;
let sharedInfo = null;

const filters = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

watch(() => props.filters, (newFilters) => {
    Object.assign(filters, newFilters);
}, { deep: true });

function applyFilters() {
    router.get(route('admin.orders.map'), filters, { preserveState: true, replace: true });
}

const statusColors = {
    pending: '#FBBF24',
    invoiced: '#3B82F6',
    on_the_way: '#8B5CF6',
    delivered: '#10B981',
    cancelled: '#6B7280',
};

onMounted(() => {
    if (typeof google !== 'undefined') {
        initMap();
    } else {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyAke5BPxPnbx-jNK6kefDC10bvKS4GcqR4&callback=initGMap`;
        script.async = true;
        window.initGMap = initMap;
        document.head.appendChild(script);
    }

    // Global focus order callback for InfoWindow custom clicks
    window.focusSingleOrder = (orderId) => {
        const order = props.orders.find(o => o.id === orderId);
        if (order) {
            focusOrder(order);
        }
    };
});

onUnmounted(() => {
    if (window.focusSingleOrder) {
        delete window.focusSingleOrder;
    }
});

function initMap() {
    const ordersWithCoords = props.orders.filter(o => o.lat && o.lng);

    const center = ordersWithCoords.length
        ? { lat: parseFloat(ordersWithCoords[0].lat), lng: parseFloat(ordersWithCoords[0].lng) }
        : { lat: -0.1807, lng: -78.4678 }; // Quito default

    mapInstance = new google.maps.Map(document.getElementById('map'), {
        zoom: 12,
        center,
        styles: [
            { featureType: 'poi', elementType: 'all', stylers: [{ visibility: 'off' }] },
            { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#e0f2fe' }] },
        ],
    });

    drawMarkers();
}

function clearMarkers() {
    for (const id in markerRegistry) {
        if (markerRegistry[id].marker) {
            markerRegistry[id].marker.setMap(null);
        }
    }
    // Empty registry
    for (const key in markerRegistry) {
        delete markerRegistry[key];
    }
}

// Distance helper (Haversine)
function getDistanceInMeters(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // metres
    const phi1 = lat1 * Math.PI/180;
    const phi2 = lat2 * Math.PI/180;
    const deltaPhi = (lat2-lat1) * Math.PI/180;
    const deltaLambda = (lon2-lon1) * Math.PI/180;

    const a = Math.sin(deltaPhi/2) * Math.sin(deltaPhi/2) +
              Math.cos(phi1) * Math.cos(phi2) *
              Math.sin(deltaLambda/2) * Math.sin(deltaLambda/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return R * c; // in metres
}

function drawMarkers() {
    if (!mapInstance) return;

    if (!sharedInfo && typeof google !== 'undefined') {
        sharedInfo = new google.maps.InfoWindow();
    }

    const ordersWithCoords = props.orders.filter(o => o.lat && o.lng);

    if (ordersWithCoords.length) {
        const bounds = new google.maps.LatLngBounds();
        ordersWithCoords.forEach(order => {
            bounds.extend({ lat: parseFloat(order.lat), lng: parseFloat(order.lng) });
        });
        mapInstance.fitBounds(bounds);
        if (ordersWithCoords.length === 1) {
            mapInstance.setZoom(14);
        }
    }

    // Draw individual markers for each order
    ordersWithCoords.forEach(order => {
        const position = { lat: parseFloat(order.lat), lng: parseFloat(order.lng) };

        const marker = new google.maps.Marker({
            position,
            map: mapInstance,
            title: order.client,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 11,
                fillColor: statusColors[order.status] ?? '#6B7280',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2.5,
            },
        });

        // Register in registry
        markerRegistry[order.id] = {
            marker,
            order,
            sharedInfo
        };

        marker.addListener('click', () => {
            // Find all orders within 100 meters of this order
            const closeOrders = ordersWithCoords.filter(other => {
                return getDistanceInMeters(
                    parseFloat(order.lat), parseFloat(order.lng),
                    parseFloat(other.lat), parseFloat(other.lng)
                ) <= 100;
            });

            const content = buildGroupInfoContent({ orders: closeOrders }, order.id);
            sharedInfo.setContent(content);
            sharedInfo.open(mapInstance, marker);
            selectedId.value = order.id;
        });
    });
}

watch(() => props.orders, () => {
    clearMarkers();
    drawMarkers();
}, { deep: true });

function focusOrder(order) {
    if (!order.lat || !order.lng) return; // no coords, ignore
    selectedId.value = order.id;

    const entry = markerRegistry[order.id];
    if (!entry || !mapInstance) return;

    // Animate marker scale briefly
    entry.marker.setIcon({
        path: google.maps.SymbolPath.CIRCLE,
        scale: 15,
        fillColor: statusColors[order.status] ?? '#6B7280',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 3,
    });
    setTimeout(() => {
        entry.marker.setIcon({
            path: google.maps.SymbolPath.CIRCLE,
            scale: 11,
            fillColor: statusColors[order.status] ?? '#6B7280',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 2.5,
        });
    }, 600);

    // Pan and zoom to marker, then open info
    mapInstance.panTo(entry.marker.getPosition());
    mapInstance.setZoom(16);

    // Find all orders within 100 meters of this order's coordinates
    const ordersWithCoords = props.orders.filter(o => o.lat && o.lng);
    const closeOrders = ordersWithCoords.filter(other => {
        return getDistanceInMeters(
            parseFloat(order.lat), parseFloat(order.lng),
            parseFloat(other.lat), parseFloat(other.lng)
        ) <= 100;
    });

    const content = buildGroupInfoContent({ orders: closeOrders }, order.id);
    entry.sharedInfo.setContent(content);
    entry.sharedInfo.open(mapInstance, entry.marker);
}

function buildGroupInfoContent(group, activeOrderId = null) {
    let html = `<div style="font-family:Inter,sans-serif;padding:8px;max-height:280px;overflow-y:auto;min-width:280px">
        <h4 style="font-weight:800;margin:0 0 8px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;padding-bottom:6px">
            ${group.orders.length === 1 ? '1 Pedido en este sector' : `${group.orders.length} Pedidos en este sector (radio 100m)`}
        </h4>
        <div style="display:flex;flex-direction:column;gap:8px">`;

    group.orders.forEach(order => {
        const statusLabel = {
            pending: 'En Proceso',
            invoiced: 'Facturado',
            on_the_way: 'En Camino',
            delivered: 'Entregado',
            cancelled: 'Cancelado',
        }[order.status] ?? order.status;
        const color = statusColors[order.status] ?? '#6B7280';
        const isCurrent = order.id === activeOrderId;
        const borderStyle = isCurrent ? '2px solid #22d3ee' : '1px solid #e2e8f0';
        const bgStyle = isCurrent ? '#ecfeff' : '#ffffff';

        html += `
        <div style="padding:8px;border-radius:10px;background:${bgStyle};border:${borderStyle};box-shadow: 0 1px 2px rgba(0,0,0,0.02);cursor:pointer;transition: all 0.2s" onclick="window.focusSingleOrder(${order.id})">
            <p style="font-weight:700;margin:0 0 2px;font-size:12px;color:#0f172a">#${order.id} — ${order.client ?? ''}</p>
            <p style="color:#64748b;font-size:10px;margin:0 0 2px">${order.branch ?? ''}</p>
            <p style="color:#6b7280;font-size:10px;margin:0 0 6px">Vendedor: ${order.seller ?? '—'}</p>
            <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-weight:800;color:#0891b2;font-size:12px">$${Number(order.total).toFixed(2)}</span>
                <span style="font-size:9px;padding:2px 8px;border-radius:9999px;background:${color}15;color:${color};font-weight:600">${statusLabel}</span>
            </div>
        </div>`;
    });

    html += `</div></div>`;
    return html;
}
</script>
