<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const open = defineModel<boolean>('open', { required: true });

withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel?: string;
        destructive?: boolean;
    }>(),
    { confirmLabel: 'Ya, lanjutkan', destructive: false },
);

const emit = defineEmits<{ confirm: [] }>();

function confirm() {
    emit('confirm');
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    @click="confirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
