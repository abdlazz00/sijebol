<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title?: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="bg-card flex min-h-screen flex-col lg:flex-row">
        <aside
            class="bg-sidebar text-sidebar-foreground flex flex-col justify-between p-6 lg:w-[45%] lg:p-12"
        >
            <div class="text-xl font-semibold tracking-tight">SIJEBOL</div>
            <div class="hidden lg:block">
                <h1 class="text-3xl leading-tight font-semibold">
                    Sistem Jemput Bola<br />Perekaman KTP-El Pelajar
                </h1>
                <p class="text-sidebar-muted mt-3">
                    Pendaftaran digital yang lebih mudah untuk sekolah dan
                    pelajar.
                </p>
            </div>
            <p class="text-sidebar-muted hidden text-sm lg:block">
                Kecamatan Sungai Beduk
            </p>
        </aside>
        <main class="flex flex-1 items-center justify-center px-4 py-10">
            <div class="w-full max-w-[400px]"><slot /></div>
        </main>
    </div>
</template>
