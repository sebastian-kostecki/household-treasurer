<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();
const t = useTranslations();
</script>

<template>
    <Head :title="t.settings.security_title" />

    <Form
        v-bind="SecurityController.update.form()"
        :options="{
            preserveScroll: true,
        }"
        reset-on-success
        :reset-on-error="[
            'password',
            'password_confirmation',
            'current_password',
        ]"
        v-slot="{ errors, processing }"
    >
        <SettingsPanel
            :title="t.settings.password_heading"
            :description="t.settings.password_description"
        >
            <FormField
                id="current_password"
                :label="t.settings.current_password"
                :error="errors.current_password"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <PasswordInput
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    name="current_password"
                    autocomplete="current-password"
                />
            </FormField>

            <FormField
                id="password"
                :label="t.settings.new_password"
                :error="errors.password"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <PasswordInput
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="props.passwordRules"
                />
            </FormField>

            <FormField
                id="password_confirmation"
                :label="t.settings.confirm_password"
                :error="errors.password_confirmation"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <PasswordInput
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="props.passwordRules"
                />
            </FormField>

            <template #footer>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t.settings.save }}
                </Button>
            </template>
        </SettingsPanel>
    </Form>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
