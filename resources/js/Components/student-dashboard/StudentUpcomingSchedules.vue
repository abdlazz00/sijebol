<script setup lang="ts">
import type { SchoolScheduleItem } from '@/types/student-dashboard';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Calendar, MapPin, Users, ArrowRight } from '@lucide/vue';

defineProps<{
    schedules: SchoolScheduleItem[];
}>();

function formatDate(dateStr: string) {
    try {
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(d);
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="inline-flex size-7 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                    <Calendar class="size-4" aria-hidden="true" />
                </span>
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                    Jadwal Sesi Rekaman Sekolah
                </h3>
            </div>

            <Button as-child variant="ghost" size="sm" class="text-xs">
                <Link :href="route('me.sessions.index')">
                    Lihat Semua
                </Link>
            </Button>
        </div>

        <div v-if="schedules.length > 0" class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
            <div
                v-for="s in schedules"
                :key="s.id"
                class="flex items-center justify-between py-3 first:pt-0 last:pb-0"
            >
                <div class="min-w-0 flex-1 pr-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ formatDate(s.date) }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                            :class="[
                                s.is_open
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                        >
                            {{ s.remaining_quota }} kursi tersisa
                        </span>
                    </div>

                    <div class="mt-1 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <MapPin class="size-3.5 shrink-0" aria-hidden="true" />
                        <span class="truncate">{{ s.location }}</span>
                    </div>
                </div>

                <div class="shrink-0">
                    <Button as-child size="sm" variant="outline" class="h-8 text-xs min-h-[36px]">
                        <Link :href="route('me.sessions.index')">
                            Daftar
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <div v-else class="mt-6 flex flex-col items-center justify-center py-6 text-center">
            <Calendar class="size-8 text-slate-300 dark:text-slate-600" aria-hidden="true" />
            <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                Belum ada sesi perekaman dibuka
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-xs">
                Pihak sekolah atau kecamatan akan segera menjadwalkan sesi jemput bola di sekolah Anda.
            </p>
        </div>
    </div>
</template>
