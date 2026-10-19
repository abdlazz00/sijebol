<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    formatDate,
    formatDateWithDay,
    formatTimeRange,
    formatWhen,
} from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import type { Paginator } from '@/types/access';
import type { QueueRow, QueueSession } from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { BadgeCheck, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    queue: Paginator<QueueRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
    sessions: QueueSession[];
}>();

const { filters, loading, apply, reset } = useDataTable('/verifikasi', {
    search: props.filters.search ?? '',
    school_id: props.filters.school_id ?? '',
    schedule_id: props.filters.schedule_id ?? '',
});

watch(() => [filters.school_id, filters.schedule_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);
const sessionOptions = computed(() =>
    props.sessions.map((s) => ({
        value: s.id,
        label: `${formatDate(s.date)} · ${formatTimeRange(s.start_time, s.end_time)} · ${s.location}${s.school ? ` (${s.school})` : ''}`,
    })),
);

const { can } = usePermission();
const canDecide = computed(() => can(PERMISSIONS.VerifikasiPutuskan));

const selected = ref<number[]>([]);
const approveOpen = ref(false);
const approving = ref(false);
const approveError = ref<string | null>(null);

// Pilihan hanya berlaku untuk halaman yang sedang dilihat.
watch(
    () => props.queue.data,
    () => (selected.value = []),
);

const allSelected = computed(
    () =>
        props.queue.data.length > 0 &&
        selected.value.length === props.queue.data.length,
);

function toggle(id: number, on: boolean) {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((x) => x !== id);
}

function toggleAll(on: boolean) {
    selected.value = on ? props.queue.data.map((r) => r.id) : [];
}

function approve() {
    router.post(
        route('verifikasi.setujui'),
        { entry_ids: selected.value },
        {
            preserveScroll: true,
            onStart: () => {
                approving.value = true;
                approveError.value = null;
            },
            onFinish: () => (approving.value = false),
            onSuccess: () => (selected.value = []),
            onError: (errors) =>
                (approveError.value = errors.entry_ids ?? 'Gagal menyetujui.'),
        },
    );
}

const columns = computed(() => [
    ...(canDecide.value && props.queue.data.length
        ? [{ key: 'select', label: 'Pilih', class: 'w-[64px]' }]
        : []),
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'session', label: 'Sesi' },
    { key: 'since', label: 'Mendaftar', class: 'w-[160px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' },
]);
</script>

<template>
    <AppLayout title="Antrean verifikasi">
        <PageHeader
            title="Antrean verifikasi"
            description="Periksa siswa yang mendaftar sesi rekaman; putuskan satu per satu atau setujui beberapa sekaligus."
        />

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari siswa"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari nama atau NISN"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField
                    v-if="sessions.length"
                    v-slot="{ id }"
                    label="Sesi rekaman"
                    class="col-span-2 lg:w-[320px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.schedule_id"
                        :options="sessionOptions"
                        all-label="Semua sesi"
                    />
                </FormField>
                <FormField
                    v-if="schools"
                    v-slot="{ id }"
                    label="Sekolah"
                    class="col-span-2 lg:w-[240px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.school_id"
                        :options="schoolOptions"
                        all-label="Semua sekolah"
                    />
                </FormField>
            </div>

            <div class="flex flex-col gap-4">
                <InlineAlert v-if="approveError">{{
                    approveError
                }}</InlineAlert>
                <div
                    v-if="canDecide && queue.data.length"
                    class="flex flex-wrap items-center gap-3"
                >
                    <Checkbox
                        id="pilih-semua"
                        :model-value="allSelected"
                        @update:model-value="toggleAll($event === true)"
                    />
                    <label for="pilih-semua" class="text-sm leading-5"
                        >Pilih semua di halaman ini</label
                    >
                    <p
                        role="status"
                        class="text-muted-foreground text-sm leading-5"
                    >
                        {{ selected.length }} dipilih
                    </p>
                    <Button
                        type="button"
                        class="ml-auto h-10 px-4"
                        :disabled="!selected.length || approving"
                        @click="approveOpen = true"
                    >
                        <BadgeCheck class="size-4" aria-hidden="true" />
                        Setujui terpilih
                    </Button>
                </div>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    label="Antrean verifikasi"
                    :columns="columns"
                    :rows="queue.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-select="{ row }">
                        <Checkbox
                            :id="`pilih-${row.id}`"
                            :model-value="selected.includes(row.id)"
                            :aria-label="`Pilih ${row.student.name}`"
                            @update:model-value="
                                toggle(row.id, $event === true)
                            "
                        />
                    </template>
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
                    <template #cell-session="{ row }">
                        <div class="min-w-0">
                            <p class="truncate text-sm leading-5 font-medium">
                                {{ formatDateWithDay(row.session.date) }}
                            </p>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{
                                    formatTimeRange(
                                        row.session.start_time,
                                        row.session.end_time,
                                    )
                                }}
                                · {{ row.session.location }}
                            </p>
                        </div>
                    </template>
                    <template #cell-since="{ row }">{{
                        formatWhen(row.since)
                    }}</template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button as-child class="h-9 px-3">
                                <Link :href="route('verifikasi.show', row.id)">
                                    Periksa
                                    <span class="sr-only">{{
                                        row.student.name
                                    }}</span>
                                </Link>
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada siswa yang sesuai dengan pencarian atau filter Anda."
                        >
                            <template #action>
                                <Button
                                    variant="outline"
                                    class="h-9"
                                    @click="reset"
                                    >Reset filter</Button
                                >
                            </template>
                        </EmptyState>
                        <EmptyState
                            v-else
                            :icon="BadgeCheck"
                            title="Tidak ada antrean verifikasi"
                            description="Siswa yang mendaftar sesi rekaman akan muncul di sini."
                        />
                    </template>
                </DataTable>
                <Pagination :paginator="queue" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="approveOpen"
            :title="`Setujui ${selected.length} siswa?`"
            description="Kelayakan setiap siswa diperiksa ulang. Bila ada yang tidak layak, tidak ada yang disetujui."
            confirm-label="Setujui"
            @confirm="approve"
        />
    </AppLayout>
</template>
