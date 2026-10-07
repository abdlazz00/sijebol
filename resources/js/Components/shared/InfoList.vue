<script setup lang="ts">
defineProps<{ items: { label: string; value: string | null }[] }>();
</script>

<template>
    <dl class="grid md:grid-cols-2 md:gap-x-8">
        <div
            v-for="item in items"
            :key="item.label"
            class="flex flex-col gap-1 border-t py-3 text-sm leading-5 sm:flex-row sm:gap-4"
        >
            <dt class="text-muted-foreground shrink-0 font-medium sm:w-[140px]">
                {{ item.label }}
            </dt>
            <dd class="min-w-0 flex-1 break-words">{{ item.value ?? '—' }}</dd>
        </div>
    </dl>
</template>
