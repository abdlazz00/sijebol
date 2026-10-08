<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
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
import { formatDate } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { Paginator } from '@/types/access';
import type { RegistrationRow } from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ClipboardList, Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    registrations: Paginator<RegistrationRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/pendaftaran', {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => [filters.status, filters.school_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const statusOptions = [
    'draf',
    'diajukan',
    'perlu_perbaikan',
    'terverifikasi',
    'ditolak',
].map((value) => ({ value, label: registrationStatus(value).label }));
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'title', label: 'Pendaftaran', class: 'w-[280px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'students_count', label: 'Peserta', class: 'w-[96px]' },
    { key: 'counts', label: 'Ringkasan status' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
]);

const isDraft = (row: RegistrationRow) => row.submitted_at === null;
const confirmOpen = ref(false);
const target = ref<RegistrationRow | null>(null);

function askDelete(row: RegistrationRow) {
    target.value = row;
    confirmOpen.value = true;
}

function destroy() {
    if (target.value)
        router.delete(route('pendaftaran.destroy', target.value.id), {
            preserveScroll: true,
        });
}
</script>

<template>
    <AppLayout title="Pendaftaran">
        <PageHeader
            title="Pendaftaran"
            description="Kelompokkan siswa eligible untuk diajukan ke verifikasi."
        >
            <template #actions>
                <Button
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    as-child
                    class="h-10 px-4"
                >
                    <Link :href="route('pendaftaran.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Buat pendaftaran
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari pendaftaran"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari judul pendaftaran"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Status" class="lg:w-[200px]">
                    <SelectField
                        :id="id"
                        v-model="filters.status"
                        :options="statusOptions"
                        all-label="Semua status"
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
                    :columns="columns"
                    :rows="registrations.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-title="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('pendaftaran.show', row.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.title }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                Dibuat {{ formatDate(row.created_at) }}
                            </p>
                        </div>
                    </template>
                    <template #cell-school="{ row }">{{ row.school }}</template>
                    <template #cell-counts="{ row }">
                        <p
                            v-if="isDraft(row)"
                            class="text-muted-foreground text-xs"
                        >
                            Draf · belum diajukan
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
                            <RowActions :label="`Aksi untuk ${row.title}`">
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="
                                            route('pendaftaran.show', row.id)
                                        "
                                    >
                                        <Eye
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        isDraft(row) &&
                                        can(PERMISSIONS.PendaftaranBuat)
                                    "
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="
                                            route('pendaftaran.edit', row.id)
                                        "
                                    >
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        isDraft(row) &&
                                        can(PERMISSIONS.PendaftaranBuat)
                                    "
                                    class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
                                    @select="askDelete(row)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Hapus
                                </DropdownMenuItem>
                            </RowActions>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada pendaftaran yang sesuai dengan pencarian atau filter Anda."
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
                            :icon="ClipboardList"
                            title="Belum ada pendaftaran"
                            description="Buat pendaftaran untuk mengelompokkan siswa eligible dan mengajukannya ke verifikasi."
                        >
                            <template
                                v-if="can(PERMISSIONS.PendaftaranBuat)"
                                #action
                            >
                                <Button as-child class="h-9">
                                    <Link :href="route('pendaftaran.create')"
                                        >Buat pendaftaran</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="registrations" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${target?.title ?? ''}?`"
            description="Draf dan daftar siswanya akan dihapus."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
