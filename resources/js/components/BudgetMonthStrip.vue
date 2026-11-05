<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import BudgetMonthResultSummary from '@/components/BudgetMonthResultSummary.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { monthPlan as monthPlanRoute } from '@/routes';

const props = defineProps<{
    budgetYear: number;
    viewedMonth: number;
    showArchived: boolean;
    currentMonth: number | null;
    viewedMonthTiming: 'past' | 'current' | 'future';
    monthIncomeExecutionMinorUnits: number;
    monthExpenseExecutionMinorUnits: number;
    monthResultMinorUnits: number;
    monthToSpend?: {
        state: 'proposal' | 'approved' | 'none';
        to_spend_minor_units: number | null;
    } | null;
}>();

const t = useTranslations();

const viewedMonthLabel = computed(() => {
    const monthName =
        t.budget[`month_name_${props.viewedMonth}` as keyof typeof t.budget];

    return `${monthName} ${props.budgetYear}`;
});
</script>

<template>
    <Card>
        <CardContent class="grid gap-4 pt-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p
                    class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                >
                    {{ t.budget.month_scope }}
                </p>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium capitalize">
                        {{ viewedMonthLabel }}
                    </span>
                    <Badge
                        v-if="viewedMonthTiming === 'current'"
                        variant="secondary"
                    >
                        {{ t.budget.month_today_badge }}
                    </Badge>
                </div>
            </div>
            <BudgetMonthPicker
                :budget-year="budgetYear"
                :viewed-month="viewedMonth"
                :show-archived="showArchived"
                :current-month="currentMonth"
            />
            <p
                v-if="monthToSpend?.state === 'none'"
                class="text-muted-foreground flex min-h-7 items-center text-sm"
            >
                {{ t.month_plan.none_title }}
            </p>
            <div
                v-else-if="monthToSpend"
                class="flex min-h-7 flex-wrap items-center justify-between gap-x-4 gap-y-2"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                    >
                        {{ t.month_plan.to_spend }}
                    </span>
                    <span
                        class="text-lg font-semibold tabular-nums"
                        :class="
                            (monthToSpend.to_spend_minor_units ?? 0) < 0
                                ? 'text-destructive'
                                : ''
                        "
                    >
                        {{
                            formatMoney(monthToSpend.to_spend_minor_units ?? 0)
                        }}
                    </span>
                    <Badge
                        :variant="
                            monthToSpend.state === 'approved'
                                ? 'secondary'
                                : 'outline'
                        "
                    >
                        {{
                            monthToSpend.state === 'approved'
                                ? t.month_plan.state_approved
                                : t.month_plan.state_proposal
                        }}
                    </Badge>
                </div>
                <Link
                    :href="monthPlanRoute({ query: { month: viewedMonth } })"
                    class="text-brand-text text-sm font-medium hover:underline"
                >
                    {{
                        viewedMonthTiming === 'past' &&
                        monthToSpend.state === 'approved'
                            ? t.month_plan.summary_title
                            : t.nav.month_plan
                    }}
                </Link>
            </div>
            <BudgetMonthResultSummary
                :income-execution-minor-units="monthIncomeExecutionMinorUnits"
                :expense-execution-minor-units="monthExpenseExecutionMinorUnits"
                :month-result-minor-units="monthResultMinorUnits"
            />
        </CardContent>
    </Card>
</template>
