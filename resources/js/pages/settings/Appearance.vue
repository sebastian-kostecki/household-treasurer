<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { useTranslations } from '@/composables/useTranslations';

const t = useTranslations();
</script>

<template>
    <Head :title="t.settings.appearance_title" />

    <SettingsPanel
        :title="t.settings.appearance_title"
        :description="t.settings.appearance_description"
    >
        <AppearanceTabs />
    </SettingsPanel>
</template>
