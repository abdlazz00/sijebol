<script setup lang="ts">
import EmptyState from '@/Components/shared/EmptyState.vue';
import SectionCard from '@/Components/shared/SectionCard.vue';
import StatCard from '@/Components/shared/StatCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay } from '@/lib/date';
import { humanize } from '@/lib/format';
import type {
    ActionItem,
    NotificationItem,
    ScheduleItem,
    SummaryCard,
} from '@/types/dashboard';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarPlus,
    ChevronRight,
    ClipboardList,
    FileText,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        summary?: SummaryCard[];
        actions?: ActionItem[];
        schedules?: ScheduleItem[];
        notifications?: NotificationItem[];
    }>(),
    {
        summary: () => [],
        actions: () => [],
        schedules: () => [],
        notifications: () => [],
    },
);

const user = computed(() => usePage<PageProps>().props.auth.user);
const firstName = computed(() => user.value?.name.split(' ')[0] ?? '');
const today = formatDateWithDay(new Date());

const actionIcons = {
    'clipboard-list': ClipboardList,
    'calendar-plus': CalendarPlus,
} as const;

/** "9 Okt 2026" → ["9", "Okt 2026"]. */
function dateParts(value: string): [string, string] {
    const [day, ...rest] = formatDate(value).split(' ');

    return [day, rest.join(' ')];
}
</script>

<template>
    <AppLayout title="Beranda">
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl leading-8 font-semibold">
                        Selamat datang, {{ firstName }}
                    </h1>
                    <p class="text-muted-foreground text-sm leading-5">
                        {{ humanize(user?.role) }}
                    </p>
                </div>
                <p class="text-muted-foreground text-xs leading-4">
                    {{ today }}
                </p>
            </div>

            <div v-if="summary.length" class="grid gap-4 sm:grid-cols-3">
                <StatCard
                    v-for="card in summary"
                    :key="card.label"
                    v-bind="card"
                />
            </div>

            <SectionCard title="Perlu tindakan">
                <EmptyState
                    v-if="!actions.length"
                    class="border-t"
                    title="Tidak ada yang perlu ditindaklanjuti"
                    description="Tugas yang menunggu Anda akan muncul di sini."
                />
                <div
                    v-for="item in actions"
                    :key="item.title"
                    class="flex items-center gap-3 border-t p-4"
                >
                    <span
                        class="text-primary flex h-10 w-8 items-center justify-center"
                    >
                        <component
                            :is="actionIcons[item.icon]"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <div
                        class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1"
                    >
                        <div
                            class="flex min-w-0 flex-1 flex-col gap-1 text-sm leading-5"
                        >
                            <p class="font-medium">{{ item.title }}</p>
                            <p class="text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>
                        <Link
                            :href="item.href"
                            class="text-primary flex h-10 items-center gap-2 text-sm font-medium hover:underline"
                        >
                            {{ item.label }}
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Jadwal terdekat">
                <EmptyState
                    v-if="!schedules.length"
                    class="border-t"
                    title="Belum ada jadwal terdekat"
                    description="Jadwal rekaman yang akan datang tampil di sini."
                />
                <div v-else class="grid border-t sm:grid-cols-2">
                    <div
                        v-for="(schedule, index) in schedules"
                        :key="index"
                        class="flex gap-4 p-4 sm:[&:nth-child(even)]:border-l"
                    >
                        <div
                            class="flex w-[60px] shrink-0 flex-col items-center py-1"
                        >
                            <p class="text-[28px] leading-9 font-semibold">
                                {{ dateParts(schedule.date)[0] }}
                            </p>
                            <p
                                class="text-muted-foreground text-xs leading-4 whitespace-nowrap"
                            >
                                {{ dateParts(schedule.date)[1] }}
                            </p>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <p class="text-sm leading-5 font-medium">
                                {{ schedule.school }}
                            </p>
                            <p class="text-muted-foreground text-sm leading-5">
                                {{ schedule.location }}
                            </p>
                            <p
                                class="text-muted-foreground flex items-center gap-2 text-xs leading-4"
                            >
                                <Users class="size-4" aria-hidden="true" />
                                {{ schedule.participants }} peserta
                            </p>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Notifikasi terbaru">
                <EmptyState
                    v-if="!notifications.length"
                    class="border-t"
                    title="Belum ada notifikasi"
                    description="Pemberitahuan sistem akan muncul di sini."
                />
                <template v-else>
                    <p
                        v-if="notifications.every((n) => n.read !== false)"
                        class="text-muted-foreground px-4 pb-3 text-xs leading-4"
                    >
                        Semua telah dibaca
                    </p>
                    <div
                        v-for="(note, index) in notifications"
                        :key="index"
                        class="flex items-start gap-3 border-t px-4 py-3"
                    >
                        <span
                            class="text-muted-foreground flex size-6 items-center"
                        >
                            <FileText class="size-4" aria-hidden="true" />
                        </span>
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div
                                class="flex min-w-0 flex-1 flex-col gap-1 text-sm leading-5"
                            >
                                <p class="font-medium">{{ note.title }}</p>
                                <p class="text-muted-foreground">
                                    {{ note.description }}
                                </p>
                            </div>
                            <p
                                class="text-muted-foreground text-xs leading-4 whitespace-nowrap"
                            >
                                {{ formatDate(note.at) }}
                            </p>
                        </div>
                    </div>
                </template>
            </SectionCard>
        </div>
    </AppLayout>
</template>
