<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p
                v-if="description"
                class="text-muted-foreground text-sm leading-5"
            >
                {{ description }}
            </p>
        </div>
        <div
            class="flex w-full items-center gap-2 sm:w-auto [&>*]:w-full sm:[&>*]:w-auto"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
