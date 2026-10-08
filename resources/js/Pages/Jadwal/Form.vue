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

const props = defineProps<{
    schedule: {
        id: number;
        date: string;
        location: string;
        has_participants: boolean;
    } | null;
    schools: { id: number; name: string }[] | null;
}>();

const isEdit = computed(() => props.schedule !== null);
const title = computed(() => (isEdit.value ? 'Ubah jadwal' : 'Buat jadwal'));

const form = useForm({
    school_id: '' as number | '',
    date: props.schedule?.date ?? '',
    location: props.schedule?.location ?? '',
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
    if (!form.location.trim()) errors.location = 'Lokasi wajib diisi.';
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
    { label: 'Jadwal', href: route('jadwal.index') },
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
                        ? 'Perbarui tanggal atau lokasi kegiatan.'
                        : 'Tentukan sekolah, tanggal, dan lokasi kegiatan perekaman.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Detail jadwal
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >
                <InlineAlert
                    v-if="schedule?.has_participants"
                    variant="warning"
                >
                    Operator sekolah akan diberi tahu bila tanggal atau lokasi
                    berubah.
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
