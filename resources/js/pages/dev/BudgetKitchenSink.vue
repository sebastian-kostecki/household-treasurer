<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import BudgetCategoryList, {
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
import BudgetEmptyState from '@/components/BudgetEmptyState.vue';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { DEFAULT_CATEGORY_COLOR } from '@/lib/category-appearance';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';

const t = useTranslations();

const YEAR_ELAPSED_PERCENT = 45;

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
    overrides: Partial<BudgetCategoryRow> = {},
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
        ...overrides,
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
    overrides: Partial<BudgetCategoryRow> = {},
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
        ...overrides,
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
    draftRow(111, 'Czynsz', 'expense', 250000, 'building', {
        month_item_kind: 'bill',
    }),
    draftRow(112, 'Jedzenie', 'expense', 180000, 'apple'),
    draftRow(113, 'Transport', 'expense', 45000, 'bus'),
];

const executionIncome = [
    executionRow(201, 'Wynagrodzenie', 'income', 650000, 650000, 650000),
    executionRow(202, 'Premia', 'income', 100000, 120000, 135000),
    executionRow(203, 'Zwrot podatku', 'income', 80000, 60000, 20000),
];
const executionExpense = [
    executionRow(211, 'Czynsz', 'expense', 250000, 250000, 250000, 'building'),
    executionRow(212, 'Jedzenie', 'expense', 180000, 200000, 215000, 'apple'),
    executionRow(213, 'Transport', 'expense', 45000, 45000, 12000, 'bus'),
    executionRow(214, 'Rozrywka', 'expense', 30000, 20000, 0),
    executionRow(
        215,
        'Remont łazienki',
        'expense',
        900000,
        900000,
        310000,
        'tag',
        {
            is_paced: false,
        },
    ),
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
        initial_plan_minor_units: 10000,
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

const showArchivedOn = ref(true);
const showArchivedOff = ref(false);
const createBudgetOpen = ref(false);

const noop = (): void => undefined;

const stateMatrix: { state: string; where: string }[] = [
    {
        state: 'default',
        where: 'Realizacja: wiersze w normie, dochód ponad 100%, przekroczenie, zarchiwizowany, kategoria jednorazowa (bez kreski roku) i równomierna (z kreską). Szkic: kolumna „Typ”.',
    },
    {
        state: 'hover',
        where: 'Najedź na wiersz (tło secondary), przyciski akcji i uchwyt przeciągania w sekcjach list.',
    },
    {
        state: 'focus-visible',
        where: 'Sekcja „Fokus”: przejdź Tab-em przez filtr, wyzwalacz archiwum, menu akcji i oba przełączniki. Uchwyt przeciągania: N/A, bo jest tylko dla wskaźnika (tabindex -1), a przestawianie z klawiatury jest odroczone.',
    },
    {
        state: 'disabled',
        where: 'Sekcja „Bez zmiany kolejności” (canReorder = false: brak uchwytów). Przycisk w trakcie wysyłki dialogu: otwórz „Dodaj” w sekcji „Brak budżetu” i wyślij formularz. Uwaga: to prawdziwa trasa, więc wysłanie utworzy budżet na koncie deweloperskim.',
    },
    {
        state: 'error',
        where: 'N/A w fiksurach: błąd zmiany kolejności to toast sprawdzany ręcznie w podglądzie (DevTools offline + przeciągnięcie w prawdziwym /budget).',
    },
    {
        state: 'empty',
        where: 'Sekcje „Pusta lista” (brak kategorii), „Brak budżetu” oraz „14 kategorii” z frazą bez trafień.',
    },
    {
        state: 'loading',
        where: 'N/A dla listy: dane przychodzą z serwera razem ze stroną.',
    },
];
</script>

<template>
    <Head title="Kitchen sink: Budżet" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:gap-10">
        <header class="flex flex-col gap-1">
            <h1 class="page-title">Kitchen sink: Budżet</h1>
            <p class="text-muted-foreground text-sm">
                Tylko środowisko lokalne. Prawdziwe komponenty widoku /budget z
                fiksurami (postęp roku {{ YEAR_ELAPSED_PERCENT }}%). Akcje
                wierszy nie działają, bo identyfikatory fiksur nie istnieją w
                bazie.
            </p>
        </header>

        <section class="flex flex-col gap-3" aria-labelledby="ks-matrix">
            <h2 id="ks-matrix" class="section-title">Macierz stanów</h2>
            <ul class="flex flex-col gap-2 text-sm">
                <li v-for="entry in stateMatrix" :key="entry.state">
                    <span class="font-medium">{{ entry.state }}:</span>
                    <span class="text-muted-foreground">
                        {{ entry.where }}
                    </span>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-draft">
            <h2 id="ks-draft" class="section-title">
                Lista w szkicu (kolumna „Typ”)
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
                :year-elapsed-percent="null"
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
                :year-elapsed-percent="null"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-income">
            <h2 id="ks-income" class="section-title">
                Realizacja dochodów (dochód ponad plan, kreska roku, plan
                zwiększony „Premia” i zmniejszony „Zwrot podatku”)
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
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-expense">
            <h2 id="ks-expense" class="section-title">
                Realizacja wydatków (przekroczenie, plan zwiększony „Jedzenie” i
                zmniejszony „Rozrywka”, bez zmiany „Czynsz”, równomierna z
                kreską, jednorazowa „Remont łazienki” bez kreski)
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
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-archived">
            <h2 id="ks-archived" class="section-title">
                Kategorie zarchiwizowane (rozwinięte archiwum po kliknięciu;
                „Siłownia” ze zmianą planu, strzałka wyciszona)
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
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-empty">
            <h2 id="ks-empty" class="section-title">
                Pusta lista (brak kategorii)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="[]"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="emptyTotals"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :year-elapsed-percent="null"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-many">
            <h2 id="ks-many" class="section-title">
                14 kategorii (widoczny filtr nazw)
            </h2>
            <p class="text-muted-foreground text-sm">
                Wpisz „zzz”, aby zobaczyć komunikat braku wyników; po
                wyczyszczeniu wiersze wracają.
            </p>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="manyCategories"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="totalsOf(manyCategories, true)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :year-elapsed-percent="null"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-noreorder">
            <h2 id="ks-noreorder" class="section-title">
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
                :year-elapsed-percent="null"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-strips">
            <h2 id="ks-strips" class="section-title">
                Panel miar (4 miary w realizacji, 3 w szkicu)
            </h2>
            <BudgetPlanSummaryStrip
                :income-totals="totalsOf(executionIncome, false)"
                :expense-totals="totalsOf(executionExpense, false)"
                :planned-balance-minor-units="125000"
                :expense-percent-complete="
                    totalsOf(executionExpense, false).percent_complete
                "
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
            />
            <BudgetPlanSummaryStrip
                :income-totals="totalsOf(draftIncome, true)"
                :expense-totals="totalsOf(draftExpense, true)"
                :planned-balance-minor-units="-35000"
                :expense-percent-complete="null"
                :year-elapsed-percent="null"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-focus">
            <h2 id="ks-focus" class="section-title">Fokus</h2>
            <p class="text-muted-foreground text-sm">
                Przejdź Tab-em przez listy powyżej i przełączniki poniżej: każda
                kontrolka ma pierścień ring i dostępną nazwę.
            </p>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-toggle">
            <h2 id="ks-toggle" class="section-title">
                Przełącznik „Pokaż zarchiwizowane” (wł. i wył.)
            </h2>
            <div class="flex items-center gap-2">
                <Switch id="ks-show-archived-on" v-model="showArchivedOn" />
                <Label
                    for="ks-show-archived-on"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }} (włączony)
                </Label>
            </div>
            <div class="flex items-center gap-2">
                <Switch id="ks-show-archived-off" v-model="showArchivedOff" />
                <Label
                    for="ks-show-archived-off"
                    class="text-muted-foreground text-sm"
                >
                    {{ t.budget.show_archived }} (wyłączony)
                </Label>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-no-budget">
            <h2 id="ks-no-budget" class="section-title">Brak budżetu</h2>
            <BudgetEmptyState v-model:open="createBudgetOpen" />
        </section>
    </div>
</template>
