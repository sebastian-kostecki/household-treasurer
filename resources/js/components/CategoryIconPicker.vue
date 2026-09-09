<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { categoryTileStyle } from '@/lib/category-appearance';

defineProps<{
    icons: string[];
    modelValue: string;
    color: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div
        class="border-border/50 grid max-h-48 grid-cols-8 gap-2 overflow-y-auto rounded-xl border p-3"
    >
        <button
            v-for="icon in icons"
            :key="icon"
            type="button"
            class="hover:bg-foreground/[0.04] focus-visible:ring-ring flex size-9 items-center justify-center rounded-lg border-2 transition-colors focus-visible:ring-2 focus-visible:outline-none"
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
