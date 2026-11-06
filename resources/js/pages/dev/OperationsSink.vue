<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import OperationsListPanel from '@/components/OperationsListPanel.vue';
import OperationsPageActions from '@/components/OperationsPageActions.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    accountFilterFixture,
    capitalMovementFixtures,
    emptyPaginator,
    filterAccountsFixture,
    filterCategoriesFixture,
    filterTermDepositsFixture,
    financialOperationFixtures,
    operationDaySumsFixture,
    ownTransferFixtures,
    paginate,
    searchFiltersFixture,
    sectionTotalsFixture,
} from '@/lib/operations-fixtures';
import { EMPTY_OPERATIONS_FILTERS } from '@/lib/operations-list-query';

const t = useTranslations();

const actionStates = [
    { label: '2 konta, 1 lokata', accountsCount: 2, termDepositsCount: 1 },
    { label: '1 konto, 1 lokata', accountsCount: 1, termDepositsCount: 1 },
    { label: '2 konta, 0 lokat', accountsCount: 2, termDepositsCount: 0 },
    { label: '0 kont, 0 lokat', accountsCount: 0, termDepositsCount: 0 },
];

const noSuppressedSections: OperationsListSection[] = [];

const base = {
    section: 'financial_operations' as OperationsListSection,
    hasAnyRecords: true,
    operations: paginate(financialOperationFixtures),
    ownTransfers: paginate(ownTransferFixtures),
    capitalMovements: paginate(capitalMovementFixtures),
    operationDaySums: operationDaySumsFixture,
    sectionTotals: sectionTotalsFixture,
    filters: EMPTY_OPERATIONS_FILTERS,
    filtersActive: false,
    suppressedSections: noSuppressedSections,
    filterYears: [2026, 2025],
    filterCategories: filterCategoriesFixture,
    filterAccounts: filterAccountsFixture,
    filterTermDeposits: filterTermDepositsFixture,
};

const firstPage = {
    ...base,
    operations: paginate(financialOperationFixtures, 1, 3),
};

const filteredEmpty = {
    ...base,
    filters: searchFiltersFixture,
    filtersActive: true,
    operations: emptyPaginator,
};

const accountFiltered = {
    ...base,
    filters: accountFilterFixture,
    filtersActive: true,
};

const suppressedSection = {
    ...base,
    section: 'own_transfers' as OperationsListSection,
    filters: searchFiltersFixture,
    filtersActive: true,
    suppressedSections: [
        'own_transfers',
        'capital_movements',
    ] as OperationsListSection[],
    operations: paginate(financialOperationFixtures.slice(2, 3), 1, 1),
    ownTransfers: emptyPaginator,
    capitalMovements: emptyPaginator,
};

const emptySection = {
    ...base,
    section: 'capital_movements' as OperationsListSection,
    sectionTotals: { ...sectionTotalsFixture, capital_movements: 0 },
    capitalMovements: emptyPaginator,
};

const noRecords = {
    ...base,
    hasAnyRecords: false,
    operations: emptyPaginator,
    ownTransfers: emptyPaginator,
    capitalMovements: emptyPaginator,
    sectionTotals: {
        financial_operations: 0,
        own_transfers: 0,
        capital_movements: 0,
    },
};
</script>

