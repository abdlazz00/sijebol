<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import PasswordInput from '@/Components/shared/PasswordInput.vue';
import NewPasswordFields from '@/Components/shared/NewPasswordFields.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { humanize } from '@/lib/format';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        phone: string | null;
        role: string | null;
    };
}>();

const info = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
});
const infoErrors = ref<Record<string, string>>({});

function saveInfo() {
    const errors: Record<string, string> = {};
    if (!info.name.trim()) errors.name = 'Nama lengkap wajib diisi.';
    if (info.phone && !/^08\d{8,12}$/.test(info.phone))
        errors.phone = 'Nomor HP harus diawali 08.';
    infoErrors.value = errors;
    if (Object.keys(errors).length > 0) return focusFirstInvalid();

    info.clearErrors();
    info.patch(route('profile.update'), {
        preserveScroll: true,
        onError: focusFirstInvalid,
    });
}

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const passwordErrors = ref<Record<string, string>>({});

function savePassword() {
    const errors: Record<string, string> = {};
    if (!password.current_password)
        errors.current_password = 'Password saat ini wajib diisi.';
    if (password.password !== password.password_confirmation)
        errors.password_confirmation = 'Konfirmasi password tidak sama.';
    passwordErrors.value = errors;
    if (Object.keys(errors).length > 0) return focusFirstInvalid();

    password.clearErrors();
    password.put(route('password.update'), {
        preserveScroll: true,
        onError: focusFirstInvalid,
        onSuccess: () => password.reset(),
    });
}
</script>

<template>
    <AppLayout title="Profil">
        <PageHeader
            title="Profil"
            description="Kelola informasi akun dan password Anda."
        />

        <div class="flex max-w-[720px] flex-col gap-6">
            <form
                class="bg-card flex flex-col gap-4 rounded-lg border p-6"
                novalidate
                @submit.prevent="saveInfo"
            >
                <h2 class="text-base leading-6 font-semibold">
                    Informasi profil
                </h2>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nama lengkap"
                    required
                    :error="infoErrors.name ?? info.errors.name"
                >
                    <Input
                        :id="id"
                        v-model="info.name"
                        class="h-10"
                        autocomplete="name"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Nomor HP"
                    optional
                    hint="Gunakan nomor yang diawali 08."
                    :error="infoErrors.phone ?? info.errors.phone"
                >
                    <Input
                        :id="id"
                        v-model="info.phone"
                        class="h-10"
                        inputmode="tel"
                        placeholder="08xxxxxxxxxx"
                        autocomplete="tel"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy }"
                    label="Email"
                    hint="Email tidak dapat diubah."
                >
                    <div class="relative">
                        <Input
                            :id="id"
                            :model-value="profile.email"
                            class="bg-secondary h-10 pr-10 disabled:opacity-100"
                            disabled
                            :aria-describedby="describedBy"
                        />
                        <Lock
                            class="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                    </div>
                </FormField>

                <FormField v-slot="{ id }" label="Role">
                    <Input
                        :id="id"
                        :model-value="humanize(profile.role)"
                        class="bg-secondary h-10 disabled:opacity-100"
                        disabled
                    />
                </FormField>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        class="h-10 px-4"
                        :disabled="info.processing"
                    >
                        <LoaderCircle
                            v-if="info.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{ info.processing ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </div>
            </form>

            <form
                class="bg-card flex flex-col gap-4 rounded-lg border p-6"
                novalidate
                @submit.prevent="savePassword"
            >
                <h2 class="text-base leading-6 font-semibold">Ubah password</h2>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Password saat ini"
                    :error="
                        passwordErrors.current_password ??
                        password.errors.current_password
                    "
                >
                    <PasswordInput
                        :id="id"
                        v-model="password.current_password"
                        autocomplete="current-password"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <NewPasswordFields
                    v-model:password="password.password"
                    v-model:confirmation="password.password_confirmation"
                    :error="password.errors.password"
                    :confirmation-error="passwordErrors.password_confirmation"
                />

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        class="h-10 px-4"
                        :disabled="password.processing"
                    >
                        <LoaderCircle
                            v-if="password.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{
                            password.processing
                                ? 'Menyimpan…'
                                : 'Perbarui password'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
