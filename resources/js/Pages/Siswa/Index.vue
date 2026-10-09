<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import ImportStudentsDialog from '@/Components/shared/ImportStudentsDialog.vue';
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
import { PERMISSIONS } from '@/lib/permissions';
import type { Paginator } from '@/types/access';
import type { ImportStatus, StudentRow } from '@/types/student';
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Check,
    Eye,
    GraduationCap,
    Pencil,
    Plus,
    Upload,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    students: Paginator<StudentRow>;
    recent_import: ImportStatus | null;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/siswa', {
    search: props.filters.search ?? '',
    docs: props.filters.docs ?? '',
    eligible: props.filters.eligible ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => [filters.docs, filters.eligible, filters.school_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const docsOptions = [
    { value: 'lengkap', label: 'Lengkap' },
    { value: 'kurang', label: 'Belum lengkap' },
];
const eligibleOptions = [{ value: '1', label: 'Eligible' }];
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'name', label: 'Siswa', class: 'w-[240px]' },
    { key: 'nik', label: 'NIK', class: 'w-[176px]' },
    { key: 'age', label: 'Umur', class: 'w-[80px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'docs', label: 'Dokumen', class: 'w-[160px]' },
    { key: 'eligible', label: 'Status', class: 'w-[144px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
]);

const confirmOpen = ref(false);
const importOpen = ref(props.recent_import !== null);
const target = ref<StudentRow | null>(null);

function askDelete(student: StudentRow) {
    target.value = student;
    confirmOpen.value = true;
}

function destroy() {
    if (target.value)
        router.delete(route('siswa.destroy', target.value.id), {
            preserveScroll: true,
        });
}
</script>

<template>
    <AppLayout title="Siswa">
        <PageHeader
            title="Siswa"
            description="Kelola data siswa dan kelengkapan dokumennya."
        >
            <template #actions>
                <Button
                    v-if="can(PERMISSIONS.SiswaImpor)"
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    @click="importOpen = true"
                >
                    <Upload class="size-4" aria-hidden="true" />
                    Impor Excel
                </Button>
                <Button
                    v-if="can(PERMISSIONS.SiswaBuat)"
                    as-child
                    class="h-10 px-4"
                >
                    <Link :href="route('siswa.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah siswa
                    </Link>
                </Button>
            </template>
        </PageHeader>

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
                        placeholder="Cari nama, NISN, atau NIK lengkap"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Dokumen" class="lg:w-[180px]">
                    <SelectField
                        :id="id"
                        v-model="filters.docs"
                        :options="docsOptions"
                        all-label="Semua dokumen"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Status" class="lg:w-[180px]">
                    <SelectField
                        :id="id"
                        v-model="filters.eligible"
                        :options="eligibleOptions"
                        all-label="Semua status"
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
                    :rows="students.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[960px]"
                >
                    <template #cell-name="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('siswa.show', row.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.name }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.nisn }}
                            </p>
                        </div>
                    </template>
                    <template #cell-nik="{ row }">
                        <span class="font-mono text-xs">{{ row.nik }}</span>
                    </template>
                    <template #cell-age="{ row }">{{ row.age }} th</template>
                    <template #cell-docs="{ row }">
                        <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                            <li
                                v-for="doc in [
                                    { key: 'kk', label: 'KK' },
                                    { key: 'akta_lahir', label: 'Akta' },
                                ] as const"
                                :key="doc.key"
                                class="flex items-center gap-1"
                                :class="
                                    row.docs[doc.key]
                                        ? 'text-success'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Check
                                    v-if="row.docs[doc.key]"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <X v-else class="size-3.5" aria-hidden="true" />
                                {{ doc.label }}
                                <span class="sr-only">{{
                                    row.docs[doc.key]
                                        ? 'terunggah'
                                        : 'belum diunggah'
                                }}</span>
                            </li>
                        </ul>
                    </template>
                    <template #cell-eligible="{ row }">
                        <span
                            class="inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium"
                            :class="
                                row.eligible
                                    ? 'bg-success-soft text-success'
                                    : 'bg-neutral-soft text-neutral-foreground'
                            "
                            :title="row.reasons.join(' ')"
                        >
                            {{ row.eligible ? 'Eligible' : 'Belum eligible' }}
                            <span v-if="!row.eligible" class="sr-only">{{
                                row.reasons.join(' ')
                            }}</span>
                        </span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <RowActions :label="`Aksi untuk ${row.name}`">
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('siswa.show', row.id)">
                                        <Eye
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="can(PERMISSIONS.SiswaUbah)"
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('siswa.edit', row.id)">
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="can(PERMISSIONS.SiswaHapus)"
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
                            :icon="GraduationCap"
                            title="Belum ada siswa"
                            description="Tambahkan siswa satu per satu agar dapat didaftarkan untuk perekaman."
                        >
                            <template v-if="can(PERMISSIONS.SiswaBuat)" #action>
                                <Button as-child class="h-9">
                                    <Link :href="route('siswa.create')"
                                        >Tambah siswa</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="students" />
            </div>
        </div>

        <ImportStudentsDialog
            v-model:open="importOpen"
            :schools="schools"
            :recent-import="recent_import"
        />

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${target?.name ?? ''}?`"
            description="Data siswa dan dokumennya akan dihapus permanen."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
