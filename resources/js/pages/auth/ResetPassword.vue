<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/password';

const t = useTranslations();

setLayoutProps({
    title: t.auth.reset_password.title,
    description: t.auth.reset_password.description,
});

const props = defineProps<{
    token: string;
    email: string | null;
    passwordRules: string;
}>();

const emailLocked = props.email !== null && props.email !== '';
const inputEmail = ref(props.email ?? '');
</script>

<template>
    <Head :title="t.auth.reset_password.title" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email: inputEmail })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FormField
            :label="t.auth.reset_password.email"
            :error="errors.email"
            v-slot="{ id, describedBy, invalid }"
        >
            <Input
                :id="id"
                v-model="inputEmail"
                type="email"
                name="email"
                autocomplete="email"
                :autofocus="!emailLocked"
                :readonly="emailLocked"
                :class="emailLocked ? 'bg-muted text-muted-foreground' : ''"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            :label="t.auth.reset_password.password"
            :error="errors.password"
            v-slot="{ id, describedBy, invalid }"
        >
            <PasswordInput
                :id="id"
                name="password"
                autocomplete="new-password"
                :autofocus="emailLocked"
                :passwordrules="passwordRules"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>

        <FormField
            :label="t.auth.reset_password.confirm_password"
            :error="errors.password_confirmation"
            v-slot="{ id, describedBy, invalid }"
        >
            <PasswordInput
                :id="id"
                name="password_confirmation"
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
            data-test="reset-password-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.reset_password.submit }}
        </Button>
    </Form>
</template>
