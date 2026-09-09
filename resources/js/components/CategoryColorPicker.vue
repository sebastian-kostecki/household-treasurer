<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { categoryTileStyle } from '@/lib/category-appearance';

defineProps<{
    colors: string[];
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="color in colors"
            :key="color"
            type="button"
            class="focus-visible:ring-ring size-8 rounded-lg border-2 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:outline-none"
            :class="
                modelValue === color
                    ? 'border-foreground'
                    : 'border-transparent'
            "
            :style="categoryTileStyle(color)"
            :aria-label="color"
            :aria-pressed="modelValue === color"
            @click="emit('update:modelValue', color)"
        >
            <span class="sr-only">{{ color }}</span>
        </button>
    </div>
</template>
