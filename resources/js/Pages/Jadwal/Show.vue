<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import FormField from '@/Components/shared/FormField.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { Textarea } from '@/Components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay, formatTimeRange } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { ParticipantRow, ScheduleDetail } from '@/types/schedule';
import { Link, router } from '@inertiajs/vue3';
import { Ban, ClipboardCheck, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    participants: ParticipantRow[];
}>();

const { can } = usePermission();
const canEdit = computed(() => can(PERMISSIONS.JadwalUbah));
const canCancel = computed(
    () => canEdit.value && !props.schedule.cancelled && !props.schedule.is_past,
);
const canDelete = computed(
    () => canEdit.value && props.participants.length === 0,
);

// Pendaftaran yang ikut dibatalkan saat sesi dibatalkan (belum sampai hari kehadiran).
const CANCELLABLE = ['menunggu_verifikasi', 'perlu_perbaikan', 'terdaftar'];
const affected = computed(
    () =>
        props.participants.filter((p) => CANCELLABLE.includes(p.status)).length,
);

// Rincian kursi tertahan menurut status, tertulis (bukan hanya angka).
const HOLDING = [
    'menunggu_verifikasi',
    'perlu_perbaikan',
    'terdaftar',
    'sudah_rekaman',
    'belum_rekaman',
];
const seatBreakdown = computed(() =>
    HOLDING.map((status) => ({
        status,
        total: props.participants.filter((p) => p.status === status).length,
    }))
        .filter((s) => s.total > 0)
        .map(
            (s) =>
                `${s.total} ${registrationStatus(s.status).label.toLowerCase()}`,
        )
        .join(' · '),
);

const info = computed(() => [
    { label: 'Tanggal', value: formatDateWithDay(props.schedule.date) },
    {
        label: 'Jam',
        value: formatTimeRange(
            props.schedule.start_time,
            props.schedule.end_time,
        ),
    },
    { label: 'Lokasi', value: props.schedule.location },
    { label: 'Sekolah', value: props.schedule.school },
    {
        label: 'Kuota',
        value: `${props.schedule.taken} dari ${props.schedule.quota} kursi${
            seatBreakdown.value ? ` (${seatBreakdown.value})` : ''
        }`,
    },
    {
        label: 'Pendaftaran',
        value: props.schedule.cancelled
            ? 'Ditutup (sesi dibatalkan)'
            : `Dibuka sampai ${formatDate(props.schedule.last_registration_day)}`,
    },
    { label: 'Dibuat oleh', value: props.schedule.created_by ?? null },
]);

const columns = [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
];

const cancelOpen = ref(false);
const cancelNote = ref('');

function startCancel() {
    cancelNote.value = '';
    cancelOpen.value = true;
}

function cancelSession() {
    router.post(
        route('jadwal.batalkan', props.schedule.id),
        { note: cancelNote.value.trim() },
        { preserveScroll: true },
    );
}

const deleteOpen = ref(false);

function destroy() {
    router.delete(route('jadwal.destroy', props.schedule.id));
}

const crumbs = computed(() => [
    { label: 'Sesi rekaman', href: route('jadwal.index') },
    { label: formatDateWithDay(props.schedule.date) },
]);
</script>

<template>
    <AppLayout title="Detail sesi rekaman" :breadcrumbs="crumbs">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold break-words">
                    {{ schedule.location }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ formatDateWithDay(schedule.date) }} ·
                    {{ formatTimeRange(schedule.start_time, schedule.end_time)
                    }}<template v-if="schedule.cancelled">
                        · Dibatalkan</template
                    ><template v-else-if="schedule.is_past">
                        · Sudah lewat</template
                    >
                </p>
            </div>
            <div
                class="flex w-full items-center gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none"
            >
                <Button
                    v-if="canEdit && !schedule.cancelled"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.edit', schedule.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Ubah
                    </Link>
                </Button>
                <Button
                    v-if="can(PERMISSIONS.RekamanLihat)"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.rekaman.show', schedule.id)">
                        <ClipboardCheck class="size-4" aria-hidden="true" />
                        Penandaan rekaman
                    </Link>
                </Button>
                <Button
                    v-if="canCancel"
                    type="button"
                    variant="outline"
                    class="text-danger h-10 px-4"
                    @click="startCancel"
                >
                    <Ban class="size-4" aria-hidden="true" />
                    Batalkan sesi
                </Button>
                <RowActions
                    v-if="canDelete"
                    label="Aksi lainnya untuk sesi ini"
                    outlined
                >
                    <DropdownMenuItem
                        class="text-danger h-10 gap-3 px-3 text-sm font-medium"
                        @select="deleteOpen = true"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Hapus sesi
                    </DropdownMenuItem>
                </RowActions>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <InlineAlert v-if="schedule.cancelled" variant="warning">
                Sesi ini dibatalkan. Pendaftaran yang belum sampai hari
                kehadiran ikut dibatalkan dan sesi tidak dapat diubah lagi.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Informasi</h2>
                <InfoList :items="info" />
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base leading-6 font-semibold">Peserta</h2>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    label="Peserta sesi"
                    :columns="columns"
                    :rows="participants"
                    row-class="h-16"
                    table-class="min-w-[560px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('siswa.show', row.student.id)"
                                class="block truncate py-1 text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.student.name }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.student.nisn }}
                            </p>
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" kind="registration" />
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa yang mendaftar di sesi ini.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="cancelOpen"
            :title="`Batalkan sesi ${schedule.location}?`"
            :description="`${affected} pendaftaran akan dibatalkan dan kursinya dilepas. Siswa dapat mendaftar sesi lain. Sesi yang dibatalkan tidak dapat dibuka kembali.`"
            confirm-label="Batalkan sesi"
            destructive
            :confirm-disabled="cancelNote.trim().length < 5"
            @confirm="cancelSession"
        >
            <FormField
                v-slot="{ id, describedBy }"
                label="Catatan"
                hint="Wajib, minimal 5 karakter; jelaskan alasan pembatalan."
            >
                <Textarea
                    :id="id"
                    v-model="cancelNote"
                    rows="3"
                    maxlength="500"
                    :aria-describedby="describedBy"
                />
            </FormField>
        </ConfirmDialog>

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Hapus sesi ini?"
            description="Sesi tanpa peserta dihapus permanen dan tercatat di log aktivitas."
            confirm-label="Hapus sesi"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
