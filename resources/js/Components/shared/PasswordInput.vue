<script setup lang="ts">
import { Input } from '@/Components/ui/input';
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const model = defineModel<string>({ default: '' });
const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            v-model="model"
            :type="visible ? 'text' : 'password'"
            class="h-10 pr-10"
            v-bind="$attrs"
        />
        <button
            type="button"
            class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-10 items-center justify-center"
            :aria-label="
                visible ? 'Sembunyikan password' : 'Tampilkan password'
            "
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
