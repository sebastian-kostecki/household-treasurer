<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import StatPanel, { type StatPanelMetric } from '@/components/StatPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoney } from '@/lib/format';
import type { MonthPace } from '@/types/month-plan';

const props = defineProps<{
    budgetYear: number;
    currentMonth: number | null;
    monthToSpend: {
        state: 'proposal' | 'approved';
        to_spend_minor_units: number;
    } | null;
    monthPace: MonthPace | null;
    spendableLiquidityMinorUnits: number;
    totalFundsMinorUnits: number;
    yearElapsedAsOf: string | null;
    yearElapsedPercent: number | null;
}>();

const emit = defineEmits<{
    start: [];
    finish: [];
    error: [];
}>();

const t = useTranslations();

const monthToSpendLabel = computed(() => {
    if (props.currentMonth === null) {
        return '';
    }

    const monthName =
        t.budget[`month_name_${props.currentMonth}` as keyof typeof t.budget] ??
        '';

    return t.dashboard.month_to_spend.replace(':month', monthName);
});

const monthDays = computed(() => {
    const pace = props.monthPace;

    if (pace === null) {
        return [];
    }

    return Array.from({ length: pace.days_in_month }, (_, index) => ({
        day: index + 1,
        isPast: index + 1 <= pace.elapsed_day,
    }));
});

const monthDaysCaption = computed(() => {
    if (props.monthPace === null) {
        return null;
    }

    return t.dashboard.month_days_caption
        .replace(':elapsed', String(props.monthPace.elapsed_day))
        .replace(':days', String(props.monthPace.days_in_month));
});

const yearElapsedCaption = computed(() => {
    if (props.monthPace !== null) {
        return props.monthPace.percent === null
            ? t.dashboard.month_pace_none
            : t.dashboard.month_pace_percent.replace(
                  ':percent',
                  String(Math.round(props.monthPace.percent)),
              );
    }

    return props.yearElapsedAsOf === null
        ? undefined
        : t.dashboard.year_elapsed_as_of.replace(
              ':date',
              formatDate(props.yearElapsedAsOf),
          );
});

const metrics = computed<StatPanelMetric[]>(() => {
    const list: StatPanelMetric[] = [];

    if (props.monthToSpend !== null) {
        list.push({
            id: 'to-spend',
            label: monthToSpendLabel.value,
            value: formatMoney(props.monthToSpend.to_spend_minor_units),
            toneClass: amountToneClass(props.monthToSpend.to_spend_minor_units),
        });
    }

    list.push(
        {
            id: 'liquidity',
            label: t.dashboard.spendable_liquidity,
            value: formatMoney(props.spendableLiquidityMinorUnits),
            caption: t.dashboard.liquidity_caption,
        },
        {
            id: 'total-funds',
            label: t.dashboard.total_funds,
            value: formatMoney(props.totalFundsMinorUnits),
            caption: t.dashboard.total_funds_caption,
        },
    );

    if (props.yearElapsedPercent !== null) {
        list.push({
            id: 'year-elapsed',
            label: t.dashboard.year_elapsed,
            value: `${Math.round(props.yearElapsedPercent)}%`,
            caption: yearElapsedCaption.value,
        });
    }

    return list;
});
</script>

<template>
    <StatPanel class="dashboard-stat-panel" :metrics="metrics">
        <template #label-extra-to-spend>
            <Badge
                v-if="monthToSpend?.state === 'proposal'"
                variant="outline"
                class="tracking-normal normal-case"
            >
                {{ t.month_plan.state_proposal }}
            </Badge>
        </template>
        <template #footer-to-spend>
            <div
                v-if="monthPace !== null"
                class="month-days"
                aria-hidden="true"
            >
                <span
                    v-for="day in monthDays"
                    :key="day.day"
                    class="month-days-day"
                    :class="{ 'month-days-day-past': day.isPast }"
                />
            </div>
            <div class="stat-panel-footer-line meta text-muted-foreground">
                <span v-if="monthDaysCaption">{{ monthDaysCaption }} ·</span>
                <Form
                    v-bind="BudgetController.selectYear.form()"
                    v-slot="{ processing }"
                    @start="emit('start')"
                    @finish="emit('finish')"
                    @error="emit('error')"
                >
                    <input type="hidden" name="year" :value="budgetYear" />
                    <input
                        type="hidden"
                        name="destination"
                        value="month-plan"
                    />
                    <Button
                        type="submit"
                        variant="link"
                        size="sm"
                        class="h-auto gap-1 p-0 text-xs has-[>svg]:px-0"
                        :disabled="processing"
                    >
                        {{ t.nav.month_plan }}
                        <ArrowRight class="size-3" aria-hidden="true" />
                    </Button>
                </Form>
            </div>
        </template>
    </StatPanel>
</template>
