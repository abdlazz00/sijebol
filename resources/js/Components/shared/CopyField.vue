<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { copyText } from '@/lib/clipboard';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{ value: string; label: string }>();

const input = ref<HTMLTextAreaElement | null>(null);
const copied = ref(false);
const failed = ref(false);

async function copy() {
    failed.value = !(await copyText(props.value));
    copied.value = !failed.value;

    // Bila salin otomatis gagal, pilih teksnya agar bisa disalin manual.
    if (failed.value) input.value?.select();
    else setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row">
        <textarea
            ref="input"
            :value="value"
            :aria-label="label"
            readonly
            rows="2"
            class="border-input bg-secondary field-sizing-content min-h-10 min-w-0 flex-1 resize-none rounded-lg border px-3 py-2.5 font-mono text-xs leading-4 break-all"
            @focus="input?.select()"
        />
        <Button type="button" class="h-10 px-4" @click="copy">
            <Check v-if="copied" class="size-4" aria-hidden="true" />
            <Copy v-else class="size-4" aria-hidden="true" />
            {{ copied ? 'Tersalin' : 'Salin link' }}
        </Button>
        <span class="sr-only" role="status">{{
            copied
                ? 'Link disalin.'
                : failed
                  ? 'Gagal menyalin. Salin link secara manual.'
                  : ''
        }}</span>
    </div>
</template>
