<script setup lang="ts">
import { Label } from '@/Components/ui/label';
import { computed, useId } from 'vue';

const props = defineProps<{
    label: string;
    required?: boolean;
    optional?: boolean;
    hint?: string;
    error?: string | null;
    id?: string;
}>();

const autoId = useId();
const fieldId = computed(() => props.id ?? autoId);
const describedBy = computed(() => {
    if (props.error) return `${fieldId.value}-error`;

    return props.hint ? `${fieldId.value}-hint` : undefined;
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label :for="fieldId" class="text-sm leading-5 font-medium">
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true"
                >*</span
            >
            <span v-else-if="optional" class="text-muted-foreground font-normal"
                >(opsional)</span
            >
        </Label>
        <slot :id="fieldId" :described-by="describedBy" :invalid="!!error" />
        <p
            v-if="error"
            :id="`${fieldId}-error`"
            class="text-danger text-xs leading-4"
            role="alert"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="`${fieldId}-hint`"
            class="text-muted-foreground text-xs leading-4"
        >
            {{ hint }}
        </p>
    </div>
</template>
