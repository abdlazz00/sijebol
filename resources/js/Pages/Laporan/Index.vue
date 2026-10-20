<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay, formatTimeRange } from '@/lib/date';
import { formatNumber } from '@/lib/format';
import { recapTable, type RecapTableRow } from '@/lib/recap';
import type { Recap, ReportFilters, SessionRecap } from '@/types/report';
import { router, usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, reactive } from 'vue';

const props = defineProps<{
    recap: Recap;
    session_recap: SessionRecap;
    filters: ReportFilters;
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const form = reactive({
    dari: props.filters.dari ?? '',
    sampai: props.filters.sampai ?? '',
});
const hasFilters = computed(() => form.dari !== '' || form.sampai !== '');
const table = computed(() => recapTable(props.recap));

/** Hanya kirim batas yang terisi. */
const query = (v: { dari: string; sampai: string }) =>
    Object.fromEntries(Object.entries(v).filter(([, x]) => x !== ''));

// Ekspor mengikuti filter yang sedang diterapkan di server, bukan isian yang belum disimpan.
const applied = computed(() =>
    query({
        dari: props.filters.dari ?? '',
        sampai: props.filters.sampai ?? '',
    }),
);
const exportHref = computed(() => route('laporan.ekspor', applied.value));
const sessionExportHref = computed(() =>
    route('laporan.ekspor-sesi', applied.value),
);

const numeric = 'w-[104px] text-right';
const sessionColumns = [
    { key: 'date', label: 'Sesi', class: 'min-w-[200px]' },
    { key: 'school', label: 'Sekolah dan lokasi', class: 'min-w-[200px]' },
    { key: 'quota', label: 'Kuota', class: numeric },
    { key: 'taken', label: 'Terisi', class: numeric },
    { key: 'present', label: 'Sudah rekaman', class: numeric },
    { key: 'not_recorded', label: 'Belum rekaman', class: numeric },
    { key: 'absent', label: 'Tidak hadir', class: numeric },
    { key: 'attendance', label: 'Kehadiran', class: numeric },
];
// Kolom angka (setelah Sesi dan Sekolah dan lokasi) memakai slot sel yang sama.
const numericKeys = sessionColumns.slice(2);

type SessionTableRow = Record<string, string | number | boolean | null>;
const sessionRows = computed<SessionTableRow[]>(() => {
    const { rows, totals } = props.session_recap;
    if (!rows.length) return [];

    return [
        ...rows.map((r): SessionTableRow => ({ ...r, isTotal: false })),
        { ...totals, date: '', school: '', location: '', isTotal: true },
    ];
});

function sessionCell(row: SessionTableRow, key: string): string {
    const value = row[key];
    if (key === 'attendance') return value === null ? '—' : `${value}%`;

    return typeof value === 'number' ? formatNumber(value) : String(value);
}

function apply() {
    router.get(route('laporan.index'), query(form), { preserveScroll: true });
}

function reset() {
    form.dari = '';
    form.sampai = '';
    apply();
}

function cell(row: RecapTableRow, key: string): string {
    const value = row[key];

    return typeof value === 'number' ? formatNumber(value) : String(value);
}
</script>

<template>
    <AppLayout title="Laporan">
        <PageHeader
            title="Rekap pendaftaran"
            description="Jumlah pendaftaran sesi per sekolah dan status, menurut tanggal pendaftaran."
        >
            <template #actions>
                <Button as-child class="h-10 px-4">
                    <a :href="exportHref">
                        <Download class="size-4" aria-hidden="true" />
                        Ekspor Excel<span class="sr-only">
                            rekap pendaftaran</span
                        >
                    </a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <form
                class="grid grid-cols-2 items-start gap-4 lg:flex"
                novalidate
                @submit.prevent="apply"
            >
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Dari tanggal"
                    :error="errors.dari"
                    class="lg:w-[200px]"
                >
                    <Input
                        :id="id"
                        v-model="form.dari"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Sampai tanggal"
                    :error="errors.sampai"
                    class="lg:w-[200px]"
                >
                    <Input
                        :id="id"
                        v-model="form.sampai"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <div class="col-span-2 flex items-center gap-2 lg:pt-7">
                    <Button type="submit" class="h-10 px-4">Terapkan</Button>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="outline"
                        class="h-10 px-4"
                        @click="reset"
                    >
                        Reset
                    </Button>
                </div>
            </form>

            <div class="flex flex-col gap-4">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    label="Rekap pendaftaran per sekolah"
                    :columns="table.columns"
                    :rows="table.rows"
                    row-class="h-12"
                    table-class="min-w-[960px]"
                >
                    <template
                        v-for="column in table.columns"
                        :key="column.key"
                        #[`cell-${column.key}`]="{ row }"
                    >
                        <span :class="{ 'font-semibold': row.isTotal }">{{
                            cell(row, column.key)
                        }}</span>
                    </template>
                    <template #empty>
                        <EmptyState
                            title="Belum ada sekolah"
                            description="Tambahkan sekolah di Master Data untuk melihat rekap."
                        />
                    </template>
                </DataTable>
            </div>

            <section class="flex flex-col gap-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex max-w-[640px] flex-col gap-1">
                        <h2 class="text-lg leading-7 font-semibold">
                            Kehadiran per sesi
                        </h2>
                        <p class="text-muted-foreground text-sm leading-5">
                            Jumlah siswa per sesi rekaman, menurut tanggal sesi.
                            Kehadiran = (sudah rekaman + belum rekaman) dibagi
                            (sudah rekaman + belum rekaman + tidak hadir). Sesi
                            yang dibatalkan tidak dimuat.
                        </p>
                    </div>
                    <Button as-child class="h-10 shrink-0 px-4">
                        <a :href="sessionExportHref">
                            <Download class="size-4" aria-hidden="true" />
                            Ekspor Excel<span class="sr-only">
                                kehadiran per sesi</span
                            >
                        </a>
                    </Button>
                </div>

                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    label="Kehadiran per sesi"
                    :columns="sessionColumns"
                    :rows="sessionRows"
                    row-class="h-14"
                    table-class="min-w-[960px]"
                >
                    <template #cell-date="{ row }">
                        <span v-if="row.isTotal" class="font-semibold"
                            >Total</span
                        >
                        <div v-else class="min-w-0">
                            <p class="text-sm leading-5 font-medium">
                                {{ formatDateWithDay(String(row.date)) }}
                            </p>
                            <p class="text-muted-foreground text-xs leading-4">
                                {{
                                    formatTimeRange(
                                        String(row.start_time),
                                        String(row.end_time),
                                    )
                                }}
                            </p>
                        </div>
                    </template>
                    <template #cell-school="{ row }">
                        <span v-if="row.isTotal" />
                        <div v-else class="min-w-0">
                            <p class="text-sm leading-5 font-medium">
                                {{ row.school }}
                            </p>
                            <p class="text-muted-foreground text-xs leading-4">
                                {{ row.location }}
                            </p>
                        </div>
                    </template>
                    <template
                        v-for="column in numericKeys"
                        :key="column.key"
                        #[`cell-${column.key}`]="{ row }"
                    >
                        <span :class="{ 'font-semibold': row.isTotal }">{{
                            sessionCell(row, column.key)
                        }}</span>
                    </template>
                    <template #empty>
                        <EmptyState
                            title="Tidak ada sesi pada rentang ini"
                            description="Ubah rentang tanggal, atau buat sesi di menu Sesi rekaman."
                        />
                    </template>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
