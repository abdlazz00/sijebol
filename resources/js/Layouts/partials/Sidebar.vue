<script setup lang="ts">
import lambang from '@/assets/brand/lambang-kota-batam.png';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { menuIcon } from '@/config/menuIcons';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ collapsed: boolean }>();

const page = usePage<PageProps>();
const groups = computed(() => page.props.menu ?? []);

function isActive(href: string) {
    return page.url === href || page.url.startsWith(`${href}/`);
}
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <nav
            class="bg-sidebar text-sidebar-foreground flex h-full flex-col"
            aria-label="Menu utama"
        >
            <div
                class="flex h-16 shrink-0 items-center gap-3"
                :class="collapsed ? 'px-5' : 'px-4'"
            >
                <Tooltip :disabled="!collapsed">
                    <TooltipTrigger as-child>
                        <img
                            :src="lambang"
                            alt="Lambang Kota Batam"
                            class="h-[43.87px] w-8 shrink-0 object-cover"
                        />
                    </TooltipTrigger>
                    <TooltipContent side="right"
                        >SIJEBOL · Kec. Sungai Beduk</TooltipContent
                    >
                </Tooltip>
                <div v-if="!collapsed" class="min-w-0">
                    <p
                        class="font-brand text-2xl leading-[30px] font-bold text-white"
                    >
                        SIJEBOL
                    </p>
                    <p class="text-sidebar-muted text-xs leading-4">
                        Kec. Sungai Beduk
                    </p>
                </div>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto px-4 pt-6">
                <div
                    v-for="group in groups"
                    :key="group.group"
                    class="space-y-2"
                >
                    <p
                        v-if="!collapsed"
                        class="text-sidebar-muted text-[11px] leading-4 font-medium uppercase"
                    >
                        {{ group.group }}
                    </p>
                    <Tooltip
                        v-for="item in group.items"
                        :key="item.href"
                        :disabled="!collapsed"
                    >
                        <TooltipTrigger as-child>
                            <Link
                                :href="item.href"
                                :aria-label="item.label"
                                :aria-current="
                                    isActive(item.href) ? 'page' : undefined
                                "
                                class="hover:bg-sidebar-accent flex h-10 items-center rounded-lg text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                :class="[
                                    collapsed
                                        ? 'w-10 justify-center'
                                        : 'gap-3 px-3',
                                    isActive(item.href)
                                        ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                                        : 'text-sidebar-foreground',
                                ]"
                            >
                                <component
                                    :is="menuIcon(item.icon)"
                                    class="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span v-if="!collapsed">{{ item.label }}</span>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="right">{{
                            item.label
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </nav>
    </TooltipProvider>
</template>
