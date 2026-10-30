<script setup lang="ts">
import { ShieldCheck, Check } from '@lucide/vue';

defineProps<{
    guidelines: string[];
}>();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-4 dark:border-slate-800">
            <span class="inline-flex size-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                <ShieldCheck class="size-4" aria-hidden="true" />
            </span>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                Panduan Hari-H Perekaman
            </h3>
        </div>

        <ul class="mt-4 space-y-2.5">
            <li
                v-for="(guide, index) in guidelines"
                :key="index"
                class="flex items-start gap-2.5 text-xs leading-relaxed text-slate-600 dark:text-slate-300"
            >
                <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <Check class="size-2.5 stroke-[3]" aria-hidden="true" />
                </div>
                <span>{{ guide }}</span>
            </li>
        </ul>
    </div>
</template>
