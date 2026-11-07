<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MoreHorizontal, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import BudgetCategoryList, {
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
import BudgetEmptyState from '@/components/BudgetEmptyState.vue';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import type { CategoryAppearanceOptions } from '@/lib/category-appearance';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';
import { budget as budgetRoute } from '@/routes';

type BudgetSummary = {
    id: number;
    year: number;
    phase: 'draft' | 'execution';
    can_delete: boolean;
    can_start_execution: boolean;
    can_reorder: boolean;
};

type DeficitWarning = {
    planned_deficit_minor_units: number;
    liquidity_at_year_start_minor_units: number;
    uncovered_deficit_minor_units: number;
};

type PlanSummary = {
    income: DashboardTypeTotals;
    expense: DashboardTypeTotals;
    planned_balance_minor_units: number;
};

const props = defineProps<{
    budget: BudgetSummary | null;
    incomeCategories: BudgetCategoryRow[];
    expenseCategories: BudgetCategoryRow[];
    planSummary: PlanSummary | null;
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    deficitWarning: DeficitWarning | null;
    show_archived: boolean;
    year_elapsed_percent: number | null;
    categoryAppearanceOptions: CategoryAppearanceOptions;
}>();

const t = useTranslations();

const createBudgetDialogOpen = ref(false);
const createCategoryDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingCategory = ref<BudgetCategoryRow | null>(null);
const deleteBudgetDialogOpen = ref(false);
const showArchived = ref(props.show_archived);

watch(
    () => props.show_archived,
    (value) => {
        showArchived.value = value;
    },
);

watch(showArchived, (value) => {
    if (value !== props.show_archived) {
        toggleShowArchived(value);
    }
});

const phaseLabel = computed(() =>
    props.budget?.phase === 'execution'
        ? t.budget.phase_execution
        : t.budget.phase_draft,
);

const phaseBadgeVariant = computed(() =>
    props.budget?.phase === 'execution' ? 'brand' : 'warning',
);

const phaseDescription = computed(() =>
    props.budget?.phase === 'execution'
        ? t.budget.description_execution
        : t.budget.description_draft,
);

const deficitWarningMessage = computed(() => {
    if (props.deficitWarning === null) {
        return null;
    }

    return t.budget.deficit_warning
        .replace(
            ':deficit',
            formatMoney(props.deficitWarning.planned_deficit_minor_units),
        )
        .replace(
            ':liquidity',
            formatMoney(
                props.deficitWarning.liquidity_at_year_start_minor_units,
            ),
        )
        .replace(
            ':uncovered',
            formatMoney(props.deficitWarning.uncovered_deficit_minor_units),
        );
});

function openEditDialog(category: BudgetCategoryRow): void {
    editingCategory.value = category;
    editDialogOpen.value = true;
}

function toggleShowArchived(checked: boolean): void {
    router.get(
        budgetRoute({
            query: {
                show_archived: checked ? 1 : undefined,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                showArchived.value = props.show_archived;
            },
        },
    );
}

function openDeleteBudgetDialog(): void {
    deleteBudgetDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.budget.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <BudgetEmptyState
            v-if="budget === null"
            v-model:open="createBudgetDialogOpen"
        />

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h1 class="page-title">
                            {{ t.budget.title }} {{ budget.year }}
                        </h1>
                        <Badge :variant="phaseBadgeVariant">
                            {{ phaseLabel }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ phaseDescription }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="createCategoryDialogOpen = true"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        {{ t.budget.add_category }}
                    </Button>
                    <ConfirmActionDialog
                        v-if="budget.can_start_execution"
                        :form="BudgetController.startExecution.form(budget)"
                        :title="t.budget.start_execution_title"
                        :description="t.budget.start_execution_description"
                        :confirm-label="t.budget.start_execution_confirm"
                        :cancel-label="t.budget.cancel"
                    >
                        <template #trigger>
                            <DialogTrigger as-child>
                                <Button>
                                    {{ t.budget.start_execution }}
                                </Button>
                            </DialogTrigger>
                        </template>
                        <Alert v-if="deficitWarningMessage" variant="warning">
                            {{ deficitWarningMessage }}
                        </Alert>
                    </ConfirmActionDialog>
                    <DropdownMenu v-if="budget.can_delete">
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                :aria-label="t.budget.budget_actions"
                            >
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive"
                                @select="openDeleteBudgetDialog"
                            >
                                {{ t.budget.delete_budget }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <BudgetPlanSummaryStrip
                v-if="planSummary"
                :income-totals="incomeTotals"
                :expense-totals="expenseTotals"
                :planned-balance-minor-units="
                    planSummary.planned_balance_minor_units
                "
                :expense-percent-complete="expenseTotals.percent_complete"
                :year-elapsed-percent="year_elapsed_percent"
            />

            <div class="flex items-center gap-2">
                <Switch id="show-archived-budget" v-model="showArchived" />
                <Label
                    for="show-archived-budget"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }}
                </Label>
            </div>

            <div class="flex flex-col gap-4">
                <BudgetCategoryList
                    :title="t.budget.income_table"
                    :categories="incomeCategories"
                    :budget-id="budget.id"
                    category-type="income"
                    :phase="budget.phase"
                    :totals="incomeTotals"
                    :can-reorder="budget.can_reorder"
                    :empty-description="t.budget.empty"
                    :year-elapsed-percent="year_elapsed_percent"
                    @edit="openEditDialog"
                />

                <BudgetCategoryList
                    :title="t.budget.expense_table"
                    :categories="expenseCategories"
                    :budget-id="budget.id"
                    category-type="expense"
                    :phase="budget.phase"
                    :totals="expenseTotals"
                    :can-reorder="budget.can_reorder"
                    :empty-description="t.budget.empty"
                    :year-elapsed-percent="year_elapsed_percent"
                    @edit="openEditDialog"
                />
            </div>
        </template>
    </div>

    <ConfirmActionDialog
        v-if="budget"
        v-model:open="deleteBudgetDialogOpen"
        :form="BudgetController.destroy.form(budget)"
        destructive
        :title="t.budget.delete_budget_confirm_title"
        :description="t.budget.delete_budget_confirm_body"
        :confirm-label="t.budget.delete_budget"
        :cancel-label="t.budget.cancel"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createCategoryDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="expense"
        :category-appearance-options="categoryAppearanceOptions"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="editDialogOpen"
        mode="edit"
        :budget-id="budget.id"
        default-type="income"
        :category="editingCategory"
        :lock-type="editingCategory?.can_change_type === false"
        :category-appearance-options="categoryAppearanceOptions"
    />
</template>
