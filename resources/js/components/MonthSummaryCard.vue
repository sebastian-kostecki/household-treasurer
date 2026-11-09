<script setup lang="ts">
import { computed } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount, formatSignedMoneyAmount } from '@/lib/format';
import type { MonthSummary } from '@/types/month-plan';

const props = defineProps<{
    summary: MonthSummary;
}>();

const t = useTranslations();

type SummaryKey = 'income' | 'expense' | 'set_aside';

type SummaryRow = {
    key: SummaryKey;
    label: string;
    /** Approved, final, actual, deviation: amount and its column caption. */
    cells: { caption: string; minorUnits: number }[];
    deviationMinorUnits: number;
};

const totalsCaptions = computed(() => [
    t.month_plan.summary_approved,
    t.month_plan.summary_final,
    t.month_plan.summary_actual,
    t.month_plan.summary_deviation,
]);

const setAsideCaptions = computed(() => [
    t.month_plan.summary_set_aside_approved,
    t.month_plan.summary_set_aside_final,
    t.month_plan.summary_set_aside_real,
    t.month_plan.summary_set_aside_difference,
]);

function totalsRow(key: 'income' | 'expense', label: string): SummaryRow {
    const totals = props.summary[key];
    const captions = totalsCaptions.value;

    return {
        key,
        label,
        cells: [
            { caption: captions[0], minorUnits: totals.approved_minor_units },
            { caption: captions[1], minorUnits: totals.final_minor_units },
            { caption: captions[2], minorUnits: totals.actual_minor_units },
        ],
        deviationMinorUnits: totals.deviation_minor_units,
    };
}

const totalsRows = computed<SummaryRow[]>(() => [
    totalsRow('income', t.month_plan.summary_income),
    totalsRow('expense', t.month_plan.summary_expense),
]);

const setAsideRow = computed<SummaryRow>(() => {
    const setAside = props.summary.set_aside;
    const captions = setAsideCaptions.value;

    return {
        key: 'set_aside',
        label: t.month_plan.summary_set_aside,
        cells: [
            { caption: captions[0], minorUnits: setAside.approved_minor_units },
            { caption: captions[1], minorUnits: setAside.final_minor_units },
            { caption: captions[2], minorUnits: setAside.real_minor_units },
        ],
        deviationMinorUnits: setAside.difference_minor_units,
    };
});

/** Worse than planned is destructive: less income or set aside, more expense. */
function deviationClass(key: SummaryKey, minorUnits: number): string {
    const isWorse = key === 'expense' ? minorUnits > 0 : minorUnits < 0;

    return isWorse ? 'text-destructive' : '';
}
</script>

<template>
    <ListSurface>
        <template #header>
            <div class="space-y-1">
                <h2 class="section-title">
                    {{ t.month_plan.summary_title }}
                </h2>
                <p class="meta text-muted-foreground">
                    {{ t.month_plan.summary_hint }}
                </p>
            </div>
        </template>

        <template
            v-for="group in [
                { captions: totalsCaptions, rows: totalsRows },
                { captions: setAsideCaptions, rows: [setAsideRow] },
            ]"
            :key="group.rows[0].key"
        >
            <div
                class="month-plan-summary-grid month-plan-summary-head border-border-soft border-b py-2"
                aria-hidden="true"
            >
                <span></span>
                <span
                    v-for="caption in group.captions"
                    :key="caption"
                    class="table-header text-right"
                >
                    {{ caption }}
                </span>
            </div>

            <div
                v-for="row in group.rows"
                :key="row.key"
                class="month-plan-summary-grid border-border-soft border-b py-2"
            >
                <span class="month-plan-summary-label text-sm font-medium">
                    {{ row.label }}
                </span>
                <span
                    v-for="cell in row.cells"
                    :key="cell.caption"
                    class="month-plan-summary-cell"
                >
                    <span class="meta text-muted-foreground block sm:sr-only">
                        {{ cell.caption }}
                    </span>
                    <span class="amount whitespace-nowrap">
                        {{ formatMoneyAmount(cell.minorUnits) }}
                    </span>
                </span>
                <span class="month-plan-summary-cell">
                    <span class="meta text-muted-foreground block sm:sr-only">
                        {{ group.captions[3] }}
                    </span>
                    <span
                        class="amount whitespace-nowrap"
                        :class="
                            deviationClass(row.key, row.deviationMinorUnits)
                        "
                    >
                        {{ formatSignedMoneyAmount(row.deviationMinorUnits) }}
                    </span>
                </span>
            </div>
        </template>

        <p class="meta text-muted-foreground pt-3">
            {{ t.month_plan.summary_year_draw }}:
            {{ formatMoneyAmount(props.summary.year_draw_minor_units) }}
        </p>
        <p class="meta text-muted-foreground pt-1">
            {{ t.common.amounts_in_zl }}
        </p>
    </ListSurface>
</template>
