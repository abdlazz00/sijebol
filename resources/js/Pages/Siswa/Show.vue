<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import FileUpload from '@/Components/shared/FileUpload.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { initials } from '@/lib/format';
import { PERMISSIONS } from '@/lib/permissions';
import type {
    DocumentType,
    StudentDetail,
    StudentDocumentRow,
} from '@/types/student';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    student: StudentDetail;
    documents: StudentDocumentRow[];
    eligibility: { eligible: boolean; reasons: string[] };
}>();

const { can } = usePermission();
const confirmOpen = ref(false);

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
]);

const docTypes: { type: DocumentType; label: string }[] = [
    { type: 'kk', label: 'Kartu Keluarga' },
    { type: 'akta_lahir', label: 'Akta Kelahiran' },
];
const existing = (type: DocumentType) =>
    props.documents.find((d) => d.type === type) ?? null;

function destroy() {
    router.delete(route('siswa.destroy', props.student.id));
}

const crumbs = [
    { label: 'Siswa', href: route('siswa.index') },
    { label: 'Detail siswa' },
];
</script>

<template>
    <AppLayout title="Detail siswa" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Detail siswa</h1>
            <p class="text-muted-foreground text-sm leading-5">
                Data siswa dan kelengkapan dokumen persyaratan.
            </p>
        </div>

        <div class="flex flex-col gap-6">
            <section
                class="bg-card flex flex-col gap-4 rounded-lg border p-6 sm:flex-row sm:items-center sm:gap-6"
            >
                <div class="flex min-w-0 flex-1 items-center gap-4">
                    <Avatar class="size-16 shrink-0">
                        <AvatarFallback
                            class="bg-neutral-soft text-neutral-foreground text-2xl font-semibold"
                        >
                            {{ initials(student.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex min-w-0 flex-col items-start gap-1">
                        <h2
                            class="text-2xl leading-8 font-semibold break-words"
                        >
                            {{ student.name }}
                        </h2>
                        <p class="text-muted-foreground text-sm leading-5">
                            NISN {{ student.nisn }}
                        </p>
                        <span
                            class="inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium"
                            :class="
                                eligibility.eligible
                                    ? 'bg-success-soft text-success'
                                    : 'bg-neutral-soft text-neutral-foreground'
                            "
                        >
                            {{
                                eligibility.eligible
                                    ? 'Eligible'
                                    : 'Belum eligible'
                            }}
                        </span>
                        <ul
                            v-if="!eligibility.eligible"
                            class="text-muted-foreground list-disc pl-4 text-sm leading-5"
                        >
                            <li
                                v-for="reason in eligibility.reasons"
                                :key="reason"
                            >
                                {{ reason }}
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <Button
                        v-if="can(PERMISSIONS.SiswaUbah)"
                        as-child
                        variant="outline"
                        class="h-10 px-4"
                    >
                        <Link :href="route('siswa.edit', student.id)">
                            <Pencil class="size-4" aria-hidden="true" />
                            Ubah
                        </Link>
                    </Button>
                    <RowActions
                        v-if="can(PERMISSIONS.SiswaHapus)"
                        :label="`Aksi lainnya untuk ${student.name}`"
                        outlined
                    >
                        <DropdownMenuItem
                            class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
                            @select="confirmOpen = true"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                            Hapus
                        </DropdownMenuItem>
                    </RowActions>
                </div>
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">
                    Informasi siswa
                </h2>
                <InfoList :items="info" />
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">Dokumen</h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Kartu Keluarga dan Akta Kelahiran wajib diunggah agar
                        siswa eligible.
                    </p>
                </div>
                <div class="grid gap-6 md:grid-cols-2">
                    <FileUpload
                        v-for="doc in docTypes"
                        :key="doc.type"
                        :label="doc.label"
                        :existing="existing(doc.type)"
                        :can-upload="can(PERMISSIONS.DokumenUnggah)"
                        :can-delete="can(PERMISSIONS.DokumenHapus)"
                        :upload-url="
                            route('siswa.dokumen.store', [student.id, doc.type])
                        "
                        :delete-url="
                            route('siswa.dokumen.destroy', [
                                student.id,
                                doc.type,
                            ])
                        "
                    />
                </div>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${student.name}?`"
            description="Data siswa dan dokumennya akan dihapus permanen."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
