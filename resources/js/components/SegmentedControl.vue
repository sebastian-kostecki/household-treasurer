<script setup lang="ts" generic="T extends string">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

withDefaults(
    defineProps<{
        options: { value: T; label: string }[];
        ariaLabel: string;
        tone?: 'primary' | 'neutral';
        disabled?: boolean;
    }>(),
    { tone: 'primary', disabled: false },
);

const modelValue = defineModel<T>({ required: true });

function onUpdate(value: unknown): void {
    // Reka emits undefined when the active option is clicked again; keep the value.
    if (typeof value !== 'string') {
        return;
    }

    modelValue.value = value as T;
}
</script>

<template>
    <ToggleGroupRoot
        type="single"
        :model-value="modelValue"
        :aria-label="ariaLabel"
        :disabled="disabled"
        :data-tone="tone"
        class="segmented-control"
        @update:model-value="onUpdate"
    >
        <ToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="segmented-control-item"
        >
            {{ option.label }}
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>
