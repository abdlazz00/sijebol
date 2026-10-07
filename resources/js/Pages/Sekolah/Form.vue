<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import type { SchoolRow } from '@/types/access';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    school: Pick<
        SchoolRow,
        'id' | 'name' | 'npsn' | 'level' | 'address'
    > | null;
}>();

const isEdit = computed(() => props.school !== null);
const title = computed(() =>
    isEdit.value ? 'Ubah sekolah' : 'Tambah sekolah',
);

const form = useForm({
    name: props.school?.name ?? '',
    npsn: props.school?.npsn ?? '',
    level: props.school?.level ?? '',
    address: props.school?.address ?? '',
});

const levelOptions = ['SMA', 'SMK', 'MA'].map((l) => ({ value: l, label: l }));

// Validasi sisi klien memakai pesan yang sama dengan form lain; server tetap penentu akhir.
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
    if (!form.name.trim()) errors.name = 'Nama sekolah wajib diisi.';
    if (!/^\d{8}$/.test(form.npsn)) errors.npsn = 'NPSN harus 8 digit angka.';
    if (!form.level) errors.level = 'Pilih jenjang.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };

    if (props.school)
        form.put(route('sekolah.update', props.school.id), options);
    else form.post(route('sekolah.store'), options);
}

const crumbs = computed(() => [
    { label: 'Sekolah', href: route('sekolah.index') },
    { label: title.value },
]);
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{
                    isEdit
                        ? 'Perbarui data sekolah.'
                        : 'Tambahkan sekolah baru di Kecamatan Sungai Beduk.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Data sekolah
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nama sekolah"
                    required
                    :error="error('name')"
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        class="h-10"
                        placeholder="Contoh: SMAN 1 Sungai Beduk"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="NPSN"
                    required
                    :error="error('npsn')"
                    hint="8 digit angka."
                >
                    <Input
                        :id="id"
                        v-model="form.npsn"
                        class="h-10"
                        inputmode="numeric"
                        maxlength="8"
                        placeholder="12345678"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Jenjang"
                    required
                    :error="error('level')"
                >
                    <SelectField
                        :id="id"
                        v-model="form.level"
                        :options="levelOptions"
                        placeholder="Pilih jenjang"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy }"
                    label="Alamat"
                    optional
                    :error="error('address')"
                >
                    <Textarea
                        :id="id"
                        v-model="form.address"
                        class="h-20"
                        :aria-describedby="describedBy"
                    />
                </FormField>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link :href="route('sekolah.index')">Batal</Link>
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
