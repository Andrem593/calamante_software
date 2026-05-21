<template>
    <AdminLayout title="Mapa de Entregas">
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
            <div v-if="!orders.length" class="col-span-3 text-center text-slate-400 text-sm py-8">Sin entregas para hoy
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ orders: Array });

const selectedId = ref(null);

// map and markers registry: { [orderId]: { marker, infowindow } }
const markerRegistry = {};
let mapInstance = null;

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

    // One shared infowindow (closes previous on open)
    const sharedInfo = new google.maps.InfoWindow();

    props.orders.forEach(order => {
        if (!order.lat || !order.lng) return;

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

        const content = buildInfoContent(order);

        marker.addListener('click', () => {
            sharedInfo.setContent(content);
            sharedInfo.open(mapInstance, marker);
            selectedId.value = order.id;
        });

        markerRegistry[order.id] = { marker, content, sharedInfo };
    });
}

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
    entry.sharedInfo.setContent(entry.content);
    entry.sharedInfo.open(mapInstance, entry.marker);
}

function buildInfoContent(order) {
    const statusLabel = {
        pending: 'En Proceso',
        invoiced: 'Facturado',
        on_the_way: 'En Camino',
        delivered: 'Entregado',
        cancelled: 'Cancelado',
    }[order.status] ?? order.status;

    const color = statusColors[order.status] ?? '#6B7280';

    return `<div style="font-family:Inter,sans-serif;padding:6px 4px;min-width:180px">
        <p style="font-weight:700;margin:0 0 2px;font-size:13px">#${order.id} — ${order.client ?? ''}</p>
        <p style="color:#9ca3af;font-size:11px;margin:0 0 2px">${order.branch ?? ''}</p>
        <p style="color:#6b7280;font-size:11px;margin:0 0 6px">Vendedor: ${order.seller ?? '—'}</p>
        <div style="display:flex;align-items:center;justify-content:space-between">
            <span style="font-weight:700;color:#0e7490;font-size:13px">$${Number(order.total).toFixed(2)}</span>
            <span style="font-size:10px;padding:2px 8px;border-radius:9999px;background:${color}22;color:${color};font-weight:600">${statusLabel}</span>
        </div>
    </div>`;
}
</script>
