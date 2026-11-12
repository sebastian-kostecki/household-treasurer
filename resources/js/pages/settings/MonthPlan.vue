<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import MonthPlanSettingsController from '@/actions/App/Http/Controllers/Settings/MonthPlanSettingsController';
import FormField from '@/components/FormField.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{
    defaultSetAsidePercent: number | null;
}>();

const t = useTranslations();
</script>

<template>
    <Head :title="t.settings.month_plan_title" />

    <Form
        v-bind="MonthPlanSettingsController.update.form()"
        v-slot="{ errors, processing }"
    >
        <SettingsPanel
            :title="t.settings.month_plan"
            :description="t.settings.month_plan_description"
        >
            <FormField
                id="default_set_aside_percent"
                :label="t.settings.default_set_aside_percent"
                :hint="t.settings.default_set_aside_percent_help"
                :error="errors.default_set_aside_percent"
                unit="%"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <Input
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    type="number"
                    name="default_set_aside_percent"
                    min="0"
                    max="100"
                    step="1"
                    inputmode="numeric"
                    :default-value="defaultSetAsidePercent ?? ''"
                />
            </FormField>

            <template #footer>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="update-month-plan-settings-button"
                >
                    <Spinner v-if="processing" />
                    {{ t.settings.save }}
                </Button>
            </template>
        </SettingsPanel>
    </Form>
</template>
