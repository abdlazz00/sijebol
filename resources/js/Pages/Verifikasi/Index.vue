<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatWhen } from '@/lib/date';
import type { Paginator } from '@/types/access';
import type { QueueRow } from '@/types/registration';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { BadgeCheck, Search } from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    queue: Paginator<QueueRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { filters, loading, apply, reset } = useDataTable('/verifikasi', {
    search: props.filters.search ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => filters.school_id, apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'registration', label: 'Pendaftaran' },
    { key: 'since', label: 'Diajukan', class: 'w-[160px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' },
]);
</script>

<template>
    <AppLayout title="Antrean verifikasi">
        <PageHeader
            title="Antrean verifikasi"
            description="Periksa siswa yang diajukan sekolah dan putuskan satu per satu."
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
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="queue.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
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
                            description="Siswa yang diajukan sekolah akan muncul di sini."
                        />
                    </template>
                </DataTable>
                <Pagination :paginator="queue" />
            </div>
        </div>
    </AppLayout>
</template>
