<script setup lang="ts">
import NewPasswordFields from '@/Components/shared/NewPasswordFields.vue';
import { Button } from '@/Components/ui/button';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Clock, LoaderCircle, Lock } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
    valid: boolean;
    name?: string;
    email?: string;
    school?: string | null;
}>();

// Backend tidak mengirim token sebagai prop; ambil dari segmen terakhir URL.
const token = usePage().url.split('?')[0].split('/').pop() ?? '';

const form = useForm({ password: '', password_confirmation: '' });
const mismatch = ref('');

function submit() {
    mismatch.value = '';
    if (form.password !== form.password_confirmation) {
        mismatch.value = 'Konfirmasi password tidak sama.';
        return focusFirstInvalid();
    }

    form.post(route('aktivasi.store', token), {
        onError: focusFirstInvalid,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Aktifkan akun">
        <form
            v-if="valid"
            class="flex flex-col gap-8"
            novalidate
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Aktifkan akun Anda
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Halo, {{ name }}. Buat password untuk mengaktifkan akun.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <dl
                    class="bg-secondary flex flex-col gap-1 rounded-lg p-3 text-sm leading-5"
                >
                    <div class="flex items-center justify-between gap-2">
                        <dt class="sr-only">Email</dt>
                        <dd class="min-w-0 break-all">{{ email }}</dd>
                        <Lock
                            class="text-muted-foreground size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </div>
                    <div v-if="school">
                        <dt class="sr-only">Sekolah</dt>
                        <dd class="text-muted-foreground text-xs leading-4">
                            {{ school }}
                        </dd>
                    </div>
                </dl>

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
                    Aktifkan akun
                </Button>
            </div>
        </form>

        <div v-else class="flex flex-col items-start gap-4">
            <Clock class="text-muted-foreground size-10" aria-hidden="true" />
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Link aktivasi tidak berlaku
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Link ini sudah kedaluwarsa atau sudah dipakai. Bila akun
                    sudah aktif, silakan masuk. Bila belum, buka lagi link
                    pendaftaran dari sekolah Anda dan buat akun dengan email
                    yang sama untuk menerima link baru.
                </p>
            </div>
            <Button as-child variant="outline" class="h-10 px-4">
                <Link :href="route('login')">Ke halaman masuk</Link>
            </Button>
        </div>
    </AuthLayout>
</template>
