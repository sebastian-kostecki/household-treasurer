<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import FormField from '@/components/FormField.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/password/confirm';

const t = useTranslations();

setLayoutProps({
    title: t.auth.confirm_password.title,
    description: t.auth.confirm_password.description,
    icon: ShieldCheck,
});
</script>

<template>
    <Head :title="t.auth.confirm_password.title" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="t.auth.passkey.confirm"
        :loading-label="t.auth.passkey.loading"
        :separator="t.auth.passkey.or"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FormField
            :label="t.auth.confirm_password.password"
            :error="errors.password"
            v-slot="{ id, describedBy, invalid }"
        >
            <PasswordInput
                :id="id"
                name="password"
                required
                autocomplete="current-password"
                autofocus
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <Button
            type="submit"
            class="auth-submit"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.confirm_password.submit }}
        </Button>
    </Form>
</template>
