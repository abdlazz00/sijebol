<script setup lang="ts">
import type { SmartHeroData } from '@/types/student-dashboard';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    ArrowRight,
    Clock,
    CircleAlert,
    CircleCheck,
    Calendar,
    QrCode,
    FilePenLine,
    CircleX,
} from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    hero: SmartHeroData;
}>();

const heroStyles = computed(() => {
    switch (props.hero.variant) {
        case 'rose':
            return {
                card: 'border-rose-300 bg-rose-50/70 dark:border-rose-900/60 dark:bg-rose-950/20',
                badge: 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200 border-rose-200 dark:border-rose-800',
                title: 'text-rose-950 dark:text-rose-100',
                subtitle: 'text-rose-800/90 dark:text-rose-300',
                btn: 'bg-rose-600 hover:bg-rose-700 text-white shadow-xs',
            };
        case 'emerald':
            return {
                card: 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/20',
                badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800',
                title: 'text-emerald-950 dark:text-emerald-100',
                subtitle: 'text-emerald-800/90 dark:text-emerald-300',
                btn: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs',
            };
        case 'indigo':
            return {
                card: 'border-indigo-300 bg-indigo-50/70 dark:border-indigo-900/60 dark:bg-indigo-950/20',
                badge: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200 border-indigo-200 dark:border-indigo-800',
                title: 'text-indigo-950 dark:text-indigo-100',
                subtitle: 'text-indigo-800/90 dark:text-indigo-300',
                btn: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs',
            };
        case 'blue':
            return {
                card: 'border-blue-300 bg-blue-50/70 dark:border-blue-900/60 dark:bg-blue-950/20',
                badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 border-blue-200 dark:border-blue-800',
                title: 'text-blue-950 dark:text-blue-100',
                subtitle: 'text-blue-800/90 dark:text-blue-300',
                btn: 'bg-blue-600 hover:bg-blue-700 text-white shadow-xs',
            };
        case 'amber':
            return {
                card: 'border-amber-300 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/20',
                badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200 border-amber-200 dark:border-amber-800',
                title: 'text-amber-950 dark:text-amber-100',
                subtitle: 'text-amber-800/90 dark:text-amber-300',
                btn: 'bg-amber-600 hover:bg-amber-700 text-white shadow-xs',
            };
        default:
            return {
                card: 'border-slate-300 bg-slate-50 dark:border-slate-800 dark:bg-slate-900',
                badge: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700',
                title: 'text-slate-900 dark:text-slate-100',
                subtitle: 'text-slate-600 dark:text-slate-400',
                btn: 'bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900 shadow-xs',
            };
    }
});
</script>

<template>
    <div
        class="relative overflow-hidden rounded-xl border p-6 transition-all sm:p-7"
        :class="heroStyles.card"
    >
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="max-w-2xl min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                        :class="heroStyles.badge"
                    >
                        <Clock v-if="hero.state === 'MENUNGGU_VERIFIKASI'" class="size-3.5" aria-hidden="true" />
                        <CircleAlert v-else-if="hero.state === 'PERBAIKAN' || hero.state === 'NO_PROFILE' || hero.state === 'MISSING_DOCS'" class="size-3.5" aria-hidden="true" />
                        <CircleCheck v-else-if="hero.state === 'TERDAFTAR' || hero.state === 'SUDAH_REKAMAN'" class="size-3.5" aria-hidden="true" />
                        <Calendar v-else-if="hero.state === 'NO_REGISTRATION'" class="size-3.5" aria-hidden="true" />
                        <CircleX v-else class="size-3.5" aria-hidden="true" />
                        {{ hero.badge }}
                    </span>

                    <span v-if="hero.deadline_at" class="inline-flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                        <Clock class="size-3.5" aria-hidden="true" />
                        Batas perbaikan segera
                    </span>
                </div>

                <h2 class="mt-2.5 text-xl font-bold tracking-tight sm:text-2xl" :class="heroStyles.title">
                    {{ hero.title }}
                </h2>
                <p class="mt-1.5 text-sm leading-relaxed" :class="heroStyles.subtitle">
                    {{ hero.subtitle }}
                </p>

                <!-- Kotak Catatan Revisi Petugas jika ada -->
                <div
                    v-if="hero.notes"
                    class="mt-3.5 rounded-lg border border-rose-200 bg-white/90 p-3 text-xs text-rose-900 shadow-xs dark:border-rose-900/60 dark:bg-slate-900/90 dark:text-rose-200"
                >
                    <div class="flex items-start gap-2">
                        <CircleAlert class="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" aria-hidden="true" />
                        <div>
                            <span class="font-semibold">Catatan Verifikator:</span>
                            <p class="mt-0.5">{{ hero.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="shrink-0 pt-1 sm:pt-0">
                <Button
                    as-child
                    size="lg"
                    class="h-11 min-h-[44px] w-full px-5 font-semibold transition-transform active:scale-[0.98] sm:w-auto"
                    :class="heroStyles.btn"
                >
                    <Link :href="hero.cta_url" class="inline-flex items-center justify-center gap-2">
                        <QrCode v-if="hero.state === 'TERDAFTAR'" class="size-4.5" aria-hidden="true" />
                        <FilePenLine v-else-if="hero.state === 'PERBAIKAN'" class="size-4.5" aria-hidden="true" />
                        <span>{{ hero.cta_label }}</span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
