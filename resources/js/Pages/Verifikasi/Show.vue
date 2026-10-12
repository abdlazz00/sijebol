<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DocumentPreviewDialog from '@/Components/shared/DocumentPreviewDialog.vue';
import FormField from '@/Components/shared/FormField.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import StatusTimeline from '@/Components/shared/StatusTimeline.vue';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { formatBytes } from '@/lib/files';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { PERMISSIONS } from '@/lib/permissions';
import type { EntryRow } from '@/types/registration';
import type { DocumentType, StudentDocumentRow } from '@/types/student';
import { Link, useForm } from '@inertiajs/vue3';
import { Download, Eye, LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    entry: EntryRow & { registration: { id: number; title: string } };
    student: {
        id: number;
        name: string;
        nisn: string;
        nik: string;
        birth_date: string;
        age: number;
        gender: 'L' | 'P';
        school: string | null;
    };
    documents: StudentDocumentRow[];
    eligibility: { eligible: boolean; reasons: string[] };
}>();

const previewOpen = ref(false);
const selectedDocType = ref<DocumentType>('kk');

function openPreview(type: DocumentType) {
    selectedDocType.value = type;
    previewOpen.value = true;
}

const { can } = usePermission();
const canDecide = computed(
    () =>
        props.entry.status === 'diajukan' &&
        can(PERMISSIONS.VerifikasiPutuskan),
);

const decisions = [
    {
        value: 'approve',
        label: 'Setujui',
        hint: 'Siswa lanjut ke penjadwalan.',
    },
    {
        value: 'fix',
        label: 'Minta perbaikan',
        hint: 'Operator diminta membetulkan data atau dokumen.',
    },
    {
        value: 'reject',
        label: 'Tolak',
        hint: 'Siswa tidak dapat didaftarkan pada pendaftaran ini.',
    },
];

const form = useForm({ decision: '', note: '' });
const clientErrors = ref<Record<string, string>>({});
const error = (key: 'decision' | 'note') =>
    clientErrors.value[key] ?? form.errors[key];
const rejectOpen = ref(false);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.decision) errors.decision = 'Pilih keputusan.';
    else if (form.decision !== 'approve' && !form.note.trim())
        errors.note = 'Catatan wajib diisi untuk keputusan ini.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function send() {
    form.post(route('verifikasi.decide', props.entry.id), {
        onError: focusFirstInvalid,
    });
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();
    if (form.decision === 'reject') rejectOpen.value = true;
    else send();
}

const info = computed(() => [
    { label: 'NIK', value: props.student.nik },
    {
        label: 'Tanggal lahir',
        value: `${formatDate(props.student.birth_date)} (${props.student.age} th)`,
    },
    {
        label: 'Jenis kelamin',
        value: props.student.gender === 'L' ? 'Laki-laki' : 'Perempuan',
    },
    { label: 'Sekolah', value: props.student.school },
    { label: 'Pendaftaran', value: props.entry.registration.title },
]);

const crumbs = computed(() => [
    { label: 'Antrean verifikasi', href: route('verifikasi.index') },
    { label: props.student.name },
]);
</script>

<template>
    <AppLayout title="Verifikasi siswa" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold break-words">
                {{ student.name }}
            </h1>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-muted-foreground text-sm leading-5">
                    NISN {{ student.nisn }}
                </p>
                <StatusBadge :status="entry.status" kind="registration" />
            </div>
        </div>

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="!eligibility.eligible" variant="warning">
                Siswa belum memenuhi syarat:
                {{ eligibility.reasons.join(' ') }}
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Data siswa</h2>
                <InfoList :items="info" />
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Dokumen</h2>
                <ul v-if="documents.length" class="flex flex-col gap-2">
                    <li
                        v-for="doc in documents"
                        :key="doc.type"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="min-w-0 truncate"
                            >{{
                                doc.type === 'kk'
                                    ? 'Kartu Keluarga'
                                    : 'Akta Kelahiran'
                            }}
                            <span class="text-muted-foreground"
                                >· {{ formatBytes(doc.size) }}</span
                            ></span
                        >
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-9 px-3 gap-1.5"
                                @click="openPreview(doc.type)"
                            >
                                <Eye class="size-4" aria-hidden="true" />
                                Lihat
                            </Button>
                            <Button as-child variant="outline" class="h-9 px-3">
                                <a :href="doc.url">
                                    <Download class="size-4" aria-hidden="true" />
                                    Unduh
                                    <span class="sr-only">{{ doc.name }}</span>
                                </a>
                            </Button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">
                    Tidak ada dokumen yang dapat ditampilkan.
                </p>
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Riwayat</h2>
                <StatusTimeline :items="entry.history" />
            </section>

            <section
                v-if="canDecide"
                class="bg-card flex flex-col gap-4 rounded-lg border p-6"
            >
                <h2 class="text-base leading-6 font-semibold">Keputusan</h2>
                <form
                    class="flex flex-col gap-4"
                    novalidate
                    @submit.prevent="submit"
                >
                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">Keputusan</legend>
                        <label
                            v-for="d in decisions"
                            :key="d.value"
                            class="flex cursor-pointer items-start gap-3 text-sm leading-5"
                        >
                            <input
                                v-model="form.decision"
                                type="radio"
                                name="decision"
                                :value="d.value"
                                class="accent-primary mt-1 size-4"
                                :aria-invalid="!!error('decision')"
                            />
                            <span>
                                <span class="block font-medium">{{
                                    d.label
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    d.hint
                                }}</span>
                            </span>
                        </label>
                        <p
                            v-if="error('decision')"
                            class="text-danger text-xs"
                            role="alert"
                        >
                            {{ error('decision') }}
                        </p>
                    </fieldset>

                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Catatan"
                        :error="error('note')"
                        hint="Wajib untuk Minta perbaikan dan Tolak."
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

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="outline" class="h-10 px-4">
                            <Link :href="route('verifikasi.index')">Batal</Link>
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
                            {{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan keputusan'
                            }}
                        </Button>
                    </div>
                </form>
            </section>

            <section
                v-else-if="entry.decided_at"
                class="bg-card flex flex-col gap-2 rounded-lg border p-6"
            >
                <h2 class="text-base leading-6 font-semibold">Keputusan</h2>
                <p class="text-sm leading-5">
                    Sudah diputuskan: {{ entry.note ?? 'tanpa catatan' }}
                </p>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="rejectOpen"
            title="Tolak siswa ini?"
            description="Siswa tidak dapat didaftarkan pada pendaftaran ini. Operator diberi tahu beserta catatan Anda."
            confirm-label="Tolak"
            destructive
            @confirm="send"
        />

        <DocumentPreviewDialog
            v-model:open="previewOpen"
            :title="`Dokumen ${student.name}`"
            :documents="documents"
            :initial-type="selectedDocType"
        />
    </AppLayout>
</template>
