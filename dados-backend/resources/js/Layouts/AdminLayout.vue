<template>
    <div class="min-h-screen flex bg-slate-50 font-[Inter]">
        <!-- Sidebar -->
        <aside
            class="flex flex-col w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl fixed h-full z-30 transition-all duration-300"
            :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }">
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
                <div
                    class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-lg shadow-cyan-900/40 overflow-hidden">
                    <img src="/img/logo.jpeg" alt="Logo" class="w-full h-full object-cover">
                </div>
                <div>
                    <p class="text-white font-bold text-base leading-tight tracking-wide">Dados</p>
                    <p class="text-cyan-400 text-xs font-medium">Panel Administrativo</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-4 px-3">
                <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider px-3 mb-2">Principal</p>
                <NavItem :href="route('admin.dashboard')" icon="chart-bar" label="Dashboard" />
                <NavItem :href="route('admin.orders.index')" icon="clipboard-list" label="Pedidos del Día" />
                <NavItem :href="route('admin.orders.map')" icon="map" label="Mapa en Vivo" />

                <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider px-3 mt-5 mb-2">Gestión</p>
                <NavItem :href="route('admin.clients.index')" icon="users" label="Clientes y Sucursales" />
                <NavItem :href="route('admin.special-prices.index')" icon="tag" label="Precios Especiales" />
                <NavItem :href="route('admin.products.index')" icon="cube" label="Productos" />
                <NavItem :href="route('admin.users.index')" icon="user-group" label="Usuarios" />
                <NavItem v-if="$page.props.auth?.user?.role === 'superadmin'" :href="route('admin.settings.index')"
                    icon="cog" label="Configuración" />

                <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider px-3 mt-5 mb-2">Reportes</p>
                <NavItem :href="route('admin.reports.index')" icon="trending-up" label="Resumen" />
                <NavItem :href="route('admin.reports.sales')" icon="currency-dollar" label="Ventas" />
                <NavItem :href="route('admin.reports.sellers')" icon="user-group" label="Vendedores" />
            </nav>

            <!-- User section -->
            <div class="px-4 py-4 border-t border-slate-700">
                <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-700 transition cursor-pointer">
                    <div
                        class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-white font-bold text-sm">
                        {{ $page.props.auth?.user?.name?.charAt(0) ?? 'A' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ $page.props.auth?.user?.name ?? 'Admin' }}
                        </p>
                        <p class="text-slate-400 text-xs truncate">{{ $page.props.auth?.user?.email }}</p>
                    </div>
                    <form @submit.prevent="logout">
                        <button type="submit" class="text-slate-400 hover:text-red-400 transition"
                            title="Cerrar sesión">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col ml-64">
            <!-- Top bar -->
            <header
                class="bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-800">{{ title }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-500">{{ today }}</span>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success"
                class="mx-6 mt-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $page.props.flash.error }}
            </div>

            <!-- Page content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import NavItem from '@/Components/NavItem.vue';

defineProps({ title: String });

const sidebarOpen = ref(true);
const today = computed(() => new Date().toLocaleDateString('es-EC', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));

function logout() {
    router.post(route('logout'));
}
</script>
