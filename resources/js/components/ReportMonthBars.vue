<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import {
    reportMonthBars,
    reportMonthBarsAccessibleList,
} from '@/lib/report-category-panel';
import { reportMonthNamesFrom } from '@/lib/report-window';

/**
 * The 12 month bars of the Category Panel (ADR-0097): CSS marks from tokens
 * (expense `chart-1`, income `success`, the highest month `foreground`, the
 * month in progress dashed, future months empty). They repeat the numbers of
 * the `sr-only` list next to them, so the marks are `aria-hidden`.
 */
const props = defineProps<{
    type: 'income' | 'expense';
    /** 12 cells of the server, January first. */
    months: (number | null)[];
    closedMonthCount: number;
    inProgressMonth: number | null;
    highestMonth: number | null;
}>();

const t = useTranslations();

const monthNames = computed(() => reportMonthNamesFrom(t.budget));

const bars = computed(() =>
    reportMonthBars(
        props.months,
        props.closedMonthCount,
        props.inProgressMonth,
        props.highestMonth,
    ),
);

const accessibleList = computed(() =>
    reportMonthBarsAccessibleList(
        bars.value,
        monthNames.value,
        formatMoney,
        '—',
    ),
);

function barStyle(ratio: number): Record<string, number> {
    return { '--report-bar-ratio': ratio };
}
</script>

<template>
    <div data-report-month-bars>
        <div class="report-month-bars" aria-hidden="true">
            <div v-for="bar in bars" :key="bar.month" class="report-month-col">
                <div class="report-month-track">
                    <div
                        class="report-month-bar"
                        :class="[
                            type === 'income'
                                ? 'report-month-bar-income'
                                : 'report-month-bar-expense',
                            {
                                'is-highest': bar.isHighest,
                                'is-in-progress': bar.state === 'in-progress',
                            },
                        ]"
                        :style="barStyle(bar.ratio)"
                        :data-month="bar.month"
                        :data-state="bar.state"
                    />
                </div>
                <span class="report-month-label">{{
                    t.budget[`month_chip_${bar.month}`]
                }}</span>
            </div>
        </div>
        <ul class="sr-only" :aria-label="t.reports.panel_months_list">
            <li v-for="item in accessibleList" :key="item">{{ item }}</li>
        </ul>
    </div>
</template>
