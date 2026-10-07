<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PermissionMatrix from '@/Components/shared/PermissionMatrix.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import type { CatalogModule } from '@/lib/permissionMatrix';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

interface FormRole {
    id: number;
    name: string;
    description: string | null;
    is_school_scoped: boolean;
    users_count: number;
    permissions: string[];
    protected: boolean;
}

const props = defineProps<{
    role: FormRole | null;
    catalog: CatalogModule[];
}>();

const isEdit = computed(() => props.role !== null);
const readonly = computed(() => props.role?.protected === true);
const scopeLocked = computed(() => (props.role?.users_count ?? 0) > 0);

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    is_school_scoped: props.role?.is_school_scoped ?? false,
    permissions: props.role?.permissions ?? ([] as string[]),
});

const total = computed(() =>
    props.catalog.reduce((sum, module) => sum + module.actions.length, 0),
);

// Pengumuman penghitung ditunda agar pembaca layar tidak membacanya di setiap centang.
const announced = ref('');
const announce = useDebounceFn(() => {
    announced.value = `${form.permissions.length} dari ${total.value} permission dipilih`;
}, 800);
watch(() => form.permissions.length, announce);

const title = computed(() => (isEdit.value ? 'Ubah role' : 'Tambah role'));
const crumbs = computed(() => [
    { label: 'Role & Permission', href: route('role.index') },
    { label: title.value },
]);

const permissionsError = computed(
    () =>
        form.errors.permissions ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('permissions.'),
        )?.[1],
);

function submit() {
    if (readonly.value) return;

    const options = { onError: focusFirstInvalid };

    if (props.role) form.put(route('role.update', props.role.id), options);
    else form.post(route('role.store'), options);
}
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="crumbs">
        <form
            class="flex min-h-full flex-col gap-6"
            novalidate
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{
                        isEdit
                            ? 'Sesuaikan data role dan hak akses pengguna.'
                            : 'Tentukan data role dan permission yang diberikan.'
                    }}
                </p>
                <p
                    v-if="isEdit && !readonly"
                    class="text-muted-foreground text-xs leading-4"
                >
                    Perubahan role berlaku untuk
                    {{ role?.users_count }} pengguna.
                </p>
                <p v-else-if="!isEdit" class="text-warning text-xs leading-4">
                    Draf baru · belum disimpan
                </p>
            </div>

            <InlineAlert v-if="readonly" variant="warning">
                Role ini dilindungi sistem dan tidak dapat diubah.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">Data role</h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>
                <div class="flex flex-col gap-4 md:flex-row md:gap-6">
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Nama role"
                        required
                        :error="form.errors.name"
                        class="md:w-[360px] md:shrink-0"
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            class="h-10"
                            placeholder="Contoh: verifikator"
                            autocomplete="off"
                            :disabled="readonly"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy }"
                        label="Deskripsi"
                        optional
                        :error="form.errors.description"
                        class="md:flex-1"
                    >
                        <Textarea
                            :id="id"
                            v-model="form.description"
                            class="h-20"
                            :disabled="readonly"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                </div>
                <!-- Tidak ada di Figma, tetapi backend mewajibkan nilainya. -->
                <label class="flex items-start gap-2 text-sm leading-5">
                    <Checkbox
                        v-model="form.is_school_scoped"
                        class="mt-0.5"
                        :disabled="readonly || scopeLocked"
                    />
                    <span>
                        <span class="font-medium">Role terikat sekolah</span>
                        <span
                            class="text-muted-foreground block text-xs leading-4"
                        >
                            {{
                                scopeLocked
                                    ? 'Tidak dapat diubah selama role masih dipakai pengguna.'
                                    : 'Pengguna dengan role ini wajib dikaitkan dengan satu sekolah.'
                            }}
                        </span>
                    </span>
                </label>
            </section>

            <section class="bg-card overflow-hidden rounded-lg border">
                <div class="flex flex-col gap-2 p-6">
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <h2 class="text-base leading-6 font-semibold">
                            Matriks permission
                        </h2>
                        <p class="text-sm leading-5 font-medium">
                            {{ form.permissions.length }} dari
                            {{ total }} permission dipilih
                        </p>
                    </div>
                    <span class="sr-only" role="status">{{ announced }}</span>
                    <p class="text-muted-foreground text-xs leading-4">
                        Hanya permission yang Anda miliki yang dapat diberikan.
                        Pilih semua berlaku per modul.
                    </p>
                    <p
                        class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                    >
                        <span aria-hidden="true">↔</span> Geser tabel untuk
                        melihat kolom lainnya
                    </p>
                    <InlineAlert v-if="permissionsError">{{
                        permissionsError
                    }}</InlineAlert>
                </div>
                <PermissionMatrix
                    v-model="form.permissions"
                    :catalog="catalog"
                    :disabled="readonly"
                />
            </section>

            <div
                class="bg-card sticky bottom-0 z-10 -mx-4 mt-auto -mb-4 flex flex-col gap-3 border-t p-4 sm:-mx-6 sm:-mb-6 sm:flex-row sm:items-center sm:justify-between sm:p-6"
            >
                <p
                    class="text-warning text-xs leading-4"
                    :class="form.isDirty && !readonly ? '' : 'invisible'"
                    role="status"
                >
                    Ada perubahan belum disimpan
                </p>
                <div class="flex gap-3 [&>*]:flex-1 sm:[&>*]:flex-none">
                    <Button as-child variant="outline" class="h-10 px-4">
                        <Link :href="route('role.index')">Batal</Link>
                    </Button>
                    <Button
                        v-if="!readonly"
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
            </div>
        </form>
    </AppLayout>
</template>
