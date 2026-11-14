<script setup lang="ts">
import { computed } from 'vue';
import { reportYearsSparkBars } from '@/lib/report-years';

/**
 * A mark that repeats the numbers of its row (ADR-0097): one bar per year,
 * scaled to the row's maximum, the current year in `foreground`, a year
 * without a Budget Line as a short stroke in `track`.
 */
const props = defineProps<{
    cells: (number | null)[];
    columns: number[];
    currentYear: number | null;
    type: 'income' | 'expense';
}>();

/** SVG user units, not pixels: the svg scales to its cell. */
const BAR_WIDTH = 6;
const BAR_GAP = 2;
const HEIGHT = 24;
const EMPTY_HEIGHT = 2;

const bars = computed(() =>
    reportYearsSparkBars(props.cells, props.columns, props.currentYear).map(
        (bar, index) => {
            const height = bar.isEmpty
                ? EMPTY_HEIGHT
                : Math.round(bar.heightRatio * HEIGHT);

            return {
                year: bar.year,
                x: index * (BAR_WIDTH + BAR_GAP),
                y: HEIGHT - height,
                height,
                fillClass: bar.isEmpty
                    ? 'fill-track'
                    : bar.isCurrent
                      ? 'fill-foreground'
                      : props.type === 'income'
                        ? 'fill-success'
                        : 'fill-chart-1',
            };
        },
    ),
);

const width = computed(
    () => props.columns.length * (BAR_WIDTH + BAR_GAP) - BAR_GAP,
);
</script>

<template>
    <svg
        class="report-years-spark"
        :viewBox="`0 0 ${width} ${HEIGHT}`"
        preserveAspectRatio="none"
        aria-hidden="true"
        focusable="false"
    >
        <rect
            v-for="bar in bars"
            :key="bar.year"
            :x="bar.x"
            :y="bar.y"
            :width="BAR_WIDTH"
            :height="bar.height"
            :class="bar.fillClass"
        />
    </svg>
</template>
