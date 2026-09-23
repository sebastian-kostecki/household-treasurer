<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

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

const checklistComplete = computed(() =>
    checklist.value.every((item) => item.done),
);

const showMonthRemainingTile = computed(
    () => props.paced_pool_month_remaining_minor_units !== null,
);

const monthRemainingLabel = computed(() => {
    if (props.current_month === null) {
        return '';
    }

    const monthName =
        t.budget[`month_name_${props.current_month}` as keyof typeof t.budget] ??
        '';

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
        <div class="grid gap-4 lg:grid-cols-3 lg:gap-5">
            <div class="flex flex-col gap-4 md:gap-5 lg:col-span-2">
                <Card v-if="shell.firstRun.showChecklist">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <CardTitle class="text-base">
                                    {{ t.dashboard.checklist_title }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        checklistComplete
                                            ? t.dashboard.checklist_complete_description
                                            : t.dashboard.checklist_description
                                    }}
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

                <DashboardNotices
                    v-if="dashboardNotices.length > 0"
                    :notices="dashboardNotices"
                    :budget-year="budget_year"
                    :current-month="current_month"
                />

                <div
                    class="grid gap-3"
                    :class="showMonthRemainingTile ? 'sm:grid-cols-2' : ''"
                >
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
                                    formatMoney(
                                        paced_pool_month_remaining_minor_units!,
                                    )
                                }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card :class="showMonthRemainingTile ? '' : 'sm:col-span-2'">
                        <CardHeader class="pb-2">
                            <CardDescription>
                                {{ t.dashboard.spendable_liquidity }}
                            </CardDescription>
                            <CardTitle
                                class="text-3xl tabular-nums"
                                :class="spendableLiquidityClass"
                            >
                                {{
                                    formatMoney(spendable_liquidity_minor_units)
                                }}
                            </CardTitle>
                            <p
                                class="text-muted-foreground pt-1 text-sm tabular-nums"
                            >
                                {{ t.dashboard.total_funds }}:
                                <span :class="totalFundsClass">
                                    {{ formatMoney(total_funds_minor_units) }}
                                </span>
                            </p>
                        </CardHeader>
                    </Card>
                </div>

                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
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

                        <div class="flex flex-wrap gap-2">
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

                        <div v-if="showBudgetCategories" class="space-y-3">
                            <DashboardCategorySection
                                :title="t.dashboard.expenses"
                                category-type="expense"
                                :categories="expenseCategories"
                                :totals="expenseTotals"
                                :year-elapsed-as-of="year_elapsed_as_of ?? ''"
                                :year-elapsed-percent="year_elapsed_percent ?? 0"
                                show-order-toggle
                            />

                            <DashboardCategorySection
                                :title="t.dashboard.income"
                                category-type="income"
                                :categories="incomeCategories"
                                :totals="incomeTotals"
                                :year-elapsed-as-of="year_elapsed_as_of ?? ''"
                                :year-elapsed-percent="year_elapsed_percent ?? 0"
                            />
                        </div>
                    </template>
                </section>
            </div>

            <aside class="lg:col-span-1">
                <DashboardHoldings
                    :accounts="accounts"
                    :term-deposits="termDeposits"
                />
            </aside>
        </div>
    </div>
</template>
