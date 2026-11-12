<script setup lang="ts">
import { AlertTriangle, Check, Pencil } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import MonthPlanEditBar from '@/components/MonthPlanEditBar.vue';
import MonthPlanStatPanel from '@/components/MonthPlanStatPanel.vue';
import MonthPlanTable from '@/components/MonthPlanTable.vue';
import MonthPlanTitleRow from '@/components/MonthPlanTitleRow.vue';
import type {
    MonthPlanRowState,
    SetAsideDirection,
} from '@/components/MonthPlanTable.vue';
import MonthSummaryCard from '@/components/MonthSummaryCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/composables/useTranslations';
import { collectedAtEndMinorUnits } from '@/lib/collected-calculator';
import { minorUnitsToMoneyInput } from '@/lib/format';
import {
    isMonthItemOverPlan,
    monthItemDeviationMinorUnits,
} from '@/lib/month-item-deviation';
import { parsePlanAmountInput } from '@/lib/month-plan-amount';
import {
    changeModeAmountInputs,
    matchActualState,
} from '@/lib/month-plan-match-actual';
import {
    monthPlanGroupTree,
    unreadableGroupIds,
    withoutUnreadableGroups,
} from '@/lib/month-plan-groups';
import { monthItemStatus } from '@/lib/month-plan-status';
import {
    allMonthStates,
    approvedAheadReading,
    approvedNoPaceReading,
    approvedReading,
    buildMonthPlanReading,
    futureProposalReading,
    groupedReading,
    joinCandidateExpense,
    negativeDrawReading,
    paidBill,
    pastReading,
    pastSummary,
    runningExpense,
    yearTotals,
} from '@/lib/month-plan-fixtures';
import { budget as budgetRoute } from '@/routes';
import type {
    MonthPlanGroup,
    MonthPlanItem,
    MonthPlanReading,
} from '@/types/month-plan';

const t = useTranslations();

const noop = (): void => undefined;

function monthUrl(month: number): string {
    return `/dev/month-plan?month=${month}`;
}

const stateMatrix: { state: string; where: string }[] = [
    {
        state: 'default',
        where: 'Każda sekcja poniżej: wiersz tytułu z odznakami, pasek miesięcy z kropkami wszystkich stanów, panel miar (domyślny, ujemny, podgląd na żywo, końcowe, tempo w normie, wyprzedzające z notatką, bez procentu, przyszły miesiąc), tabela w odczycie i edycji, podsumowanie minionego miesiąca, pasek edycji i stany puste.',
    },
    {
        state: 'irregular',
        where: 'Wiersze nieregularnej (Serwis auta: zebrane 450,00 przed rachunkiem; Rekolekcje: rachunek pokryty, zebrane 50,00; Remont: nadwyżka 150,00 ze statusem „ponad plan”, zebrane 0,00; Naprawa dachu: „Poza planem”, stawka 0; przyszły miesiąc: prognoza zebranego) w tabelach odczytu, edycji, przyszłego i minionego miesiąca. W trybie zmian nieregularna startuje ze stawką i nigdy nie ma „Ustaw plan na wydatki”; zmiana stawki w polu od razu zmienia „zebrane”, odchylenie i status.',
    },
    {
        state: 'match-actual',
        where: 'Tabela „Tryb zmian: plan = wydatki”: znacznik „Plan podniesiony do wydatków” (Paliwo), przyciski „Ustaw plan na wydatki” (Zakupy dodatkowe, ręcznie obniżony plan Jedzenia) oraz wiersze bez kontrolki (dochody, wydatek poniżej planu). Kliknięcie przycisku wpisuje kwotę do pola i przenosi fokus na pole.',
    },
    {
        state: 'groups',
        where: 'Tabele „Grupy kategorii”: odczyt z grupami (Codzienne zwinięta, ale z „ponad plan” na wierszu grupy; kategoria bez grupy między grupami; Mieszkanie rozwinięta z wciętymi wierszami; zarchiwizowana na końcu bez grupy), edycja z błędnym polem w grupie (grupa zwinięta na starcie rozwija się sama, suma Planu „—”), grupa przychodów (bez „ponad plan”) i wygląd telefonu przy oknie 390 px (nazwa i status w pierwszej linii, sumy w drugiej). Przycisk grupy zwija i rozwija z klawiatury.',
    },
    {
        state: 'hover',
        where: 'Najedź na wiersze tabeli, miesiące w pasku, przyciski „Zatwierdź” i „Zmień plan”, przełącznik kierunku odkładania oraz wiersz zwijania równomiernych rocznych.',
    },
    {
        state: 'focus-visible',
        where: 'Sekcja „Fokus”: Tab przez pasek miesięcy, „Zmień plan”, pola `cell-input` w tabeli edycji, przełączniki i przyciski pasa edycji (na tle primary).',
    },
    {
        state: 'disabled',
        where: 'Sekcja „Wyłączone”: „Zatwierdź” z licznikiem błędnych kwot i „Zapisz zmiany” bez zmian. „Zmień plan” znika przy `!can_change`, a komórka planu nie ma stanu wyłączonego: N/A.',
    },
    {
        state: 'error',
        where: 'Tabela „Błędy” (błędna kwota, błąd klienta i serwera pola odkładania) oraz sekcja „Błędy w pasie edycji”.',
    },
    {
        state: 'empty',
        where: 'Sekcja „Stany puste” (brak budżetu, szkic, miesiąc bez planu; dwa pierwsze z akcją „Przejdź do budżetu”) i tabela „Pusta grupa”.',
    },
    {
        state: 'loading',
        where: 'Spinner na „Zatwierdź” i „Zapisz zmiany” (sekcja „Ładowanie”). Pasek miesięcy `aria-busy` wymaga nawigacji (kliknięcie miesiąca to prawdziwy GET), a strona dostaje propsy razem z nawigacją Inertia: N/A.',
    },
];

