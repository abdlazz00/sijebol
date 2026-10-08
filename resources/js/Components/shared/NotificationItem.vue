<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { relativeTime } from '@/lib/date';
import type { NotificationRow } from '@/types/notification';
import { Check } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ notification: NotificationRow }>();
const emit = defineEmits<{
    open: [notification: NotificationRow];
    read: [id: string];
}>();

const unread = computed(() => props.notification.read_at === null);
</script>

<template>
    <li
        class="group relative flex items-start gap-3 px-4 py-3"
        :class="unread ? 'bg-primary-soft/40' : ''"
    >
        <span
            class="mt-1.5 size-2 shrink-0 rounded-full"
            :class="unread ? 'bg-primary' : 'bg-transparent'"
            aria-hidden="true"
        />
        <a
            :href="notification.url"
            class="flex min-w-0 flex-1 flex-col gap-0.5"
            @click.prevent="emit('open', notification)"
        >
            <span
                class="truncate text-sm leading-5"
                :class="unread ? 'font-semibold' : 'font-medium'"
            >
                {{ notification.title }}
                <span v-if="unread" class="sr-only">(belum dibaca)</span>
            </span>
            <span class="text-muted-foreground truncate text-xs leading-4">{{
                notification.body
            }}</span>
            <span class="text-muted-foreground text-xs leading-4">{{
                relativeTime(notification.created_at)
            }}</span>
        </a>
        <Button
            v-if="unread"
            type="button"
            variant="ghost"
            size="icon"
            class="size-8 shrink-0 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
            aria-label="Tandai dibaca"
            @click="emit('read', notification.id)"
        >
            <Check class="size-4" aria-hidden="true" />
        </Button>
    </li>
</template>
