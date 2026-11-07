<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import AuthCardFooter from '@/components/AuthCardFooter.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/two-factor/login';

const t = useTranslations();

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed(() =>
    showRecoveryInput.value
        ? {
              description: t.auth.two_factor.recovery_description,
              toggleText: t.auth.two_factor.use_code,
          }
        : {
              description: t.auth.two_factor.description,
              toggleText: t.auth.two_factor.use_recovery,
          },
);

watchEffect(() => {
    setLayoutProps({
        title: t.auth.two_factor.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head :title="t.auth.two_factor.title" />

    <Form
        v-if="!showRecoveryInput"
        v-bind="store.form()"
        class="flex flex-col gap-4"
        reset-on-error
        @error="code = ''"
        #default="{ errors, processing, clearErrors }"
    >
        <input type="hidden" name="code" :value="code" />
        <div class="flex flex-col items-center gap-2">
            <InputOTP
                id="otp"
                v-model="code"
                class="auth-otp"
                :maxlength="6"
                :disabled="processing"
                :aria-label="t.auth.two_factor.otp_label"
                :aria-invalid="errors.code ? 'true' : undefined"
                autofocus
            >
                <InputOTPGroup>
                    <InputOTPSlot
                        v-for="index in 3"
                        :key="index"
                        :index="index - 1"
                    />
                </InputOTPGroup>
                <InputOTPSeparator />
                <InputOTPGroup>
                    <InputOTPSlot
                        v-for="index in 3"
                        :key="index + 3"
                        :index="index + 2"
                    />
                </InputOTPGroup>
            </InputOTP>
            <InputError :message="errors.code" size="xs" />
        </div>
        <Button type="submit" class="auth-submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{ t.auth.two_factor.submit }}
        </Button>
        <AuthCardFooter>
            <span>{{ t.auth.two_factor.no_phone }}</span>
            <TextLink as="button" @click="toggleRecoveryMode(clearErrors)">
                {{ authConfigContent.toggleText }}
            </TextLink>
        </AuthCardFooter>
    </Form>

    <Form
        v-else
        v-bind="store.form()"
        class="flex flex-col gap-4"
        reset-on-error
        #default="{ errors, processing, clearErrors }"
    >
        <div class="grid gap-2">
            <Input
                name="recovery_code"
                type="text"
                :placeholder="t.auth.two_factor.recovery_placeholder"
                :aria-label="t.auth.two_factor.recovery_placeholder"
                :aria-describedby="
                    errors.recovery_code ? 'recovery-code-error' : undefined
                "
                :aria-invalid="errors.recovery_code ? 'true' : undefined"
                autofocus
                required
            />
            <InputError
                id="recovery-code-error"
                :message="errors.recovery_code"
                size="xs"
            />
        </div>
        <Button type="submit" class="auth-submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{ t.auth.two_factor.submit }}
        </Button>
        <AuthCardFooter>
            <span>{{ t.auth.two_factor.no_phone }}</span>
            <TextLink as="button" @click="toggleRecoveryMode(clearErrors)">
                {{ authConfigContent.toggleText }}
            </TextLink>
        </AuthCardFooter>
    </Form>
</template>