function splitRows(reading: MonthPlanReading): {
    incomeRows: MonthPlanItem[];
    expenseRows: MonthPlanItem[];
    idleRows: MonthPlanItem[];
} {
    return {
        incomeRows: reading.income_items,
        expenseRows: reading.expense_items.filter(
            (row) => !row.is_idle_even_annual,
        ),
        idleRows: reading.expense_items.filter(
            (row) => row.is_idle_even_annual,
        ),
    };
}

type TableConfig = {
    key: string;
    caption: string;
    editable: boolean;
    incomeRows: MonthPlanItem[];
    expenseRows: MonthPlanItem[];
    idleRows: MonthPlanItem[];
    incomeGroups: MonthPlanGroup[];
    expenseGroups: MonthPlanGroup[];
    collapsedGroupIds: number[];
    amountInputs: Record<number, string>;
    invalidIds: number[];
    yearStale: boolean;
    setAsideMinorUnits: number | null;
    showSetAside: boolean;
    flagged: boolean;
    serverError?: string;
    idleOpen: boolean;
    direction: SetAsideDirection;
    setAsideInput: string;
    setAsideDefault: { percent: number; incomeMinorUnits: number } | null;
};

function inputsFor(reading: MonthPlanReading): Record<number, string> {
    return Object.fromEntries(
        [...reading.income_items, ...reading.expense_items].map((row) => [
            row.category_id,
            minorUnitsToMoneyInput(row.plan_minor_units),
        ]),
    );
}

function tableConfig(
    key: string,
    caption: string,
    reading: MonthPlanReading,
    overrides: Partial<TableConfig> = {},
): TableConfig {
    const setAside = reading.set_aside_minor_units;

    return {
        key,
        caption,
        editable: false,
        ...splitRows(reading),
        incomeGroups: reading.income_groups,
        expenseGroups: reading.expense_groups,
        collapsedGroupIds: [],
        amountInputs: inputsFor(reading),
        invalidIds: [],
        yearStale: false,
        setAsideMinorUnits: setAside,
        showSetAside: reading.summary === null,
        flagged: false,
        idleOpen: false,
        direction: setAside < 0 ? 'draw' : 'set_aside',
        setAsideInput: minorUnitsToMoneyInput(Math.abs(setAside)),
        setAsideDefault: null,
        ...overrides,
    };
}

