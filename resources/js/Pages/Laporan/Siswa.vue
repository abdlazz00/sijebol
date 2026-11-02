<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { formatNumber } from '@/lib/format';
import type { Paginator } from '@/types/access';
import type {
    StudentRecordGroup,
    StudentRecordGroupKey,
    StudentRecordRow,
} from '@/types/report';
import { usePage } from '@inertiajs/vue3';
import { Download, Search, Users } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, watch } from 'vue';

const props = defineProps<{
    records: Paginator<StudentRecordRow>;
    groups: StudentRecordGroup[];
    filters: Record<string, string | number | undefined>;
    schools: { id: number; name: string }[] | null;
    schedules: { id: number; label: string }[];
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const { filters, loading, apply, reset } = useDataTable(
    route('reports.students'),
    {
        search: props.filters.search ?? '',
        group: props.filters.group ?? '',
        school_id: props.filters.school_id ?? '',
        schedule_id: props.filters.schedule_id ?? '',
        dari: props.filters.dari ?? '',
        sampai: props.filters.sampai ?? '',
    },
);

watch(
    () => [
        filters.group,
        filters.school_id,
        filters.schedule_id,
        filters.dari,
        filters.sampai,
    ],
    apply,
);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);
const scheduleOptions = computed(() =>
    props.schedules.map((s) => ({ value: s.id, label: s.label })),
);

// Ekspor mengikuti filter yang sudah diterapkan di server, bukan isian yang belum dikirim.
const exportHref = computed(() =>
    route('reports.students.export', { filter: props.filters }),
);

function toggleGroup(key: StudentRecordGroupKey) {
    filters.group = filters.group === key ? '' : key;
}

const columns = computed(() => [
    { key: 'name', label: 'Siswa', class: 'min-w-[200px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'session_date', label: 'Tanggal rekaman', class: 'w-[160px]' },
    { key: 'ktp', label: 'KTP-el', class: 'w-[200px]' },
    { key: 'reason', label: 'Alasan', class: 'w-[200px]' },
]);
</script>

<template>
    <AppLayout title="Laporan">
        <PageHeader
            title="Laporan KTP-el pelajar"
            description="Siswa yang sudah rekaman, yang KTP-el-nya langsung terbit, dan yang belum. Terbit berarti memenuhi syarat umur; pencetakan oleh Disdukcapil tidak dicatat di sistem."
        >
            <template #actions>
                <Button as-child class="h-10 px-4">
                    <a :href="exportHref">
                        <Download class="size-4" aria-hidden="true" />
                        Ekspor Excel<span class="sr-only">
                            laporan KTP-el pelajar</span
                        >
                    </a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <button
                    v-for="group in groups"
                    :key="group.key"
                    type="button"
                    :aria-pressed="filters.group === group.key"
                    class="bg-card hover:bg-secondary focus-visible:ring-ring flex min-h-20 flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:p-4"
                    :class="
                        filters.group === group.key
                            ? 'border-primary bg-primary-soft'
                            : ''
                    "
                    @click="toggleGroup(group.key)"
                >
                    <span class="text-muted-foreground text-sm leading-5">{{
                        group.label
                    }}</span>
                    <span class="text-2xl leading-8 font-semibold">{{
                        formatNumber(group.count)
                    }}</span>
                </button>
            </div>

            <div
                class="grid grid-cols-2 gap-4 lg:flex lg:flex-wrap lg:items-start"
            >
                <FormField
                    v-slot="{ id }"
                    label="Cari siswa"
                    class="col-span-2 lg:min-w-[240px] lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari nama atau NISN"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField
                    v-if="schools"
                    v-slot="{ id }"
                    label="Sekolah"
                    class="col-span-2 lg:w-[220px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.school_id"
                        :options="schoolOptions"
                        all-label="Semua sekolah"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Sesi"
                    class="col-span-2 lg:w-[240px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.schedule_id"
                        :options="scheduleOptions"
                        all-label="Semua sesi"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Rekaman dari"
                    :error="errors['filter.dari']"
                    class="lg:w-[170px]"
                >
                    <Input
                        :id="id"
                        :model-value="String(filters.dari ?? '')"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                        @update:model-value="filters.dari = String($event)"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Sampai"
                    :error="errors['filter.sampai']"
                    class="lg:w-[170px]"
                >
                    <Input
                        :id="id"
                        :model-value="String(filters.sampai ?? '')"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                        @update:model-value="filters.sampai = String($event)"
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
                    label="Laporan KTP-el pelajar"
                    :columns="columns"
                    :rows="records.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[880px]"
                >
                    <template #cell-name="{ row }">
                        <div class="min-w-0">
                            <p class="truncate text-sm leading-5 font-semibold">
                                {{ row.name }}
                            </p>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.nisn }}
                            </p>
                        </div>
                    </template>
                    <template #cell-session_date="{ row }">
                        <span
                            v-if="row.group !== 'belum' && row.session_date"
                            >{{ formatDate(row.session_date) }}</span
                        >
                        <span v-else class="text-muted-foreground">—</span>
                    </template>
                    <template #cell-ktp="{ row }">
                        <KtpLangsungBadge v-if="row.group === 'langsung'" />
                        <span
                            v-else-if="row.group === 'menunggu'"
                            class="bg-neutral-soft text-neutral-foreground inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium whitespace-nowrap"
                        >
                            Terbit mulai {{ formatDate(row.eligible_from) }}
                        </span>
                        <span v-else class="text-muted-foreground">—</span>
                    </template>
                    <template #cell-reason="{ row }">
                        <span v-if="row.reason">{{ row.reason }}</span>
                        <span v-else class="text-muted-foreground">—</span>
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
                            :icon="Users"
                            title="Belum ada siswa terdata"
                            description="Daftar terisi setelah siswa mendaftar akun lewat link pendaftaran sekolahnya."
                        />
                    </template>
                </DataTable>
                <Pagination :paginator="records" />
            </div>
        </div>
    </AppLayout>
</template>
