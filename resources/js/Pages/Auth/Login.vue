<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import PasswordInput from '@/Components/shared/PasswordInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({ email: '', password: '', remember: false });
const clientErrors = ref<{ email?: string; password?: string }>({});

// Batas percobaan: server mengirim error `throttle` berisi sisa detik; hitung mundur di sini.
const retryIn = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

watch(
    () => (form.errors as Record<string, string | undefined>).throttle,
    (message) => {
        clearInterval(timer);
        retryIn.value = Number(message?.match(/(\d+)\s*detik/)?.[1] ?? 0);
        if (retryIn.value > 0) {
            timer = setInterval(() => {
                retryIn.value -= 1;
                if (retryIn.value <= 0) {
                    clearInterval(timer);
                    form.clearErrors('throttle' as never);
                }
            }, 1000);
        }
    },
);
onUnmounted(() => clearInterval(timer));

const throttled = computed(() => retryIn.value > 0);
// Setelah email terisi, kegagalan dari server berarti kredensial salah: tampil sebagai banner.
const credentialError = computed(() => form.errors.email ?? null);

function submit() {
    clientErrors.value = {};
    if (!form.email) clientErrors.value.email = 'Email wajib diisi.';
    if (!form.password) clientErrors.value.password = 'Password wajib diisi.';
    if (Object.keys(clientErrors.value).length > 0) return;

    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthLayout title="Masuk">
        <form class="flex flex-col gap-8" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">Masuk</h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Gunakan akun yang diberikan admin kecamatan.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <InlineAlert v-if="status" variant="success">{{
                    status
                }}</InlineAlert>
                <InlineAlert v-if="throttled" variant="warning">
                    Terlalu banyak percobaan. Coba lagi dalam
                    {{ retryIn }} detik.
                </InlineAlert>
                <InlineAlert v-else-if="credentialError">{{
                    credentialError
                }}</InlineAlert>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email"
                    :error="clientErrors.email"
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

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Password"
                    :error="clientErrors.password ?? form.errors.password"
                >
                    <PasswordInput
                        :id="id"
                        v-model="form.password"
                        autocomplete="current-password"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <div class="flex h-10 items-center justify-between">
                    <label class="flex items-center gap-2 text-sm leading-5">
                        <Checkbox
                            v-model="form.remember"
                            class="border-muted-foreground"
                        />
                        Ingat saya
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-primary text-sm leading-5 font-medium hover:underline"
                    >
                        Lupa password?
                    </Link>
                </div>

                <Button
                    type="submit"
                    class="h-10 w-full"
                    :disabled="form.processing || throttled"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    Masuk
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
