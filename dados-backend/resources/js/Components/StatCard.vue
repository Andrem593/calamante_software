<template>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="iconBg">
                <!-- icons by color -->
                <svg v-if="icon === 'clipboard'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <svg v-if="icon === 'truck'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <svg v-if="icon === 'clock'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-if="icon === 'dollar'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1" />
                </svg>
                <svg v-if="icon === 'users'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg v-if="icon === 'cube'" class="w-5 h-5" :class="iconColor" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800">{{ value }}</p>
        <p class="text-slate-400 text-sm mt-0.5">{{ label }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ label: String, value: [String, Number], icon: String, color: String });
const iconBg = computed(() => ({
    cyan: 'bg-cyan-50', amber: 'bg-amber-50', rose: 'bg-rose-50', emerald: 'bg-emerald-50', violet: 'bg-violet-50'
}[props.color] ?? 'bg-slate-50'));
const iconColor = computed(() => ({
    cyan: 'text-cyan-500', amber: 'text-amber-500', rose: 'text-rose-500', emerald: 'text-emerald-500', violet: 'text-violet-500'
}[props.color] ?? 'text-slate-500'));
</script>
