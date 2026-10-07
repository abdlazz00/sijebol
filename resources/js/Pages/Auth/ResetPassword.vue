<script setup lang="ts">
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import NewPasswordFields from '@/Components/shared/NewPasswordFields.vue';
import { Button } from '@/Components/ui/button';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
const mismatch = ref('');

function submit() {
    mismatch.value = '';
    if (form.password !== form.password_confirmation) {
        mismatch.value = 'Konfirmasi password tidak sama.';
        return focusFirstInvalid();
    }

    form.post(route('password.store'), {
        onError: focusFirstInvalid,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Atur ulang password">
        <form class="flex flex-col gap-8" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Atur ulang password
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Buat password baru untuk akun Anda.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <InlineAlert v-if="form.errors.email">
                    {{ form.errors.email }}
                    <Link
                        :href="route('password.request')"
                        class="font-medium underline"
                        >Minta link baru</Link
                    >
                </InlineAlert>

                <div
                    class="bg-secondary flex items-center justify-between gap-2 rounded-lg p-3 text-sm leading-5"
                >
                    <span class="min-w-0 break-all">{{ email }}</span>
                    <Lock
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                </div>

                <NewPasswordFields
                    v-model:password="form.password"
                    v-model:confirmation="form.password_confirmation"
                    :error="form.errors.password"
                    :confirmation-error="mismatch"
                    autofocus
                />

                <Button
                    type="submit"
                    class="h-10 w-full"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    Simpan password
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