const tables = reactive<TableConfig[]>([
    tableConfig(
        'read',
        'Odczyt, bieżący zatwierdzony (linia „W roku”, kolumna „Zatwierdzony” ze strzałką, etykiety rytmów, „zostało X na Y mies.” w bieżącym miesiącu: X > 0, X = 0, X < 0, równomierne roczne bez planu i wydatków zwinięte)',
        approvedReading,
    ),
    tableConfig(
        'read-open',
        'Odczyt, równomierne roczne rozwinięte, odkładanie w kierunku „Dobieram”',
        negativeDrawReading,
        { idleOpen: true },
    ),
    tableConfig(
        'edit',
        'Edycja (pola `cell-input`, linia „W roku” nieaktualna, odkładanie z przełącznikiem)',
        approvedReading,
        { editable: true, yearStale: true, idleOpen: true },
    ),
    tableConfig(
        'edit-match-actual',
        'Tryb zmian: plan = wydatki (znacznik, przycisk, ręcznie obniżony plan, wiersze bez kontrolki)',
        approvedReading,
        {
            editable: true,
            amountInputs: {
                ...changeModeAmountInputs([
                    ...approvedReading.income_items,
                    ...approvedReading.expense_items,
                ]),
                [runningExpense.category_id]: minorUnitsToMoneyInput(50_000),
            },
        },
    ),
    tableConfig(
        'edit-join',
        'Wiersz dołączenia (kategoria listy bez pozycji w budżecie, plan 0; z kwotą > 0 dołącza do planu)',
        approvedReading,
        {
            editable: true,
            expenseRows: [
                ...splitRows(approvedReading).expenseRows,
                joinCandidateExpense,
            ],
            amountInputs: {
                ...inputsFor(approvedReading),
                [joinCandidateExpense.category_id]:
                    minorUnitsToMoneyInput(8_000),
            },
        },
    ),
    tableConfig(
        'edit-errors',
        'Błędy (błędna kwota, odkładanie nieczytelne z błędem serwera)',
        approvedReading,
        {
            editable: true,
            invalidIds: [runningExpense.category_id],
            flagged: true,
            setAsideMinorUnits: null,
            setAsideInput: 'abc',
            serverError: 'Kwota odkładania jest niepoprawna.',
            idleOpen: true,
        },
    ),
    tableConfig(
        'future-proposal',
        'Przyszły miesiąc, propozycja (bez linii „W roku”; „zostało X na 12 mies.” przy X > 0, X = 0 i X < 0; zwinięte równomierne roczne)',
        futureProposalReading,
        { editable: true },
    ),
    tableConfig(
        'proposal-default-percent',
        'Propozycja z domyślnym procentem (10 % planowanych przychodów, podstawa 9 000,00, link do Ustawień pod polem „odkładam”)',
        buildMonthPlanReading({ set_aside_minor_units: 90_000 }),
        {
            editable: true,
            setAsideDefault: { percent: 10, incomeMinorUnits: 900_000 },
        },
    ),
    tableConfig(
        'past',
        'Miniony miesiąc z podsumowaniem (bez wiersza odkładania)',
        pastReading,
    ),
    tableConfig(
        'groups-read',
        'Grupy kategorii, odczyt (Codzienne zwinięta z „ponad plan”, kategoria bez grupy między grupami, Mieszkanie rozwinięta, zarchiwizowana na końcu; Wynagrodzenia w przychodach bez „ponad plan”; przy oknie 390 px układ telefonu)',
        groupedReading,
        { collapsedGroupIds: [102] },
    ),
    tableConfig(
        'groups-edit-error',
        'Grupy kategorii, edycja z błędnym polem w grupie (Mieszkanie zwinięta na starcie rozwija się sama, suma Planu „—”)',
        groupedReading,
        {
            editable: true,
            collapsedGroupIds: [101],
            amountInputs: {
                ...inputsFor(groupedReading),
                [paidBill.category_id]: 'abc',
            },
            invalidIds: [paidBill.category_id],
        },
    ),
    tableConfig(
        'empty',
        'Pusta grupa (dochody i wydatki bez pozycji)',
        approvedReading,
        {
            incomeRows: [],
            expenseRows: [],
            idleRows: [],
        },
    ),
]);

