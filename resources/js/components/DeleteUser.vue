<script setup lang="ts">
import type { Form } from '@inertiajs/vue3';
import { Trash2, TriangleAlert } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';

type FormBinding = InstanceType<typeof Form>['$props'];

withDefaults(
    defineProps<{
        /** Overrides the Wayfinder binding; used only by the settings kitchen sink. */
        form?: Pick<FormBinding, 'action' | 'method'>;
    }>(),
    {
        form: () => ProfileController.destroy.form(),
    },
);

/** Controlled from outside only by the settings kitchen sink. */
const open = defineModel<boolean>('open', { default: false });

const t = useTranslations();
const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <SettingsPanel
        :title="t.settings.delete_account"
        :description="t.settings.delete_account_description"
    >
        <Alert variant="danger">
            <TriangleAlert />
            <AlertTitle>{{ t.settings.delete_warning }}</AlertTitle>
            <AlertDescription>{{
                t.settings.delete_warning_body
            }}</AlertDescription>
        </Alert>

        <ConfirmActionDialog
            v-model:open="open"
            :form="form"
            destructive
            :title="t.settings.delete_confirm_title"
            :description="t.settings.delete_confirm_body"
            :confirm-label="t.settings.delete_account"
            :cancel-label="t.settings.cancel"
            @error="() => passwordInput?.focus()"
        >
            <template #trigger>
                <DialogTrigger as-child>
                    <Button
                        variant="outline-destructive"
                        class="self-start"
                        data-test="delete-user-button"
                    >
                        <Trash2 />
                        {{ t.settings.delete_account }}
                    </Button>
                </DialogTrigger>
            </template>

            <FormField
                id="delete-user-password"
                :label="t.auth.login.password"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <PasswordInput
                    :id="id"
                    ref="passwordInput"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    name="password"
                    autocomplete="current-password"
                />
            </FormField>
        </ConfirmActionDialog>
    </SettingsPanel>
</template>
