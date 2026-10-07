<script setup lang="ts">
import { formatNumber } from '@/lib/format';
import { CalendarDays, ClipboardCheck, ClipboardList } from '@lucide/vue';

defineProps<{
    label: string;
    value: number;
    caption: string;
    icon: 'clipboard-list' | 'clipboard-check' | 'calendar-days';
}>();

const icons = {
    'clipboard-list': ClipboardList,
    'clipboard-check': ClipboardCheck,
    'calendar-days': CalendarDays,
} as const;
</script>

<template>
    <div class="bg-card relative flex flex-col gap-2 rounded-lg border p-4">
        <p class="text-muted-foreground pr-8 text-xs leading-4">{{ label }}</p>
        <span
            class="bg-primary-soft text-primary absolute top-[11px] right-[11px] flex size-7 items-center justify-center rounded-full"
        >
            <component :is="icons[icon]" class="size-5" aria-hidden="true" />
        </span>
        <p class="text-[28px] leading-9 font-semibold">
            {{ formatNumber(value) }}
        </p>
        <p class="text-muted-foreground text-xs leading-4">{{ caption }}</p>
    </div>
</template>
