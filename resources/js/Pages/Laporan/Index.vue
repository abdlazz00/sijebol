<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { recapTable, type RecapTableRow } from '@/lib/recap';
import type { Recap, ReportFilters } from '@/types/report';
import { router, usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, reactive } from 'vue';

const props = defineProps<{ recap: Recap; filters: ReportFilters }>();

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
const exportHref = computed(() =>
    route(
        'laporan.ekspor',
        query({
            dari: props.filters.dari ?? '',
            sampai: props.filters.sampai ?? '',
        }),
    ),
);

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
            description="Jumlah entri per sekolah dan status, menurut tanggal pengajuan pendaftaran."
        >
            <template #actions>
                <Button as-child class="h-10 px-4">
                    <a :href="exportHref">
                        <Download class="size-4" aria-hidden="true" />
                        Ekspor Excel
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
        </div>
    </AppLayout>
</template>
