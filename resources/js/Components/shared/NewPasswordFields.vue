<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import PasswordInput from '@/Components/shared/PasswordInput.vue';
import PasswordRules from '@/Components/shared/PasswordRules.vue';

defineProps<{
    error?: string;
    confirmationError?: string;
    autofocus?: boolean;
}>();

const password = defineModel<string>('password', { required: true });
const confirmation = defineModel<string>('confirmation', { required: true });
</script>

<template>
    <FormField
        v-slot="{ id, describedBy, invalid }"
        label="Password baru"
        :error="error"
    >
        <PasswordInput
            :id="id"
            v-model="password"
            autocomplete="new-password"
            :autofocus="autofocus"
            :aria-invalid="invalid"
            :aria-describedby="describedBy"
        />
    </FormField>
    <PasswordRules :password="password" />
    <FormField
        v-slot="{ id, describedBy, invalid }"
        label="Konfirmasi password"
        :error="confirmationError"
    >
        <PasswordInput
            :id="id"
            v-model="confirmation"
            autocomplete="new-password"
            :aria-invalid="invalid"
            :aria-describedby="describedBy"
        />
    </FormField>
</template>
