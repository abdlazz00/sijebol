<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </div>
        <div class="flex items-center gap-2"><slot name="actions" /></div>
    </div>
</template>
