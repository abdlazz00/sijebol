<script setup lang="ts">
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { usePermission } from '@/composables/usePermission';
import { PERMISSIONS } from '@/lib/permissions';
import type { UserRow } from '@/types/access';
import { router } from '@inertiajs/vue3';
import { Mail, UserCheck, UserRoundX } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ user: UserRow }>();
const emit = defineEmits<{ deactivate: [user: UserRow] }>();

const { can } = usePermission();

const canResend = computed(
    () =>
        props.user.can_manage &&
        can(PERMISSIONS.UserKirimUlangAktivasi) &&
        ['menunggu', 'kedaluwarsa'].includes(props.user.status),
);
const canDeactivate = computed(
    () =>
        props.user.can_manage &&
        can(PERMISSIONS.UserNonaktifkan) &&
        props.user.status !== 'nonaktif',
);
const canActivate = computed(
    () =>
        props.user.can_manage &&
        can(PERMISSIONS.UserNonaktifkan) &&
        props.user.status === 'nonaktif',
);

function resend() {
    router.post(route('users.resend-activation', props.user.id));
}

function activate() {
    router.post(
        route('users.activate', props.user.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenuItem
        v-if="canResend"
        class="h-10 gap-3 px-3 text-sm font-medium"
        @select="resend"
    >
        <Mail class="size-4" aria-hidden="true" />
        Kirim ulang aktivasi
    </DropdownMenuItem>
    <DropdownMenuItem
        v-if="canDeactivate"
        class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
        @select="emit('deactivate', user)"
    >
        <UserRoundX class="size-4" aria-hidden="true" />
        Nonaktifkan
    </DropdownMenuItem>
    <DropdownMenuItem
        v-if="canActivate"
        class="text-primary focus:text-primary h-10 gap-3 px-3 text-sm font-medium"
        @select="activate"
    >
        <UserCheck class="size-4" aria-hidden="true" />
        Aktifkan
    </DropdownMenuItem>
</template>
