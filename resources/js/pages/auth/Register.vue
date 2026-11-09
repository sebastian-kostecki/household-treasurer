<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import AuthCardFooter from '@/components/AuthCardFooter.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';

const t = useTranslations();

setLayoutProps({
    title: t.auth.register.title,
    description: t.auth.register.description,
});

// Registration is off (`Features::registration()`), so Wayfinder has no `register`
// route yet; `action` stays a literal until it is enabled, then use its `.form()`.
defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="t.auth.register.title" />

    <Form
        action="/register"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FormField
            :label="t.auth.register.name"
            :error="errors.name"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            :label="t.auth.register.email"
            :error="errors.email"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                type="email"
                name="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            :label="t.auth.register.password"
            :error="errors.password"
            v-slot="{ id, describedBy, invalid }"
        >
            <PasswordInput
                :id="id"
                name="password"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            :label="t.auth.register.confirm_password"
            :error="errors.password_confirmation"
            v-slot="{ id, describedBy, invalid }"
        >
            <PasswordInput
                :id="id"
                name="password_confirmation"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <Button
            type="submit"
            class="auth-submit"
            :disabled="processing"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.register.submit }}
        </Button>
    </Form>

    <AuthCardFooter>
        <span>{{ t.auth.register.has_account }}</span>
        <TextLink :href="login()">{{ t.auth.register.log_in }}</TextLink>
    </AuthCardFooter>
</template>
