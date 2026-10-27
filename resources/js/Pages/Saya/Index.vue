<script setup lang="ts">
import DocumentPreviewDialog from '@/Components/shared/DocumentPreviewDialog.vue';
import FileUpload from '@/Components/shared/FileUpload.vue';
import FormField from '@/Components/shared/FormField.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { PERMISSIONS } from '@/lib/permissions';
import type {
    DocumentType,
    SelfStudent,
    StudentDocumentRow,
} from '@/types/student';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    student: SelfStudent | null;
    school: string;
    account_name: string;
    documents: StudentDocumentRow[];
    eligibility: { eligible: boolean; reasons: string[] };
    locked: boolean;
}>();

const { can } = usePermission();

const form = useForm({
    nik: props.student?.nik ?? '',
    nisn: props.student?.nisn ?? '',
    name: props.student?.name ?? props.account_name,
    birth_date: props.student?.birth_date ?? '',
    gender: props.student?.gender ?? '',
});

// Tempelan "3201 1234 …" atau dengan titik tidak boleh memicu galat: karakter non-angka dibuang.
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
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

const docsCard = ref<HTMLElement | null>(null);

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const firstSave = props.student === null;
    form.put(route('me.update'), {
        preserveScroll: true,
        onError: focusFirstInvalid,
        // Langkah berikutnya langsung terlihat setelah data pertama tersimpan.
        onSuccess: () => {
            if (firstSave) void nextTick(() => docsCard.value?.focus());
        },
    });
}

const hasStudent = computed(() => props.student !== null);
const canUpload = computed(
    () => can(PERMISSIONS.DokumenUnggah) && !props.locked && hasStudent.value,
);
const canDelete = computed(
    () => can(PERMISSIONS.DokumenHapus) && !props.locked && hasStudent.value,
);

const info = computed(() => [
    { label: 'NIK', value: props.student?.nik ?? null },
    { label: 'NISN', value: props.student?.nisn ?? null },
    { label: 'Nama lengkap', value: props.student?.name ?? null },
    {
        label: 'Tanggal lahir',
        value: props.student ? formatDate(props.student.birth_date) : null,
    },
    {
        label: 'Jenis kelamin',
        value:
            props.student?.gender === 'L'
                ? 'Laki-laki'
                : props.student
                  ? 'Perempuan'
                  : null,
    },
    { label: 'Sekolah', value: props.school },
]);

const docTypes: { type: DocumentType; label: string }[] = [
    { type: 'kk', label: 'Kartu Keluarga' },
    { type: 'akta_lahir', label: 'Akta Kelahiran' },
];
const existing = (type: DocumentType) =>
    props.documents.find((d) => d.type === type) ?? null;

const previewOpen = ref(false);
const selectedDocType = ref<DocumentType>('kk');
function openPreview(type: DocumentType) {
    selectedDocType.value = type;
    previewOpen.value = true;
}
</script>

