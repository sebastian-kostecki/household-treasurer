<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Check, Circle } from '@lucide/vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import DashboardHoldings from '@/components/DashboardHoldings.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import DashboardCategorySection from '@/components/DashboardCategorySection.vue';
import DashboardNotices, {
    type DashboardNotice,
} from '@/components/DashboardNotices.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    useFirstRunChecklist,
    useShell,
    useTranslations,
} from '@/composables/useTranslations';
import type { AccountBank } from '@/lib/account-identity';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import { formatDate, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

const YEAR_ELAPSED_STORAGE_KEY = 'dashboard-year-elapsed';

type AccountSummary = {
    id: number;
    name: string;
    type: 'bank' | 'cash';
    bank: AccountBank | null;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

type TermDepositSummary = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    end_date: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

type BudgetSummary = {
    year: number;
    phase: 'draft' | 'execution';
};

type PlanSummary = {
    income: DashboardTypeTotals;
    expense: DashboardTypeTotals;
    planned_balance_minor_units: number;
};

const props = defineProps<{
    accounts: AccountSummary[];
    termDeposits: TermDepositSummary[];
    spendable_liquidity_minor_units: number;
    total_funds_minor_units: number;
    budget_year: number;
    budget: BudgetSummary | null;
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    year_elapsed_as_of: string | null;
    year_elapsed_percent: number | null;
    dashboardNotices: DashboardNotice[];
    paced_pool_month_remaining_minor_units: number | null;
    current_month: number | null;
    planSummary: PlanSummary | null;
}>();

const t = useTranslations();
const shell = useShell();
const checklist = useFirstRunChecklist();
const createBudgetDialogOpen = ref(false);
const showYearElapsed = ref(false);

onMounted(() => {
    showYearElapsed.value =
        window.localStorage.getItem(YEAR_ELAPSED_STORAGE_KEY) === '1';
});

watch(showYearElapsed, (enabled) => {
    window.localStorage.setItem(YEAR_ELAPSED_STORAGE_KEY, enabled ? '1' : '0');
});

const yearElapsedLabel = computed(() => {
    if (props.year_elapsed_as_of === null) {
        return t.dashboard.year_elapsed;
    }

    return `${t.dashboard.year_elapsed} ${formatDate(props.year_elapsed_as_of)}`;
});

const checklistComplete = computed(() =>
    checklist.value.every((item) => item.done),
);

const showMonthRemainingTile = computed(
    () => props.paced_pool_month_remaining_minor_units !== null,
);

const headlineTilesClass = computed(() =>
    showMonthRemainingTile.value ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
);

const monthRemainingLabel = computed(() => {
    if (props.current_month === null) {
        return '';
    }

    const monthName =
        t.budget[
            `month_name_${props.current_month}` as keyof typeof t.budget
        ] ?? '';

    return t.dashboard.paced_pool_month_remaining.replace(':month', monthName);
});

const monthRemainingClass = computed(() => {
    if (
        props.paced_pool_month_remaining_minor_units === null ||
        props.paced_pool_month_remaining_minor_units >= 0
    ) {
        return 'text-success';
    }

    return 'text-destructive';
});

const spendableLiquidityClass = computed(() =>
    props.spendable_liquidity_minor_units < 0
        ? 'text-destructive'
        : 'text-success',
);

const totalFundsClass = computed(() =>
    props.total_funds_minor_units < 0 ? 'text-destructive' : 'text-success',
);

const budgetYearLabel = computed(() =>
    t.dashboard.budget_year_label.replace(':year', String(props.budget_year)),
);

const noBudgetMessage = computed(() =>
    t.dashboard.no_budget_for_year.replace(':year', String(props.budget_year)),
);

const canSelectCurrentYearInShell = computed(() =>
    shell.budgetYears.includes(props.budget_year),
);

const showBudgetCategories = computed(
    () => props.budget?.phase === 'execution',
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.dashboard,
            href: dashboard(),
        },
    ],
});
</script>

