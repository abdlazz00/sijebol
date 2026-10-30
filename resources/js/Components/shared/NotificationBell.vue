<script setup lang="ts">
import NotificationItem from '@/Components/shared/NotificationItem.vue';
import { Button } from '@/Components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { useNotificationActions } from '@/composables/useNotificationActions';
import type { PageProps } from '@/types';
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage<PageProps>();
const summary = computed(() => page.props.notifications);
const unread = computed(() => summary.value?.unread_count ?? 0);
const { markRead, open, markAllRead } = useNotificationActions();

// Hanya untuk pengguna login; usePoll menjeda sendiri saat tab tersembunyi.
if (summary.value !== null) usePoll(60_000, { only: ['notifications'] });
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground relative size-10"
                :aria-label="
                    unread > 0
                        ? `Notifikasi, ${unread} belum dibaca`
                        : 'Notifikasi'
                "
            >
                <Bell class="size-4" aria-hidden="true" />
                <span
                    v-if="unread > 0"
                    class="bg-danger absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-semibold text-white"
                    aria-hidden="true"
                    >{{ unread > 9 ? '9+' : unread }}</span
                >
            </Button>
        </PopoverTrigger>
        <PopoverContent
            align="end"
            aria-label="Notifikasi"
            class="w-[min(380px,calc(100vw-2rem))] gap-0 p-0"
        >
            <div class="flex h-16 items-center justify-between border-b px-4">
                <p class="text-sm font-semibold">Notifikasi</p>
                <Button
                    v-if="unread > 0"
                    type="button"
                    variant="ghost"
                    class="text-primary h-8 px-2 text-xs"
                    @click="markAllRead"
                >
                    Tandai semua dibaca
                </Button>
            </div>

            <ul
                v-if="summary && summary.latest.length > 0"
                class="max-h-[420px] divide-y overflow-y-auto"
            >
                <NotificationItem
                    v-for="item in summary.latest"
                    :key="item.id"
                    :notification="item"
                    @open="open"
                    @read="markRead"
                />
            </ul>
            <div
                v-else
                class="flex flex-col items-center gap-2 px-6 py-8 text-center"
            >
                <span
                    class="bg-secondary flex size-10 items-center justify-center rounded-full"
                >
                    <Bell
                        class="text-muted-foreground size-5"
                        aria-hidden="true"
                    />
                </span>
                <p class="text-sm font-medium">Belum ada notifikasi</p>
                <p class="text-muted-foreground text-xs">
                    Pemberitahuan sistem akan muncul di sini.
                </p>
            </div>

            <div class="border-t p-2 text-center">
                <Link
                    :href="route('notifications.index')"
                    class="text-primary inline-flex h-10 items-center px-3 text-sm font-medium hover:underline"
                >
                    Lihat semua
                </Link>
            </div>
        </PopoverContent>
    </Popover>
</template>