<template>
    <AppLayout title="Data saya">
        <PageHeader
            title="Data saya"
            description="Lengkapi data diri dan unggah dokumen persyaratan."
        />

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="locked" variant="warning">
                Data Anda sedang diperiksa petugas dan tidak dapat diubah. Bila
                ada yang salah, petugas akan meminta perbaikan; atau hubungi
                operator sekolah atau admin kecamatan.
            </InlineAlert>

            <template v-if="student">
                <InlineAlert v-if="eligibility.eligible" variant="success">
                    Data dan dokumen Anda sudah lengkap.
                </InlineAlert>
                <InlineAlert v-else variant="warning">
                    Yang masih perlu dilengkapi:
                    <ul class="mt-1 list-disc pl-4">
                        <li v-for="reason in eligibility.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
                </InlineAlert>
            </template>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Langkah 1 dari 2: Data diri
                    </h2>
                    <p
                        v-if="!locked"
                        class="text-muted-foreground text-xs leading-4"
                    >
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InfoList v-if="locked" :items="info" />

                <form
                    v-else
                    class="flex flex-col gap-4"
                    novalidate
                    @submit.prevent="submit"
                >
                    <InlineAlert v-if="hasErrors"
                        >Periksa kembali data yang Anda isi.</InlineAlert
                    >

                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Nama lengkap"
                        required
                        :error="error('name')"
                        hint="Sesuai Kartu Keluarga."
                    >
                        <Input
                            :id="id"
                            v-model="form.name"
                            class="h-10"
                            autocomplete="name"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>

                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="NIK"
                        required
                        :error="error('nik')"
                        hint="NIK siswa, 16 digit — bukan Nomor KK. Ada di tabel anggota keluarga pada Kartu Keluarga."
                    >
                        <Input
                            :id="id"
                            v-model="form.nik"
                            class="h-10 font-mono"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            enterkeyhint="next"
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
                        hint="10 digit — bukan NIS sekolah."
                    >
                        <Input
                            :id="id"
                            v-model="form.nisn"
                            class="h-10 font-mono"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            enterkeyhint="next"
                            maxlength="10"
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
                        hint="Sesuai Kartu Keluarga."
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

                    <p class="text-muted-foreground text-sm leading-5">
                        Sekolah: <span class="font-medium">{{ school }}</span>
                    </p>

                    <Button
                        v-if="can(PERMISSIONS.ProfilUbah)"
                        type="submit"
                        class="h-10 w-full sm:w-auto sm:self-end sm:px-4"
                        :disabled="form.processing"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan data' }}
                    </Button>
                </form>
            </section>

            <section
                ref="docsCard"
                tabindex="-1"
                class="bg-card flex flex-col gap-4 rounded-lg border p-6 focus:outline-none"
            >
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Langkah 2 dari 2: Dokumen
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Foto atau pindai Kartu Keluarga dan Akta Kelahiran.
                        Pastikan semua tulisan terbaca.
                    </p>
                </div>
                <p
                    v-if="!hasStudent"
                    class="text-muted-foreground text-sm leading-5"
                >
                    Simpan data diri di Langkah 1 agar bisa mengunggah dokumen.
                </p>
                <div class="grid gap-6 md:grid-cols-2">
                    <FileUpload
                        v-for="doc in docTypes"
                        :key="doc.type"
                        :label="doc.label"
                        :existing="existing(doc.type)"
                        :can-upload="canUpload"
                        :can-delete="canDelete"
                        hide-download
                        :upload-url="
                            student
                                ? route('students.documents.store', [
                                      student.id,
                                      doc.type,
                                  ])
                                : ''
                        "
                        :delete-url="
                            student
                                ? route('students.documents.destroy', [
                                      student.id,
                                      doc.type,
                                  ])
                                : ''
                        "
                        @preview="openPreview(doc.type)"
                    />
                </div>
            </section>

            <section
                v-if="
                    student &&
                    eligibility.eligible &&
                    can(PERMISSIONS.PendaftaranLihat)
                "
                class="bg-card flex flex-col gap-3 rounded-lg border p-6"
            >
                <h2 class="text-base leading-6 font-semibold">Sesi rekaman</h2>
                <p class="text-muted-foreground text-sm leading-5">
                    {{
                        locked
                            ? 'Pendaftaran Anda sedang diproses atau sudah terdaftar. Lihat statusnya di halaman sesi rekaman.'
                            : 'Data dan dokumen Anda sudah lengkap. Pilih sesi perekaman untuk sekolah Anda.'
                    }}
                </p>
                <Button as-child class="h-10 self-start px-4">
                    <Link :href="route('me.sessions.index')">{{
                        locked ? 'Lihat pendaftaran saya' : 'Pilih sesi rekaman'
                    }}</Link>
                </Button>
            </section>
        </div>

        <DocumentPreviewDialog
            v-model:open="previewOpen"
            title="Dokumen saya"
            :documents="documents"
            :initial-type="selectedDocType"
        />
    </AppLayout>
</template>
