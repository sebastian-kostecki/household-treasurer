<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { categoryTileStyle } from '@/lib/category-appearance';

defineProps<{
    colors: string[];
    modelValue: string | null;
    ariaLabelledby?: string;
}>();

const t = useTranslations();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div
        class="flex flex-wrap gap-2"
        role="group"
        :aria-labelledby="ariaLabelledby"
    >
        <button
            v-for="(color, index) in colors"
            :key="color"
            type="button"
            class="focus-visible:outline-ring size-8 rounded-lg border-2 transition-transform outline-none hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid"
            :class="
                modelValue === color
                    ? 'border-foreground'
                    : 'border-transparent'
            "
            :style="categoryTileStyle(color)"
            :aria-label="
                t.budget.category_color_option.replace(
                    ':number',
                    String(index + 1),
                )
            "
            :aria-pressed="modelValue === color"
            @click="emit('update:modelValue', color)"
        ></button>
    </div>
</template>
