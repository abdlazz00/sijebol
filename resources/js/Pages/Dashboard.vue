<script setup lang="ts">
import DashboardCharts from '@/Components/dashboard/DashboardCharts.vue';
import DashboardKpiCards from '@/Components/dashboard/DashboardKpiCards.vue';
import DashboardMiniCalendar from '@/Components/dashboard/DashboardMiniCalendar.vue';
import DashboardQuickActions from '@/Components/dashboard/DashboardQuickActions.vue';
import DashboardUpcomingTable from '@/Components/dashboard/DashboardUpcomingTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import SectionCard from '@/Components/shared/SectionCard.vue';
import StatCard from '@/Components/shared/StatCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay } from '@/lib/date';
import { humanize } from '@/lib/format';
import type { PageProps } from '@/types';
import type {
    ActionItem,
    CalendarEventItem,
    DashboardCharts as DashboardChartsType,
    KpiCardItem,
    QuickActionItem,
    ScheduleItem,
    SummaryCard,
    UpcomingScheduleRow,
} from '@/types/dashboard';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarPlus,
    ChevronRight,
    ClipboardList,
} from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        summary?: SummaryCard[];
        actions?: ActionItem[];
        schedules?: ScheduleItem[];
        kpi?: KpiCardItem[];
        quick_actions?: QuickActionItem[];
        charts?: DashboardChartsType;
        schedules_table?: UpcomingScheduleRow[];
        calendar_events?: CalendarEventItem[];
        is_operator?: boolean;
        school_name?: string | null;
    }>(),
    {
        summary: () => [],
        actions: () => [],
        schedules: () => [],
        kpi: () => [],
        quick_actions: () => [],
        charts: () => ({
            school_recap: { categories: [], series: [] },
            recording_status: { labels: [], series: [], total: 0 },
            monthly_trend: { categories: [], series: [] },
        }),
        schedules_table: () => [],
        calendar_events: () => [],
        is_operator: false,
        school_name: null,
    },
);

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const isOperator = computed(
    () => props.is_operator || !!user.value?.school_id,
);
const schoolName = computed(
    () => props.school_name || user.value?.school?.name || null,
);
const notifications = computed(
    () => page.props.notifications?.latest.slice(0, 5) ?? [],
);
const firstName = computed(() => user.value?.name.split(' ')[0] ?? '');
const today = formatDateWithDay(new Date());

const actionIcons = {
    'clipboard-list': ClipboardList,
    'calendar-plus': CalendarPlus,
} as const;

</script>

<template>
    <AppLayout title="Beranda">
        <div class="flex flex-col gap-6">
            <!-- 1. Header Salam & Informasi Pengguna -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Selamat datang, {{ firstName }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span
                            class="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                        >
                            {{ humanize(user?.role) }}
                        </span>
                        <span v-if="schoolName" class="font-medium text-foreground">
                            • {{ schoolName }}
                        </span>
                        <span>• Kecamatan Sungai Beduk</span>
                    </div>
                </div>
                <div
                    class="rounded-lg border border-border/70 bg-card px-3.5 py-1.5 text-xs font-medium text-muted-foreground shadow-xs"
                >
                    {{ today }}
                </div>
            </div>

            <!-- 2. Section 1: KPI Cards -->
            <DashboardKpiCards v-if="kpi.length" :items="kpi" />
            <div v-else-if="summary.length" class="grid gap-4 sm:grid-cols-3">
                <StatCard
                    v-for="card in summary"
                    :key="card.label"
                    v-bind="card"
                />
            </div>

            <!-- 3. Section 2: Bilah Aksi Cepat (Opsi B: Bawah KPI) -->
            <DashboardQuickActions
                v-if="quick_actions.length"
                :items="quick_actions"
            />

            <!-- 4. Section 3: Visualisasi Grafik ApexCharts -->
            <DashboardCharts
                v-if="charts"
                :charts="charts"
                :is-operator="isOperator"
            />

            <!-- 5. Section 4: Grid 2 Kolom Asimetris Operasional -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Kolom Kiri: Tabel Jadwal Terdekat (~60% / 7 cols) -->
                <div class="flex flex-col gap-6 lg:col-span-7">
                    <DashboardUpcomingTable :schedules="schedules_table" />

                    <!-- Perlu Tindakan (Jika Ada) -->
                    <SectionCard v-if="actions.length" title="Perlu tindakan">
                        <div
                            v-for="item in actions"
                            :key="item.title"
                            class="flex items-center gap-3 border-t border-border/50 p-4"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <component
                                    :is="actionIcons[item.icon] || ClipboardList"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <div
                                class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-xs">
                                    <p class="font-semibold text-foreground text-sm">
                                        {{ item.title }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ item.description }}
                                    </p>
                                </div>
                                <Link
                                    :href="item.href"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                                >
                                    <span>{{ item.label }}</span>
                                    <ChevronRight class="size-3.5" aria-hidden="true" />
                                </Link>
                            </div>
                        </div>
                    </SectionCard>
                </div>

                <!-- Kolom Kanan: Notifikasi Terbaru & Kalender Mini (~40% / 5 cols) -->
                <div class="flex flex-col gap-6 lg:col-span-5">
                    <!-- Card Notifikasi Terbaru -->
                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs"
                    >
                        <div
                            class="flex items-center justify-between gap-2 border-b border-border/50 pb-3"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex size-7 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400"
                                >
                                    <Bell class="size-4" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-foreground">
                                        Notifikasi Terbaru
                                    </h3>
                                    <p class="text-xs text-muted-foreground">
                                        Pemberitahuan sistem terbaru
                                    </p>
                                </div>
                            </div>
                            <Link
                                href="/notifikasi"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                            >
                                <span>Lihat Semua</span>
                                <ChevronRight class="size-3.5" aria-hidden="true" />
                            </Link>
                        </div>

                        <EmptyState
                            v-if="!notifications.length"
                            class="border-t-0 py-8"
                            title="Belum ada notifikasi"
                            description="Pemberitahuan sistem akan muncul di sini."
                        />
                        <div v-else class="mt-2 divide-y divide-border/40 text-xs">
                            <div
                                v-for="note in notifications"
                                :key="note.id"
                                class="flex items-start gap-3 py-3 first:pt-1 last:pb-1"
                            >
                                <span
                                    class="mt-1 size-2 shrink-0 rounded-full bg-primary"
                                    :class="note.read_at ? 'opacity-30' : 'opacity-100'"
                                />
                                <div class="min-w-0 flex-1 space-y-0.5">
                                    <p class="font-medium text-foreground line-clamp-1">
                                        {{ note.title }}
                                    </p>
                                    <p class="text-muted-foreground line-clamp-2 text-[11px]">
                                        {{ note.body }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-[10px] text-muted-foreground whitespace-nowrap"
                                >
                                    {{ formatDate(note.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Mini Kalender -->
                    <DashboardMiniCalendar :events="calendar_events" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
