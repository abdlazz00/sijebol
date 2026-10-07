<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{ title: string; description?: string; icon?: Component }>(),
    {
        icon: () => Inbox,
    },
);
</script>

<template>
    <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
        <component
            :is="icon"
            class="text-muted-foreground size-10"
            aria-hidden="true"
        />
        <p class="text-sm font-medium">{{ title }}</p>
        <p v-if="description" class="text-muted-foreground text-sm">
            {{ description }}
        </p>
        <div class="mt-2"><slot name="action" /></div>
    </div>
</template>
