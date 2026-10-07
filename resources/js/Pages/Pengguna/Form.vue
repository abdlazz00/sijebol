<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SearchableSelect from '@/Components/shared/SearchableSelect.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { humanize } from '@/lib/format';
import type { Option } from '@/types/access';
import { Link, useForm } from '@inertiajs/vue3';
import { Info, LoaderCircle, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';

interface FormUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role_id: number | null;
    school_id: number | null;
    activated: boolean;
}

const props = defineProps<{
    user: FormUser | null;
    roles: (Option & { is_school_scoped: boolean })[];
    schools: Option[];
}>();

const isEdit = computed(() => props.user !== null);
const emailLocked = computed(() => props.user?.activated === true);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    role_id: props.user?.role_id ?? (null as number | null),
    school_id: props.user?.school_id ?? (null as number | null),
});

const roleOptions = computed(() =>
    props.roles.map((r) => ({ value: r.id, label: humanize(r.name) })),
);
const schoolOptions = computed(() =>
    props.schools.map((s) => ({ value: s.id, label: s.name })),
);
const schoolRequired = computed(
    () =>
        props.roles.find((r) => r.id === form.role_id)?.is_school_scoped ===
        true,
);

function onRoleChange(value: string | number | null) {
    form.role_id = value === null || value === '' ? null : Number(value);
    if (!schoolRequired.value) form.school_id = null;
}

// Validasi sisi klien memakai pesan dari Figma; server tetap menjadi penentu akhir.
const clientErrors = ref<Record<string, string>>({});
const error = (key: keyof typeof form.errors) =>
    clientErrors.value[key] ?? form.errors[key];
const hasErrors = computed(
    () =>
        Object.keys(clientErrors.value).length > 0 ||
        Object.keys(form.errors).length > 0,
);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) errors.name = 'Nama lengkap wajib diisi.';
    if (!form.email.trim()) errors.email = 'Email wajib diisi.';
    else if (!/^\S+@\S+\.\S+$/.test(form.email.trim()))
        errors.email = 'Masukkan alamat email yang valid.';
    if (form.phone && !/^08\d{8,12}$/.test(form.phone))
        errors.phone = 'Nomor HP harus diawali 08.';
    if (form.role_id === null) errors.role_id = 'Pilih role.';
    if (schoolRequired.value && form.school_id === null)
        errors.school_id = 'Pilih sekolah untuk operator sekolah.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return;

    if (props.user) form.put(route('pengguna.update', props.user.id));
    else form.post(route('pengguna.store'));
}

const crumbs = computed(() => [
    { label: 'Pengguna', href: route('pengguna.index') },
    { label: isEdit.value ? 'Ubah pengguna' : 'Tambah pengguna' },
]);
</script>

<template>
    <AppLayout
        :title="isEdit ? 'Ubah pengguna' : 'Tambah pengguna'"
        :breadcrumbs="crumbs"
    >
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">
                {{ isEdit ? 'Ubah pengguna' : 'Tambah pengguna' }}
            </h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{
                    isEdit
                        ? 'Perbarui data akun pengguna SIJEBOL.'
                        : 'Buat akun baru untuk mengakses SIJEBOL.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">Data akun</h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nama lengkap"
                    required
                    :error="error('name')"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        class="h-10"
                        placeholder="Contoh: Budi Santoso"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email"
                    required
                    :error="error('email')"
                    :hint="
                        !isEdit
                            ? undefined
                            : emailLocked
                              ? 'Email tidak dapat diubah setelah akun aktif.'
                              : 'Email dapat diubah karena akun belum pernah diaktifkan.'
                    "
                >
                    <div class="relative">
                        <Input
                            :id="id"
                            v-model="form.email"
                            type="email"
                            class="h-10 disabled:opacity-100"
                            :class="emailLocked ? 'bg-secondary pr-10' : ''"
                            placeholder="nama@example.com"
                            :disabled="emailLocked"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                        <Lock
                            v-if="emailLocked"
                            class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                    </div>
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nomor HP"
                    optional
                    :error="error('phone')"
                    hint="Gunakan nomor yang diawali 08."
                >
                    <Input
                        :id="id"
                        v-model="form.phone"
                        inputmode="tel"
                        class="h-10"
                        placeholder="08xxxxxxxxxx"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Role"
                    required
                    :error="error('role_id')"
                >
                    <SelectField
                        :id="id"
                        :model-value="form.role_id"
                        :options="roleOptions"
                        placeholder="Pilih role"
                        :invalid="invalid"
                        :described-by="describedBy"
                        @update:model-value="onRoleChange"
                    />
                </FormField>

                <FormField
                    v-if="schoolRequired"
                    v-slot="{ id, describedBy, invalid }"
                    label="Sekolah"
                    required
                    :error="error('school_id')"
                    hint="Wajib untuk operator sekolah."
                >
                    <SearchableSelect
                        :id="id"
                        v-model="form.school_id"
                        :options="schoolOptions"
                        placeholder="Cari dan pilih sekolah"
                        search-placeholder="Cari sekolah"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>

                <p
                    v-if="!isEdit"
                    class="bg-secondary text-muted-foreground flex items-center gap-2 rounded-lg px-3 py-2.5 text-xs leading-4"
                >
                    <Info class="size-4 shrink-0" aria-hidden="true" />
                    Link aktivasi dibuat setelah akun disimpan.
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link :href="route('pengguna.index')">Batal</Link>
                </Button>
                <Button
                    type="submit"
                    class="h-10 px-4"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
