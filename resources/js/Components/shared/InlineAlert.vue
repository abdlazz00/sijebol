<script setup lang="ts">
import { CircleAlert, CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ variant?: 'danger' | 'warning' | 'success' }>(),
    {
        variant: 'danger',
    },
);

const styles = {
    danger: 'bg-danger-soft text-danger',
    warning: 'bg-warning-soft text-warning',
    success: 'bg-success-soft text-success',
} as const;

const icon = computed(
    () =>
        ({ danger: CircleAlert, warning: TriangleAlert, success: CircleCheck })[
            props.variant
        ],
);
</script>

<template>
    <div
        class="flex items-start gap-2 rounded-lg px-3 py-3 text-sm leading-5"
        :class="styles[variant]"
        :role="variant === 'danger' ? 'alert' : 'status'"
    >
        <component
            :is="icon"
            class="mt-0.5 size-4 shrink-0"
            aria-hidden="true"
        />
        <div><slot /></div>
    </div>
</template>
