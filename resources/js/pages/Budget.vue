<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import CreateBudgetDialog from '@/components/CreateBudgetDialog.vue';
import BudgetCategoryList, {
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
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
    categoryAppearanceOptions: CategoryAppearanceOptions;
}>();

const t = useTranslations();

const createBudgetDialogOpen = ref(false);
const createIncomeDialogOpen = ref(false);
const createExpenseDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingCategory = ref<BudgetCategoryRow | null>(null);
const deleteBudgetDialogOpen = ref(false);

const phaseLabel = computed(() =>
    props.budget?.phase === 'execution'
        ? t.budget.phase_execution
        : t.budget.phase_draft,
);

const deficitWarningMessage = computed(() => {
    if (props.deficitWarning === null) {
        return null;
    }

    return t.budget.deficit_warning
        .replace(':deficit', formatMoney(props.deficitWarning.planned_deficit_minor_units))
        .replace(
            ':liquidity',
            formatMoney(props.deficitWarning.liquidity_at_year_start_minor_units),
        )
        .replace(
            ':uncovered',
            formatMoney(props.deficitWarning.uncovered_deficit_minor_units),
        );
});

setLayoutProps({
    breadcrumbs: [
        {
            title: t.nav.budget,
            href: budgetRoute(),
        },
    ],
});

function openEditDialog(category: BudgetCategoryRow): void {
    editingCategory.value = category;
    editDialogOpen.value = true;
}

function toggleShowArchived(checked: boolean): void {
    router.get(
        budgetRoute({ query: { show_archived: checked ? 1 : undefined } }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function openDeleteBudgetDialog(): void {
    deleteBudgetDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t.budget.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div
            v-if="budget === null"
            class="flex flex-col items-start gap-4"
        >
            <Card class="w-full max-w-lg">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t.budget.no_budget }}
                    </CardTitle>
                    <CardDescription>
                        {{ t.budget.no_budget_description }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <CreateBudgetDialog
                        v-model:open="createBudgetDialogOpen"
                        year-input-id="budget-page-create-budget-year"
                    >
                        <template #trigger>
                            <Button>
                                {{ t.budget.create_budget }}
                            </Button>
                        </template>
                    </CreateBudgetDialog>
                </CardContent>
            </Card>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold">
                        {{ t.budget.title }} {{ budget.year }}
                    </h1>
                    <Badge variant="secondary">{{ phaseLabel }}</Badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Dialog v-if="budget.can_start_execution">
                        <DialogTrigger as-child>
                            <Button>
                                {{ t.budget.start_execution }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="BudgetController.startExecution.form(budget)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.budget.start_execution_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.budget.start_execution_description }}
                                    </DialogDescription>
                                </DialogHeader>
                                <p
                                    v-if="deficitWarningMessage"
                                    class="text-sm text-destructive"
                                >
                                    {{ deficitWarningMessage }}
                                </p>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button type="button" variant="secondary">
                                            {{ t.budget.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="processing">
                                        {{ t.budget.start_execution_confirm }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
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
                :planned-balance-minor-units="planSummary.planned_balance_minor_units"
            />

            <div class="flex items-center gap-2">
                <Checkbox
                    id="show-archived-budget"
                    :checked="show_archived"
                    @update:checked="toggleShowArchived"
                />
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
                    :add-label="t.budget.add_income"
                    @add="createIncomeDialogOpen = true"
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
                    :add-label="t.budget.add_expense"
                    @add="createExpenseDialogOpen = true"
                    @edit="openEditDialog"
                />
            </div>
        </template>
    </div>

    <Dialog v-if="budget" v-model:open="deleteBudgetDialogOpen">
        <DialogContent>
            <Form
                v-bind="BudgetController.destroy.form(budget)"
                v-slot="{ processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ t.budget.delete_budget_confirm_title }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t.budget.delete_budget_confirm_body }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            {{ t.budget.cancel }}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        {{ t.budget.delete_budget }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createIncomeDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="income"
        :category-appearance-options="categoryAppearanceOptions"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createExpenseDialogOpen"
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
