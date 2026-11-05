<script setup lang="ts">
import ListSurface from '@/components/ListSurface.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatMoney,
    formatMoneyAmount,
    formatSignedMoneyAmount,
} from '@/lib/format';
import type { MonthSummary } from '@/types/month-plan';

const props = defineProps<{
    summary: MonthSummary;
}>();

const t = useTranslations();

const totalsRows = [
    { key: 'income', label: t.month_plan.summary_income },
    { key: 'expense', label: t.month_plan.summary_expense },
] as const;

/** Worse than planned is destructive: less income or set aside, more expense. */
function deviationClass(
    key: 'income' | 'expense' | 'set_aside',
    minorUnits: number,
): string {
    const isWorse = key === 'expense' ? minorUnits > 0 : minorUnits < 0;

    return isWorse ? 'text-destructive' : '';
}
</script>

<template>
    <ListSurface>
        <template #header>
            <div class="space-y-1">
                <h3 class="section-title">
                    {{ t.month_plan.summary_title }}
                </h3>
                <p class="meta text-muted-foreground">
                    {{ t.month_plan.summary_hint }}
                </p>
            </div>
        </template>

        <div class="month-plan-summary-grid border-border-soft border-b pb-2">
            <span class="table-header">
                <span class="sr-only">{{ t.month_plan.summary_title }}</span>
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_approved }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_final }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_actual }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_deviation }}
            </span>
        </div>

        <div
            v-for="row in totalsRows"
            :key="row.key"
            class="month-plan-summary-grid border-border-soft border-b py-2"
        >
            <span class="text-sm font-medium">{{ row.label }}</span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(
                        props.summary[row.key].approved_minor_units,
                    )
                }}
            </span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(props.summary[row.key].final_minor_units)
                }}
            </span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(props.summary[row.key].actual_minor_units)
                }}
            </span>
            <span
                class="amount text-right"
                :class="
                    deviationClass(
                        row.key,
                        props.summary[row.key].deviation_minor_units,
                    )
                "
            >
                {{
                    formatSignedMoneyAmount(
                        props.summary[row.key].deviation_minor_units,
                    )
                }}
            </span>
        </div>

        <div class="month-plan-summary-grid border-border-soft border-b py-2">
            <span class="table-header">
                {{ t.month_plan.summary_set_aside }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_set_aside_approved }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_set_aside_final }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_set_aside_real }}
            </span>
            <span class="table-header text-right">
                {{ t.month_plan.summary_set_aside_difference }}
            </span>
        </div>
        <div class="month-plan-summary-grid border-border-soft border-b py-2">
            <span></span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(
                        props.summary.set_aside.approved_minor_units,
                    )
                }}
            </span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(props.summary.set_aside.final_minor_units)
                }}
            </span>
            <span class="amount text-right">
                {{
                    formatMoneyAmount(props.summary.set_aside.real_minor_units)
                }}
            </span>
            <span
                class="amount text-right"
                :class="
                    deviationClass(
                        'set_aside',
                        props.summary.set_aside.difference_minor_units,
                    )
                "
            >
                {{
                    formatSignedMoneyAmount(
                        props.summary.set_aside.difference_minor_units,
                    )
                }}
            </span>
        </div>

        <p class="meta text-muted-foreground pt-3">
            {{ t.month_plan.summary_year_draw }}:
            {{ formatMoney(props.summary.year_draw_minor_units) }}
        </p>
        <p class="meta text-muted-foreground pt-1">
            {{ t.budget.amounts_in_zl }}
        </p>
    </ListSurface>
</template>
