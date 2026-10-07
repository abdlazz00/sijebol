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
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PERMISSIONS } from '@/lib/permissions';
import type { Paginator, SchoolRow } from '@/types/access';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Plus, School, Search } from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    schools: Paginator<SchoolRow>;
    filters: Record<string, string | undefined>;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/sekolah', {
    search: props.filters.search ?? '',
    level: props.filters.level ?? '',
});

watch(() => filters.level, apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const levelOptions = ['SMA', 'SMK', 'MA'].map((l) => ({ value: l, label: l }));

const canEdit = computed(() => can(PERMISSIONS.SekolahUbah));
const columns = computed(() => [
    { key: 'name', label: 'Nama sekolah' },
    { key: 'npsn', label: 'NPSN', class: 'w-[128px]' },
    { key: 'level', label: 'Jenjang', class: 'w-[104px]' },
    {
        key: 'users_count',
        label: 'Jumlah operator',
        class: 'w-[144px] text-right',
    },
    ...(canEdit.value
        ? [{ key: 'actions', label: 'Aksi', class: 'w-[88px] text-right' }]
        : []),
]);
</script>

<template>
    <AppLayout title="Sekolah">
        <PageHeader
            title="Sekolah"
            description="Kelola data sekolah di Kecamatan Sungai Beduk."
        >
            <template v-if="can(PERMISSIONS.SekolahBuat)" #actions>
                <Button as-child class="h-10 px-4">
                    <Link :href="route('sekolah.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah sekolah
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari sekolah"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari nama atau NPSN"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Jenjang"
                    class="col-span-2 lg:w-[204px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.level"
                        :options="levelOptions"
                        all-label="Semua jenjang"
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
                    :rows="schools.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[760px]"
                >
                    <template #cell-name="{ row }">
                        <div class="min-w-0">
                            <p class="truncate text-sm leading-5 font-medium">
                                {{ row.name }}
                            </p>
                            <p
                                v-if="row.address"
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{ row.address }}
                            </p>
                        </div>
                    </template>
                    <template #cell-users_count="{ row }">
                        <span class="block text-right">{{
                            row.users_count
                        }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button
                                as-child
                                variant="ghost"
                                class="text-primary h-10 min-w-10 px-2"
                            >
                                <Link
                                    :href="route('sekolah.edit', row.id)"
                                    :aria-label="`Ubah ${row.name}`"
                                    >Ubah</Link
                                >
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada sekolah yang sesuai dengan pencarian atau filter Anda."
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
                            :icon="School"
                            title="Belum ada sekolah"
                            description="Tambahkan sekolah agar operator dapat dikaitkan dengan sekolahnya."
                        >
                            <template
                                v-if="can(PERMISSIONS.SekolahBuat)"
                                #action
                            >
                                <Button as-child class="h-9">
                                    <Link :href="route('sekolah.create')"
                                        >Tambah sekolah</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="schools" />
            </div>
        </div>
    </AppLayout>
</template>