/** Editable tables compute the reading from the field like the page does; others keep the server reading. */
function readingOf(
    config: TableConfig,
    row: MonthPlanItem,
    fieldAmount: number | null,
): { deviation: number | null; overPlan: boolean } {
    if (!config.editable) {
        return {
            deviation: row.deviation_minor_units,
            overPlan: row.is_over_plan,
        };
    }

    if (fieldAmount === null) {
        return { deviation: null, overPlan: false };
    }

    const item = {
        type: row.type,
        rhythm: row.rhythm,
        plan: fieldAmount,
        yearExecution: row.year_execution_minor_units,
        currentPlan: row.current_plan_minor_units,
        actual: row.actual_minor_units,
        hasPositiveAmount: row.has_positive_amount,
        collectedAtStart: row.collected?.start_minor_units ?? 0,
    };

    return {
        deviation: monthItemDeviationMinorUnits(item),
        overPlan: isMonthItemOverPlan(item),
    };
}

/** The Collected at the end of the month; an editable table follows the typed rate like the page does. */
function collectedOf(
    config: TableConfig,
    row: MonthPlanItem,
    fieldAmount: number | null,
): number | null {
    if (row.collected === null) {
        return null;
    }

    if (!config.editable || fieldAmount === null) {
        return config.editable ? null : row.collected.end_minor_units;
    }

    return collectedAtEndMinorUnits(
        row.collected.start_minor_units,
        fieldAmount,
        row.actual_minor_units,
    );
}

function stateOf(
    config: TableConfig,
): (row: MonthPlanItem) => MonthPlanRowState {
    return (row) => {
        const hasField = config.editable && (row.in_plan || !row.is_archived);
        const fieldAmount = hasField
            ? parsePlanAmountInput(config.amountInputs[row.category_id] ?? '')
            : row.plan_minor_units;
        const reading = readingOf(config, row, fieldAmount);

        return {
            planMinorUnits: fieldAmount,
            deviation: reading.deviation,
            overPlan: reading.overPlan,
            status: monthItemStatus(row, {
                overPlan: reading.overPlan,
                planMinorUnits: config.editable
                    ? fieldAmount
                    : row.plan_minor_units,
                isClosed: !config.showSetAside,
            }),
            collectedMinorUnits: collectedOf(config, row, fieldAmount),
            yearStale: config.yearStale,
            invalid: config.invalidIds.includes(row.category_id),
            matchActual: config.editable
                ? matchActualState(row, fieldAmount, hasField)
                : null,
        };
    };
}

/** Groups with an unreadable plan field, from the same trees the table draws (the page does the same). */
function unreadableIdsOf(config: TableConfig): number[] {
    if (!config.editable) {
        return [];
    }

    const state = stateOf(config);

    return unreadableGroupIds(
        monthPlanGroupTree(config.incomeGroups, config.incomeRows, state),
        monthPlanGroupTree(config.expenseGroups, config.expenseRows, state),
    );
}

// Like the page: a group with an unreadable field opens by itself.
watch(
    () => tables.map(unreadableIdsOf),
    (idsByTable) => {
        idsByTable.forEach((ids, index) => {
            tables[index].collapsedGroupIds = withoutUnreadableGroups(
                tables[index].collapsedGroupIds,
                ids,
            );
        });
    },
    { immediate: true },
);

