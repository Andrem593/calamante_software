<template>
    <div v-if="data.last_page > 1"
        class="px-6 py-4 border-t border-slate-50 flex items-center justify-between text-sm text-slate-500">
        <span>Mostrando {{ data.from }}–{{ data.to }} de {{ data.total }}</span>
        <div class="flex gap-1">
            <template v-for="(link, index) in data.links" :key="index">
                <span v-if="link.url === null"
                    class="px-3 py-1.5 rounded-lg text-slate-400 cursor-not-allowed select-none text-xs"
                    v-html="link.label">
                </span>
                <Link v-else
                    :href="cleanUrl(link.url)"
                    :preserve-scroll="preserveScroll"
                    :preserve-state="preserveState"
                    class="px-3 py-1.5 rounded-lg transition text-xs"
                    :class="link.active ? 'bg-cyan-500 text-white font-medium' : 'hover:bg-slate-100 text-slate-600'"
                    v-html="link.label">
                </Link>
            </template>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    data: {
        type: Object,
        required: true,
    },
    preserveScroll: {
        type: Boolean,
        default: false,
    },
    preserveState: {
        type: Boolean,
        default: false,
    },
});

const cleanUrl = (url) => {
    if (!url) return '';
    try {
        const parsed = new URL(url);
        return parsed.pathname + parsed.search;
    } catch (e) {
        return url;
    }
};
</script>
