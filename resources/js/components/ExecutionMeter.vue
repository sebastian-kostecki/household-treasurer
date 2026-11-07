<script setup lang="ts">
import { computed } from 'vue';
import {
    meterBarClass,
    meterPercentClass,
    progressWidth,
} from '@/lib/dashboard-categories';

const props = defineProps<{
    percentComplete: number | null;
    categoryType: 'income' | 'expense';
    isOverLimit: boolean;
    /** Elapsed share of the year; null hides the mark. */
    yearElapsedPercent: number | null;
    /** Archived rows: no status colours. */
    muted?: boolean;
}>();

const barClass = computed(() =>
    props.muted
        ? 'bg-muted-foreground'
        : meterBarClass(
              props.categoryType,
              props.percentComplete,
              props.isOverLimit,
          ),
);

const percentClass = computed(() =>
    props.muted
        ? 'text-muted-foreground'
        : meterPercentClass(
              props.categoryType,
              props.percentComplete,
              props.isOverLimit,
          ),
);
</script>

<template>
    <div class="execution-meter">
        <div
            v-if="percentComplete !== null || yearElapsedPercent !== null"
            class="execution-meter-track"
        >
            <div
                v-if="percentComplete !== null"
                class="h-full rounded-full transition-all"
                :class="barClass"
                :style="{ width: progressWidth(percentComplete) }"
            />
            <div
                v-if="yearElapsedPercent !== null"
                class="bg-brand-text ring-background pointer-events-none absolute top-1/2 z-10 h-3 w-0.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-1"
                :style="{ left: `${yearElapsedPercent}%` }"
                aria-hidden="true"
            />
        </div>
        <span class="execution-meter-percent" :class="percentClass">
            <template v-if="percentComplete !== null">
                {{ percentComplete }}%
            </template>
        </span>
    </div>
</template>