const statPanels: {
    key: string;
    caption: string;
    toSpend: number;
    income: number;
    expense: number;
    label: 'live' | 'final' | 'default';
    reading: MonthPlanReading;
}[] = [
    {
        key: 'default',
        caption: 'Domyślny, tempo w normie',
        toSpend: 120_000,
        income: 500_000,
        expense: 380_000,
        label: 'default',
        reading: approvedReading,
    },
    {
        key: 'negative',
        caption: 'Ujemne Do wydania',
        toSpend: -45_000,
        income: 500_000,
        expense: 545_000,
        label: 'default',
        reading: approvedReading,
    },
    {
        key: 'live',
        caption: 'Podgląd na żywo (edycja)',
        toSpend: 95_000,
        income: 500_000,
        expense: 405_000,
        label: 'live',
        reading: approvedReading,
    },
    {
        key: 'final',
        caption: 'Końcowe (miniony miesiąc)',
        toSpend: 130_000,
        income: 520_000,
        expense: 390_000,
        label: 'final',
        reading: pastReading,
    },
    {
        key: 'ahead',
        caption: 'Tempo wyprzedzające, z notatką',
        toSpend: 120_000,
        income: 500_000,
        expense: 380_000,
        label: 'default',
        reading: approvedAheadReading,
    },
    {
        key: 'no-pace',
        caption: 'Bez planu bieżących (procent null)',
        toSpend: 120_000,
        income: 500_000,
        expense: 380_000,
        label: 'default',
        reading: approvedNoPaceReading,
    },
    {
        key: 'future',
        caption: 'Przyszły miesiąc (brak tempa)',
        toSpend: 120_000,
        income: 500_000,
        expense: 380_000,
        label: 'default',
        reading: futureProposalReading,
    },
];

function paceOf(reading: MonthPlanReading): {
    percent: number | null;
    isAhead: boolean;
    elapsedDay: number;
    daysInMonth: number;
} | null {
    const pace = reading.month_pace;

    return pace === null
        ? null
        : {
              percent: pace.percent,
              isAhead: pace.is_ahead,
              elapsedDay: pace.elapsed_day,
              daysInMonth: pace.days_in_month,
          };
}

function matchActualIn(config: TableConfig, categoryId: number): void {
    const row = [
        ...config.incomeRows,
        ...config.expenseRows,
        ...config.idleRows,
    ].find((item) => item.category_id === categoryId);

    if (row) {
        config.amountInputs[categoryId] = minorUnitsToMoneyInput(
            row.actual_minor_units,
        );
    }
}

const stripMonth = ref(11);
</script>

