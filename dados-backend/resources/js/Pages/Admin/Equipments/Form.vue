<template>
    <AdminLayout :title="equipment ? 'Editar Equipo' : 'Nuevo Equipo'">
        <div class="max-w-lg">
            <Link :href="route('admin.equipments.index')"
                class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-6 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Volver
            </Link>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <form @submit.prevent="submit" class="space-y-4">
                    <FormField label="Modelo del Equipo" :error="form.errors.model">
                        <input v-model="form.model" type="text" class="input" placeholder="C30" required />
                    </FormField>
                    <FormField label="Capacidad" :error="form.errors.capacity">
                        <input v-model="form.capacity" type="text" class="input" placeholder="130 FUNDAS" required />
                    </FormField>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition shadow-sm shadow-cyan-200">
                            {{ form.processing ? 'Guardando...' : (equipment ? 'Actualizar' : 'Crear Equipo') }}
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

const props = defineProps({ equipment: Object });
const form = useForm({
    model: props.equipment?.model ?? '',
    capacity: props.equipment?.capacity ?? '',
});

function submit() {
    if (props.equipment) form.put(route('admin.equipments.update', props.equipment.id));
    else form.post(route('admin.equipments.store'));
}
</script>

<style scoped>
@reference "../../../../css/app.css";

.input {
    @apply w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition;
}
</style>
