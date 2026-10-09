<script setup lang="ts">
import { Input } from '@/Components/ui/input';
import { Search } from '@lucide/vue';

defineOptions({ inheritAttrs: false });
defineProps<{ placeholder?: string; id?: string }>();

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <Search
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            aria-hidden="true"
        />
        <Input
            v-bind="$attrs"
            :id="id"
            v-model="model"
            class="h-10 bg-white pl-9"
            :placeholder="placeholder"
        />
    </div>
</template>
