<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Clock, SearchX, ShieldAlert, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ status: number }>();

const messages: Record<
    number,
    { icon: typeof SearchX; title: string; text: string }
> = {
    403: {
        icon: ShieldAlert,
        title: 'Anda tidak memiliki akses ke halaman ini',
        text: 'Hubungi admin kecamatan bila Anda merasa perlu akses.',
    },
    404: {
        icon: SearchX,
        title: 'Halaman tidak ditemukan',
        text: 'Periksa kembali alamat atau kembali ke beranda.',
    },
    429: {
        icon: Clock,
        title: 'Terlalu banyak permintaan',
        text: 'Tunggu sebentar, lalu coba lagi.',
    },
};
const fallback = {
    icon: TriangleAlert,
    title: 'Terjadi kesalahan',
    text: 'Silakan coba lagi beberapa saat lagi.',
};

const message = computed(() => messages[props.status] ?? fallback);

// Pengguna login memakai shell; tamu memakai layout autentikasi.
const loggedIn = computed(() => usePage<PageProps>().props.auth?.user != null);
const layout = computed(() => (loggedIn.value ? AppLayout : AuthLayout));
const home = computed(() =>
    loggedIn.value ? route('dashboard') : route('login'),
);
</script>

<template>
    <component :is="layout" :title="message.title">
        <div class="flex flex-col items-start gap-4 py-8">
            <component
                :is="message.icon"
                class="text-muted-foreground size-16"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-2">
                <p class="text-muted-foreground text-xs leading-4 font-medium">
                    Kode {{ status }}
                </p>
                <h1 class="text-2xl leading-8 font-semibold">
                    {{ message.title }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ message.text }}
                </p>
            </div>
            <Button as-child class="h-10 px-4">
                <Link :href="home">{{
                    loggedIn ? 'Kembali ke beranda' : 'Ke halaman masuk'
                }}</Link>
            </Button>
        </div>
    </component>
</template>
