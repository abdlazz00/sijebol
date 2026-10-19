<script setup lang="ts">
import { IdCard } from '@lucide/vue';

const MAKNA = 'Berumur lebih dari 17 tahun; KTP-el dapat langsung terbit.';
</script>

<template>
    <span
        class="bg-primary-soft text-primary inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium whitespace-nowrap"
        :title="MAKNA"
    >
        <IdCard class="size-3.5" aria-hidden="true" />
        KTP-el langsung
        <span class="sr-only">. {{ MAKNA }}</span>
    </span>
</template>
