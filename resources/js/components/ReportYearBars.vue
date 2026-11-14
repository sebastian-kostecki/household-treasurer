<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import {
    reportYearBars,
    reportYearBarsAccessibleList,
} from '@/lib/report-category-panel';

/**
 * The year bars of the Category Panel's "Lata" tab (ADR-0101): CSS marks from
 * tokens (expense `chart-1`, income `success`, the current year `foreground`,
 * a year without a line a short stroke in `track`). They repeat the numbers
 * of the `sr-only` list next to them, so the marks are `aria-hidden`.
 */
const props = defineProps<{
    type: 'income' | 'expense';
    columns: number[];
    currentYear: number | null;
    /** `full` marks the current year as in progress in the `sr-only` list, as the Years table does. */
    mode: 'same_day' | 'full';
    /** Cells of the server in the order of `columns`; null is a year without a line. */
    cells: (number | null)[];
}>();

const t = useTranslations();

const bars = computed(() =>
    reportYearBars(props.cells, props.columns, props.currentYear),
);

const accessibleList = computed(() =>
    reportYearBarsAccessibleList(
        bars.value,
        props.cells,
        formatMoney,
        t.reports.panel_years_no_line,
        props.mode === 'full' && props.currentYear !== null
            ? { year: props.currentYear, label: t.reports.years_in_progress }
            : null,
    ),
);

const gridStyle = computed(() => ({
    '--report-year-count': props.columns.length,
}));

function barStyle(ratio: number): Record<string, number> {
    return { '--report-bar-ratio': ratio };
}
</script>

<template>
    <div data-report-year-bars>
        <div class="report-year-bars" :style="gridStyle" aria-hidden="true">
            <div v-for="bar in bars" :key="bar.year" class="report-month-col">
                <div class="report-month-track">
                    <div
                        class="report-month-bar report-year-bar"
                        :class="[
                            type === 'income'
                                ? 'report-month-bar-income'
                                : 'report-month-bar-expense',
                            {
                                'is-current': bar.isCurrent,
                                'is-none': bar.state === 'none',
                            },
                        ]"
                        :style="barStyle(bar.ratio)"
                        :data-year="bar.year"
                        :data-state="bar.state"
                    />
                </div>
                <span class="report-month-label">{{ bar.year }}</span>
            </div>
        </div>
        <ul class="sr-only" :aria-label="t.reports.panel_years_list">
            <li v-for="item in accessibleList" :key="item">{{ item }}</li>
        </ul>
    </div>
</template>
