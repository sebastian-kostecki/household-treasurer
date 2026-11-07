<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import FormField from '@/components/FormField.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';

const t = useTranslations();
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="t.settings.profile_title" />

    <Form
        v-bind="ProfileController.update.form()"
        v-slot="{ errors, processing }"
    >
        <SettingsPanel
            :title="t.settings.profile_heading"
            :description="t.settings.profile_description"
        >
            <FormField
                id="name"
                :label="t.settings.name"
                :error="errors.name"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <Input
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                />
            </FormField>

            <FormField
                id="email"
                :label="t.settings.email"
                :error="errors.email"
                v-slot="{ id, describedBy, invalid, controlClass }"
            >
                <Input
                    :id="id"
                    :class="controlClass"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    type="email"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                />
            </FormField>

            <template #footer>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    <Spinner v-if="processing" />
                    {{ t.settings.save }}
                </Button>
            </template>
        </SettingsPanel>
    </Form>

    <DeleteUser />
</template>
