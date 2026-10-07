<script setup lang="ts">
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/Components/ui/combobox';
import { Check, ChevronDown } from '@lucide/vue';
import { computed } from 'vue';

type Value = string | number | null;

const props = defineProps<{
    modelValue: Value;
    options: { value: string | number; label: string }[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    id?: string;
    invalid?: boolean;
    describedBy?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: Value] }>();

const current = computed(() =>
    props.modelValue === null || props.modelValue === ''
        ? undefined
        : String(props.modelValue),
);
const label = computed(
    () =>
        props.options.find((o) => String(o.value) === current.value)?.label ??
        null,
);

function onChange(value: unknown) {
    const option = props.options.find((o) => String(o.value) === String(value));
    emit('update:modelValue', option ? option.value : null);
}
</script>

<template>
    <Combobox :model-value="current" @update:model-value="onChange">
        <ComboboxAnchor as-child>
            <ComboboxTrigger as-child>
                <button
                    :id="id"
                    type="button"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive flex h-10 w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 text-left text-sm outline-none focus-visible:ring-3"
                    :aria-invalid="invalid"
                    :aria-describedby="describedBy"
                >
                    <span :class="label ? '' : 'text-muted-foreground'">{{
                        label ?? placeholder
                    }}</span>
                    <ChevronDown
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                </button>
            </ComboboxTrigger>
        </ComboboxAnchor>
        <ComboboxList>
            <ComboboxInput
                class="h-9 border-0 bg-transparent px-0 text-sm shadow-none ring-0 focus:ring-0"
                :placeholder="searchPlaceholder ?? 'Cari…'"
            />
            <ComboboxEmpty>{{ emptyText ?? 'Tidak ada hasil' }}</ComboboxEmpty>
            <ComboboxViewport>
                <ComboboxItem
                    v-for="option in options"
                    :key="option.value"
                    :value="String(option.value)"
                >
                    {{ option.label }}
                    <ComboboxItemIndicator class="absolute right-2">
                        <Check class="size-4" aria-hidden="true" />
                    </ComboboxItemIndicator>
                </ComboboxItem>
            </ComboboxViewport>
        </ComboboxList>
    </Combobox>
</template>
