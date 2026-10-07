<script setup lang="ts">
import lambang from '@/assets/brand/lambang-kota-batam.png';
import { Head } from '@inertiajs/vue3';

defineProps<{ title?: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-dvh flex-col bg-white lg:flex-row">
        <aside
            class="bg-sidebar relative flex shrink-0 flex-col justify-center px-6 py-6 text-white lg:min-h-dvh lg:w-[45%] lg:p-16"
        >
            <div class="flex items-start gap-2">
                <img
                    :src="lambang"
                    alt="Lambang Kota Batam"
                    class="h-24 w-auto shrink-0 object-contain lg:h-40"
                />
                <div>
                    <p
                        class="font-brand mt-1 h-[52px] text-[40px] leading-[60px] font-bold lg:mt-[6px] lg:h-[76px] lg:text-[60px] lg:leading-[90px]"
                    >
                        SIJEBOL
                    </p>
                    <p
                        class="font-brand-sub text-xs leading-[18px] uppercase lg:mt-0 lg:text-base lg:leading-6"
                    >
                        Sistem Jemput Bola<br />Perekaman KTP-El Pelajar<br />Kecamatan
                        Sungai Beduk
                    </p>
                </div>
            </div>
            <p
                class="text-sidebar-muted absolute bottom-16 left-16 hidden text-sm leading-5 lg:block"
            >
                Mendekatkan layanan administrasi kependudukan.
            </p>
        </aside>
        <main
            class="flex flex-1 flex-col items-center justify-center px-6 py-10 lg:p-12"
        >
            <div class="w-full max-w-[400px] flex-1 lg:flex-none"><slot /></div>
            <p
                class="text-muted-foreground mt-10 w-full max-w-[400px] text-xs leading-4 lg:hidden"
            >
                Mendekatkan layanan administrasi kependudukan.
            </p>
        </main>
    </div>
</template>
