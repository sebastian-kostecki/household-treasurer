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
    <div class="grid max-h-48 grid-cols-8 gap-2 overflow-y-auto rounded-xl border border-border/50 p-3">
        <button
            v-for="icon in icons"
            :key="icon"
            type="button"
            class="flex size-9 items-center justify-center rounded-lg border-2 transition-colors hover:bg-foreground/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="modelValue === icon ? 'border-foreground' : 'border-transparent'"
            :style="categoryTileStyle(color)"
            :aria-label="icon"
            :aria-pressed="modelValue === icon"
            @click="emit('update:modelValue', icon)"
        >
            <CategoryIcon :name="icon" class="size-4" />
        </button>
    </div>
</template>
