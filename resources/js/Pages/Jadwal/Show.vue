<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EntryChecklist from '@/Components/shared/EntryChecklist.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import type {
    ChecklistItem,
    ParticipantRow,
    ScheduleDetail,
    ScheduleTarget,
} from '@/types/schedule';
import { Link, router } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    MoveRight,
    Pencil,
    Trash2,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    participants: ParticipantRow[];
    candidates: ChecklistItem[];
    targets: ScheduleTarget[];
}>();

const { can } = usePermission();
const canEdit = computed(() => can(PERMISSIONS.JadwalUbah));
const canAssign = computed(() => canEdit.value && !props.schedule.is_past);

const info = computed(() => [
    { label: 'Tanggal', value: formatDateWithDay(props.schedule.date) },
    { label: 'Lokasi', value: props.schedule.location },
    { label: 'Sekolah', value: props.schedule.school },
    { label: 'Dibuat oleh', value: props.schedule.created_by ?? null },
]);

const columns = computed(() => [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
    ...(canEdit.value
        ? [{ key: 'actions', label: 'Aksi', class: 'w-[220px] text-right' }]
        : []),
]);

const addOpen = ref(false);
const picked = ref<number[]>([]);
const assigning = ref(false);
const assignError = ref<string | null>(null);

function openAdd() {
    picked.value = [];
    assignError.value = null;
    addOpen.value = true;
}

function assign() {
    router.post(
        route('jadwal.siswa.store', props.schedule.id),
        { entry_ids: picked.value },
        {
            preserveScroll: true,
            onStart: () => (assigning.value = true),
            onFinish: () => (assigning.value = false),
            onSuccess: () => (addOpen.value = false),
            onError: (errors) =>
                (assignError.value = errors.entry_ids ?? 'Gagal menjadwalkan.'),
        },
    );
}

const removing = ref<ParticipantRow | null>(null);
const removeOpen = computed({
    get: () => removing.value !== null,
    set: (open: boolean) => {
        if (!open) removing.value = null;
    },
});

function remove() {
    if (removing.value)
        router.delete(
            route('jadwal.siswa.destroy', [
                props.schedule.id,
                removing.value.id,
            ]),
            { preserveScroll: true },
        );
}

const canDelete = computed(
    () => canEdit.value && props.participants.length === 0,
);
const deleteOpen = ref(false);

function destroy() {
    router.delete(route('jadwal.destroy', props.schedule.id));
}

const moving = ref<ParticipantRow | null>(null);
const targetId = ref<number | null>(null);
const moveOpen = computed({
    get: () => moving.value !== null,
    set: (open: boolean) => {
        if (!open) moving.value = null;
    },
});
const targetOptions = computed(() =>
    props.targets.map((t) => ({
        value: t.id,
        label: `${formatDate(t.date)} · ${t.location}`,
    })),
);

function startMove(row: ParticipantRow) {
    targetId.value = null;
    moving.value = row;
}

function move() {
    if (!moving.value || targetId.value === null) return;
    router.post(
        route('jadwal.siswa.pindah', [props.schedule.id, moving.value.id]),
        { target_schedule_id: targetId.value },
        { preserveScroll: true },
    );
}

const crumbs = computed(() => [
    { label: 'Jadwal', href: route('jadwal.index') },
    { label: formatDateWithDay(props.schedule.date) },
]);
</script>

<template>
    <AppLayout title="Detail jadwal" :breadcrumbs="crumbs">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold break-words">
                    {{ schedule.location }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ formatDateWithDay(schedule.date)
                    }}<template v-if="schedule.is_past">
                        · Sudah lewat</template
                    >
                </p>
            </div>
            <div
                class="flex w-full items-center gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none"
            >
                <Button
                    v-if="canEdit"
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
                    v-if="canDelete"
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    @click="deleteOpen = true"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Hapus
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
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Informasi</h2>
                <InfoList :items="info" />
            </section>

            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base leading-6 font-semibold">Peserta</h2>
                    <Button
                        v-if="canAssign"
                        type="button"
                        class="h-10 px-4"
                        @click="openAdd"
                    >
                        <UserPlus class="size-4" aria-hidden="true" />
                        Tambah siswa
                    </Button>
                </div>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="participants"
                    row-class="h-16"
                    table-class="min-w-[640px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('siswa.show', row.student.id)"
                                class="block truncate py-0.5 text-sm leading-5 font-semibold hover:underline"
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
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-1">
                            <Button
                                v-if="
                                    row.status === 'terjadwal' && targets.length
                                "
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="startMove(row)"
                            >
                                <MoveRight class="size-4" aria-hidden="true" />
                                Pindahkan
                                <span class="sr-only">{{
                                    row.student.name
                                }}</span>
                            </Button>
                            <Button
                                v-if="row.status === 'terjadwal'"
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="removing = row"
                            >
                                Keluarkan
                                <span class="sr-only"
                                    >{{ row.student.name }} dari jadwal</span
                                >
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa di jadwal ini.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <Dialog v-model:open="addOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto rounded-xl">
                <DialogHeader>
                    <DialogTitle>Tambah siswa ke jadwal</DialogTitle>
                    <DialogDescription>
                        Siswa Terverifikasi dari sekolah ini yang belum
                        dijadwalkan. Operator sekolah akan diberi tahu.
                    </DialogDescription>
                </DialogHeader>
                <InlineAlert v-if="assignError">{{ assignError }}</InlineAlert>
                <EntryChecklist
                    v-model="picked"
                    :items="candidates"
                    empty-text="Belum ada siswa terverifikasi yang belum dijadwalkan."
                />
                <DialogFooter>
                    <Button variant="outline" @click="addOpen = false"
                        >Batal</Button
                    >
                    <Button
                        :disabled="!picked.length || assigning"
                        @click="assign"
                    >
                        Jadwalkan {{ picked.length }} siswa
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`Keluarkan ${removing?.student.name ?? ''}?`"
            description="Siswa kembali menjadi Terverifikasi dan dapat dijadwalkan ulang."
            confirm-label="Keluarkan"
            destructive
            @confirm="remove"
        />

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Hapus jadwal ini?"
            description="Jadwal tanpa peserta dihapus permanen dan tercatat di log aktivitas."
            confirm-label="Hapus jadwal"
            destructive
            @confirm="destroy"
        />

        <ConfirmDialog
            v-model:open="moveOpen"
            :title="`Pindahkan ${moving?.student.name ?? ''}?`"
            description="Siswa dipindahkan ke jadwal lain milik sekolah ini dan operator sekolah diberi tahu."
            confirm-label="Pindahkan"
            :confirm-disabled="targetId === null"
            @confirm="move"
        >
            <FormField v-slot="{ id }" label="Jadwal tujuan">
                <SelectField
                    :id="id"
                    v-model="targetId"
                    :options="targetOptions"
                    placeholder="Pilih jadwal tujuan"
                />
            </FormField>
        </ConfirmDialog>
    </AppLayout>
</template>
