<script setup lang="ts">
import { computed } from 'vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const t = useTranslations();
const { appearance, updateAppearance } = useAppearance();

const selected = computed<Appearance>({
    get: () => appearance.value,
    set: (value) => updateAppearance(value),
});

const options = computed<{ value: Appearance; label: string }[]>(() => [
    { value: 'light', label: t.settings.appearance_light },
    { value: 'dark', label: t.settings.appearance_dark },
    { value: 'system', label: t.settings.appearance_system },
]);
</script>

<template>
    <SegmentedControl
        v-model="selected"
        :options="options"
        :ariaLabel="t.settings.appearance_theme"
        tone="neutral"
    />
</template>
