<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

interface FormStudent {
    id: number;
    nik: string;
    nisn: string;
    name: string;
    birth_date: string;
    gender: 'L' | 'P';
    school_id: number;
}

const props = defineProps<{
    student: FormStudent;
    schools: { id: number; name: string }[] | null;
}>();

const form = useForm({
    nik: props.student.nik,
    nisn: props.student.nisn,
    name: props.student.name,
    birth_date: props.student.birth_date,
    gender: props.student.gender as string,
    school_id: props.student.school_id as number | '',
    note: '',
});

// Tempelan dengan spasi atau titik tidak boleh memicu galat: karakter non-angka dibuang.
watch(
    () => form.nik,
    (v) => {
        const digits = v.replace(/\D/g, '');
        if (digits !== v) form.nik = digits;
    },
);
watch(
    () => form.nisn,
    (v) => {
        const digits = v.replace(/\D/g, '');
        if (digits !== v) form.nisn = digits;
    },
);

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
    if (form.note.trim().length < 5)
        errors.note = 'Catatan koreksi wajib diisi (minimal 5 karakter).';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    form.put(route('siswa.update', props.student.id), {
        onError: focusFirstInvalid,
    });
}

const crumbs = computed(() => [
    { label: 'Siswa', href: route('siswa.index') },
    { label: 'Koreksi data siswa' },
]);
</script>

<template>
    <AppLayout title="Koreksi data siswa" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Koreksi data siswa</h1>
            <p class="text-muted-foreground text-sm leading-5">
                Koreksi hanya untuk data yang salah dan tidak dapat diperbaiki
                siswa sendiri. Siswa diberi tahu dan perubahan tercatat di log.
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
                        pattern="[0-9]*"
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
                        pattern="[0-9]*"
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
                    v-if="schools"
                    v-slot="{ id, describedBy, invalid }"
                    label="Sekolah"
                    :error="error('school_id')"
                    hint="Pindah sekolah ditolak bila siswa masih punya pendaftaran aktif."
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

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Catatan koreksi"
                    required
                    :error="error('note')"
                    hint="Alasan koreksi; dikirim ke siswa dan dicatat di log."
                >
                    <Textarea
                        :id="id"
                        v-model="form.note"
                        rows="3"
                        maxlength="500"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link :href="route('siswa.show', student.id)">Batal</Link>
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
                    {{ form.processing ? 'Menyimpan…' : 'Simpan koreksi' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
