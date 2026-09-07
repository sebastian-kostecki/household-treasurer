<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useTranslations } from '@/composables/useTranslations';
import { edit } from '@/routes/appearance';

const t = useTranslations();

setLayoutProps({
    breadcrumbs: [
        {
            title: t.settings.appearance,
            href: edit(),
        },
    ],
});
</script>

<template>
    <Head :title="t.settings.appearance_title" />

    <h1 class="sr-only">{{ t.settings.appearance_title }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t.settings.appearance_title"
            :description="t.settings.appearance_description"
        />
        <AppearanceTabs />
    </div>
</template>
