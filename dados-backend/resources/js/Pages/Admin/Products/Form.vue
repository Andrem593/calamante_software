<template>
    <AdminLayout :title="product ? 'Editar Producto' : 'Nuevo Producto'">
        <div class="max-w-lg">
            <Link :href="route('admin.products.index')"
                class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-6 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Volver
            </Link>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <form @submit.prevent="submit" class="space-y-4">
                    <FormField label="Nombre del Producto" :error="form.errors.name">
                        <input v-model="form.name" type="text" class="input" placeholder="Hielo en Cubo 20kg" />
                    </FormField>
                    <FormField label="Descripción">
                        <textarea v-model="form.description" class="input" rows="3"
                            placeholder="Descripción opcional..."></textarea>
                    </FormField>
                    <FormField label="Precio (USD)" :error="form.errors.price">
                        <input v-model="form.price" type="number" step="0.01" min="0" class="input"
                            placeholder="0.00" />
                    </FormField>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition shadow-sm shadow-cyan-200">
                            {{ form.processing ? 'Guardando...' : (product ? 'Actualizar' : 'Crear Producto') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({ product: Object });
const form = useForm({
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
});

function submit() {
    if (props.product) form.put(route('admin.products.update', props.product.id));
    else form.post(route('admin.products.store'));
}
</script>

<style scoped>
@reference "../../../../css/app.css";

.input {
    @apply w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition;
}
</style>
