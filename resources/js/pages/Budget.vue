<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/BudgetController';
import CategoryFormDialog from '@/components/CategoryFormDialog.vue';
import BudgetCategoryList, {
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
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
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { budget as budgetRoute } from '@/routes';

type BudgetSummary = {
    id: number;
    year: number;
    phase: 'draft' | 'execution';
    can_delete: boolean;
    can_start_execution: boolean;
};

type DeficitWarning = {
    planned_deficit_grosze: number;
    liquidity_at_year_start_grosze: number;
    uncovered_deficit_grosze: number;
};

const props = defineProps<{
    budget: BudgetSummary | null;
    incomeCategories: BudgetCategoryRow[];
    expenseCategories: BudgetCategoryRow[];
    deficitWarning: DeficitWarning | null;
    show_archived: boolean;
}>();

const t = useTranslations();

const createIncomeDialogOpen = ref(false);
const createExpenseDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingCategory = ref<BudgetCategoryRow | null>(null);

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
        .replace(':deficit', formatMoney(props.deficitWarning.planned_deficit_grosze))
        .replace(
            ':liquidity',
            formatMoney(props.deficitWarning.liquidity_at_year_start_grosze),
        )
        .replace(
            ':uncovered',
            formatMoney(props.deficitWarning.uncovered_deficit_grosze),
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
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button>
                                {{ t.budget.create_budget }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="BudgetController.store.form()"
                                class="space-y-4"
                                reset-on-success
                                v-slot="{ errors, processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{ t.budget.create_budget_title }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{ t.budget.create_budget_description }}
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="grid gap-2">
                                    <Label for="budget-page-year">
                                        {{ t.budget.year }}
                                    </Label>
                                    <Input
                                        id="budget-page-year"
                                        name="year"
                                        type="number"
                                        :default-value="new Date().getFullYear()"
                                        min="2000"
                                        max="2100"
                                        required
                                    />
                                    <InputError :message="errors.year" />
                                </div>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button type="button" variant="secondary">
                                            {{ t.budget.cancel }}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="processing">
                                        {{ t.budget.create_budget }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
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
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        @click="createIncomeDialogOpen = true"
                    >
                        {{ t.budget.add_income }}
                    </Button>
                    <Button
                        variant="outline"
                        @click="createExpenseDialogOpen = true"
                    >
                        {{ t.budget.add_expense }}
                    </Button>
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
                    <Dialog v-if="budget.can_delete">
                        <DialogTrigger as-child>
                            <Button variant="destructive">
                                {{ t.budget.delete_budget }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="BudgetController.destroy.form(budget)"
                                v-slot="{ processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        {{
                                            t.budget.delete_budget_confirm_title
                                        }}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {{
                                            t.budget.delete_budget_confirm_body
                                        }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
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
                </div>
            </div>

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

            <div class="grid gap-4 lg:grid-cols-2">
                <BudgetCategoryList
                    :title="t.budget.income_table"
                    :categories="incomeCategories"
                    :empty-description="t.budget.empty"
                    :add-label="t.budget.add_income"
                    highlight-initial-plan
                    @add="createIncomeDialogOpen = true"
                    @edit="openEditDialog"
                />

                <BudgetCategoryList
                    :title="t.budget.expense_table"
                    :categories="expenseCategories"
                    :empty-description="t.budget.empty"
                    :add-label="t.budget.add_expense"
                    @add="createExpenseDialogOpen = true"
                    @edit="openEditDialog"
                />
            </div>
        </template>
    </div>

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createIncomeDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="income"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="createExpenseDialogOpen"
        mode="create"
        :budget-id="budget.id"
        default-type="expense"
    />

    <CategoryFormDialog
        v-if="budget"
        v-model:open="editDialogOpen"
        mode="edit"
        :budget-id="budget.id"
        default-type="income"
        :category="editingCategory"
        :lock-type="editingCategory?.can_change_type === false"
    />
</template>
