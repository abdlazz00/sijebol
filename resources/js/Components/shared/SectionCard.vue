<script setup lang="ts">
defineProps<{ title: string }>();
</script>

<template>
    <section class="bg-card overflow-hidden rounded-lg border">
        <div class="flex min-h-14 items-center gap-3 px-4 py-2">
            <h2 class="flex-1 text-base leading-6 font-semibold">
                {{ title }}
            </h2>
            <slot name="action" />
        </div>
        <slot />
    </section>
</template>
