<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { formatDate } from '@/lib/date';
import type { CalendarEventItem } from '@/types/dashboard';
import { Link } from '@inertiajs/vue3';
import { Calendar, ChevronRight, MapPin, Users } from '@lucide/vue';

defineProps<{
    open: boolean;
    date: string;
    events: CalendarEventItem[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'Hari Ini':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 border-blue-200/80';
        case 'Terlaksana':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 border-emerald-200/80';
        case 'Dijadwalkan':
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 border-amber-200/80';
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <span
                        class="flex size-7 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400"
                    >
                        <Calendar class="size-4" aria-hidden="true" />
                    </span>
                    <DialogTitle class="text-base font-semibold">
                        Agenda Kegiatan: {{ date ? formatDate(date) : '-' }}
                    </DialogTitle>
                </div>
                <DialogDescription class="text-xs text-muted-foreground">
                    Daftar kegiatan perekaman KTP-el pemula pada tanggal ini.
                </DialogDescription>
            </DialogHeader>

            <div class="mt-2 space-y-3">
                <div
                    v-if="events.length === 0"
                    class="py-6 text-center text-xs text-muted-foreground"
                >
                    Tidak ada agenda kegiatan pada tanggal ini.
                </div>

                <div
                    v-for="ev in events"
                    :key="ev.id"
                    class="flex flex-col gap-2 rounded-lg border border-border/80 bg-muted/30 p-3 text-xs transition-colors hover:bg-muted/50"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-foreground text-sm">
                            {{ ev.school_name || ev.school || '-' }}
                        </p>
                        <span
                            :class="[
                                'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                statusBadgeClass(ev.status),
                            ]"
                        >
                            {{ ev.status }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <MapPin class="size-3.5 text-muted-foreground/70" aria-hidden="true" />
                            <span>{{ ev.location || '-' }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <Users class="size-3.5 text-muted-foreground/70" aria-hidden="true" />
                            <span>{{ ev.participants }} peserta</span>
                        </span>
                    </div>

                    <div class="mt-1 flex justify-end border-t border-border/40 pt-2">
                        <Link
                            :href="`/jadwal/${ev.id}`"
                            class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
                        >
                            <span>Lihat Rincian Jadwal</span>
                            <ChevronRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
