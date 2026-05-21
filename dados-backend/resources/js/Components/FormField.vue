<template>
    <div class="space-y-1">
        <label class="block text-sm font-medium text-slate-700">{{ label }}</label>
        <slot />
        <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, error: String });
</script>
