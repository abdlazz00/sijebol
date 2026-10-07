<script setup lang="ts">
import { usePermission } from '@/composables/usePermission';
import { navigation, visibleNavigation } from '@/config/navigation';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ collapsed: boolean }>();

const { can } = usePermission();
const groups = computed(() => visibleNavigation(navigation, can));
const page = usePage();

function isActive(href: string) {
    return page.url === href || page.url.startsWith(`${href}/`);
}
</script>

<template>
    <nav
        class="bg-sidebar text-sidebar-foreground flex h-full flex-col"
        aria-label="Menu utama"
    >
        <div class="flex h-16 items-center px-4 text-lg font-semibold">
            <span v-if="!collapsed">SIJEBOL</span>
            <span v-else class="mx-auto">S</span>
        </div>
        <div class="flex-1 space-y-5 overflow-y-auto px-3 py-2">
            <div v-for="group in groups" :key="group.group">
                <p
                    v-if="!collapsed"
                    class="text-sidebar-muted px-3 pb-1 text-[11px] font-medium tracking-wide uppercase"
                >
                    {{ group.group }}
                </p>
                <Link
                    v-for="item in group.items"
                    :key="item.href"
                    :href="item.href"
                    :title="collapsed ? item.label : undefined"
                    :aria-label="item.label"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                    class="hover:bg-sidebar-accent flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                    :class="
                        isActive(item.href)
                            ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                            : 'text-sidebar-foreground'
                    "
                >
                    <component
                        :is="item.icon"
                        class="size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span v-if="!collapsed">{{ item.label }}</span>
                </Link>
            </div>
        </div>
    </nav>
</template>
