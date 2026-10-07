<script setup lang="ts">
import { Inbox } from '@lucide/vue';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
        <Inbox class="text-muted-foreground size-10" aria-hidden="true" />
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="description" class="text-muted-foreground text-sm">
            {{ description }}
        </p>
        <div class="mt-2"><slot name="action" /></div>
    </div>
</template>
