<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Ellipsis } from '@lucide/vue';

defineProps<{ label: string }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-10"
                :aria-label="label"
            >
                <Ellipsis class="size-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 p-2">
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
