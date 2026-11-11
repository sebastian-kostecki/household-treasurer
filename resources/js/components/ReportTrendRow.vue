<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatWholeMoneyAmount } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        /** `header` renders the month captions; the others render amounts. */
        variant: 'header' | 'row' | 'group' | 'total';
        /** 12 cells, January first; null is a month without an operation. */
        months?: (number | null)[];
        /** The amount of the year. */
        totalMinorUnits?: number;
        /** Month (1 to 12) that is in progress; its header gets a caption. */
        inProgressMonth?: number | null;
        /** Colour every cell by its sign (the Wynik row). */
        signTone?: boolean;
        /** The row stands under a group row; the name cell is indented. */
        nested?: boolean;
        /** Archived: the whole row is muted and carries no tone. */
        muted?: boolean;
    }>(),
    {
        months: () => [],
        totalMinorUnits: 0,
        inProgressMonth: null,
        signTone: false,
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

const monthHeads = computed(() =>
    Array.from({ length: 12 }, (_, index) => {
        const month = index + 1;
        const name = t.budget[`month_name_${month}` as keyof typeof t.budget];

        return {
            month,
            chip: t.budget[`month_chip_${month}` as keyof typeof t.budget],
            label:
                month === props.inProgressMonth
                    ? `${name}, ${t.reports.trend_in_progress}`
                    : name,
            inProgress: month === props.inProgressMonth,
        };
    }),
);

function cellText(value: number | null): string {
    return value === null ? '—' : formatWholeMoneyAmount(value);
}

function cellToneClass(value: number | null): string {
    return props.signTone && !props.muted && value !== null
        ? amountToneClass(value)
        : '';
}
</script>

<template>
    <div role="row" class="report-trend-grid" :class="recipeClass">
        <template v-if="variant === 'header'">
            <div role="columnheader" class="report-trend-name">
                <slot />
            </div>
            <div
                v-for="head in monthHeads"
                :key="head.month"
                role="columnheader"
                class="report-trend-head-cell"
                :aria-label="head.label"
            >
                <span aria-hidden="true">{{ head.chip }}</span>
                <span
                    v-if="head.inProgress"
                    class="report-trend-in-progress"
                    aria-hidden="true"
                >
                    {{ t.reports.trend_in_progress }}
                </span>
            </div>
            <div role="columnheader" class="text-right">
                {{ t.reports.totals }}
            </div>
        </template>
        <template v-else>
            <div role="rowheader" class="report-trend-name">
                <slot />
            </div>
            <div
                v-for="(value, index) in months"
                :key="index"
                role="cell"
                class="report-cell-amount"
                :class="[isSum ? 'amount-sum' : '', cellToneClass(value)]"
            >
                {{ cellText(value) }}
            </div>
            <div
                role="cell"
                class="report-cell-amount amount-sum"
                :class="cellToneClass(totalMinorUnits)"
            >
                {{ formatWholeMoneyAmount(totalMinorUnits) }}
            </div>
        </template>
    </div>
</template>
