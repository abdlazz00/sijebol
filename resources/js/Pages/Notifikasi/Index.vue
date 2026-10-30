<script setup lang="ts">
import EmptyState from '@/Components/shared/EmptyState.vue';
import NotificationItem from '@/Components/shared/NotificationItem.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { useNotificationActions } from '@/composables/useNotificationActions';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageProps } from '@/types';
import type { Paginator } from '@/types/access';
import type { NotificationRow } from '@/types/notification';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    items: Paginator<NotificationRow>;
    filters: { status?: string | null };
}>();

const { markRead, open, markAllRead } = useNotificationActions();
const unreadOnly = computed(() => props.filters.status === 'unread');
const unreadTotal = computed(
    () => usePage<PageProps>().props.notifications?.unread_count ?? 0,
);

const tab =
    'inline-flex h-9 items-center rounded-lg px-4 text-sm font-medium transition-colors';
</script>

<template>
    <AppLayout title="Notifikasi">
        <PageHeader
            title="Notifikasi"
            description="Pemberitahuan sistem untuk akun Anda."
        >
            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    :disabled="unreadTotal === 0"
                    @click="markAllRead"
                >
                    Tandai semua dibaca
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-4">
            <nav class="flex gap-2" aria-label="Filter notifikasi">
                <Link
                    :href="route('notifications.index')"
                    :class="[
                        tab,
                        !unreadOnly
                            ? 'bg-primary-soft text-primary'
                            : 'text-muted-foreground hover:bg-secondary',
                    ]"
                    :aria-current="!unreadOnly ? 'page' : undefined"
                >
                    Semua
                </Link>
                <Link
                    :href="
                        route('notifications.index', {
                            filter: { status: 'unread' },
                        })
                    "
                    :class="[
                        tab,
                        unreadOnly
                            ? 'bg-primary-soft text-primary'
                            : 'text-muted-foreground hover:bg-secondary',
                    ]"
                    :aria-current="unreadOnly ? 'page' : undefined"
                >
                    Belum dibaca
                </Link>
            </nav>

            <ul
                v-if="items.data.length > 0"
                class="bg-card divide-y overflow-hidden rounded-lg border"
            >
                <NotificationItem
                    v-for="item in items.data"
                    :key="item.id"
                    :notification="item"
                    @open="open"
                    @read="markRead"
                />
            </ul>
            <div v-else class="bg-card rounded-lg border">
                <EmptyState
                    :icon="Bell"
                    title="Tidak ada notifikasi"
                    :description="
                        unreadOnly
                            ? 'Semua notifikasi sudah dibaca.'
                            : 'Pemberitahuan sistem akan muncul di sini.'
                    "
                />
            </div>

            <Pagination :paginator="items" />
        </div>
    </AppLayout>
</template>
