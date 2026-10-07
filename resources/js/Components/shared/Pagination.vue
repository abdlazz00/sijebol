<script setup lang="ts">
import type { Paginator } from '@/types/access';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    paginator: Pick<
        Paginator<unknown>,
        'from' | 'to' | 'total' | 'per_page' | 'links'
    >;
}>();

const prev = computed(() => props.paginator.links[0]?.url ?? null);
const next = computed(() => props.paginator.links.at(-1)?.url ?? null);
// Tautan pertama/terakhir adalah "Previous/Next"; sisanya nomor halaman.
const pages = computed(() => props.paginator.links.slice(1, -1));

const base =
    'flex size-10 items-center justify-center rounded-lg border text-sm font-medium';
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted-foreground text-xs leading-4">
            <template v-if="paginator.total > 0">
                Menampilkan {{ paginator.from }}–{{ paginator.to }} dari
                {{ paginator.total }}
            </template>
        </p>
        <nav class="flex items-center gap-3" aria-label="Paginasi">
            <span class="text-muted-foreground text-xs leading-4"
                >{{ paginator.per_page }} per halaman</span
            >
            <div class="flex items-center gap-2">
                <Link
                    v-if="prev"
                    :href="prev"
                    preserve-scroll
                    :class="[base, 'bg-card hover:bg-primary-soft']"
                    aria-label="Halaman sebelumnya"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    :class="[base, 'bg-secondary text-muted-foreground/60']"
                    aria-hidden="true"
                >
                    <ChevronLeft class="size-4" />
                </span>

                <template v-for="link in pages" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        :class="[
                            base,
                            link.active
                                ? 'border-primary bg-card text-primary'
                                : 'bg-card hover:bg-primary-soft',
                        ]"
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                    <span v-else class="text-muted-foreground px-1 text-sm">{{
                        link.label
                    }}</span>
                </template>

                <Link
                    v-if="next"
                    :href="next"
                    preserve-scroll
                    :class="[base, 'bg-card hover:bg-primary-soft']"
                    aria-label="Halaman berikutnya"
                >
                    <ChevronRight class="size-4" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    :class="[base, 'bg-secondary text-muted-foreground/60']"
                    aria-hidden="true"
                >
                    <ChevronRight class="size-4" />
                </span>
            </div>
        </nav>
    </div>
</template>
