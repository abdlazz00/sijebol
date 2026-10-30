<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import PasswordInput from '@/Components/shared/PasswordInput.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';

const open = defineModel<boolean>('open', { required: true });

const form = useForm({ current_password: '' });
const clientError = ref<string>();

// Dibuka lewat kode, bukan DialogTrigger: reka-ui tak tahu ke mana fokus kembali, jadi diingat sendiri.
let opener: HTMLElement | null = null;

// Dialog yang dibuka lagi tidak boleh menyisakan password atau galat sebelumnya.
watch(open, (isOpen) => {
    if (isOpen) {
        opener = document.activeElement as HTMLElement | null;
        return;
    }
    form.reset();
    form.clearErrors();
    clientError.value = undefined;
});

function restoreFocus(event: Event) {
    event.preventDefault();
    opener?.focus();
}

function submit() {
    if (!form.current_password) {
        clientError.value = 'Password saat ini wajib diisi.';
        return focusFirstInvalid();
    }

    clientError.value = undefined;
    form.clearErrors();
    form.delete(route('profile.sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: focusFirstInvalid,
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="rounded-xl" @close-auto-focus="restoreFocus">
            <form
                class="flex flex-col gap-4"
                novalidate
                @submit.prevent="submit"
            >
                <DialogHeader>
                    <DialogTitle>Keluar dari perangkat lain?</DialogTitle>
                    <DialogDescription>
                        Semua perangkat lain akan keluar dari akun ini.
                        Perangkat ini tetap masuk.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Password saat ini"
                    :error="clientError ?? form.errors.current_password"
                >
                    <PasswordInput
                        :id="id"
                        v-model="form.current_password"
                        autocomplete="current-password"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{
                            form.processing
                                ? 'Memproses…'
                                : 'Keluar dari perangkat lain'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
