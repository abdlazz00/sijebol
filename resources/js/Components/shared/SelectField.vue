<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { computed } from 'vue';

type Value = string | number | null;

const props = defineProps<{
    modelValue: Value;
    options: { value: string | number; label: string }[];
    /** Bila diisi, ditambahkan opsi "semua" yang bernilai kosong (untuk filter). */
    allLabel?: string;
    placeholder?: string;
    id?: string;
    invalid?: boolean;
    describedBy?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: Value] }>();

// reka-ui tidak mengizinkan nilai kosong pada item, jadi opsi "semua" memakai penanda.
const ALL = '__all__';

const current = computed(() => {
    if (props.modelValue === null || props.modelValue === '')
        return props.allLabel ? ALL : undefined;

    return String(props.modelValue);
});

function onChange(value: unknown) {
    if (value === ALL) return emit('update:modelValue', '');

    const option = props.options.find((o) => String(o.value) === String(value));
    emit('update:modelValue', option ? option.value : null);
}
</script>

<template>
    <Select
        :model-value="current"
        :disabled="disabled"
        @update:model-value="onChange"
    >
        <SelectTrigger
            :id="id"
            class="h-10 w-full bg-white px-3"
            :aria-invalid="invalid"
            :aria-describedby="describedBy"
        >
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="allLabel" :value="ALL">{{ allLabel }}</SelectItem>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="String(option.value)"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
