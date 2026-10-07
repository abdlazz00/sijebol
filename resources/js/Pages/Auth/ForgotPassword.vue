<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from '@lucide/vue';
import { ref } from 'vue';

// `status` hanya dipakai sebagai penanda terkirim; teks selalu sama agar
// tidak membocorkan apakah email terdaftar.
defineProps<{ status?: string }>();

const form = useForm({ email: '' });
const emailError = ref('');

function submit() {
    emailError.value = form.email.trim() ? '' : 'Email wajib diisi.';
    if (emailError.value) return focusFirstInvalid();

    form.post(route('password.email'), { onError: focusFirstInvalid });
}
</script>

<template>
    <AuthLayout title="Lupa password">
        <div v-if="status" class="flex flex-col items-start gap-4">
            <Mail class="text-muted-foreground size-10" aria-hidden="true" />
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Periksa email Anda
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Jika email terdaftar, link reset password sudah dikirim.
                </p>
            </div>
            <Button as-child variant="outline" class="h-10 px-4">
                <Link :href="route('login')">Kembali ke login</Link>
            </Button>
        </div>

        <form
            v-else
            class="flex flex-col gap-8"
            novalidate
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">Lupa password?</h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Masukkan email akun Anda. Kami akan mengirim link untuk
                    mengatur ulang password.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email"
                    :error="emailError || form.errors.email"
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        class="h-10"
                        autocomplete="username"
                        autofocus
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

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
                    Kirim link reset
                </Button>
                <Link
                    :href="route('login')"
                    class="text-primary text-center text-sm leading-5 font-medium hover:underline"
                >
                    Kembali ke login
                </Link>
            </div>
        </form>
    </AuthLayout>
</template>
