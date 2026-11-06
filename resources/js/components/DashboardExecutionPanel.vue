<script setup lang="ts">
import { computed } from 'vue';
import CategoryMeterRow from '@/components/CategoryMeterRow.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import { formatMoneyAmount } from '@/lib/format';

const props = defineProps<{
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    yearElapsedPercent: number | null;
    budgetYear: number;
}>();

const t = useTranslations();

const tables = computed(() => [
    {
        type: 'income' as const,
        title: t.dashboard.income,
        sumLabel: t.dashboard.income_sum,
        categories: props.incomeCategories,
        totals: props.incomeTotals,
        isOverLimit: false,
    },
    {
        type: 'expense' as const,
        title: t.dashboard.expenses,
        sumLabel: t.dashboard.expense_sum,
        categories: props.expenseCategories,
        totals: props.expenseTotals,
        isOverLimit: props.expenseTotals.remaining_minor_units < 0,
    },
]);

const footerText = computed(() =>
    props.yearElapsedPercent === null
        ? t.dashboard.execution_footer_no_mark
        : t.dashboard.execution_footer.replace(
              ':percent',
              String(Math.round(props.yearElapsedPercent)),
          ),
);
</script>

<template>
    <div class="space-y-3">
        <ListSurface
            v-for="table in tables"
            :key="table.type"
            flush
            class="category-meter-table"
        >
            <template #header>
                <h3 class="section-title">{{ table.title }}</h3>
            </template>

            <div class="category-meter-header-row">
                <span class="category-meter-cell-category">
                    {{ t.budget.category }}
                </span>
                <span class="category-meter-cell-plan">
                    {{ t.budget.current_plan }}
                </span>
                <span class="category-meter-cell-execution">
                    {{ t.dashboard.execution_column }}
                </span>
                <span class="category-meter-cell-remaining">
                    {{ t.dashboard.remaining }}
                </span>
                <span class="category-meter-cell-meter">
                    {{ t.dashboard.percent_complete }}
                </span>
            </div>

            <p
                v-if="table.categories.length === 0"
                class="meta text-muted-foreground px-4 py-3"
            >
                {{ t.dashboard.no_categories }}
            </p>

            <CategoryMeterRow
                v-for="category in table.categories"
                :key="category.id"
                :category="category"
                :category-type="table.type"
                :budget-year="budgetYear"
                :year-elapsed-percent="yearElapsedPercent"
            />

            <div class="category-meter-sum-row">
                <div class="category-meter-cell-category">
                    {{ table.sumLabel }}
                </div>
                <div class="category-meter-cell-plan amount-sum">
                    {{
                        formatMoneyAmount(table.totals.current_plan_minor_units)
                    }}
                </div>
                <div class="category-meter-cell-execution amount-sum">
                    {{ formatMoneyAmount(table.totals.execution_minor_units) }}
                </div>
                <div class="category-meter-cell-remaining amount-sum">
                    <span class="sr-only">{{ t.dashboard.remaining }}</span>
                    {{ formatMoneyAmount(table.totals.remaining_minor_units) }}
                </div>
                <div class="category-meter-cell-meter">
                    <ExecutionMeter
                        :percent-complete="table.totals.percent_complete"
                        :category-type="table.type"
                        :is-over-limit="table.isOverLimit"
                        :year-elapsed-percent="null"
                    />
                    <Badge v-if="table.isOverLimit" variant="destructive-soft">
                        {{ t.budget.over_limit_badge }}
                    </Badge>
                </div>
            </div>

            <template v-if="table.type === 'expense'" #footer>
                <p class="meta text-muted-foreground">{{ footerText }}</p>
            </template>
        </ListSurface>
    </div>
</template>
