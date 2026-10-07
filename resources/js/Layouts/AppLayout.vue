<script setup lang="ts">
import { Toaster } from '@/Components/ui/sonner';
import { useFlash } from '@/composables/useFlash';
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Sidebar from './partials/Sidebar.vue';
import Topbar from './partials/Topbar.vue';

defineProps<{ title?: string }>();

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
    <div class="bg-background flex min-h-screen">
        <aside
            class="hidden shrink-0 transition-[width] lg:block"
            :class="collapsed ? 'w-[72px]' : 'w-[264px]'"
        >
            <div class="sticky top-0 h-screen">
                <Sidebar :collapsed="collapsed" />
            </div>
        </aside>

        <div v-if="drawerOpen" class="fixed inset-0 z-40 lg:hidden">
            <div
                class="absolute inset-0 bg-black/40"
                @click="drawerOpen = false"
            />
            <div class="absolute inset-y-0 left-0 w-[264px]">
                <Sidebar :collapsed="false" />
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <Topbar
                @toggle-sidebar="collapsed = !collapsed"
                @open-drawer="drawerOpen = true"
            >
                <template #breadcrumb><slot name="breadcrumb" /></template>
            </Topbar>
            <main class="mx-auto w-full max-w-[1280px] flex-1 p-4 sm:p-6">
                <slot />
            </main>
        </div>
        <Toaster />
    </div>
</template>
