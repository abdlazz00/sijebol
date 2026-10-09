<script setup lang="ts">
import type { Crumb } from '@/Components/shared/Breadcrumbs.vue';
import LoadFailedBanner from '@/Components/shared/LoadFailedBanner.vue';
import { Sheet, SheetContent, SheetTitle } from '@/Components/ui/sheet';
import { Toaster } from '@/Components/ui/sonner';
import { useFlash } from '@/composables/useFlash';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Sidebar from './partials/Sidebar.vue';
import Topbar from './partials/Topbar.vue';

const props = defineProps<{ title?: string; breadcrumbs?: Crumb[] }>();

const crumbs = computed<Crumb[]>(
    () => props.breadcrumbs ?? (props.title ? [{ label: props.title }] : []),
);

const collapsed = ref(false);
const drawerOpen = ref(false);

const { flash } = useFlash();
watch(
    flash,
    (value) => {
        if (value.success) toast.success(value.success);
        if (value.error) toast.error(value.error);
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="title" />
    <div class="bg-background flex min-h-dvh">
        <aside
            class="hidden shrink-0 transition-[width] lg:block"
            :class="collapsed ? 'w-[72px]' : 'w-[264px]'"
        >
            <div class="sticky top-0 h-screen">
                <Sidebar :collapsed="collapsed" />
            </div>
        </aside>

        <Sheet v-model:open="drawerOpen">
            <SheetContent
                side="left"
                class="border-sidebar-border bg-sidebar [&>button]:hover:bg-sidebar-accent w-[264px] p-0 text-white sm:max-w-[264px] [&>button]:text-white"
            >
                <SheetTitle class="sr-only">Menu navigasi</SheetTitle>
                <Sidebar :collapsed="false" />
            </SheetContent>
        </Sheet>

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar
                :breadcrumbs="crumbs"
                @toggle-sidebar="collapsed = !collapsed"
                @open-drawer="drawerOpen = true"
            />
            <main class="mx-auto w-full max-w-[1280px] flex-1 p-4 sm:p-6">
                <LoadFailedBanner />
                <slot />
            </main>
        </div>
        <Toaster />
    </div>
</template>
