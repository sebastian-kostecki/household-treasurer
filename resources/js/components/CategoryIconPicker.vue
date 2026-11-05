<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { categoryTileStyle } from '@/lib/category-appearance';

defineProps<{
    icons: string[];
    modelValue: string;
    color: string;
    ariaLabelledby?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div
        class="bg-background grid max-h-48 grid-cols-8 gap-2 overflow-y-auto rounded-md p-3"
        role="group"
        :aria-labelledby="ariaLabelledby"
    >
        <button
            v-for="icon in icons"
            :key="icon"
            type="button"
            class="hover:bg-secondary focus-visible:outline-ring flex size-9 items-center justify-center rounded-lg border-2 transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid"
            :class="
                modelValue === icon ? 'border-foreground' : 'border-transparent'
            "
            :style="categoryTileStyle(color)"
            :aria-label="icon"
            :aria-pressed="modelValue === icon"
            @click="emit('update:modelValue', icon)"
        >
            <CategoryIcon :name="icon" class="size-4" />
        </button>
    </div>
</template>
