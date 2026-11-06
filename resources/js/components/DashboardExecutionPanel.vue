<script setup lang="ts">
import { computed, ref } from 'vue';
import CategoryMeterRow from '@/components/CategoryMeterRow.vue';
import ExecutionMeter from '@/components/ExecutionMeter.vue';
import ListSurface from '@/components/ListSurface.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import { formatMoneyAmount } from '@/lib/format';

type ExecutionTab = 'all' | 'income' | 'expense';

const props = defineProps<{
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    yearElapsedPercent: number | null;
    budgetYear: number;
}>();

const t = useTranslations();

const activeTab = ref<ExecutionTab>('all');

const tabOptions = computed(() => [
    { value: 'all' as const, label: t.dashboard.execution_tab_all },
    { value: 'income' as const, label: t.dashboard.income },
    { value: 'expense' as const, label: t.dashboard.expenses },
]);

const groups = computed(() => {
    const all = [
        {
            type: 'income' as const,
            label: t.dashboard.income,
            categories: props.incomeCategories,
            totals: props.incomeTotals,
            isOverLimit: false,
        },
        {
            type: 'expense' as const,
            label: t.dashboard.expenses,
            categories: props.expenseCategories,
            totals: props.expenseTotals,
            isOverLimit: props.expenseTotals.remaining_minor_units < 0,
        },
    ];

    return activeTab.value === 'all'
        ? all
        : all.filter((group) => group.type === activeTab.value);
});

const title = computed(() =>
    t.dashboard.execution_panel_title.replace(
        ':year',
        String(props.budgetYear),
    ),
);

const footerText = computed(() =>
    props.yearElapsedPercent === null
        ? t.dashboard.execution_footer_no_mark
        : t.dashboard.execution_footer.replace(
              ':percent',
              String(props.yearElapsedPercent),
          ),
);
</script>

<template>
    <ListSurface flush>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="section-title">{{ title }}</h2>
                <SegmentedControl
                    v-model="activeTab"
                    tone="neutral"
                    class="segmented-control-tabs"
                    :ariaLabel="t.dashboard.execution_tabs_aria"
                    :options="tabOptions"
                />
            </div>
        </template>

        <div class="category-meter-header-row">
            <span class="category-meter-cell-category">
                {{ t.budget.category }}
            </span>
            <span class="category-meter-cell-plan">
                {{ t.dashboard.planned }}
            </span>
            <span class="category-meter-cell-execution">
                {{ t.dashboard.executed }}
            </span>
            <span class="category-meter-cell-remaining">
                {{ t.dashboard.remaining }}
            </span>
            <span class="category-meter-cell-meter">
                {{ t.dashboard.percent_complete }}
            </span>
            <span class="category-meter-cell-status">
                {{ t.dashboard.execution_status }}
            </span>
        </div>

        <template v-for="group in groups" :key="group.type">
            <div class="category-meter-group-row">
                <div class="category-meter-cell-category">
                    {{ group.label }}
                </div>
                <div class="category-meter-cell-plan amount-sum">
                    {{
                        formatMoneyAmount(group.totals.current_plan_minor_units)
                    }}
                </div>
                <div class="category-meter-cell-execution amount-sum">
                    {{ formatMoneyAmount(group.totals.execution_minor_units) }}
                </div>
                <div class="category-meter-cell-remaining amount-sum">
                    <span class="sr-only">{{ t.dashboard.remaining }}</span>
                    {{ formatMoneyAmount(group.totals.remaining_minor_units) }}
                </div>
                <div class="category-meter-cell-meter">
                    <ExecutionMeter
                        :percent-complete="group.totals.percent_complete"
                        :category-type="group.type"
                        :is-over-limit="group.isOverLimit"
                        :year-elapsed-percent="null"
                    />
                </div>
                <div
                    v-if="group.isOverLimit"
                    class="category-meter-cell-status"
                >
                    <Badge variant="destructive-soft">
                        {{ t.budget.over_limit_badge }}
                    </Badge>
                </div>
            </div>

            <p
                v-if="group.categories.length === 0"
                class="meta text-muted-foreground px-4 py-3"
            >
                {{ t.dashboard.no_categories }}
            </p>

            <CategoryMeterRow
                v-for="category in group.categories"
                :key="category.id"
                :category="category"
                :category-type="group.type"
                :budget-year="budgetYear"
                :year-elapsed-percent="yearElapsedPercent"
            />
        </template>

        <template #footer>
            <p class="meta text-muted-foreground">{{ footerText }}</p>
        </template>
    </ListSurface>
</template>
