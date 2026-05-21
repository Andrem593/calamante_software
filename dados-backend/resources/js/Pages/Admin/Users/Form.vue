<template>
    <AdminLayout :title="user ? 'Editar Usuario' : 'Nuevo Usuario'">
        <div class="max-w-lg">
            <Link :href="route('admin.users.index')"
                class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-6 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Volver
            </Link>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                <h2 class="font-semibold text-slate-800 mb-5">{{ user ? 'Editar Usuario' : 'Nuevo Usuario' }}</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <FormField label="Nombre completo" :error="form.errors.name">
                        <input v-model="form.name" type="text"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition"
                            placeholder="Juan Pérez" />
                    </FormField>

                    <FormField label="Email" :error="form.errors.email">
                        <input v-model="form.email" type="email"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition"
                            placeholder="juan@empresa.com" />
                    </FormField>

                    <!-- Rol con descripción visual -->
                    <FormField label="Rol" :error="form.errors.role">
                        <div class="grid gap-2 mt-1">
                            <label v-for="(label, value) in roles" :key="value"
                                class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition" :class="form.role === value
                                    ? 'border-cyan-400 bg-cyan-50 ring-1 ring-cyan-300'
                                    : 'border-slate-200 hover:border-slate-300'">
                                <input type="radio" :value="value" v-model="form.role" class="accent-cyan-500" />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-800">{{ label }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                            :class="roleBadge[value]">{{ value }}</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ roleDesc[value] }}</p>
                                </div>
                            </label>
                        </div>
                    </FormField>

                    <FormField :label="user ? 'Nueva contraseña (dejar vacío para no cambiar)' : 'Contraseña'"
                        :error="form.errors.password">
                        <input v-model="form.password" type="password"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition"
                            placeholder="••••••••" />
                    </FormField>

                    <FormField v-if="!user || form.password" label="Confirmar contraseña">
                        <input v-model="form.password_confirmation" type="password"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition"
                            placeholder="••••••••" />
                    </FormField>

                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition shadow-sm shadow-cyan-200 disabled:opacity-60">
                            {{ form.processing ? 'Guardando...' : (user ? 'Actualizar' : 'Crear Usuario') }}
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

const props = defineProps({
    user: Object,
    roles: Object, // { superadmin: 'Super Administrador', admin: 'Administrador', ... }
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'seller',
    password: '',
    password_confirmation: '',
});

const roleBadge = {
    superadmin: 'bg-violet-100 text-violet-700',
    admin: 'bg-blue-100 text-blue-700',
    supervisor: 'bg-amber-100 text-amber-700',
    seller: 'bg-cyan-100 text-cyan-700',
};

const roleDesc = {
    superadmin: 'Acceso total al sistema, puede gestionar todos los módulos y usuarios.',
    admin: 'Acceso al panel administrativo completo, puede crear usuarios y productos.',
    supervisor: 'Puede supervisar pedidos y reportes, pero no crea usuarios.',
    seller: 'Solo acceso a la app móvil para tomar pedidos. Sin acceso al panel web.',
};

function submit() {
    if (props.user) form.put(route('admin.users.update', props.user.id));
    else form.post(route('admin.users.store'));
}
</script>
