<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Check, Circle } from '@lucide/vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import StatTile from '@/components/StatTile.vue';
import DashboardHoldings from '@/components/DashboardHoldings.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import DashboardCategorySection from '@/components/DashboardCategorySection.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    useFirstRunChecklist,
    useShell,
    useTranslations,
} from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import type { DashboardPageProps } from '@/types/dashboard';

const YEAR_ELAPSED_STORAGE_KEY = 'dashboard-year-elapsed';

const props = defineProps<DashboardPageProps>();

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

const showMonthToSpendTile = computed(() => props.month_to_spend !== null);

const headlineTilesClass = computed(() =>
    showMonthToSpendTile.value ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
);

const monthToSpendLabel = computed(() => {
    if (props.current_month === null) {
        return '';
    }

    const monthName =
        t.budget[
            `month_name_${props.current_month}` as keyof typeof t.budget
        ] ?? '';

    return t.dashboard.month_to_spend.replace(':month', monthName);
});

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
            <StatTile
                v-if="month_to_spend !== null"
                :label="monthToSpendLabel"
                :amount-minor-units="month_to_spend.to_spend_minor_units"
            >
                <template
                    v-if="month_to_spend.state === 'proposal'"
                    #label-extra
                >
                    <Badge variant="outline">
                        {{ t.month_plan.state_proposal }}
                    </Badge>
                </template>
                <template #footer>
                    <Form
                        v-bind="BudgetController.selectYear.form()"
                        v-slot="{ processing }"
                    >
                        <input type="hidden" name="year" :value="budget_year" />
                        <input
                            type="hidden"
                            name="destination"
                            value="month-plan"
                        />
                        <Button
                            type="submit"
                            variant="link"
                            size="sm"
                            class="h-auto p-0"
                            :disabled="processing"
                        >
                            {{ t.nav.month_plan }}
                        </Button>
                    </Form>
                </template>
            </StatTile>

            <StatTile
                :label="t.dashboard.spendable_liquidity"
                :amount-minor-units="spendable_liquidity_minor_units"
            />

            <StatTile
                :label="t.dashboard.total_funds"
                :amount-minor-units="total_funds_minor_units"
            />
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
                        v-if="planSummary"
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
