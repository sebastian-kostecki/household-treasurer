<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import BudgetCategoryList, {
    type BudgetCategoryGroup,
    type BudgetCategoryRow,
} from '@/components/BudgetCategoryList.vue';
import BudgetEmptyState from '@/components/BudgetEmptyState.vue';
import BudgetPlanSummaryStrip from '@/components/BudgetPlanSummaryStrip.vue';
import InputError from '@/components/InputError.vue';
import { Alert } from '@/components/ui/alert';
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
        group_id: null,
        sort_order: overrides.id,
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
        rhythm: overrides.type === 'expense' ? 'running' : null,
        collected_at_year_start: null,
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

function groupOf(
    id: number,
    name: string,
    sortOrder: number,
    rows: BudgetCategoryRow[],
    isDraft: boolean,
): BudgetCategoryGroup {
    const members = rows.filter(
        (category) => category.group_id === id && !category.is_archived,
    );

    return {
        id,
        name,
        sort_order: sortOrder,
        totals: totalsOf(members, isDraft),
        has_over_limit_category: members.some(
            (category) => category.type === 'expense' && category.is_over_limit,
        ),
    };
}

const draftIncome = [
    draftRow(101, 'Wynagrodzenie', 'income', 650000, 'banknote'),
    draftRow(102, 'Premia', 'income', 120000, 'coins'),
];
const draftExpense = [
    draftRow(111, 'Czynsz', 'expense', 250000, 'building', {
        rhythm: 'fixed_charge',
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
            rhythm: 'even_annual',
        },
    ),
    // Nieregularna (ADR-0066): bez kreski "Postęp roku".
    executionRow(216, 'Rekolekcje', 'expense', 120000, 120000, 45000, 'tag', {
        rhythm: 'irregular',
    }),
];

// Budżet z grupami (ADR-0069): "Opłaty stałe" (Czynsz, Prąd), Jedzenie bez
// grupy i "Zdrowie" (Apteka, Badania). Jedna sekwencja miejsc dla grup i kategorii.
const groupedDraftExpense = [
    draftRow(411, 'Czynsz', 'expense', 250000, 'building', {
        rhythm: 'fixed_charge',
        group_id: 41,
        sort_order: 1,
    }),
    draftRow(412, 'Prąd', 'expense', 40000, 'tag', {
        rhythm: 'fixed_charge',
        group_id: 41,
        sort_order: 2,
    }),
    draftRow(413, 'Jedzenie', 'expense', 180000, 'apple', { sort_order: 3 }),
    draftRow(414, 'Apteka', 'expense', 120000, 'tag', {
        group_id: 42,
        sort_order: 5,
    }),
    draftRow(415, 'Badania', 'expense', 80000, 'tag', {
        group_id: 42,
        sort_order: 6,
    }),
];
const groupedDraftExpenseGroups = [
    groupOf(41, 'Opłaty stałe', 0, groupedDraftExpense, true),
    groupOf(42, 'Zdrowie', 4, groupedDraftExpense, true),
];

// Zdrowie: Apteka 1 200 / 1 400 (ponad plan), Badania 800 / 300; grupa 2 000 /
// 1 700, pozostało +300, a mimo to znacznik "ponad plan". Identyfikatory grup
// są różne w każdej liście, bo id treści grupy trafia do DOM.
function groupedExecutionExpenseFixture(base: number): {
    rows: BudgetCategoryRow[];
    groups: BudgetCategoryGroup[];
} {
    const fixed = base + 1;
    const health = base + 2;
    const rows = [
        executionRow(
            base + 11,
            'Czynsz',
            'expense',
            250000,
            250000,
            250000,
            'building',
            {
                group_id: fixed,
                sort_order: 1,
            },
        ),
        executionRow(base + 12, 'Prąd', 'expense', 40000, 40000, 31000, 'tag', {
            group_id: fixed,
            sort_order: 2,
        }),
        executionRow(
            base + 13,
            'Jedzenie',
            'expense',
            180000,
            180000,
            90000,
            'apple',
            {
                sort_order: 3,
            },
        ),
        executionRow(
            base + 14,
            'Apteka',
            'expense',
            120000,
            120000,
            140000,
            'tag',
            {
                group_id: health,
                sort_order: 5,
            },
        ),
        executionRow(
            base + 15,
            'Badania',
            'expense',
            80000,
            80000,
            30000,
            'tag',
            {
                group_id: health,
                sort_order: 6,
            },
        ),
    ];

    return {
        rows,
        groups: [
            groupOf(fixed, 'Opłaty stałe', 0, rows, false),
            groupOf(health, 'Zdrowie', 4, rows, false),
        ],
    };
}

const groupedExecution = groupedExecutionExpenseFixture(500);
const collapsedExecution = groupedExecutionExpenseFixture(600);
const HEALTH_GROUP_COLLAPSED = 602;

