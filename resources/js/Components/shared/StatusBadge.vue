<script setup lang="ts">
import { userStatus, type BadgeVariant } from '@/lib/status';
import { computed } from 'vue';

const props = defineProps<{ status: string }>();

const classes: Record<BadgeVariant, string> = {
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-neutral-soft text-muted-foreground',
    info: 'bg-primary-soft text-primary',
};

const meta = computed(() => userStatus(props.status));
</script>

<template>
    <span
        class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium"
        :class="classes[meta.variant]"
    >
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ meta.label }}
    </span>
</template>
