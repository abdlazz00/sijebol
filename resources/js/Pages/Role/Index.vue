<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PERMISSIONS } from '@/lib/permissions';
import type { RoleRow } from '@/types/access';
import { Link, router } from '@inertiajs/vue3';
import { LockKeyhole, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

defineProps<{ roles: RoleRow[] }>();

const { can } = usePermission();
const canEdit = computed(() => can(PERMISSIONS.RoleUbah));
const canDelete = computed(() => can(PERMISSIONS.RoleHapus));

const columns = computed(() => [
    { key: 'name', label: 'Nama role', class: 'w-[228px]' },
    { key: 'description', label: 'Deskripsi' },
    {
        key: 'users_count',
        label: 'Jumlah pengguna',
        class: 'w-[112px] text-right',
    },
    {
        key: 'permissions_count',
        label: 'Jumlah permission',
        class: 'w-[126px] text-right',
    },
    ...(canEdit.value || canDelete.value
        ? [{ key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' }]
        : []),
]);

const confirmOpen = ref(false);
const target = ref<RoleRow | null>(null);
const inUse = computed(() => (target.value?.users_count ?? 0) > 0);

function askDelete(role: RoleRow) {
    target.value = role;
    confirmOpen.value = true;
}

function destroy() {
    if (target.value)
        router.delete(route('role.destroy', target.value.id), {
            preserveScroll: true,
        });
}
</script>

<template>
    <AppLayout title="Role & Permission">
        <PageHeader
            title="Role & Permission"
            description="Kelola role dan hak akses pengguna SIJEBOL."
        >
            <template v-if="can(PERMISSIONS.RoleBuat)" #actions>
                <Button as-child class="h-10 px-4">
                    <Link :href="route('role.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah role
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-4">
            <p
                class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
            >
                <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                kolom lainnya
            </p>
            <DataTable
                label="Daftar role"
                :columns="columns"
                :rows="roles"
                row-class="h-16"
                table-class="min-w-[760px]"
            >
                <template #cell-name="{ row }">
                    <div class="flex flex-col gap-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-sm leading-5 font-medium">{{
                                row.name
                            }}</span>
                            <LockKeyhole
                                v-if="row.protected"
                                class="text-muted-foreground size-4"
                                aria-hidden="true"
                            />
                        </div>
                        <span
                            v-if="row.protected"
                            class="text-muted-foreground text-xs leading-4"
                            >Role dilindungi</span
                        >
                    </div>
                </template>
                <template #cell-description="{ row }">
                    <span class="whitespace-normal">{{
                        row.description ?? '—'
                    }}</span>
                </template>
                <template #cell-users_count="{ row }">
                    <span class="block text-right">{{ row.users_count }}</span>
                </template>
                <template #cell-permissions_count="{ row }">
                    <span class="block text-right">{{
                        row.permissions_count
                    }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div
                        v-if="!row.protected"
                        class="flex items-center justify-end gap-1"
                    >
                        <Button
                            v-if="canEdit"
                            as-child
                            variant="ghost"
                            class="text-primary h-10 min-w-10 px-2"
                        >
                            <Link
                                :href="route('role.edit', row.id)"
                                :aria-label="`Ubah role ${row.name}`"
                                >Ubah</Link
                            >
                        </Button>
                        <Button
                            v-if="canDelete"
                            variant="ghost"
                            class="text-danger hover:text-danger h-10 min-w-10 px-2"
                            :aria-label="`Hapus role ${row.name}`"
                            @click="askDelete(row)"
                        >
                            Hapus
                        </Button>
                    </div>
                </template>
            </DataTable>
            <p class="text-muted-foreground text-xs leading-4">
                {{ roles.length }} role
            </p>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus role ${target?.name ?? ''}?`"
            :description="
                inUse
                    ? 'Role tidak dapat dihapus selama masih digunakan.'
                    : 'Role ini akan dihapus. Tindakan ini tidak dapat dibatalkan.'
            "
            confirm-label="Hapus"
            destructive
            :confirm-disabled="inUse"
            @confirm="destroy"
        >
            <InlineAlert v-if="inUse"
                >Role masih dipakai
                {{ target?.users_count }} pengguna.</InlineAlert
            >
            <p
                v-else
                class="bg-secondary text-muted-foreground rounded-lg p-3 text-xs leading-4"
            >
                Tidak ada pengguna yang memakai role ini.
            </p>
        </ConfirmDialog>
    </AppLayout>
</template>
