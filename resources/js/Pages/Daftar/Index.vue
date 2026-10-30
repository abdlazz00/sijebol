<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Clock, LoaderCircle, Mail } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    valid: boolean;
    school?: string;
    sent: boolean;
    ttl_days: number;
}>();

// Backend tidak mengirim token sebagai prop; ambil dari segmen terakhir URL.
const token = usePage().url.split('?')[0].split('/').pop() ?? '';

// Siswa berpindah ke aplikasi email lalu kembali: isian disimpan agar tidak hilang.
const STORAGE_KEY = 'sijebol.daftar';
function remembered(): { name?: string; email?: string } {
    try {
        return JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
}
const saved = remembered();
const form = useForm({ name: saved.name ?? '', email: saved.email ?? '' });

const editing = ref(false);
const showForm = computed(() => !props.sent || editing.value);

const clientErrors = ref<Record<string, string>>({});
const error = (key: 'name' | 'email') =>
    clientErrors.value[key] ?? form.errors[key];
const hasErrors = computed(
    () =>
        Object.keys(clientErrors.value).length > 0 ||
        Object.keys(form.errors).length > 0,
);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) errors.name = 'Nama lengkap wajib diisi.';
    if (!form.email.trim()) errors.email = 'Email wajib diisi.';
    else if (!/^\S+@\S+\.\S+$/.test(form.email.trim()))
        errors.email = 'Format email tidak valid.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

const RESEND_SECONDS = 60;
const cooldown = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

function startCooldown() {
    clearInterval(timer);
    cooldown.value = RESEND_SECONDS;
    timer = setInterval(() => {
        cooldown.value -= 1;
        if (cooldown.value <= 0) clearInterval(timer);
    }, 1000);
}

onMounted(() => {
    if (props.sent) startCooldown();
});
onUnmounted(() => clearInterval(timer));

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    try {
        sessionStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ name: form.name, email: form.email }),
        );
    } catch {
        // Penyimpanan peramban boleh gagal; formulir tetap berfungsi.
    }

    form.post(route('register.store', token), {
        preserveScroll: true,
        onError: focusFirstInvalid,
        onSuccess: () => {
            editing.value = false;
            startCooldown();
        },
    });
}
</script>

<template>
    <AuthLayout title="Buat akun">
        <div v-if="!valid" class="flex flex-col items-start gap-4">
            <Clock class="text-muted-foreground size-10" aria-hidden="true" />
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Link pendaftaran tidak berlaku
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Link ini sudah diganti atau salah. Minta link terbaru ke
                    operator sekolah Anda.
                </p>
            </div>
            <Button as-child variant="outline" class="h-10 px-4">
                <Link :href="route('login')">Ke halaman masuk</Link>
            </Button>
        </div>

        <form
            v-else-if="showForm"
            class="flex flex-col gap-8"
            novalidate
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Buat akun SIJEBOL
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    Pendaftaran siswa {{ school }}. Data Anda dipakai Kecamatan
                    Sungai Beduk untuk perekaman KTP-el pelajar.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nama lengkap"
                    required
                    :error="error('name')"
                    hint="Sesuai Kartu Keluarga."
                >
                    <Input
                        :id="id"
                        v-model="form.name"
                        class="h-10"
                        autocomplete="name"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Email"
                    required
                    :error="error('email')"
                    hint="Link aktivasi dikirim ke email ini."
                >
                    <Input
                        :id="id"
                        v-model="form.email"
                        type="email"
                        inputmode="email"
                        class="h-10"
                        autocomplete="email"
                        autocapitalize="none"
                        spellcheck="false"
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
                    Buat akun
                </Button>
            </div>
        </form>

        <div v-else class="flex flex-col items-start gap-6">
            <Mail class="text-muted-foreground size-10" aria-hidden="true" />
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl leading-8 font-semibold">
                    Periksa email Anda
                </h1>
                <p class="text-sm leading-5">
                    Jika alamat
                    <strong class="break-all">{{ form.email }}</strong> dapat
                    didaftarkan, link aktivasi sudah dikirim. Buka email, lalu
                    ketuk <strong>Aktifkan akun</strong>. Link berlaku
                    {{ ttl_days }} hari.
                </p>
                <p class="text-muted-foreground text-sm leading-5">
                    Tidak ada di Kotak Masuk? Cek folder Spam atau Promosi.
                </p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    :disabled="cooldown > 0 || form.processing"
                    @click="submit"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{
                        cooldown > 0
                            ? `Kirim ulang (${cooldown} dtk)`
                            : 'Kirim ulang'
                    }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="h-10 px-4"
                    @click="editing = true"
                >
                    Salah email? Ubah
                </Button>
            </div>
            <span class="sr-only" role="status">{{
                cooldown === 0 ? 'Anda dapat mengirim ulang email.' : ''
            }}</span>
        </div>
    </AuthLayout>
</template>