<template>
    <Head :title="t.dashboard.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <Card v-if="shell.firstRun.showChecklist">
            <CardHeader class="pb-3">
                <div
                    class="flex justify-between gap-3"
                    :class="checklistComplete ? 'items-center' : 'items-start'"
                >
                    <div class="space-y-1">
                        <CardTitle class="text-base">
                            {{ t.dashboard.checklist_title }}
                        </CardTitle>
                        <CardDescription v-if="!checklistComplete">
                            {{ t.dashboard.checklist_description }}
                        </CardDescription>
                    </div>
                    <Form
                        v-if="checklistComplete"
                        v-bind="
                            DashboardController.dismissFirstRunChecklist.form()
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                        >
                            {{ t.dashboard.checklist_dismiss }}
                        </Button>
                    </Form>
                </div>
            </CardHeader>
            <CardContent v-if="!checklistComplete" class="space-y-2">
                <div
                    v-for="item in checklist"
                    :key="item.key"
                    class="flex items-center gap-3 rounded-md border px-3 py-2 text-sm"
                >
                    <Check
                        v-if="item.done"
                        class="text-success size-4 shrink-0"
                    />
                    <Circle
                        v-else
                        class="text-muted-foreground size-4 shrink-0"
                    />
                    <span
                        :class="
                            item.done
                                ? 'text-muted-foreground line-through'
                                : ''
                        "
                    >
                        {{ t.dashboard[item.labelKey] }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-3" :class="headlineTilesClass">
            <Card v-if="showMonthRemainingTile">
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ monthRemainingLabel }}
                    </CardDescription>
                    <CardTitle
                        class="text-3xl tabular-nums"
                        :class="monthRemainingClass"
                    >
                        {{
                            formatMoney(paced_pool_month_remaining_minor_units!)
                        }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.dashboard.spendable_liquidity }}
                    </CardDescription>
                    <CardTitle
                        class="text-3xl tabular-nums"
                        :class="spendableLiquidityClass"
                    >
                        {{ formatMoney(spendable_liquidity_minor_units) }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>
                        {{ t.dashboard.total_funds }}
                    </CardDescription>
                    <CardTitle
                        class="text-3xl tabular-nums"
                        :class="totalFundsClass"
                    >
                        {{ formatMoney(total_funds_minor_units) }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <DashboardNotices
            v-if="dashboardNotices.length > 0"
            :notices="dashboardNotices"
            :budget-year="budget_year"
            :current-month="current_month"
        />

        <div class="grid gap-4 lg:grid-cols-3 lg:gap-5">
            <section class="space-y-3 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-semibold">
                            {{ budgetYearLabel }}
                        </h2>
                        <Badge
                            v-if="budget?.phase === 'draft'"
                            variant="secondary"
                        >
                            {{ t.dashboard.draft_badge }}
                        </Badge>
                    </div>

                    <div
                        v-if="budget !== null"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <div
                            v-if="showBudgetCategories"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                id="dashboard-year-elapsed"
                                v-model="showYearElapsed"
                            />
                            <Label
                                for="dashboard-year-elapsed"
                                class="text-muted-foreground cursor-pointer text-sm font-normal"
                            >
                                {{ yearElapsedLabel }}
                            </Label>
                        </div>

                        <Form
                            v-if="canSelectCurrentYearInShell"
                            v-bind="BudgetController.selectYear.form()"
                            v-slot="{ processing }"
                        >
                            <input
                                type="hidden"
                                name="year"
                                :value="budget_year"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ t.dashboard.open_budget }}
                            </Button>
                        </Form>
                        <CreateBudgetDialog
                            v-else
                            v-model:open="createBudgetDialogOpen"
                            year-input-id="dashboard-open-budget-year"
                            :suggested-year="budget_year"
                        >
                            <template #trigger>
                                <Button variant="outline" size="sm">
                                    {{ t.dashboard.open_budget }}
                                </Button>
                            </template>
                        </CreateBudgetDialog>
                    </div>
                </div>

                <Card v-if="budget === null">
                    <CardHeader class="pb-2">
                        <CardDescription>
                            {{ noBudgetMessage }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CreateBudgetDialog
                            v-model:open="createBudgetDialogOpen"
                            year-input-id="dashboard-create-budget-year"
                            :suggested-year="budget_year"
                        >
                            <template #trigger>
                                <Button>
                                    {{ t.budget.create_budget }}
                                </Button>
                            </template>
                        </CreateBudgetDialog>
                    </CardContent>
                </Card>

                <template v-else>
                    <BudgetPlanSummaryStrip
                        v-if="planSummary"
                        :income-totals="planSummary.income"
                        :expense-totals="planSummary.expense"
                        :planned-balance-minor-units="
                            planSummary.planned_balance_minor_units
                        "
                    />

                    <div v-if="showBudgetCategories" class="space-y-3">
                        <DashboardCategorySection
                            :title="t.dashboard.income"
                            category-type="income"
                            :categories="incomeCategories"
                            :totals="incomeTotals"
                            :show-year-elapsed="showYearElapsed"
                            :year-elapsed-percent="year_elapsed_percent ?? 0"
                        />

                        <DashboardCategorySection
                            :title="t.dashboard.expenses"
                            category-type="expense"
                            :categories="expenseCategories"
                            :totals="expenseTotals"
                            :show-year-elapsed="showYearElapsed"
                            :year-elapsed-percent="year_elapsed_percent ?? 0"
                        />
                    </div>
                </template>
            </section>

            <aside class="lg:col-span-1">
                <DashboardHoldings
                    :accounts="accounts"
                    :term-deposits="termDeposits"
                />
            </aside>
        </div>
    </div>
</template>
