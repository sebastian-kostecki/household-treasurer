<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import BudgetCategoryList, {
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
import BudgetEmptyState from '@/components/BudgetEmptyState.vue';
import BudgetMonthStrip from '@/components/BudgetMonthStrip.vue';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';

const t = useTranslations();

const BUDGET_YEAR = 2026;

type RowOverrides = Partial<BudgetCategoryRow> &
    Pick<BudgetCategoryRow, 'id' | 'name' | 'type'>;

function row(overrides: RowOverrides): BudgetCategoryRow {
    return {
        icon: 'tag',
        color: DEFAULT_CATEGORY_COLOR,
        planned_amount_minor_units: 0,
        initial_plan_minor_units: 0,
        current_plan_minor_units: 0,
        execution_minor_units: 0,
        remaining_minor_units: 0,
        percent_complete: null,
        is_over_limit: false,
        can_edit: true,
        can_delete: true,
        can_change_type: true,
        is_archived: false,
        can_archive: true,
        can_restore: false,
        can_align_current_plan: false,
        is_paced: true,
        month_item_kind: 'running',
        ...overrides,
    };
}

function draftRow(
    id: number,
    name: string,
    type: 'income' | 'expense',
    plan: number,
    icon = 'tag',
): BudgetCategoryRow {
    return row({
        id,
        name,
        type,
        icon,
        planned_amount_minor_units: plan,
        initial_plan_minor_units: plan,
        current_plan_minor_units: plan,
        remaining_minor_units: plan,
    });
}

function executionRow(
    id: number,
    name: string,
    type: 'income' | 'expense',
    initial: number,
    current: number,
    executed: number,
    icon = 'tag',
): BudgetCategoryRow {
    const remaining = current - executed;

    return row({
        id,
        name,
        type,
        icon,
        planned_amount_minor_units: current,
        initial_plan_minor_units: initial,
        current_plan_minor_units: current,
        execution_minor_units: executed,
        remaining_minor_units: remaining,
        percent_complete:
            current === 0 ? null : Math.round((executed / current) * 100),
        is_over_limit: type === 'expense' && remaining < 0,
        can_align_current_plan: initial !== current,
    });
}

function totalsOf(
    rows: BudgetCategoryRow[],
    isDraft: boolean,
): DashboardTypeTotals {
    const active = rows.filter((category) => !category.is_archived);
    const initial = active.reduce(
        (sum, category) => sum + category.initial_plan_minor_units,
        0,
    );
    const current = active.reduce(
        (sum, category) => sum + category.current_plan_minor_units,
        0,
    );
    const executed = active.reduce(
        (sum, category) => sum + category.execution_minor_units,
        0,
    );

    return {
        initial_plan_minor_units: initial,
        current_plan_minor_units: current,
        execution_minor_units: isDraft ? 0 : executed,
        remaining_minor_units: current - (isDraft ? 0 : executed),
        percent_complete:
            isDraft || current === 0
                ? null
                : Math.round((executed / current) * 100),
    };
}

const draftIncome = [
    draftRow(101, 'Wynagrodzenie', 'income', 650000, 'banknote'),
    draftRow(102, 'Premia', 'income', 120000, 'coins'),
];
const draftExpense = [
    draftRow(111, 'Czynsz', 'expense', 250000, 'building'),
    draftRow(112, 'Jedzenie', 'expense', 180000, 'apple'),
    draftRow(113, 'Transport', 'expense', 45000, 'bus'),
];

const executionIncome = [
    executionRow(201, 'Wynagrodzenie', 'income', 650000, 650000, 650000),
    executionRow(202, 'Premia', 'income', 120000, 120000, 135000),
];
const executionExpense = [
    executionRow(211, 'Czynsz', 'expense', 250000, 250000, 250000, 'building'),
    executionRow(212, 'Jedzenie', 'expense', 180000, 200000, 215000, 'apple'),
    executionRow(213, 'Transport', 'expense', 45000, 45000, 12000, 'bus'),
    executionRow(214, 'Rozrywka', 'expense', 30000, 30000, 0),
];

const withArchived = [
    executionRow(221, 'Czynsz', 'expense', 250000, 250000, 250000, 'building'),
    executionRow(222, 'Jedzenie', 'expense', 180000, 180000, 90000, 'apple'),
    row({
        id: 223,
        name: 'Siłownia',
        type: 'expense',
        is_archived: true,
        can_archive: false,
        can_restore: true,
        can_delete: false,
        planned_amount_minor_units: 12000,
        initial_plan_minor_units: 12000,
        current_plan_minor_units: 12000,
        execution_minor_units: 15000,
        remaining_minor_units: -3000,
        percent_complete: 125,
        is_over_limit: true,
    }),
    row({
        id: 224,
        name: 'Stary abonament',
        type: 'expense',
        is_archived: true,
        can_archive: false,
        can_restore: true,
        planned_amount_minor_units: 8000,
        initial_plan_minor_units: 8000,
        current_plan_minor_units: 8000,
        execution_minor_units: 3000,
        remaining_minor_units: 5000,
        percent_complete: 38,
    }),
];

const manyCategories = Array.from({ length: 14 }, (_, index) =>
    draftRow(
        300 + index,
        `Kategoria ${index + 1}`,
        'expense',
        10000 * (index + 1),
    ),
);

const emptyTotals = totalsOf([], true);

const showArchivedChecked = ref(true);
const showArchivedUnchecked = ref(false);
const createBudgetOpen = ref(false);

const noop = (): void => undefined;
</script>

<template>
    <Head title="Kitchen sink: Budżet" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:gap-10">
        <header class="flex flex-col gap-1">
            <h1 class="text-lg font-semibold">Kitchen sink: Budżet</h1>
            <p class="text-muted-foreground text-sm">
                Tylko środowisko lokalne. Prawdziwe komponenty widoku /budget z
                fiksurami. Akcje wierszy nie działają, bo identyfikatory fiksur
                nie istnieją w bazie.
            </p>
        </header>

        <section class="flex flex-col gap-3" aria-labelledby="ks-draft">
            <h2 id="ks-draft" class="text-base font-semibold">
                Lista w szkicu
            </h2>
            <BudgetCategoryList
                :title="t.budget.income_table"
                :categories="draftIncome"
                :budget-id="1"
                category-type="income"
                phase="draft"
                :totals="totalsOf(draftIncome, true)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_income"
                @add="noop"
                @edit="noop"
            />
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="draftExpense"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="totalsOf(draftExpense, true)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-income">
            <h2 id="ks-income" class="text-base font-semibold">
                Realizacja przychodów (w tym przychód ponad plan)
            </h2>
            <BudgetCategoryList
                :title="t.budget.income_table"
                :categories="executionIncome"
                :budget-id="1"
                category-type="income"
                phase="execution"
                :totals="totalsOf(executionIncome, false)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_income"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-expense">
            <h2 id="ks-expense" class="text-base font-semibold">
                Realizacja wydatków (kategoria ponad limit, zmieniony plan)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="executionExpense"
                :budget-id="1"
                category-type="expense"
                phase="execution"
                :totals="totalsOf(executionExpense, false)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-archived">
            <h2 id="ks-archived" class="text-base font-semibold">
                Kategorie zarchiwizowane (rozwinięte archiwum po kliknięciu)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="withArchived"
                :budget-id="1"
                category-type="expense"
                phase="execution"
                :totals="totalsOf(withArchived, false)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-empty">
            <h2 id="ks-empty" class="text-base font-semibold">Pusta lista</h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="[]"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="emptyTotals"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-many">
            <h2 id="ks-many" class="text-base font-semibold">
                14 kategorii (widoczny filtr nazw)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="manyCategories"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="totalsOf(manyCategories, true)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-noreorder">
            <h2 id="ks-noreorder" class="text-base font-semibold">
                Bez zmiany kolejności (canReorder = false)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="draftExpense"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="totalsOf(draftExpense, true)"
                :can-reorder="false"
                :empty-description="t.budget.empty"
                :add-label="t.budget.add_expense"
                @add="noop"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-strips">
            <h2 id="ks-strips" class="text-base font-semibold">
                Paski podsumowania i miesiąca
            </h2>
            <BudgetPlanSummaryStrip
                :income-totals="totalsOf(executionIncome, false)"
                :expense-totals="totalsOf(executionExpense, false)"
                :planned-balance-minor-units="125000"
            />
            <BudgetMonthStrip
                :budget-year="BUDGET_YEAR"
                :viewed-month="3"
                :show-archived="false"
                :current-month="3"
                viewed-month-timing="current"
                :month-income-execution-minor-units="650000"
                :month-expense-execution-minor-units="310000"
                :month-result-minor-units="340000"
                :month-to-spend="{ state: 'none', to_spend_minor_units: null }"
            />
            <BudgetMonthStrip
                :budget-year="BUDGET_YEAR"
                :viewed-month="4"
                :show-archived="false"
                :current-month="3"
                viewed-month-timing="future"
                :month-income-execution-minor-units="0"
                :month-expense-execution-minor-units="0"
                :month-result-minor-units="0"
                :month-to-spend="{
                    state: 'proposal',
                    to_spend_minor_units: 180000,
                }"
            />
            <BudgetMonthStrip
                :budget-year="BUDGET_YEAR"
                :viewed-month="2"
                :show-archived="false"
                :current-month="3"
                viewed-month-timing="past"
                :month-income-execution-minor-units="650000"
                :month-expense-execution-minor-units="700000"
                :month-result-minor-units="-50000"
                :month-to-spend="{
                    state: 'approved',
                    to_spend_minor_units: -25000,
                }"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-toggle">
            <h2 id="ks-toggle" class="text-base font-semibold">
                Przełącznik „Pokaż zarchiwizowane”
            </h2>
            <div class="flex items-center gap-2">
                <Checkbox
                    id="ks-show-archived-on"
                    v-model="showArchivedChecked"
                />
                <Label
                    for="ks-show-archived-on"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }} (zaznaczony)
                </Label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox
                    id="ks-show-archived-off"
                    v-model="showArchivedUnchecked"
                />
                <Label
                    for="ks-show-archived-off"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }} (odznaczony)
                </Label>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-no-budget">
            <h2 id="ks-no-budget" class="text-base font-semibold">
                Brak budżetu
            </h2>
            <BudgetEmptyState v-model:open="createBudgetOpen" />
        </section>
    </div>
</template>
