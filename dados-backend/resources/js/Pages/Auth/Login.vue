<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-cyan-900 p-4">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 shadow-2xl">
                <!-- Logo -->
                <div class="text-center mb-8">
                <div
                    class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-white flex items-center justify-center shadow-lg shadow-cyan-500/30 overflow-hidden text-center">
                    <img src="/img/logo.jpeg" alt="Logo" class="w-full h-full object-cover">
                </div>
                    <h1 class="text-white text-2xl font-bold">Panel Administrativo</h1>
                    <p class="text-slate-400 text-sm mt-1">Ingresa con tus credenciales</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Email</label>
                        <input v-model="form.email" type="email" placeholder="admin@ejemplo.com"
                            class="w-full bg-white/10 border border-white/20 text-white placeholder-slate-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition" />
                        <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1.5">Contraseña</label>
                        <input v-model="form.password" type="password" placeholder="••••••••"
                            class="w-full bg-white/10 border border-white/20 text-white placeholder-slate-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.remember" type="checkbox"
                            class="rounded border-white/20 bg-white/10 text-cyan-500">
                        <span class="text-slate-400 text-sm">Recordarme</span>
                    </label>

                    <button type="submit" :disabled="form.processing"
                        class="w-full bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-400 hover:to-cyan-500 text-white font-semibold rounded-xl py-3 text-sm transition-all shadow-lg shadow-cyan-500/30 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span v-if="form.processing">Ingresando...</span>
                        <span v-else>Ingresar al Panel</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login.post'));
}
</script>
