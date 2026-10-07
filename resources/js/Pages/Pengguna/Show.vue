<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import UserStatusItems from '@/Components/shared/UserStatusItems.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { useDeactivateDialog } from '@/composables/useDeactivateDialog';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, relativeTime } from '@/lib/date';
import { humanize, initials } from '@/lib/format';
import { PERMISSIONS } from '@/lib/permissions';
import type { UserRow } from '@/types/access';
import { Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    user: UserRow & { created_by: string | null; created_at: string };
}>();

const { can } = usePermission();
const dialog = useDeactivateDialog();

const canEdit = computed(
    () => props.user.can_manage && can(PERMISSIONS.UserUbah),
);

const info = computed(() => [
    {
        label: 'Role',
        value: props.user.role ? humanize(props.user.role) : null,
    },
    { label: 'Sekolah', value: props.user.school },
    { label: 'Nomor HP', value: props.user.phone ?? 'Belum diisi' },
    { label: 'Dibuat oleh', value: props.user.created_by },
    { label: 'Tanggal dibuat', value: formatDate(props.user.created_at) },
    {
        label: 'Login terakhir',
        value: props.user.last_login_at
            ? relativeTime(props.user.last_login_at)
            : 'Belum pernah',
    },
]);

const crumbs = [
    { label: 'Pengguna', href: route('pengguna.index') },
    { label: 'Detail pengguna' },
];
</script>

<template>
    <AppLayout title="Detail pengguna" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Detail pengguna</h1>
            <p class="text-muted-foreground text-sm leading-5">
                Informasi akun dan aktivitas pengguna SIJEBOL.
            </p>
        </div>

        <div class="flex flex-col gap-6">
            <section
                class="bg-card flex flex-col gap-4 rounded-lg border p-6 sm:flex-row sm:items-center sm:gap-6"
            >
                <div class="flex min-w-0 flex-1 items-center gap-4">
                    <Avatar class="size-16 shrink-0">
                        <AvatarFallback
                            class="bg-neutral-soft text-neutral-foreground text-2xl font-semibold"
                        >
                            {{ initials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex min-w-0 flex-col items-start gap-1">
                        <h2
                            class="text-2xl leading-8 font-semibold break-words"
                        >
                            {{ user.name }}
                        </h2>
                        <p
                            class="text-muted-foreground text-sm leading-5 break-all"
                        >
                            {{ user.email }}
                        </p>
                        <StatusBadge :status="user.status" />
                    </div>
                </div>
                <div v-if="user.can_manage" class="flex items-start gap-2">
                    <Button
                        v-if="canEdit"
                        as-child
                        variant="outline"
                        class="h-10 px-4"
                    >
                        <Link :href="route('pengguna.edit', user.id)">
                            <Pencil class="size-4" aria-hidden="true" />
                            Ubah
                        </Link>
                    </Button>
                    <RowActions
                        :label="`Aksi lainnya untuk ${user.name}`"
                        outlined
                    >
                        <UserStatusItems
                            :user="user"
                            @deactivate="dialog.ask"
                        />
                    </RowActions>
                </div>
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">
                    Informasi akun
                </h2>
                <InfoList :items="info" />
            </section>
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
