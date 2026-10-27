<script setup lang="ts">
import type { ActiveEntryData } from '@/types/student-dashboard';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import KtpLangsungBadge from '@/Components/shared/KtpLangsungBadge.vue';
import { Calendar, Clock, MapPin, QrCode, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    entry: ActiveEntryData;
}>();

const formattedDate = computed(() => {
    try {
        const d = new Date(props.entry.schedule_date);
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(d);
    } catch {
        return props.entry.schedule_date;
    }
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="inline-flex size-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <QrCode class="size-4" aria-hidden="true" />
                </span>
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Sesi Perekaman Terdaftar
                </h3>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <KtpLangsungBadge v-if="entry.direct_ktp" />
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ entry.status_label }}
                </span>
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <div v-if="entry.ticket_code" class="flex items-center justify-between rounded-lg bg-slate-50 px-3.5 py-2.5 dark:bg-slate-800/60">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Kode e-Tiket</span>
                <span class="font-mono text-sm font-bold tracking-wider text-slate-900 dark:text-slate-100">
                    {{ entry.ticket_code }}
                </span>
            </div>

            <div class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                <Calendar class="mt-0.5 size-4 shrink-0 text-slate-400" aria-hidden="true" />
                <div>
                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ formattedDate }}</span>
                    <span class="ml-2 inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                        {{ entry.days_remaining }} hari lagi
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                <Clock class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                <span>{{ entry.time_window }}</span>
            </div>

            <div class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                <MapPin class="mt-0.5 size-4 shrink-0 text-slate-400" aria-hidden="true" />
                <span>{{ entry.location }}</span>
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800">
            <Button as-child variant="outline" class="w-full justify-between h-10 min-h-[44px]">
                <Link :href="route('me.tickets.show', entry.id)">
                    <span class="flex items-center gap-2">
                        <QrCode class="size-4" aria-hidden="true" />
                        Buka Detail e-Tiket & QR
                    </span>
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </div>
</template>
