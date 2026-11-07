<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const t = useTranslations();

setLayoutProps({
    title: t.auth.login.title,
    description: t.auth.login.description,
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="t.auth.login.title" />

    <Alert v-if="status" variant="ok">
        <CircleCheck />
        <span>{{ status }}</span>
    </Alert>

    <PasskeyVerify
        :label="t.auth.passkey.login"
        :loading-label="t.auth.passkey.loading"
        :separator="t.auth.passkey.or"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FormField
            :label="t.auth.login.email"
            :error="errors.email"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <div class="form-field">
            <div class="flex items-center justify-between gap-2">
                <Label for="password" class="form-field-label">{{
                    t.auth.login.password
                }}</Label>
                <TextLink v-if="canResetPassword" :href="request()">
                    {{ t.auth.login.forgot_password }}
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                :aria-describedby="
                    errors.password ? 'password-error' : undefined
                "
                :aria-invalid="errors.password ? 'true' : undefined"
            />
            <InputError
                id="password-error"
                :message="errors.password"
                size="xs"
            />
        </div>

        <Label for="remember" class="flex items-center gap-2 font-normal">
            <Checkbox id="remember" name="remember" />
            <span>{{ t.auth.login.remember }}</span>
        </Label>

        <Button
            type="submit"
            class="auth-submit"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.login.submit }}
        </Button>
    </Form>
</template>