<template>
    <Head title="Kitchen sink: plan miesiąca" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:gap-10">
        <header class="flex flex-col gap-1">
            <h1 class="page-title">Kitchen sink: plan miesiąca</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko APP_ENV=local). Prawdziwe komponenty
                widoku /month-plan z fiksur
                (resources/js/lib/month-plan-fixtures.ts). Nic się nie zapisuje.
            </p>
        </header>

        <section class="flex flex-col gap-3" aria-labelledby="mp-matrix">
            <h2 id="mp-matrix" class="section-title">Macierz stanów</h2>
            <ul class="flex flex-col gap-2 text-sm">
                <li v-for="entry in stateMatrix" :key="entry.state">
                    <span class="font-medium">{{ entry.state }}:</span>
                    <span class="text-muted-foreground">
                        {{ entry.where }}
                    </span>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-title">
            <h2 id="mp-title" class="section-title">Wiersz tytułu</h2>
            <p class="meta text-muted-foreground">
                `MonthPlanTitleRow` w czterech stanach: propozycja z
                „Zatwierdź”, zatwierdzony z „Zmień plan”, miniony „Końcowy” i
                miesiąc bez planu (tytuł bez odznaki).
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <Badge variant="brand">{{ t.month_plan.state_approved }}</Badge>
                <Badge variant="warning">
                    {{ t.month_plan.state_proposal }}
                </Badge>
                <Badge variant="outline">{{ t.month_plan.state_final }}</Badge>
            </div>
            <MonthPlanTitleRow
                heading-level="h3"
                :title="
                    t.month_plan.title_with_month.replace(
                        ':month',
                        'listopad 2026',
                    )
                "
                :badge="{
                    variant: 'warning',
                    label: t.month_plan.state_proposal,
                }"
                :hint="t.month_plan.proposal_hint"
            >
                <template #actions>
                    <Button type="button" variant="brand">
                        <Check aria-hidden="true" />
                        {{ t.month_plan.approve }}
                    </Button>
                </template>
            </MonthPlanTitleRow>
            <MonthPlanTitleRow
                heading-level="h3"
                :title="
                    t.month_plan.title_with_month.replace(
                        ':month',
                        'listopad 2026',
                    )
                "
                :badge="{
                    variant: 'brand',
                    label: t.month_plan.state_approved,
                }"
                :hint="t.month_plan.approved_hint"
            >
                <template #actions>
                    <Button type="button" variant="outline">
                        <Pencil aria-hidden="true" />
                        {{ t.month_plan.change }}
                    </Button>
                </template>
            </MonthPlanTitleRow>
            <MonthPlanTitleRow
                heading-level="h3"
                :title="
                    t.month_plan.title_with_month.replace(
                        ':month',
                        'wrzesień 2026',
                    )
                "
                :badge="{ variant: 'outline', label: t.month_plan.state_final }"
                :hint="t.month_plan.approved_past_hint"
            />
            <MonthPlanTitleRow
                heading-level="h3"
                :title="
                    t.month_plan.title_with_month.replace(
                        ':month',
                        'czerwiec 2026',
                    )
                "
                :badge="null"
                :hint="null"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-strip">
            <h2 id="mp-strip" class="section-title">Pasek miesięcy</h2>
            <p class="meta text-muted-foreground">
                Kropki: styczeń bez planu, luty i marzec podsumowanie, kwiecień
                zatwierdzony, od maja propozycja. Kliknięcie miesiąca wykonuje
                prawdziwy GET i przeładowuje sink.
            </p>
            <BudgetMonthPicker
                :budget-year="2026"
                :viewed-month="stripMonth"
                :month-url="monthUrl"
                :month-states="allMonthStates"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-stats">
            <h2 id="mp-stats" class="section-title">Panel miar</h2>
            <div
                v-for="panel in statPanels"
                :key="panel.key"
                class="flex flex-col gap-2"
            >
                <p class="table-header">{{ panel.caption }}</p>
                <MonthPlanStatPanel
                    :to-spend-minor-units="panel.toSpend"
                    :income-minor-units="panel.income"
                    :expense-minor-units="panel.expense"
                    :to-spend-label="panel.label"
                    :pace="paceOf(panel.reading)"
                />
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-summary">
            <h2 id="mp-summary" class="section-title">
                Podsumowanie minionego miesiąca
            </h2>
            <MonthSummaryCard :summary="pastSummary" />
        </section>

        <section
            v-for="config in tables"
            :key="config.key"
            class="flex flex-col gap-3"
            :aria-labelledby="`mp-table-${config.key}`"
        >
            <h2 :id="`mp-table-${config.key}`" class="section-title">
                Tabela: {{ config.caption }}
            </h2>
            <MonthPlanTable
                v-model:idle-open="config.idleOpen"
                v-model:collapsed-group-ids="config.collapsedGroupIds"
                v-model:set-aside-direction="config.direction"
                v-model:set-aside-input="config.setAsideInput"
                :income-rows="config.incomeRows"
                :income-groups="config.incomeGroups"
                :expense-groups="config.expenseGroups"
                :expense-rows="config.expenseRows"
                :idle-rows="config.idleRows"
                :editable="config.editable"
                :amount-inputs="config.amountInputs"
                :row-state="stateOf(config)"
                :year-totals="yearTotals"
                :year-totals-stale="{
                    income: config.yearStale,
                    expense: config.yearStale,
                }"
                :set-aside-minor-units="config.setAsideMinorUnits"
                :show-set-aside="config.showSetAside"
                :set-aside-flagged="config.flagged"
                :set-aside-error="config.serverError"
                :set-aside-default="config.setAsideDefault"
                @update-amount="
                    (id: number, value: string) =>
                        (config.amountInputs[id] = value)
                "
                @amount-blur="noop"
                @match-actual="(id: number) => matchActualIn(config, id)"
                @set-aside-blur="noop"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-draw">
            <h2 id="mp-draw" class="section-title">
                Notatka o dobieraniu ponad płynność
            </h2>
            <Alert variant="note">
                <AlertTriangle aria-hidden="true" />
                <AlertDescription>
                    {{
                        t.month_plan.draw_warning
                            .replace(':draw', '6 000,00 zł')
                            .replace(':liquidity', '5 000,00 zł')
                    }}
                </AlertDescription>
            </Alert>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-editbar">
            <h2 id="mp-editbar" class="section-title">Pasek edycji</h2>
            <p class="meta text-muted-foreground">
                Pas jest przyklejony do dołu przewijanego pudełka, tak jak do
                dołu ekranu w widoku.
            </p>
            <div
                class="border-border-soft h-72 overflow-y-auto rounded-lg border p-3"
            >
                <div class="flex flex-col gap-3">
                    <p
                        v-for="n in 12"
                        :key="n"
                        class="text-muted-foreground text-sm"
                    >
                        Wiersz wypełniający {{ n }}
                    </p>
                    <MonthPlanEditBar
                        :processing="false"
                        :can-submit="true"
                        :invalid-count="0"
                    />
                    <p
                        v-for="n in 6"
                        :key="`after-${n}`"
                        class="text-muted-foreground text-sm"
                    >
                        Wiersz za paskiem {{ n }}
                    </p>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-focus">
            <h2 id="mp-focus" class="section-title">Fokus</h2>
            <p class="meta text-muted-foreground">
                Przejdź klawiszem Tab przez przyciski; focus-visible ma być
                widoczny także na pasie primary.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <Button type="button" variant="brand">
                    <Check aria-hidden="true" />
                    {{ t.month_plan.approve }}
                </Button>
                <Button type="button" variant="outline">
                    <Pencil aria-hidden="true" />
                    {{ t.month_plan.change }}
                </Button>
            </div>
            <MonthPlanEditBar :processing="false" :can-submit="true" />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-disabled">
            <h2 id="mp-disabled" class="section-title">Wyłączone</h2>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-destructive text-sm">
                    {{ t.month_plan.invalid_amounts.replace(':count', '2') }}
                </span>
                <Button type="button" variant="brand" disabled>
                    <Check aria-hidden="true" />
                    {{ t.month_plan.approve }}
                </Button>
            </div>
            <MonthPlanEditBar :processing="false" :can-submit="false" />
            <MonthPlanEditBar
                :processing="false"
                :can-submit="false"
                :invalid-count="2"
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-errors">
            <h2 id="mp-errors" class="section-title">Błędy w pasie edycji</h2>
            <MonthPlanEditBar
                :processing="false"
                :can-submit="false"
                :invalid-count="1"
                error="Dodaj co najmniej jedną pozycję planu."
            />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-loading">
            <h2 id="mp-loading" class="section-title">Ładowanie</h2>
            <div class="flex flex-wrap items-center gap-3">
                <Button type="button" variant="brand" disabled>
                    <Spinner />
                    {{ t.month_plan.approve }}
                </Button>
            </div>
            <MonthPlanEditBar :processing="true" :can-submit="true" />
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="mp-empty">
            <h2 id="mp-empty" class="section-title">Stany puste</h2>
            <IndexEmptyState
                :title="t.month_plan.no_budget_title"
                :description="t.month_plan.no_budget_description"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="budgetRoute()">
                            {{ t.month_plan.go_to_budget }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>
            <IndexEmptyState
                :title="t.month_plan.draft_title"
                :description="t.month_plan.draft_description"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="budgetRoute()">
                            {{ t.month_plan.go_to_budget }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>
            <IndexEmptyState
                :title="t.month_plan.none_title"
                :description="t.month_plan.none_description"
            />
        </section>
    </div>
</template>