<template>
    <Head title="Kitchen sink: Operacje" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8">
        <header class="space-y-1">
            <h1 class="page-title">Kitchen sink: Operacje</h1>
            <p class="meta text-muted-foreground">
                Strona deweloperska (tylko APP_ENV=local). Wszystko poniżej to
                prawdziwe komponenty widoku /operations z danych testowych.
            </p>
        </header>

        <section class="flex flex-col gap-3">
            <h2 class="section-title">Default</h2>
            <p class="meta text-muted-foreground">
                Trzy zakładki z licznikami; zakładki przełączają panel lokalnie
                (w widoku przechodzą na ?section=).
            </p>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <h3 class="page-title">{{ t.operations.title }}</h3>
                    <p class="meta text-muted-foreground">
                        {{ t.operations.list_description }}
                    </p>
                </div>
                <OperationsPageActions
                    :accounts-count="2"
                    :term-deposits-count="1"
                />
            </div>
            <OperationsListPanel v-bind="base" />
            <OperationsListPanel
                v-bind="{ ...base, section: 'own_transfers' }"
            />
            <OperationsListPanel
                v-bind="{ ...base, section: 'capital_movements' }"
            />
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="section-title">Wiersz tytułu</h2>
            <p class="meta text-muted-foreground">
                Jedna akcja główna „Dodaj operację” z menu; otwórz menu, by
                zobaczyć pozycje i powody wyłączenia.
            </p>
            <div
                v-for="state in actionStates"
                :key="state.label"
                class="flex flex-col gap-2"
            >
                <p class="meta text-muted-foreground">{{ state.label }}</p>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="page-title">{{ t.operations.title }}</h3>
                    <OperationsPageActions
                        :accounts-count="state.accountsCount"
                        :term-deposits-count="state.termDepositsCount"
                    />
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="section-title">Stany z macierzy</h2>

            <h3 class="section-title">Hover</h3>
            <p class="meta text-muted-foreground">
                Rytm, nie afordancja: tło wiersza zmienia się tylko po to, żeby
                prowadzić oko wzdłuż linii. Wiersz nie jest klikalny.
            </p>
            <OperationsListPanel v-bind="base" />

            <h3 class="section-title">Focus-visible</h3>
            <p class="meta text-muted-foreground">
                Przejdź Tabem: zakładka, przycisk „Dodaj operację”, pozycja menu
                (po otwarciu), pigułka filtra i wiersz z opisem z banku (dymek)
                dostają pierścień.
            </p>
            <div class="flex justify-end">
                <OperationsPageActions
                    :accounts-count="2"
                    :term-deposits-count="1"
                />
            </div>
            <OperationsListPanel
                v-bind="{
                    ...base,
                    filters: searchFiltersFixture,
                    filtersActive: true,
                }"
            />

            <h3 class="section-title">Aktywny filtr konta</h3>
            <p class="meta text-muted-foreground">
                Chip z awatarem konta (xs) i filtr konta jako reka Select z
                awatarami, „Wszystkie konta” i dopiskiem „(zarchiwizowane)”.
                Wiersze: konto z logo, gotówka („zł”), bank bez logo (skrót),
                zwrot z kontem w linii meta.
            </p>
            <OperationsListPanel v-bind="accountFiltered" />

            <h3 class="section-title">Disabled</h3>
            <p class="meta text-muted-foreground">
                Pozycje „Transfer własny” i „Ruch kapitału” (1 konto, 0 lokat)
                po otwarciu menu mają powód jako tekst w pozycji; link paginacji
                na pierwszej stronie jest wyłączony (span z aria-disabled, poza
                Tabem).
            </p>
            <div class="flex justify-end">
                <OperationsPageActions
                    :accounts-count="1"
                    :term-deposits-count="0"
                />
            </div>
            <OperationsListPanel v-bind="firstPage" />

            <h3 class="section-title">Error</h3>
            <p class="meta text-muted-foreground">
                N/A: lista nie ma pól formularza ani walidacji; błąd serwera
                pokazuje modal Inertia.
            </p>

            <h3 class="section-title">Empty</h3>
            <p class="meta text-muted-foreground">
                Brak rekordów, pusty wynik filtrów, sekcja wyciszona filtrem
                (licznik 0 i notka) oraz sekcja bez rekordów.
            </p>
            <OperationsListPanel v-bind="noRecords" />
            <OperationsListPanel v-bind="filteredEmpty" />
            <OperationsListPanel v-bind="suppressedSection" />
            <OperationsListPanel v-bind="emptySection" />

            <h3 class="section-title">Loading</h3>
            <p class="meta text-muted-foreground">
                Panel w czasie wizyty (aria-busy="true", wyciszony).
            </p>
            <OperationsListPanel v-bind="{ ...base, busy: true }" />
        </section>
    </div>
</template>
