<script setup lang="ts">
import DashboardCalendarDialog from '@/Components/dashboard/DashboardCalendarDialog.vue';
import type { CalendarEventItem } from '@/types/dashboard';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        events?: CalendarEventItem[];
    }>(),
    {
        events: () => [],
    },
);

const today = new Date();
const currentYear = ref(today.getFullYear());
const currentMonth = ref(today.getMonth()); // 0 - 11

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const monthLabel = computed(() => {
    return `${monthNames[currentMonth.value]} ${currentYear.value}`;
});

function prevMonth() {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}

function nextMonth() {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}

// Compute days grid
const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDayIndex = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const days: Array<{
        dayNumber: number | null;
        dateStr: string | null;
        isToday: boolean;
        events: CalendarEventItem[];
    }> = [];

    // Empty cells for alignment before day 1
    for (let i = 0; i < firstDayIndex; i++) {
        days.push({
            dayNumber: null,
            dateStr: null,
            isToday: false,
            events: [],
        });
    }

    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dayEvents = props.events.filter((e) => e.date === dateStr);
        days.push({
            dayNumber: d,
            dateStr,
            isToday: dateStr === todayStr,
            events: dayEvents,
        });
    }

    return days;
});

// Event status color priority
function eventIndicatorClass(events: CalendarEventItem[]): string {
    if (events.some((e) => e.status === 'Hari Ini')) {
        return 'bg-blue-600 ring-2 ring-blue-300 dark:ring-blue-800 text-white font-bold';
    }
    if (events.some((e) => e.status === 'Dijadwalkan')) {
        return 'bg-amber-500 text-white font-semibold';
    }
    if (events.some((e) => e.status === 'Terlaksana')) {
        return 'bg-emerald-600 text-white font-semibold';
    }
    return '';
}

function eventTooltip(events: CalendarEventItem[], dateStr: string): string {
    if (!events.length) return '';
    const summaries = events.map(
        (e) => `${e.school_name || e.school || 'Sekolah'} (${e.participants} peserta, ${e.status})`,
    );
    return `${dateStr}: ${summaries.join('; ')}`;
}

// Dialog Popover state
const isDialogOpen = ref(false);
const selectedDate = ref('');
const selectedEvents = ref<CalendarEventItem[]>([]);

function handleDateClick(day: {
    dayNumber: number | null;
    dateStr: string | null;
    events: CalendarEventItem[];
}) {
    if (!day.dateStr || !day.events.length) return;
    selectedDate.value = day.dateStr;
    selectedEvents.value = day.events;
    isDialogOpen.value = true;
}
</script>

<template>
    <div
        class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
        role="region"
        aria-label="Kalender Mini Jadwal Perekaman"
    >
        <!-- Calendar Header -->
        <div class="flex items-center justify-between gap-2 border-b border-border/50 pb-3">
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded-md bg-violet-500/10 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        Kalender Kegiatan
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Agenda jemput bola perekaman
                    </p>
                </div>
            </div>

            <!-- Month Controls -->
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="flex size-7 items-center justify-center rounded-md border border-border/70 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Bulan sebelumnya"
                    @click="prevMonth"
                >
                    <ChevronLeft class="size-3.5" aria-hidden="true" />
                </button>
                <span class="min-w-[105px] text-center text-xs font-semibold text-foreground">
                    {{ monthLabel }}
                </span>
                <button
                    type="button"
                    class="flex size-7 items-center justify-center rounded-md border border-border/70 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Bulan berikutnya"
                    @click="nextMonth"
                >
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- 7-Day Names Header -->
        <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-muted-foreground">
            <div v-for="d in dayNames" :key="d" class="py-1">
                {{ d }}
            </div>
        </div>

        <!-- Calendar Days Grid -->
        <div class="grid grid-cols-7 gap-1 text-center text-xs">
            <div
                v-for="(day, idx) in calendarDays"
                :key="idx"
                class="flex aspect-square items-center justify-center p-0.5"
            >
                <button
                    v-if="day.dayNumber"
                    type="button"
                    :disabled="!day.events.length"
                    :title="eventTooltip(day.events, day.dateStr || '')"
                    :class="[
                        'relative flex size-7 sm:size-8 items-center justify-center rounded-full text-xs transition-all duration-150',
                        day.events.length
                            ? [
                                  eventIndicatorClass(day.events),
                                  'cursor-pointer hover:scale-110 shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                              ]
                            : 'cursor-default text-foreground/80 hover:bg-muted/40',
                        day.isToday && !day.events.length
                            ? 'border border-primary font-bold text-primary'
                            : '',
                    ]"
                    @click="handleDateClick(day)"
                >
                    <span>{{ day.dayNumber }}</span>
                    <!-- Dot marker if multiple events or quiet dot -->
                    <span
                        v-if="day.events.length > 1"
                        class="absolute -bottom-0.5 size-1 rounded-full bg-white ring-1 ring-background"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>

        <!-- Legend -->
        <div
            class="mt-3 flex flex-wrap items-center justify-center gap-3 border-t border-border/50 pt-2.5 text-[11px] text-muted-foreground"
        >
            <div class="flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-emerald-600" aria-hidden="true" />
                <span>Terlaksana</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-blue-600" aria-hidden="true" />
                <span>Hari Ini</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-amber-500" aria-hidden="true" />
                <span>Dijadwalkan</span>
            </div>
        </div>

        <!-- Agenda Detail Dialog -->
        <DashboardCalendarDialog
            v-model:open="isDialogOpen"
            :date="selectedDate"
            :events="selectedEvents"
        />
    </div>
</template>