const groupedExecutionIncome = [
    executionRow(
        711,
        'Wynagrodzenie',
        'income',
        650000,
        650000,
        650000,
        'tag',
        {
            group_id: 701,
            sort_order: 1,
        },
    ),
    executionRow(712, 'Premia', 'income', 100000, 120000, 135000, 'coins', {
        group_id: 701,
        sort_order: 2,
    }),
    executionRow(713, 'Zwrot podatku', 'income', 80000, 60000, 20000, 'tag', {
        sort_order: 3,
    }),
];
const groupedExecutionIncomeGroups = [
    groupOf(701, 'Praca', 0, groupedExecutionIncome, false),
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
        where: 'Grupy: sekcje „Grupy w szkicu”, „Grupy w realizacji”, „Grupa zwinięta” i „Grupa przychodów”; listy bez grup to pozostałe sekcje. Realizacja: wiersze w normie, dochód ponad 100%, przekroczenie, zarchiwizowany, stała opłata, bieżąca i równomierna roczna (każda z kreską roku). Szkic: kolumna „Typ”.',
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
                :groups="[]"
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
                :groups="[]"
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
                :groups="[]"
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
                zmniejszony „Rozrywka”, bez zmiany „Czynsz”, bieżąca z kreską,
                równomierna roczna „Remont łazienki” z kreską)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="executionExpense"
                :groups="[]"
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

        <section class="flex flex-col gap-3" aria-labelledby="ks-groups-draft">
            <h2 id="ks-groups-draft" class="section-title">
                Grupy w szkicu (wiersz grupy z kolumną planu: „Opłaty stałe”,
                „Zdrowie”; „Jedzenie” bez grupy; przeciągnij kategorię do grupy:
                prawdziwy PATCH)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="groupedDraftExpense"
                :groups="groupedDraftExpenseGroups"
                :budget-id="1"
                category-type="expense"
                phase="draft"
                :totals="totalsOf(groupedDraftExpense, true)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :year-elapsed-percent="null"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-groups-exec">
            <h2 id="ks-groups-exec" class="section-title">
                Grupy w realizacji (wiersz grupy: początkowy, aktualny,
                wykonanie, pozostało, miernik; „Zdrowie”: Apteka 1 200 / 1 400,
                Badania 800 / 300, razem 2 000 / 1 700, pozostało +300, mimo to
                „ponad plan”)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="groupedExecution.rows"
                :groups="groupedExecution.groups"
                :budget-id="1"
                category-type="expense"
                phase="execution"
                :totals="totalsOf(groupedExecution.rows, false)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
                @edit="noop"
            />
        </section>

        <section
            class="flex flex-col gap-3"
            aria-labelledby="ks-groups-collapsed"
        >
            <h2 id="ks-groups-collapsed" class="section-title">
                Grupa zwinięta („Zdrowie” zwinięte, znacznik „ponad plan” widać
                na wierszu grupy; przycisk z nazwą grupy rozwija i zwija)
            </h2>
            <BudgetCategoryList
                :title="t.budget.expense_table"
                :categories="collapsedExecution.rows"
                :groups="collapsedExecution.groups"
                :initial-collapsed-group-ids="[HEALTH_GROUP_COLLAPSED]"
                :budget-id="1"
                category-type="expense"
                phase="execution"
                :totals="totalsOf(collapsedExecution.rows, false)"
                :can-reorder="true"
                :empty-description="t.budget.empty"
                :year-elapsed-percent="YEAR_ELAPSED_PERCENT"
                @edit="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-groups-income">
            <h2 id="ks-groups-income" class="section-title">
                Grupa przychodów w realizacji („Praca”: bez znacznika „ponad
                plan”, nadwyżka dochodu nie jest przekroczeniem)
            </h2>
            <BudgetCategoryList
                :title="t.budget.income_table"
                :categories="groupedExecutionIncome"
                :groups="groupedExecutionIncomeGroups"
                :budget-id="1"
                category-type="income"
                phase="execution"
                :totals="totalsOf(groupedExecutionIncome, false)"
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
                :groups="[]"
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
                :groups="[]"
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
                :groups="[]"
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
                :groups="[]"
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

        <section class="flex flex-col gap-3" aria-labelledby="ks-messages">
            <h2 id="ks-messages" class="section-title">
                {{ t.budget.category_messages_title }}
            </h2>
            <Alert variant="ok" data-state="category-joined-existing">
                {{ t.budget.category_joined_existing }}
            </Alert>
            <InputError
                :message="t.budget.category_already_in_budget"
                data-state="category-already-in-budget"
            />
            <InputError
                :message="t.budget.category_in_archive_hint"
                data-state="category-in-archive"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="ks-no-budget">
            <h2 id="ks-no-budget" class="section-title">Brak budżetu</h2>
            <BudgetEmptyState v-model:open="createBudgetOpen" />
        </section>
    </div>
</template>
