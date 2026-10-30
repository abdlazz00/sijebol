<script setup lang="ts">
import type { StepItem } from '@/types/student-dashboard';
import { Check, CircleAlert } from '@lucide/vue';

defineProps<{
    currentStep: number;
    steps: StepItem[];
}>();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Alur Perekaman KTP-el Mandiri
        </h3>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(step, index) in steps"
                :key="step.id"
                class="relative flex items-start gap-3.5 rounded-lg border p-3.5 transition-all"
                :class="[
                    step.status === 'current'
                        ? 'border-blue-500 bg-blue-50/50 dark:border-blue-600 dark:bg-blue-950/20'
                        : step.status === 'error'
                          ? 'border-rose-400 bg-rose-50/50 dark:border-rose-700 dark:bg-rose-950/20'
                          : step.status === 'completed'
                            ? 'border-emerald-200 bg-emerald-50/30 dark:border-emerald-900/40 dark:bg-emerald-950/10'
                            : 'border-slate-200 bg-slate-50/50 opacity-70 dark:border-slate-800 dark:bg-slate-900/50',
                ]"
                :aria-current="step.status === 'current' ? 'step' : undefined"
            >
                <!-- Indikator Ikon / Nomor -->
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="[
                        step.status === 'completed'
                            ? 'bg-emerald-600 text-white shadow-xs'
                            : step.status === 'error'
                              ? 'bg-rose-600 text-white shadow-xs'
                              : step.status === 'current'
                                ? 'bg-blue-600 text-white shadow-xs ring-4 ring-blue-100 dark:ring-blue-900/40'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    <Check v-if="step.status === 'completed'" class="size-4 stroke-[3]" aria-hidden="true" />
                    <CircleAlert v-else-if="step.status === 'error'" class="size-4 stroke-[3]" aria-hidden="true" />
                    <span v-else>{{ index + 1 }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        class="text-sm font-semibold leading-snug"
                        :class="[
                            step.status === 'current'
                                ? 'text-blue-950 dark:text-blue-200'
                                : step.status === 'error'
                                  ? 'text-rose-950 dark:text-rose-200'
                                  : 'text-slate-900 dark:text-slate-100',
                        ]"
                    >
                        {{ step.title }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ step.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
