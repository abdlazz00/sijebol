<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay } from '@/lib/date';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: {
        id: number;
        date: string;
        start_time: string;
        end_time: string;
        location: string;
        quota: number;
        has_participants: boolean;
    } | null;
    schools: { id: number; name: string }[] | null;
}>();

const isEdit = computed(() => props.schedule !== null);
const title = computed(() =>
    isEdit.value ? 'Ubah sesi rekaman' : 'Buat sesi rekaman',
);
// Tanggal terkunci setelah ada pendaftar: tampil sebagai teks, bukan input yang dinonaktifkan.
const dateLocked = computed(() => props.schedule?.has_participants === true);

const form = useForm({
    school_id: '' as number | '',
    date: props.schedule?.date ?? '',
    start_time: props.schedule?.start_time ?? '',
    end_time: props.schedule?.end_time ?? '',
    location: props.schedule?.location ?? '',
    quota: props.schedule ? String(props.schedule.quota) : '',
});

const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);
// Zona Asia/Jakarta, sama dengan aturan server.
const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
}).format(new Date());

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
    if (props.schools && !isEdit.value && form.school_id === '')
        errors.school_id = 'Pilih sekolah.';
    if (!form.date) errors.date = 'Tanggal wajib diisi.';
    else if (form.date !== props.schedule?.date && form.date < today)
        errors.date = 'Tanggal tidak boleh di masa lalu.';
    if (!form.start_time) errors.start_time = 'Jam mulai wajib diisi.';
    if (!form.end_time) errors.end_time = 'Jam selesai wajib diisi.';
    else if (form.start_time && form.end_time <= form.start_time)
        errors.end_time = 'Jam selesai harus setelah jam mulai.';
    if (!form.location.trim()) errors.location = 'Lokasi wajib diisi.';
    const quota = Number(form.quota);
    if (!/^\d+$/.test(form.quota) || quota < 1 || quota > 1000)
        errors.quota = 'Kuota harus berupa angka 1–1000.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };
    if (props.schedule)
        form.put(route('jadwal.update', props.schedule.id), options);
    else form.post(route('jadwal.store'), options);
}

const crumbs = computed(() => [
    { label: 'Sesi rekaman', href: route('jadwal.index') },
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
                        ? 'Perbarui jam, lokasi, atau kuota sesi.'
                        : 'Tentukan sekolah, tanggal, jam, lokasi, dan kuota sesi perekaman.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Detail sesi
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >
                <InlineAlert v-if="dateLocked" variant="warning">
                    Sesi ini sudah punya pendaftar. Jam, lokasi, dan kuota masih
                    dapat diubah; tanggal tidak dapat diubah. Bila tanggal harus
                    berubah, batalkan sesi lalu buat sesi baru.
                </InlineAlert>

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

                <FormField
                    v-if="!dateLocked"
                    v-slot="{ id, describedBy, invalid }"
                    label="Tanggal"
                    required
                    :error="error('date')"
                >
                    <Input
                        :id="id"
                        v-model="form.date"
                        type="date"
                        class="h-10"
                        :min="schedule ? undefined : today"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <div v-else class="flex flex-col gap-1 text-sm leading-5">
                    <span class="font-medium">Tanggal</span>
                    <span>{{ formatDateWithDay(form.date) }}</span>
                    <span class="text-muted-foreground text-xs leading-4"
                        >Tidak dapat diubah karena sudah ada pendaftar.</span
                    >
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Jam mulai"
                        required
                        :error="error('start_time')"
                    >
                        <Input
                            :id="id"
                            v-model="form.start_time"
                            type="time"
                            class="h-10"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Jam selesai"
                        required
                        :error="error('end_time')"
                    >
                        <Input
                            :id="id"
                            v-model="form.end_time"
                            type="time"
                            class="h-10"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>
                </div>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Lokasi"
                    required
                    :error="error('location')"
                >
                    <Input
                        :id="id"
                        v-model="form.location"
                        class="h-10"
                        placeholder="Contoh: Aula SMP Negeri 1"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Kuota"
                    required
                    hint="Jumlah kursi yang dapat diambil siswa (1–1000)."
                    :error="error('quota')"
                >
                    <Input
                        :id="id"
                        v-model="form.quota"
                        inputmode="numeric"
                        class="h-10 sm:w-40"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link
                        :href="
                            schedule
                                ? route('jadwal.show', schedule.id)
                                : route('jadwal.index')
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
