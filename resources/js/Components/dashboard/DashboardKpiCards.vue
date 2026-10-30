<script setup lang="ts">
import type { KpiCardItem } from '@/types/dashboard';
import {
    AlertCircle,
    CalendarCheck,
    Clock,
    FileText,
    IdCard,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    items: KpiCardItem[];
}>();

const iconMap: Record<string, Component> = {
    users: Users,
    'calendar-check': CalendarCheck,
    'id-card': IdCard,
    clock: Clock,
    'file-text': FileText,
    'shield-check': ShieldCheck,
    'alert-circle': AlertCircle,
};

const variantClasses: Record<
    KpiCardItem['variant'],
    {
        card: string;
        iconWrap: string;
        badge: string;
        value: string;
    }
> = {
    blue: {
        card: 'bg-blue-50/70 border-blue-200/80 dark:bg-blue-950/20 dark:border-blue-900/40',
        iconWrap: 'bg-blue-500 text-white shadow-blue-500/20',
        badge: 'bg-blue-100/90 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300',
        value: 'text-blue-950 dark:text-blue-50',
    },
    green: {
        card: 'bg-emerald-50/70 border-emerald-200/80 dark:bg-emerald-950/20 dark:border-emerald-900/40',
        iconWrap: 'bg-emerald-500 text-white shadow-emerald-500/20',
        badge: 'bg-emerald-100/90 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
        value: 'text-emerald-950 dark:text-emerald-50',
    },
    amber: {
        card: 'bg-amber-50/70 border-amber-200/80 dark:bg-amber-950/20 dark:border-amber-900/40',
        iconWrap: 'bg-amber-500 text-white shadow-amber-500/20',
        badge: 'bg-amber-100/90 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
        value: 'text-amber-950 dark:text-amber-50',
    },
    rose: {
        card: 'bg-rose-50/70 border-rose-200/80 dark:bg-rose-950/20 dark:border-rose-900/40',
        iconWrap: 'bg-rose-500 text-white shadow-rose-500/20',
        badge: 'bg-rose-100/90 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300',
        value: 'text-rose-950 dark:text-rose-50',
    },
    slate: {
        card: 'bg-slate-50/90 border-slate-200/80 dark:bg-slate-900/40 dark:border-slate-800',
        iconWrap: 'bg-slate-500 text-white shadow-slate-500/20',
        badge: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        value: 'text-slate-950 dark:text-slate-50',
    },
};

function formatNumber(val: number): string {
    return Number(val || 0).toLocaleString('id-ID');
}
</script>

<template>
    <div
        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5"
        role="region"
        aria-label="Statistik Utama KPI"
    >
        <div
            v-for="item in items"
            :key="item.key"
            :class="[
                'group relative flex flex-col justify-between overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                variantClasses[item.variant]?.card || variantClasses.slate.card,
            ]"
        >
            <div class="flex items-start justify-between gap-3">
                <span
                    :class="[
                        'flex size-10 shrink-0 items-center justify-center rounded-lg shadow-sm',
                        variantClasses[item.variant]?.iconWrap ||
                            variantClasses.slate.iconWrap,
                    ]"
                >
                    <component
                        :is="iconMap[item.icon] || Users"
                        class="size-5"
                        aria-hidden="true"
                    />
                </span>
                <span
                    v-if="
                        item.percentage !== null &&
                        item.percentage !== undefined
                    "
                    :class="[
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold tracking-tight',
                        variantClasses[item.variant]?.badge ||
                            variantClasses.slate.badge,
                    ]"
                >
                    {{ item.percentage }}%
                </span>
            </div>

            <div class="mt-4 flex flex-col gap-0.5">
                <div class="flex items-baseline gap-1.5">
                    <p
                        :class="[
                            'text-2xl font-bold tracking-tight sm:text-3xl',
                            variantClasses[item.variant]?.value ||
                                variantClasses.slate.value,
                        ]"
                    >
                        {{ formatNumber(item.value) }}
                    </p>
                    <span
                        v-if="item.unit"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        {{ item.unit }}
                    </span>
                </div>
                <p
                    class="line-clamp-2 text-xs font-medium leading-4 text-foreground/80 dark:text-foreground/90"
                >
                    {{ item.label }}
                </p>
            </div>
        </div>
    </div>
</template>
