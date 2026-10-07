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
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import UserStatusItems from '@/Components/shared/UserStatusItems.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { useDataTable } from '@/composables/useDataTable';
import { useDeactivateDialog } from '@/composables/useDeactivateDialog';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { humanize, initials } from '@/lib/format';
import { PERMISSIONS } from '@/lib/permissions';
import { relativeTime } from '@/lib/date';
import { userStatus } from '@/lib/status';
import type { Option, Paginator, UserRow } from '@/types/access';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Eye, Pencil, Plus, Search, Users } from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    users: Paginator<UserRow>;
    filters: Record<string, string | undefined>;
    roles: Option[];
    schools: Option[];
    statuses: string[];
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/pengguna', {
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    school_id: props.filters.school_id ?? '',
    status: props.filters.status ?? '',
});

watch(() => [filters.role, filters.school_id, filters.status], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const roleOptions = computed(() =>
    props.roles.map((r) => ({ value: r.id, label: humanize(r.name) })),
);
const schoolOptions = computed(() =>
    props.schools.map((s) => ({ value: s.id, label: s.name })),
);
const statusOptions = computed(() =>
    props.statuses.map((s) => ({ value: s, label: userStatus(s).label })),
);

const columns = [
    { key: 'name', label: 'Pengguna', class: 'w-[300px]' },
    { key: 'role', label: 'Role', class: 'w-[176px]' },
    { key: 'school', label: 'Sekolah', class: 'w-[264px]' },
    { key: 'status', label: 'Status', class: 'w-[160px]' },
    { key: 'last_login_at', label: 'Login terakhir', class: 'w-[128px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
];

const canEdit = (u: UserRow) => u.can_manage && can(PERMISSIONS.UserUbah);

const dialog = useDeactivateDialog();
</script>

<template>
    <AppLayout title="Pengguna">
        <PageHeader
            title="Pengguna"
            description="Kelola akun dan status aktivasi pengguna SIJEBOL."
        >
            <template v-if="can(PERMISSIONS.UserBuat)" #actions>
                <Button as-child class="h-9 px-4">
                    <Link :href="route('pengguna.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah pengguna
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari pengguna"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari nama atau email"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Role" class="lg:w-[204px]">
                    <SelectField
                        :id="id"
                        v-model="filters.role"
                        :options="roleOptions"
                        all-label="Semua role"
                    />
                </FormField>
                <FormField
                    v-slot="{ id }"
                    label="Sekolah"
                    class="order-3 col-span-2 lg:order-2 lg:w-[284px]"
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
                    label="Status"
                    class="order-2 lg:order-3 lg:w-[204px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.status"
                        :options="statusOptions"
                        all-label="Semua status"
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
                    :rows="users.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[1100px]"
                >
                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-3">
                            <Avatar class="size-8">
                                <AvatarFallback
                                    class="bg-neutral-soft text-xs font-semibold text-slate-600"
                                >
                                    {{ initials(row.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm leading-5 font-semibold"
                                >
                                    {{ row.name }}
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-xs leading-4"
                                >
                                    {{ row.email }}
                                </p>
                            </div>
                        </div>
                    </template>
                    <template #cell-role="{ row }">{{
                        humanize(row.role)
                    }}</template>
                    <template #cell-school="{ row }">{{
                        row.school ?? '—'
                    }}</template>
                    <template #cell-status="{ row }"
                        ><StatusBadge :status="row.status"
                    /></template>
                    <template #cell-last_login_at="{ row }">
                        <span class="text-muted-foreground">{{
                            relativeTime(row.last_login_at)
                        }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <RowActions :label="`Aksi untuk ${row.name}`">
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="route('pengguna.show', row.id)"
                                    >
                                        <Eye
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canEdit(row)"
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="route('pengguna.edit', row.id)"
                                    >
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <UserStatusItems
                                    :user="row"
                                    @deactivate="dialog.ask"
                                />
                            </RowActions>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada pengguna yang sesuai dengan pencarian atau filter Anda."
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
                            title="Belum ada pengguna"
                            description="Tambahkan pengguna untuk memberikan akses ke SIJEBOL."
                        >
                            <template v-if="can(PERMISSIONS.UserBuat)" #action>
                                <Button as-child class="h-9">
                                    <Link :href="route('pengguna.create')"
                                        >Tambah pengguna</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="users" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="dialog.open.value"
            :title="`Nonaktifkan ${dialog.target.value?.name ?? ''}?`"
            :description="`${dialog.target.value?.name ?? 'Pengguna'} tidak dapat mengakses SIJEBOL setelah akun dinonaktifkan. Data pengguna tetap tersimpan.`"
            confirm-label="Nonaktifkan"
            destructive
            @confirm="dialog.confirm"
        />
    </AppLayout>
</template>
