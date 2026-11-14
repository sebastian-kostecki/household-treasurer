<script setup lang="ts">
import { computed } from 'vue';

/**
 * Share bar of a Ranking row (ADR-0097): a CSS mark in the type's token
 * (expense `chart-1`, income `success`) on the `track`. It repeats the numbers
 * of its row, so it is `aria-hidden`.
 */
const props = defineProps<{
    type: 'income' | 'expense';
    /** Width of the fill, 0 to 1; 0 leaves an empty track. */
    ratio: number;
}>();

const fillStyle = computed(() => ({ '--report-share': props.ratio }));
</script>

<template>
    <div class="report-share-bar" aria-hidden="true" data-report-share-bar>
        <div
            class="report-share-bar-fill"
            :class="
                type === 'income'
                    ? 'report-share-bar-fill-income'
                    : 'report-share-bar-fill-expense'
            "
            :style="fillStyle"
        />
    </div>
</template>
