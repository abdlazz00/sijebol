<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface Crumb {
    label: string;
    href?: string;
}

defineProps<{ items: Crumb[] }>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="min-w-0">
        <ol class="text-foreground flex items-center gap-1 text-sm font-medium">
            <li
                v-for="(item, index) in items"
                :key="index"
                class="items-center gap-1"
                :class="index < items.length - 1 ? 'hidden md:flex' : 'flex'"
            >
                <span
                    v-if="index > 0"
                    class="hidden md:inline"
                    aria-hidden="true"
                    >&gt;</span
                >
                <Link
                    v-if="item.href && index < items.length - 1"
                    :href="item.href"
                    class="inline-block py-1 hover:underline"
                >
                    {{ item.label }}
                </Link>
                <span
                    v-else
                    class="truncate"
                    :aria-current="
                        index === items.length - 1 ? 'page' : undefined
                    "
                >
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
