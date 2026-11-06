<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { Check, Circle } from '@lucide/vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import DashboardStatPanel from '@/components/DashboardStatPanel.vue';
import DashboardHoldings from '@/components/DashboardHoldings.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import DashboardExecutionPanel from '@/components/DashboardExecutionPanel.vue';
import DashboardNotices from '@/components/DashboardNotices.vue';
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
import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from '@/lib/budget-year-selection';
import { formatLongDate } from '@/lib/format';
import { dashboard } from '@/routes';
import type { DashboardPageProps } from '@/types/dashboard';

const props = defineProps<DashboardPageProps>();

const t = useTranslations();
const shell = useShell();
const checklist = useFirstRunChecklist();
const createBudgetDialogOpen = ref(false);

const yearSelectionPending = ref(false);

function onYearSelectionFailed(): void {
    notifyBudgetYearSelectionFailed(t.dashboard.select_year_failed);
}

const stopListeningForYearSelectionFailure =
    listenForBudgetYearSelectionFailure(
        () => yearSelectionPending.value,
        t.dashboard.select_year_failed,
    );

onUnmounted(stopListeningForYearSelectionFailure);

const checklistComplete = computed(() =>
    checklist.value.every((item) => item.done),
);

const budgetStateLabel = computed(() => {
    const key =
        props.budget === null
            ? 'meta_no_budget'
            : props.budget.phase === 'execution'
              ? 'meta_budget_execution'
              : 'meta_budget_draft';

    return t.dashboard[key].replace(':year', String(props.budget_year));
});

const metaLine = computed(() => {
    if (props.year_elapsed_as_of === null) {
        return budgetStateLabel.value;
    }

    return `${formatLongDate(props.year_elapsed_as_of)} · ${budgetStateLabel.value}`;
});

function onYearSelectionStart(): void {
    yearSelectionPending.value = true;
}

function onYearSelectionFinish(): void {
    yearSelectionPending.value = false;
}

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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="page-title">{{ t.dashboard.title }}</h1>
                    <Badge v-if="budget?.phase === 'execution'" variant="brand">
                        {{ t.budget.phase_execution }}
                    </Badge>
                    <Badge
                        v-else-if="budget?.phase === 'draft'"
                        variant="secondary"
                    >
                        {{ t.dashboard.draft_badge }}
                    </Badge>
                </div>
                <p class="text-muted-foreground text-sm">{{ metaLine }}</p>
            </div>

            <Form
                v-if="budget !== null && canSelectCurrentYearInShell"
                v-bind="BudgetController.selectYear.form()"
                v-slot="{ processing }"
                @start="onYearSelectionStart"
                @finish="onYearSelectionFinish"
                @error="onYearSelectionFailed"
            >
                <input type="hidden" name="year" :value="budget_year" />
                <Button type="submit" variant="outline" :disabled="processing">
                    {{ t.dashboard.open_budget }}
                </Button>
            </Form>
            <CreateBudgetDialog
                v-else-if="budget !== null"
                v-model:open="createBudgetDialogOpen"
                year-input-id="dashboard-open-budget-year"
                :suggested-year="budget_year"
            >
                <template #trigger>
                    <Button variant="outline">
                        {{ t.dashboard.open_budget }}
                    </Button>
                </template>
            </CreateBudgetDialog>
        </div>

        <Card v-if="shell.firstRun.showChecklist">
            <CardHeader class="pb-3">
                <div
                    class="flex justify-between gap-3"
                    :class="checklistComplete ? 'items-center' : 'items-start'"
                >
                    <div class="space-y-1">
                        <CardTitle class="section-title">
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

        <DashboardStatPanel
            :budget-year="budget_year"
            :current-month="current_month"
            :month-to-spend="month_to_spend"
            :month-pace="month_pace"
            :spendable-liquidity-minor-units="spendable_liquidity_minor_units"
            :total-funds-minor-units="total_funds_minor_units"
            @start="onYearSelectionStart"
            @finish="onYearSelectionFinish"
            @error="onYearSelectionFailed"
        />

        <DashboardNotices
            v-if="dashboardNotices.length > 0"
            :notices="dashboardNotices"
            :budget-year="budget_year"
            :current-month="current_month"
        />

        <div class="dashboard-grid">
            <section class="min-w-0 space-y-3">
                <Card v-if="budget === null">
                    <IndexEmptyState :title="noBudgetMessage">
                        <template #actions>
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
                        </template>
                    </IndexEmptyState>
                </Card>

                <template v-else>
                    <BudgetPlanSummaryStrip
                        v-if="planSummary && budget.phase === 'draft'"
                        :income-totals="planSummary.income"
                        :expense-totals="planSummary.expense"
                        :planned-balance-minor-units="
                            planSummary.planned_balance_minor_units
                        "
                    />

                    <Card v-if="budget.phase === 'draft'">
                        <IndexEmptyState
                            :title="t.dashboard.draft_categories_title"
                            :description="
                                t.dashboard.draft_categories_description
                            "
                        />
                    </Card>

                    <DashboardExecutionPanel
                        v-if="showBudgetCategories"
                        :income-categories="incomeCategories"
                        :expense-categories="expenseCategories"
                        :income-totals="incomeTotals"
                        :expense-totals="expenseTotals"
                        :year-elapsed-percent="year_elapsed_percent"
                        :budget-year="budget_year"
                    />
                </template>
            </section>

            <aside class="min-w-0">
                <DashboardHoldings
                    :accounts="accounts"
                    :term-deposits="termDeposits"
                />
            </aside>
        </div>
    </div>
</template>
