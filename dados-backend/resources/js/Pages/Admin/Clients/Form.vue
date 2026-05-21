<template>
    <AdminLayout :title="client ? 'Editar Cliente' : 'Nuevo Cliente'">
        <div class="w-full">
            <!-- Back -->
            <Link :href="route('admin.clients.index')"
                class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-6 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a Clientes
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Client form -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                    <h2 class="font-semibold text-slate-800 text-base mb-5">{{ client ? 'Información del Cliente' :
                        'Datos del Cliente' }}</h2>
                    <form @submit.prevent="submit" class="space-y-4">
                        <FormField label="Nombre / Razón Social" :error="form.errors.name">
                            <input v-model="form.name" type="text" class="input" placeholder="Farmacias Xyz S.A." />
                        </FormField>
                        <FormField label="Nombre Local (Comercial)" :error="form.errors.nombre_local">
                            <input v-model="form.nombre_local" type="text" class="input" placeholder="Sucursal 1 / Local Centro" />
                        </FormField>
                        <div class="grid grid-cols-3 gap-4">
                            <FormField label="Tipo Doc." :error="form.errors.identification_type">
                                <select v-model="form.identification_type" class="input">
                                    <option value="RUC">RUC</option>
                                    <option value="Cédula">Cédula</option>
                                    <option value="Pasaporte">Pasaporte</option>
                                </select>
                            </FormField>
                            <FormField label="Identificación" :error="form.errors.identification" class="col-span-2">
                                <input v-model="form.identification" type="text" class="input"
                                    placeholder="1790000000001" />
                            </FormField>
                        </div>
                        <FormField label="RUC Alternativo (VAT)" :error="form.errors.vat_number">
                            <input v-model="form.vat_number" type="text" class="input" placeholder="1790000000001" />
                        </FormField>
                        <FormField label="Email" :error="form.errors.email">
                            <input v-model="form.email" type="email" class="input" placeholder="contacto@empresa.com" />
                        </FormField>
                        <FormField label="Teléfono">
                            <input v-model="form.phone" type="text" class="input" placeholder="+593 99 000 0000" />
                        </FormField>
                        <FormField label="Dirección">
                            <input v-model="form.address" type="text" class="input" placeholder="Av. Principal..." />
                        </FormField>
                        <div class="flex justify-end pt-2">
                            <button type="submit" :disabled="form.processing"
                                class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-sm font-semibold transition shadow-sm shadow-cyan-200">
                                {{ form.processing ? 'Guardando...' : (client ? 'Actualizar' : 'Crear Cliente') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Branches -->
                <div v-if="client" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3 flex-1 mr-4">
                            <h2 class="font-semibold text-slate-800 text-base whitespace-nowrap">Sucursales</h2>
                            <div class="relative flex-1 max-w-xs">
                                <input v-model="branchSearch" type="text" placeholder="Buscar sucursal..."
                                    class="w-full pl-9 pr-4 py-1.5 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-cyan-400 outline-none transition uppercase" />
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                        <button @click="showBranchForm = !showBranchForm"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-600 rounded-lg text-sm font-medium transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar
                        </button>
                    </div>

                    <!-- New/Edit branch inline form -->
                    <div v-if="showBranchForm" class="bg-slate-50 rounded-xl p-4 mb-4 space-y-3 border border-cyan-100">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-cyan-600 uppercase tracking-wider">{{ branchForm.id ? 'Editar Sucursal' : 'Nueva Sucursal' }}</span>
                            <button v-if="branchForm.id" @click="showBranchForm = false; branchForm.reset()" class="text-xs text-slate-400 hover:text-slate-600 underline">Nueva sucursal</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Nombre Local *</label>
                                <input v-model="branchForm.name" class="input text-sm mt-1"
                                    placeholder="Sucursal Norte" />
                            </div>
                            <!-- Auto-generated code preview -->
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Código (auto)</label>
                                <div
                                    class="input text-sm mt-1 bg-slate-100 text-slate-500 font-mono select-all cursor-default overflow-hidden truncate">
                                    {{ codePreview }}
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Marca / Brand</label>
                                <input v-model="branchForm.brand_name" class="input text-sm mt-1"
                                    placeholder="FYBECA" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Ruta</label>
                                <input v-model="branchForm.route" class="input text-sm mt-1" placeholder="Norte" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Email de Sucursal</label>
                                <input v-model="branchForm.email" type="email" class="input text-sm mt-1"
                                    placeholder="sucursal@mail.com" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500 font-medium">Dirección</label>
                                <input v-model="branchForm.address" class="input text-sm mt-1"
                                    placeholder="Dirección..." />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="showBranchForm = false; branchForm.reset()"
                                class="px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-200 rounded-lg transition">Cancelar</button>
                            <button @click="addBranch" type="button" :disabled="branchForm.processing"
                                class="px-5 py-1.5 text-sm bg-cyan-500 text-white rounded-lg hover:bg-cyan-600 transition shadow-sm shadow-cyan-100 font-semibold">
                                {{ branchForm.id ? 'Actualizar' : 'Guardar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Branches list -->
                    <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                        <div v-for="branch in filteredBranches" :key="branch.id"
                            class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-cyan-200 hover:bg-cyan-50/30 transition group">
                            <div class="w-10 h-10 rounded-lg bg-white border border-slate-100 shadow-sm flex items-center justify-center group-hover:bg-cyan-500 group-hover:text-white transition-colors">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-slate-700 truncate capitalize">{{ branch.name.toLowerCase() }}</p>
                                    <span v-if="branch.route" class="px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-bold uppercase tracking-tight">{{ branch.route }}</span>
                                </div>
                                <p class="text-xs text-slate-400 flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono text-[10px] bg-slate-50 px-1 border border-slate-100 rounded">{{ branch.code }}</span>
                                    <span v-if="branch.email" class="truncate">• {{ branch.email }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                <button @click="editBranch(branch)"
                                    class="p-1.5 text-slate-400 hover:text-cyan-600 hover:bg-cyan-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <button @click="deleteBranch(branch.id)"
                                    class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p v-if="!client.branches?.length" class="text-center text-slate-400 text-sm py-8 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">Sin sucursales registradas</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({ client: Object });
const showBranchForm = ref(false);
const branchSearch = ref('');

const form = useForm({
    name: props.client?.name ?? '',
    nombre_local: props.client?.nombre_local ?? '',
    identification_type: props.client?.identification_type ?? 'RUC',
    identification: props.client?.identification ?? '',
    vat_number: props.client?.vat_number ?? '',
    email: props.client?.email ?? '',
    phone: props.client?.phone ?? '',
    address: props.client?.address ?? '',
});

const branchForm = useForm({ id: null, name: '', brand_name: '', route: '', address: '', email: '' });

const filteredBranches = computed(() => {
    if (!branchSearch.value) return props.client?.branches || [];
    const search = branchSearch.value.toLowerCase();
    return (props.client?.branches || []).filter(b => 
        (b.name?.toLowerCase().includes(search)) ||
        (b.code?.toLowerCase().includes(search)) ||
        (b.brand_name?.toLowerCase().includes(search)) ||
        (b.route?.toLowerCase().includes(search)) ||
        (b.email?.toLowerCase().includes(search))
    );
});

// Mirror del generateCode de BranchController — NNNNN-MMM-NOMBRE
const codePreview = computed(() => {
    if (branchForm.id) return 'Fijo (Editando)';
    const seq = '?????'; // el backend asignará el secuencial real
    const brand = (branchForm.brand_name || branchForm.name || '')
        .replace(/[^A-Za-z0-9]/g, '').substring(0, 3).toUpperCase().padEnd(3, 'X');
    const local = (branchForm.name || '').toUpperCase();
    if (!branchForm.name) return '-----';
    return `${seq}-${brand}-${local}`;
});

function submit() {
    if (props.client) {
        form.put(route('admin.clients.update', props.client.id));
    } else {
        form.post(route('admin.clients.store'));
    }
}

function editBranch(branch) {
    branchForm.id = branch.id;
    branchForm.name = branch.name;
    branchForm.brand_name = branch.brand_name;
    branchForm.route = branch.route;
    branchForm.address = branch.address;
    branchForm.email = branch.email;
    showBranchForm.value = true;
}

function addBranch() {
    if (branchForm.id) {
        branchForm.put(route('admin.branches.update', branchForm.id), {
            onSuccess: () => { 
                showBranchForm.value = false; 
                branchForm.reset(); 
            }
        });
    } else {
        branchForm.post(route('admin.clients.branches.store', props.client.id), {
            onSuccess: () => { showBranchForm.value = false; branchForm.reset(); }
        });
    }
}

function deleteBranch(id) {
    if (confirm('¿Eliminar esta sucursal?')) {
        router.delete(route('admin.branches.destroy', id));
    }
}
</script>

<style scoped>
@reference "../../../../css/app.css";

.input {
    @apply w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 transition;
}
</style>
