<script setup lang="ts">
import type { QuickActionItem } from '@/types/dashboard';
import { Link } from '@inertiajs/vue3';
import {
    CalendarPlus,
    ClipboardCheck,
    Download,
    IdCard,
    Plus,
    ScanLine,
    UserPlus,
    Zap,
} from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    items: QuickActionItem[];
}>();

const iconMap: Record<string, Component> = {
    plus: Plus,
    'calendar-plus': CalendarPlus,
    'id-card': IdCard,
    download: Download,
    'user-plus': UserPlus,
    'clipboard-check': ClipboardCheck,
    'scan-line': ScanLine,
};

const variantClasses: Record<QuickActionItem['variant'], string> = {
    blue: 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white shadow-sm shadow-blue-600/25 focus-visible:ring-blue-500',
    green:
        'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white shadow-sm shadow-emerald-600/25 focus-visible:ring-emerald-500',
    amber:
        'bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white shadow-sm shadow-amber-600/25 focus-visible:ring-amber-500',
    rose: 'bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white shadow-sm shadow-rose-600/25 focus-visible:ring-rose-500',
};
</script>

<template>
    <div
        v-if="items.length"
        class="rounded-xl border border-border/80 bg-card p-3.5 shadow-xs"
        role="region"
        aria-label="Aksi Cepat"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded-md bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400"
                >
                    <Zap class="size-4" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold tracking-tight text-foreground">
                        Aksi Cepat
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Akses langsung fitur dan tugas utama Anda
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <Link
                    v-for="action in items"
                    :key="action.label"
                    :href="action.href"
                    :class="[
                        'inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold tracking-tight transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                        variantClasses[action.variant] || variantClasses.blue,
                    ]"
                >
                    <component
                        :is="iconMap[action.icon] || Plus"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{{ action.label }}</span>
                </Link>
            </div>
        </div>
    </div>
</template>
