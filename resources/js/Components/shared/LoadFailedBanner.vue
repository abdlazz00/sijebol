<script setup lang="ts">
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import { Button } from '@/Components/ui/button';
import {
    dismissLoadFailure,
    retryLoad,
    useLoadFailure,
} from '@/composables/useLoadFailure';

const { failed } = useLoadFailure();
</script>

<template>
    <InlineAlert v-if="failed" class="mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="min-w-0 flex-1"
                >Gagal memuat data. Periksa koneksi Anda lalu coba lagi.</span
            >
            <Button
                type="button"
                variant="outline"
                class="h-9 px-3"
                @click="retryLoad()"
            >
                Coba lagi
            </Button>
            <Button
                type="button"
                variant="ghost"
                class="h-9 px-3"
                @click="dismissLoadFailure()"
            >
                Tutup
            </Button>
        </div>
    </InlineAlert>
</template>
