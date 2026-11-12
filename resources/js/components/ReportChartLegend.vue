<script setup lang="ts">
/**
 * Legend above a Reports chart: a colour square and a label per series, plus
 * an optional note. Shared by the charts and by their placeholders, so the
 * placeholder holds the legend's height exactly.
 */
defineProps<{
    items: { key: string; color: string; label: string }[];
    note?: string | null;
    testId: string;
}>();
</script>

<template>
    <ul
        :data-testid="testId"
        class="meta text-muted-foreground mb-3 flex flex-wrap items-center gap-x-4 gap-y-1"
    >
        <li
            v-for="item in items"
            :key="item.key"
            class="flex items-center gap-1.5"
        >
            <span
                class="size-2.5 shrink-0 rounded-sm"
                :style="{ backgroundColor: item.color }"
            />
            {{ item.label }}
        </li>
        <li v-if="note != null">{{ note }}</li>
    </ul>
</template>
