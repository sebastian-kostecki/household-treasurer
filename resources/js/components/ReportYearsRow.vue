<script setup lang="ts">
import { computed } from 'vue';
import ReportYearsSpark from '@/components/ReportYearsSpark.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatWholeMoneyAmount } from '@/lib/format';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import { formatYearsChange } from '@/lib/report-years';

const props = withDefaults(
    defineProps<{
        /** `header` renders the year captions; the others render amounts. */
        variant: 'header' | 'row' | 'group' | 'total';
        type: 'income' | 'expense';
        /** The years of the columns, ascending. */
        columns: number[];
        /** The current year of the Budget, `null` without one. */
        currentYear: number | null;
        /** Header only: the year that gets the "w toku" caption. */
        inProgressYear?: number | null;
        /** Header only: the caption of the change column. */
        changeLabel?: string;
        cells?: (number | null)[];
        maxYears?: number[];
        changeTenths?: number | null;
        changeFirstYear?: number | null;
        /** The row stands under a group row; the name cell is indented. */
        nested?: boolean;
        /** Archived: the whole row is muted and carries no tone. */
        muted?: boolean;
    }>(),
    {
        inProgressYear: null,
        changeLabel: '',
        cells: () => [],
        maxYears: () => [],
        changeTenths: null,
        changeFirstYear: null,
        nested: false,
        muted: false,
    },
);

const t = useTranslations();

const recipeClass = computed(() => ({
    'report-header-row': props.variant === 'header',
    'report-row': props.variant === 'row',
    'report-group-row': props.variant === 'group',
    'report-total-row': props.variant === 'total',
    'report-row-nested': props.nested,
    'text-muted-foreground': props.muted,
}));

const isSum = computed(
    () => props.variant === 'group' || props.variant === 'total',
);

const heads = computed(() =>
    props.columns.map((year) => ({
        year,
        isCurrent: year === props.currentYear,
        inProgress: year === props.inProgressYear,
        label:
            year === props.inProgressYear
                ? `${year}, ${t.reports.years_in_progress}`
                : String(year),
    })),
);

function cellText(value: number | null): string {
    return value === null ? '—' : formatWholeMoneyAmount(value);
}

function cellClass(year: number): string {
    return props.maxYears.includes(year) ? 'font-semibold' : '';
}

const changeToneClass = computed(() => {
    const tone = reportDifferenceTone(props.changeTenths, props.type);

    return tone === null ? '' : planChangeToneClass(tone, props.muted);
});

/** "od 2022" only when the first amount is not in the first column. */
const changeSince = computed(() =>
    props.changeTenths !== null &&
    props.changeFirstYear !== null &&
    props.changeFirstYear !== props.columns[0]
        ? t.reports.years_change_from.replace(
              ':year',
              String(props.changeFirstYear),
          )
        : null,
);
</script>

<template>
    <div role="row" class="report-years-grid" :class="recipeClass">
        <template v-if="variant === 'header'">
            <div role="columnheader" class="report-trend-name">
                <slot />
            </div>
            <div
                v-for="head in heads"
                :key="head.year"
                role="columnheader"
                class="report-trend-head-cell"
                :class="head.isCurrent ? 'text-foreground' : ''"
                :aria-label="head.label"
            >
                <span aria-hidden="true">{{ head.year }}</span>
                <span
                    v-if="head.inProgress"
                    class="report-trend-in-progress"
                    aria-hidden="true"
                >
                    {{ t.reports.years_in_progress }}
                </span>
            </div>
            <div role="columnheader" class="text-right">
                {{ changeLabel }}
            </div>
            <div role="columnheader" class="sr-only">
                {{ t.reports.years_spark_head }}
            </div>
        </template>
        <template v-else>
            <div role="rowheader" class="report-trend-name">
                <slot />
            </div>
            <div
                v-for="(value, index) in cells"
                :key="columns[index]"
                role="cell"
                class="report-cell-amount"
                :class="[isSum ? 'amount-sum' : '', cellClass(columns[index])]"
            >
                {{ cellText(value) }}
            </div>
            <div role="cell" class="report-years-change">
                <span :class="changeToneClass">{{
                    formatYearsChange(changeTenths)
                }}</span>
                <span
                    v-if="changeSince !== null"
                    class="meta text-muted-foreground"
                >
                    {{ changeSince }}
                </span>
            </div>
            <div role="cell">
                <ReportYearsSpark
                    :cells="cells"
                    :columns="columns"
                    :current-year="currentYear"
                    :type="type"
                />
            </div>
        </template>
    </div>
</template>
