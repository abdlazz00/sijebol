<script setup lang="ts">
import { passwordRules, passwordScore } from '@/lib/passwordRules';
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ password: string }>();
const rules = computed(() => passwordRules(props.password));
const score = computed(() => passwordScore(props.password));
const bar = ['bg-danger', 'bg-warning', 'bg-success'];
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex gap-1" aria-hidden="true">
            <span
                v-for="n in 3"
                :key="n"
                class="h-1 flex-1 rounded-full"
                :class="n <= score ? bar[score - 1] : 'bg-neutral-soft'"
            />
        </div>
        <ul class="flex flex-col gap-1 text-xs leading-4">
            <li
                v-for="rule in rules"
                :key="rule.label"
                class="flex items-center gap-2"
                :class="rule.met ? 'text-success' : 'text-muted-foreground'"
            >
                <Check v-if="rule.met" class="size-3.5" aria-hidden="true" />
                <Circle v-else class="size-3.5" aria-hidden="true" />
                {{ rule.label }}
                <span class="sr-only">{{
                    rule.met ? '(terpenuhi)' : '(belum terpenuhi)'
                }}</span>
            </li>
        </ul>
    </div>
</template>
