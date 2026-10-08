<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import StatusTimeline from '@/Components/shared/StatusTimeline.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { EntryRow, RegistrationDetail } from '@/types/registration';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    registration: RegistrationDetail;
    entries: EntryRow[];
}>();

const { can } = usePermission();
const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const entryError = (e: EntryRow) => errors.value[`student_ids.${e.student.id}`];

const isDraft = computed(() => props.registration.submitted_at === null);
const submitOpen = ref(false);
const deleteOpen = ref(false);
const historyOf = ref<EntryRow | null>(null);

const info = computed(() => [
    { label: 'Sekolah', value: props.registration.school },
    { label: 'Dibuat oleh', value: props.registration.created_by },
    {
        label: 'Perkiraan peserta',
        value: props.registration.expected_count?.toString() ?? null,
    },
    {
        label: 'Diajukan',
        value: props.registration.submitted_at
            ? formatDateTime(props.registration.submitted_at)
            : 'Belum diajukan',
    },
]);

const columns = [
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
    { key: 'note', label: 'Catatan petugas' },
    { key: 'actions', label: 'Aksi', class: 'w-[200px] text-right' },
];

function submit() {
    router.post(
        route('pendaftaran.submit', props.registration.id),
        {},
        { preserveScroll: true },
    );
}

function destroy() {
    router.delete(route('pendaftaran.destroy', props.registration.id));
}

function resubmit(entry: EntryRow) {
    router.post(
        route('pendaftaran.resubmit', [props.registration.id, entry.id]),
        {},
        { preserveScroll: true },
    );
}

const crumbs = computed(() => [
    { label: 'Pendaftaran', href: route('pendaftaran.index') },
    { label: props.registration.title },
]);
</script>

<template>
    <AppLayout :title="registration.title" :breadcrumbs="crumbs">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold break-words">
                    {{ registration.title }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ registration.students_count }} siswa ·
                    {{ isDraft ? 'Draf' : 'Sudah diajukan' }}
                </p>
            </div>
            <div
                v-if="isDraft"
                class="flex w-full items-center gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none"
            >
                <Button
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('pendaftaran.edit', registration.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Ubah
                    </Link>
                </Button>
                <Button
                    v-if="can(PERMISSIONS.PendaftaranAjukan)"
                    type="button"
                    class="h-10 px-4"
                    @click="submitOpen = true"
                >
                    <Send class="size-4" aria-hidden="true" />
                    Ajukan
                </Button>
                <RowActions
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    :label="`Aksi lainnya untuk ${registration.title}`"
                    outlined
                >
                    <DropdownMenuItem
                        class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
                        @select="deleteOpen = true"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Hapus
                    </DropdownMenuItem>
                </RowActions>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <InlineAlert v-if="errors.student_ids">
                {{ errors.student_ids }} Perbaiki data siswa yang ditandai, lalu
                ajukan lagi.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Informasi</h2>
                <InfoList :items="info" />
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base leading-6 font-semibold">Siswa</h2>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="entries"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('siswa.show', row.student.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.student.name }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.student.nisn }}
                            </p>
                            <p
                                v-if="entryError(row)"
                                class="text-danger text-xs leading-4"
                            >
                                {{ entryError(row) }}
                            </p>
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" kind="registration" />
                    </template>
                    <template #cell-note="{ row }">
                        <span
                            v-if="row.note"
                            class="text-sm leading-5 break-words"
                            >{{ row.note }}</span
                        >
                        <span v-else class="text-muted-foreground">—</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="row.status === 'perlu_perbaikan'"
                                as-child
                                variant="outline"
                                class="h-9 px-3"
                            >
                                <Link
                                    :href="route('siswa.show', row.student.id)"
                                    >Perbaiki data</Link
                                >
                            </Button>
                            <Button
                                v-if="
                                    row.status === 'perlu_perbaikan' &&
                                    can(PERMISSIONS.PendaftaranAjukan)
                                "
                                type="button"
                                class="h-9 px-3"
                                @click="resubmit(row)"
                            >
                                Ajukan ulang
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="historyOf = row"
                            >
                                Riwayat
                                <span class="sr-only"
                                    >untuk {{ row.student.name }}</span
                                >
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa di pendaftaran ini.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <Dialog :open="historyOf !== null" @update:open="historyOf = null">
            <DialogContent class="rounded-xl">
                <DialogHeader>
                    <DialogTitle>Riwayat status</DialogTitle>
                    <DialogDescription>
                        {{ historyOf?.student.name }} ·
                        {{
                            historyOf
                                ? registrationStatus(historyOf.status).label
                                : ''
                        }}
                    </DialogDescription>
                </DialogHeader>
                <StatusTimeline :items="historyOf?.history ?? []" />
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="submitOpen"
            :title="`Ajukan ${registration.students_count} siswa untuk diverifikasi?`"
            description="Setelah diajukan, pendaftaran tidak dapat diubah."
            confirm-label="Ajukan"
            @confirm="submit"
        />
        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`Hapus ${registration.title}?`"
            description="Draf dan daftar siswanya akan dihapus."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
