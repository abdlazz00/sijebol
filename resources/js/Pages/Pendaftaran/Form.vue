<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import StudentMultiSelect from '@/Components/shared/StudentMultiSelect.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import type { CandidateRow } from '@/types/registration';
import { Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    registration: {
        id: number;
        title: string;
        expected_count: number | null;
    } | null;
    candidates: CandidateRow[];
    schools: { id: number; name: string }[] | null;
    school_id: number | null;
}>();

const isEdit = computed(() => props.registration !== null);
const title = computed(() =>
    isEdit.value ? 'Ubah pendaftaran' : 'Buat pendaftaran',
);

const form = useForm({
    title: props.registration?.title ?? '',
    expected_count: (props.registration?.expected_count ?? '') as number | '',
    school_id: (props.school_id ?? '') as number | '',
    student_ids: props.candidates.filter((c) => c.selected).map((c) => c.id),
});

const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

// Staf memilih sekolah: muat ulang hanya daftar kandidat, pilihan lama dikosongkan.
watch(
    () => form.school_id,
    (schoolId) => {
        if (isEdit.value || !props.schools || schoolId === '') return;
        router.get(
            route('pendaftaran.create'),
            { school_id: schoolId },
            {
                only: ['candidates'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => (form.student_ids = []),
            },
        );
    },
);

const clientErrors = ref<Record<string, string>>({});
const error = (key: string) =>
    clientErrors.value[key] ?? (form.errors as Record<string, string>)[key];
const rowErrors = computed(() =>
    Object.fromEntries(
        Object.entries(form.errors as Record<string, string>)
            .filter(([key]) => key.startsWith('student_ids.'))
            .map(([key, message]) => [Number(key.split('.')[1]), message]),
    ),
);
const hasErrors = computed(
    () =>
        Object.keys(clientErrors.value).length > 0 ||
        Object.keys(form.errors).length > 0,
);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.title.trim()) errors.title = 'Judul pendaftaran wajib diisi.';
    if (props.schools && !isEdit.value && form.school_id === '')
        errors.school_id = 'Pilih sekolah.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };
    form.transform((data) => ({
        ...data,
        expected_count: data.expected_count === '' ? null : data.expected_count,
    }));

    if (props.registration)
        form.put(route('pendaftaran.update', props.registration.id), options);
    else form.post(route('pendaftaran.store'), options);
}

const crumbs = computed(() => [
    { label: 'Pendaftaran', href: route('pendaftaran.index') },
    { label: title.value },
]);
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p class="text-muted-foreground text-sm leading-5">
                Simpan sebagai draf, lalu ajukan dari halaman detail setelah
                daftar siswa sudah benar.
            </p>
        </div>

        <form
            class="flex max-w-[720px] flex-col gap-6"
            novalidate
            @submit.prevent="submit"
        >
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Detail pendaftaran
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
                    label="Judul / keterangan"
                    required
                    :error="error('title')"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        class="h-10"
                        placeholder="Contoh: Perekaman gelombang 1"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Perkiraan jumlah peserta"
                    :error="error('expected_count')"
                    hint="Opsional."
                >
                    <Input
                        :id="id"
                        v-model="form.expected_count"
                        type="number"
                        min="1"
                        class="h-10"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
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

            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Pilih siswa
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Hanya siswa eligible yang dapat dipilih.
                    </p>
                </div>
                <p
                    v-if="schools && !isEdit && form.school_id === ''"
                    class="text-muted-foreground text-sm"
                >
                    Pilih sekolah terlebih dahulu untuk menampilkan siswa.
                </p>
                <StudentMultiSelect
                    v-else
                    v-model="form.student_ids"
                    :candidates="candidates"
                    :errors="rowErrors"
                />
            </div>

            <div class="flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link
                        :href="
                            registration
                                ? route('pendaftaran.show', registration.id)
                                : route('pendaftaran.index')
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
                    {{ form.processing ? 'Menyimpan…' : 'Simpan draf' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
