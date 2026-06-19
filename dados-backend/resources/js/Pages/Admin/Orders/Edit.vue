<template>
    <AdminLayout title="Editar Pedido">
        <div class="max-w-4xl mx-auto">
            <!-- Back Link -->
            <div class="mb-6">
                <Link :href="route('admin.orders.show', order.id)" class="flex items-center gap-2 text-slate-500 hover:text-slate-800 transition text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver al pedido
                </Link>
            </div>

            <!-- Form Card -->
            <form @submit.prevent="submit" class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
                <!-- Banner -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-8 py-8 text-white flex justify-between items-center">
                    <div>
                        <p class="text-cyan-400 font-bold uppercase tracking-widest text-xs mb-1">Panel de Modificación</p>
                        <h2 class="text-2xl font-extrabold">Editar Pedido #{{ order.id }}</h2>
                        <p class="text-slate-400 text-sm mt-1">Cliente: {{ order.client?.name }} ({{ order.branch?.name ?? 'Principal' }})</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            Prefactura
                        </span>
                    </div>
                </div>

                <div class="p-8">
                    <!-- Basic Info Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Fecha de Entrega</label>
                            <input type="date" v-model="form.delivery_date"
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Observaciones / Notas</label>
                            <textarea v-model="form.notes" rows="1"
                                placeholder="Notas adicionales sobre la entrega..."
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white"></textarea>
                        </div>
                    </div>

                    <!-- Add Product Section -->
                    <div class="mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-100 relative">
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Agregar Producto</h3>
                        <div class="relative">
                            <input v-model="searchQuery" @focus="showDropdown = true" @input="filterProducts"
                                placeholder="Buscar por nombre o código de barras..."
                                class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-400 bg-white" />
                            <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>

                            <!-- Dropdown List -->
                            <div v-if="showDropdown && filteredProducts.length"
                                class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-60 overflow-y-auto z-50 divide-y divide-slate-50">
                                <button v-for="product in filteredProducts" :key="product.id" type="button"
                                    @click="addProduct(product)"
                                    class="w-full text-left px-4 py-3 hover:bg-cyan-50/50 flex justify-between items-center transition text-sm">
                                    <div>
                                        <p class="font-medium text-slate-800">{{ product.name }}</p>
                                        <p class="text-xs text-slate-400">PVP: ${{ Number(product.price).toFixed(2) }} | IVA: {{ product.tax_percentage }}%</p>
                                    </div>
                                    <span class="text-xs text-cyan-600 font-semibold bg-cyan-50 px-2 py-1 rounded">Agregar</span>
                                </button>
                            </div>
                            <div v-else-if="showDropdown && searchQuery"
                                class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-center text-slate-400 text-xs z-50">
                                No se encontraron productos
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="mb-8">
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4">Productos en el Pedido</h3>
                        <div class="border border-slate-100 rounded-2xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600 font-semibold">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Producto</th>
                                        <th class="px-6 py-3 text-center w-32">Cant.</th>
                                        <th class="px-6 py-3 text-right">Precio Unit.</th>
                                        <th class="px-6 py-3 text-center w-28">Descuento (%)</th>
                                        <th class="px-6 py-3 text-right">Subtotal</th>
                                        <th class="px-6 py-3 text-center w-16"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50/20 transition">
                                        <td class="px-6 py-4">
                                            <p class="font-medium text-slate-800">{{ getProductName(item.product_id) }}</p>
                                            <p class="text-xs text-slate-400">IVA: {{ getProductIva(item.product_id) }}%</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-1.5 justify-center">
                                                <button type="button" @click="decreaseQty(index)"
                                                    class="w-7 h-7 flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition font-bold text-sm">
                                                    -
                                                </button>
                                                <input type="number" v-model.number="item.quantity" min="1"
                                                    class="w-14 text-center border border-slate-200 rounded-lg py-1 focus:outline-none focus:ring-1 focus:ring-cyan-400 text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                                <button type="button" @click="increaseQty(index)"
                                                    class="w-7 h-7 flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-lg transition font-bold text-sm">
                                                    +
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right text-slate-600 font-medium">
                                            ${{ Number(item.price).toFixed(2) }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <input type="number" v-model.number="item.discount_percentage" min="0" max="100" step="0.5"
                                                class="w-full text-center border border-slate-200 rounded-lg py-1 focus:outline-none focus:ring-1 focus:ring-cyan-400 text-sm" />
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-slate-900">
                                            ${{ getItemSubtotal(item).toFixed(2) }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button" @click="removeItem(index)"
                                                class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                title="Eliminar">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!form.items.length">
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">No hay productos en este pedido. Agregue uno arriba.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="flex justify-end mb-8">
                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 w-80 space-y-3">
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Subtotal 0%:</span>
                                <span class="font-medium">${{ totals.subtotal0.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Subtotal IVA:</span>
                                <span class="font-medium">${{ totals.subtotalIva.toFixed(2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>IVA:</span>
                                <span class="font-medium">${{ totals.iva.toFixed(2) }}</span>
                            </div>
                            <div class="border-t border-slate-200 pt-3 flex justify-between text-base font-black text-slate-900">
                                <span>Total General:</span>
                                <span>${{ totals.total.toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                        <Link :href="route('admin.orders.show', order.id)"
                            class="px-5 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">
                            Cancelar
                        </Link>
                        <button type="submit" :disabled="submitting || !form.items.length"
                            class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-blue-200 disabled:opacity-50">
                            <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ submitting ? 'Guardando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    order: Object,
    products: Array
});

// Form state
const form = reactive({
    delivery_date: props.order.delivery_date ? props.order.delivery_date.split('T')[0] : '',
    notes: props.order.notes ?? '',
    items: props.order.items.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        price: Number(item.price),
        discount_percentage: Number(item.discount_percentage ?? 0)
    }))
});

const submitting = ref(false);

// Product search dropdown
const searchQuery = ref('');
const showDropdown = ref(false);
const filteredProducts = ref([]);

function filterProducts() {
    if (!searchQuery.value) {
        filteredProducts.value = [];
        return;
    }
    const query = searchQuery.value.toLowerCase();
    filteredProducts.value = props.products.filter(p => 
        p.name.toLowerCase().includes(query)
    ).slice(0, 10);
}

function addProduct(product) {
    // Check if item already exists
    const existingIndex = form.items.findIndex(i => i.product_id === product.id);
    if (existingIndex !== -1) {
        form.items[existingIndex].quantity += 1;
    } else {
        form.items.push({
            product_id: product.id,
            quantity: 1,
            price: Number(product.price),
            discount_percentage: 0
        });
    }
    
    // Clear search
    searchQuery.value = '';
    showDropdown.value = false;
    filteredProducts.value = [];
}

// Helpers for product info
function getProductName(productId) {
    const p = props.products.find(prod => prod.id === productId);
    return p ? p.name : 'Producto Desconocido';
}

function getProductIva(productId) {
    const p = props.products.find(prod => prod.id === productId);
    return p ? Number(p.tax_percentage ?? 0) : 0;
}

// Table row computations
function getItemSubtotal(item) {
    const discount = Number(item.discount_percentage ?? 0);
    return item.price * item.quantity * (1 - discount / 100);
}

// Quantity adjusters
function increaseQty(index) {
    form.items[index].quantity += 1;
}

function decreaseQty(index) {
    if (form.items[index].quantity > 1) {
        form.items[index].quantity -= 1;
    }
}

function removeItem(index) {
    form.items.splice(index, 1);
}

// Computed totals
const totals = computed(() => {
    let sub0 = 0;
    let subIva = 0;
    let totalIva = 0;
    
    form.items.forEach(item => {
        const prod = props.products.find(p => p.id === item.product_id);
        const taxPercentage = prod ? Number(prod.tax_percentage ?? 0) : 0;
        const discount = Number(item.discount_percentage ?? 0);
        const subtotal = Number(item.price) * Number(item.quantity) * (1 - discount / 100);
        
        if (taxPercentage > 0) {
            subIva += subtotal;
            totalIva += subtotal * (taxPercentage / 100);
        } else {
            sub0 += subtotal;
        }
    });
    
    return {
        subtotal0: sub0,
        subtotalIva: subIva,
        iva: totalIva,
        total: sub0 + subIva + totalIva
    };
});

// Submit form
function submit() {
    if (submitting.value || !form.items.length) return;
    submitting.value = true;
    router.put(route('admin.orders.update', props.order.id), form, {
        onFinish: () => {
            submitting.value = false;
        }
    });
}

// Close search dropdown on click outside
function clickOutside(e) {
    if (!e.target.closest('.relative')) {
        showDropdown.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', clickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', clickOutside);
});
</script>

<style scoped>
/* Remove spinner arrows from quantity inputs */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type=number] {
  -moz-appearance: textfield;
}
</style>
