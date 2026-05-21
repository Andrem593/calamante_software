<template>
    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" :class="styles">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ status: String });
const map = {
    pending: { label: 'En Proceso', cls: 'bg-amber-50 text-amber-600' },
    invoiced: { label: 'Facturado', cls: 'bg-blue-50 text-blue-600' },
    on_the_way: { label: 'En Camino', cls: 'bg-violet-50 text-violet-600' },
    delivered: { label: 'Entregado', cls: 'bg-emerald-50 text-emerald-600' },
    cancelled: { label: 'Cancelado', cls: 'bg-red-50 text-red-500' },
};
const label = computed(() => map[props.status]?.label ?? props.status);
const styles = computed(() => map[props.status]?.cls ?? 'bg-slate-100 text-slate-500');
</script>
