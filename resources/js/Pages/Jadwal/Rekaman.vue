<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EntryChecklist from '@/Components/shared/EntryChecklist.vue';
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay } from '@/lib/date';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type {
    ChecklistItem,
    ParticipantRow,
    ScheduleDetail,
} from '@/types/schedule';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    entries: ChecklistItem[];
    marked: ParticipantRow[];
    can_mark: boolean;
}>();

const { can } = usePermission();
const editable = computed(
    () => props.can_mark && can(PERMISSIONS.RekamanTandai),
);

const statuses = [
    {
        value: 'sudah_rekaman',
        label: 'Sudah rekaman',
        hint: 'Siswa sudah direkam pada kegiatan ini.',
    },
    { value: 'tidak_hadir', label: 'Tidak hadir', hint: 'Siswa tidak datang.' },
    {
        value: 'belum_rekaman',
        label: 'Belum rekaman',
        hint: 'Siswa hadir tetapi belum sempat direkam.',
    },
];

const form = useForm({
    status: '',
    entry_ids: [] as number[],
    note: '',
});
const clientErrors = ref<Record<string, string>>({});
const error = (key: 'status' | 'entry_ids' | 'note') =>
    clientErrors.value[key] ?? form.errors[key];
const confirmOpen = ref(false);

const statusLabel = computed(() => registrationStatus(form.status).label);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.entry_ids.length) errors.entry_ids = 'Pilih minimal satu siswa.';
    if (!form.status) errors.status = 'Pilih status penandaan.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();
    confirmOpen.value = true;
}

function save() {
    form.post(route('jadwal.rekaman.store', props.schedule.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: focusFirstInvalid,
    });
}

const markedColumns = [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
];

const crumbs = computed(() => [
    { label: 'Jadwal', href: route('jadwal.index') },
    {
        label: formatDateWithDay(props.schedule.date),
        href: route('jadwal.show', props.schedule.id),
    },
    { label: 'Penandaan rekaman' },
]);
</script>

<template>
    <AppLayout title="Penandaan rekaman" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Penandaan rekaman</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{ schedule.location }} · {{ formatDateWithDay(schedule.date)
                }}<template v-if="schedule.school">
                    · {{ schedule.school }}</template
                >
            </p>
        </div>

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="!can_mark" variant="warning">
                Penandaan dibuka pada {{ formatDate(schedule.date) }}.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Siswa terjadwal
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Pilih siswa, tentukan status, lalu simpan. Status akhir
                        tidak dapat diubah.
                    </p>
                </div>

                <form
                    class="flex flex-col gap-4"
                    novalidate
                    @submit.prevent="submit"
                >
                    <EntryChecklist
                        v-model="form.entry_ids"
                        :items="entries"
                        :disabled="!editable"
                        empty-text="Tidak ada siswa yang menunggu penandaan."
                    />
                    <p
                        v-if="error('entry_ids')"
                        class="text-danger text-xs"
                        role="alert"
                    >
                        {{ error('entry_ids') }}
                    </p>

                    <template
                        v-if="entries.length && can(PERMISSIONS.RekamanTandai)"
                    >
                        <fieldset
                            class="flex flex-col gap-3"
                            :disabled="!editable"
                        >
                            <legend class="sr-only">Status penandaan</legend>
                            <label
                                v-for="s in statuses"
                                :key="s.value"
                                class="flex cursor-pointer items-start gap-3 text-sm leading-5"
                            >
                                <input
                                    v-model="form.status"
                                    type="radio"
                                    name="status"
                                    :value="s.value"
                                    class="accent-primary mt-1 size-4"
                                    :aria-invalid="!!error('status')"
                                />
                                <span>
                                    <span class="block font-medium">{{
                                        s.label
                                    }}</span>
                                    <span
                                        class="text-muted-foreground text-xs"
                                        >{{ s.hint }}</span
                                    >
                                </span>
                            </label>
                            <p
                                v-if="error('status')"
                                class="text-danger text-xs"
                                role="alert"
                            >
                                {{ error('status') }}
                            </p>
                        </fieldset>

                        <FormField
                            v-slot="{ id, describedBy, invalid }"
                            label="Catatan"
                            :error="error('note')"
                            hint="Opsional; berlaku untuk semua siswa yang dipilih."
                        >
                            <Textarea
                                :id="id"
                                v-model="form.note"
                                rows="3"
                                maxlength="500"
                                :disabled="!editable"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </FormField>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                class="h-10 px-4"
                                :disabled="!editable || form.processing"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                {{
                                    form.processing
                                        ? 'Menyimpan…'
                                        : 'Simpan penandaan'
                                }}
                            </Button>
                        </div>
                    </template>
                </form>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base leading-6 font-semibold">
                    Sudah ditandai
                </h2>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="markedColumns"
                    :rows="marked"
                    row-class="h-14"
                    table-class="min-w-[480px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <p class="truncate text-sm leading-5 font-semibold">
                                {{ row.student.name }}
                            </p>
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
                            Belum ada siswa yang ditandai.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Tandai ${form.entry_ids.length} siswa sebagai ${statusLabel}?`"
            description="Status akhir tidak dapat diubah setelah disimpan."
            confirm-label="Simpan penandaan"
            @confirm="save"
        />
    </AppLayout>
</template>
