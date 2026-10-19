<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay, formatTimeRange } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { Paginator } from '@/types/access';
import type { ScheduleRow } from '@/types/schedule';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CalendarDays,
    ClipboardCheck,
    Eye,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    schedules: Paginator<ScheduleRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/jadwal', {
    search: props.filters.search ?? '',
    period: props.filters.period ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => [filters.period, filters.school_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const periodOptions = [
    { value: 'akan-datang', label: 'Akan datang' },
    { value: 'lewat', label: 'Sudah lewat' },
];
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'date', label: 'Sesi', class: 'w-[300px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'quota', label: 'Kuota', class: 'w-[140px]' },
    { key: 'counts', label: 'Ringkasan status' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
]);
</script>

<template>
    <AppLayout title="Sesi rekaman">
        <PageHeader
            title="Sesi rekaman"
            description="Sesi perekaman per sekolah, lengkap dengan kuota."
        >
            <template #actions>
                <Button
                    v-if="can(PERMISSIONS.JadwalBuat)"
                    as-child
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Buat sesi
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari sesi"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari lokasi"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Periode" class="lg:w-[200px]">
                    <SelectField
                        :id="id"
                        v-model="filters.period"
                        :options="periodOptions"
                        all-label="Semua periode"
                    />
                </FormField>
                <FormField
                    v-if="schools"
                    v-slot="{ id }"
                    label="Sekolah"
                    class="lg:w-[240px]"
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
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    label="Daftar sesi rekaman"
                    :columns="columns"
                    :rows="schedules.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[880px]"
                >
                    <template #cell-date="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('jadwal.show', row.id)"
                                class="block truncate py-1 text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ formatDateWithDay(row.date) }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{
                                    formatTimeRange(
                                        row.start_time,
                                        row.end_time,
                                    )
                                }}<template v-if="row.cancelled">
                                    · Dibatalkan</template
                                ><template v-else-if="row.is_past">
                                    · Sudah lewat</template
                                >
                            </p>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{ row.location }}
                            </p>
                        </div>
                    </template>
                    <template #cell-school="{ row }">{{ row.school }}</template>
                    <template #cell-quota="{ row }">
                        <span class="text-sm"
                            >{{ row.taken }} dari {{ row.quota }} kursi</span
                        >
                    </template>
                    <template #cell-counts="{ row }">
                        <p
                            v-if="!row.participants_count"
                            class="text-muted-foreground text-xs"
                        >
                            Belum ada peserta
                        </p>
                        <ul
                            v-else
                            class="flex flex-wrap gap-x-3 gap-y-1 text-xs"
                        >
                            <li
                                v-for="[status, total] in Object.entries(
                                    row.counts,
                                )"
                                :key="status"
                            >
                                {{ registrationStatus(status).label }}
                                {{ total }}
                            </li>
                        </ul>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <RowActions
                                :label="`Aksi untuk sesi ${row.location}`"
                            >
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('jadwal.show', row.id)">
                                        <Eye
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        can(PERMISSIONS.JadwalUbah) &&
                                        !row.cancelled
                                    "
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('jadwal.edit', row.id)">
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="can(PERMISSIONS.RekamanLihat)"
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="
                                            route('jadwal.rekaman.show', row.id)
                                        "
                                    >
                                        <ClipboardCheck
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Penandaan rekaman
                                    </Link>
                                </DropdownMenuItem>
                            </RowActions>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada sesi yang sesuai dengan pencarian atau filter Anda."
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
                            :icon="CalendarDays"
                            title="Belum ada sesi"
                            description="Sesi rekaman akan muncul di sini setelah dibuat oleh admin kecamatan."
                        >
                            <template
                                v-if="can(PERMISSIONS.JadwalBuat)"
                                #action
                            >
                                <Button as-child class="h-9">
                                    <Link :href="route('jadwal.create')"
                                        >Buat sesi</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="schedules" />
            </div>
        </div>
    </AppLayout>
</template>
