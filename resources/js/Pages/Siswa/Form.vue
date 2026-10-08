<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

interface FormStudent {
    id: number;
    nik: string;
    nisn: string;
    name: string;
    birth_date: string;
    gender: 'L' | 'P';
}

const props = defineProps<{
    student: FormStudent | null;
    schools: { id: number; name: string }[] | null;
}>();

const isEdit = computed(() => props.student !== null);
const title = computed(() => (isEdit.value ? 'Ubah siswa' : 'Tambah siswa'));

const form = useForm({
    nik: props.student?.nik ?? '',
    nisn: props.student?.nisn ?? '',
    name: props.student?.name ?? '',
    birth_date: props.student?.birth_date ?? '',
    gender: props.student?.gender ?? '',
    school_id: '' as number | '',
});

const genderOptions = [
    { value: 'L', label: 'Laki-laki' },
    { value: 'P', label: 'Perempuan' },
];
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);
const today = new Date().toISOString().slice(0, 10);

// Validasi sisi klien memakai pesan yang sama dengan server; server tetap penentu akhir.
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
    if (!/^\d{16}$/.test(form.nik)) errors.nik = 'NIK harus 16 digit angka.';
    if (!/^\d{10}$/.test(form.nisn)) errors.nisn = 'NISN harus 10 digit angka.';
    if (!form.name.trim()) errors.name = 'Nama lengkap wajib diisi.';
    if (!form.birth_date) errors.birth_date = 'Tanggal lahir wajib diisi.';
    else if (form.birth_date > today)
        errors.birth_date = 'Tanggal lahir tidak boleh di masa depan.';
    if (!form.gender) errors.gender = 'Pilih jenis kelamin.';
    if (props.schools && !isEdit.value && form.school_id === '')
        errors.school_id = 'Pilih sekolah.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };

    if (props.student)
        form.put(route('siswa.update', props.student.id), options);
    else form.post(route('siswa.store'), options);
}

const crumbs = computed(() => [
    { label: 'Siswa', href: route('siswa.index') },
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
                        ? 'Perbarui data siswa.'
                        : 'Tambahkan siswa baru. Dokumen diunggah setelah data disimpan.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Data siswa
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
                    label="NIK"
                    required
                    :error="error('nik')"
                    hint="16 digit angka."
                >
                    <Input
                        :id="id"
                        v-model="form.nik"
                        class="h-10 font-mono"
                        inputmode="numeric"
                        maxlength="16"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="NISN"
                    required
                    :error="error('nisn')"
                    hint="10 digit angka."
                >
                    <Input
                        :id="id"
                        v-model="form.nisn"
                        class="h-10 font-mono"
                        inputmode="numeric"
                        maxlength="10"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

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
                    label="Tanggal lahir"
                    required
                    :error="error('birth_date')"
                >
                    <Input
                        :id="id"
                        v-model="form.birth_date"
                        type="date"
                        class="h-10"
                        :max="today"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Jenis kelamin"
                    required
                    :error="error('gender')"
                >
                    <SelectField
                        :id="id"
                        v-model="form.gender"
                        :options="genderOptions"
                        placeholder="Pilih jenis kelamin"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>

                <FormField
                    v-if="schools && !isEdit"
                    v-slot="{ id, describedBy, invalid }"
                    label="Sekolah"
                    required
                    :error="error('school_id')"
                >
                    <SelectField
                        :id="id"
                        v-model="form.school_id"
                        :options="schoolOptions"
                        placeholder="Pilih sekolah"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link
                        :href="
                            student
                                ? route('siswa.show', student.id)
                                : route('siswa.index')
                        "
                        >Batal</Link
                    >
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
