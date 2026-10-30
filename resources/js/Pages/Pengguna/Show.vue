<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import UserStatusItems from '@/Components/shared/UserStatusItems.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { useDeactivateDialog } from '@/composables/useDeactivateDialog';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { summarizeChanges } from '@/lib/activity';
import {
    formatDate,
    formatDateTime,
    formatWhen,
    relativeTime,
} from '@/lib/date';
import { humanize, initials } from '@/lib/format';
import { PERMISSIONS } from '@/lib/permissions';
import type { UserRow } from '@/types/access';
import type { ActivityRow } from '@/types/notification';
import { Link } from '@inertiajs/vue3';
import { Pencil, ScrollText } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    user: UserRow & { created_by: string | null; created_at: string };
    activities: ActivityRow[] | null;
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

// Ringkasan: perubahan data bila ada; selain itu "pelaku → subjek" (hanya nama bila sama).
const note = (a: ActivityRow) => {
    if (a.changes.length > 0) return summarizeChanges(a.changes, 1);

    const who = a.causer?.name ?? 'Sistem';

    return who === a.subject.label ? who : `${who} → ${a.subject.label}`;
};

const crumbs = [
    { label: 'Pengguna', href: route('users.index') },
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
                        <Link :href="route('users.edit', user.id)">
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

            <section
                v-if="activities !== null"
                class="bg-card overflow-hidden rounded-lg border"
            >
                <div class="flex min-h-14 items-center gap-3 px-6 py-3">
                    <h2 class="flex-1 text-base leading-6 font-semibold">
                        Aktivitas terbaru
                    </h2>
                    <Link
                        v-if="can(PERMISSIONS.LogLihat)"
                        :href="
                            route('audit-logs.index', { filter: { user: user.id } })
                        "
                        class="text-primary text-sm font-medium hover:underline"
                    >
                        Lihat semua
                    </Link>
                </div>
                <EmptyState
                    v-if="activities.length === 0"
                    class="border-t"
                    :icon="ScrollText"
                    title="Belum ada aktivitas"
                />
                <ul v-else>
                    <li
                        v-for="a in activities"
                        :key="a.id"
                        class="flex flex-col gap-1 border-t px-6 py-3 text-sm leading-5 md:flex-row md:gap-6"
                    >
                        <span
                            class="text-muted-foreground md:w-[200px] md:shrink-0"
                            :title="formatDateTime(a.created_at)"
                            >{{ formatWhen(a.created_at) }}</span
                        >
                        <span class="font-medium md:w-[160px] md:shrink-0">{{
                            a.action
                        }}</span>
                        <span class="min-w-0 flex-1 break-words">{{
                            note(a)
                        }}</span>
                    </li>
                </ul>
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
