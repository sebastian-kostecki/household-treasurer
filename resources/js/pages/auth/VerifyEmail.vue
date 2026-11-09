<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { CircleCheck, Mail } from '@lucide/vue';
import AuthCardFooter from '@/components/AuthCardFooter.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const t = useTranslations();
const email = usePage().props.auth.user.email;

setLayoutProps({
    title: t.auth.verify_email.title,
    description: t.auth.verify_email.description,
    icon: Mail,
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t.auth.verify_email.title" />

    <Alert v-if="status === 'verification-link-sent'" variant="ok">
        <CircleCheck />
        <AlertTitle>{{ t.auth.verify_email.sent_title }}</AlertTitle>
        <AlertDescription>{{
            t.auth.verify_email.sent_body.replace(':email', email)
        }}</AlertDescription>
    </Alert>

    <Form v-bind="send.form()" v-slot="{ processing }">
        <Button
            type="submit"
            variant="outline"
            class="auth-submit"
            :disabled="processing"
            data-test="resend-verification-button"
        >
            <Spinner v-if="processing" />
            {{ t.auth.verify_email.resend }}
        </Button>
    </Form>

    <AuthCardFooter>
        <span>{{ t.auth.verify_email.wrong_address }}</span>
        <TextLink :href="logout()" as="button">
            {{ t.auth.verify_email.logout }}
        </TextLink>
    </AuthCardFooter>
</template>
