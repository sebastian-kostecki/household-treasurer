<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck, Lock } from '@lucide/vue';
import AuthCardFooter from '@/components/AuthCardFooter.vue';
import FormField from '@/components/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { email } from '@/routes/password';

const t = useTranslations();

setLayoutProps({
    title: t.auth.forgot_password.title,
    description: t.auth.forgot_password.description,
    icon: Lock,
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t.auth.forgot_password.title" />

    <Alert v-if="status" variant="ok">
        <CircleCheck />
        <span>{{ status }}</span>
    </Alert>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FormField
            :label="t.auth.forgot_password.email"
            :error="errors.email"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                type="email"
                name="email"
                autocomplete="email"
                autofocus
                placeholder="email@example.com"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <Button
            type="submit"
            class="auth-submit"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.forgot_password.submit }}
        </Button>
    </Form>

    <AuthCardFooter>
        <TextLink :href="login()">{{ t.auth.forgot_password.back }}</TextLink>
    </AuthCardFooter>
</template>
