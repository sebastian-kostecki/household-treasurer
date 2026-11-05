<script setup lang="ts" generic="T extends string">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

withDefaults(
    defineProps<{
        options: { value: T; label: string; count?: number }[];
        ariaLabel?: string;
        ariaLabelledby?: string;
        tone?: 'primary' | 'neutral';
        disabled?: boolean;
        block?: boolean;
        name?: string;
    }>(),
    { tone: 'primary', disabled: false, block: false },
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
        :aria-labelledby="ariaLabelledby"
        :disabled="disabled"
        :data-tone="tone"
        class="segmented-control"
        :class="{ 'segmented-control-block': block }"
        @update:model-value="onUpdate"
    >
        <ToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="segmented-control-item"
        >
            {{ option.label }}
            <span
                v-if="option.count !== undefined"
                class="segmented-control-count"
                >{{ option.count }}</span
            >
        </ToggleGroupItem>
        <input v-if="name" type="hidden" :name="name" :value="modelValue" />
    </ToggleGroupRoot>
</template>
