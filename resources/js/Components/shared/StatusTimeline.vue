<script setup lang="ts">
import { formatDateTime } from '@/lib/date';
import { registrationStatus } from '@/lib/status';
import type { HistoryRow } from '@/types/registration';

defineProps<{ items: HistoryRow[] }>();
</script>

<template>
    <ol v-if="items.length" class="flex flex-col gap-4">
        <li v-for="(item, i) in items" :key="i" class="flex gap-3">
            <span
                class="bg-primary mt-1.5 size-2 shrink-0 rounded-full"
                aria-hidden="true"
            />
            <div class="min-w-0 text-sm leading-5">
                <p class="font-medium">
                    {{ registrationStatus(item.from).label }}
                    →
                    {{ registrationStatus(item.to).label }}
                </p>
                <p class="text-muted-foreground text-xs leading-4">
                    {{ formatDateTime(item.at) }} · {{ item.by }}
                </p>
                <p v-if="item.note" class="mt-1 break-words">
                    {{ item.note }}
                </p>
            </div>
        </li>
    </ol>
    <p v-else class="text-muted-foreground text-sm">
        Belum ada perubahan status.
    </p>
</template>
