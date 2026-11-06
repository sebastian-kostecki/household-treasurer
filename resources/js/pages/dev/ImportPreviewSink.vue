<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import ImportPreviewDuplicateAcknowledgement from '@/components/ImportPreviewDuplicateAcknowledgement.vue';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
import ImportPreviewSubmitBar from '@/components/ImportPreviewSubmitBar.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { consumedFirstLegKeys } from '@/lib/import-pair-selection';
import { firstRowsError } from '@/lib/import-preview-errors';
import {
    expenseCategories,
    importAccount,
    importPreviewEntryToSpend,
    importPreviewMonthRemainingContext,
    importPreviewRows,
    importPreviewSelections,
    incomeCategories,
    noTermDeposits,
    otherAccounts,
    sampleMonthRemainingWarning,
    sampleToSpendWarning,
    termDeposits,
} from '@/lib/import-preview-fixtures';
import type { ImportRow, RowSelection } from '@/lib/import-preview-rows';

const OperationsImportPreview = defineAsyncComponent(
    () => import('@/pages/OperationsImportPreview.vue'),
);

const t = useTranslations();

const pageProps = {
    account: importAccount,
    incomeCategories,
    expenseCategories,
    otherAccounts,
    termDeposits,
    monthRemainingContext: importPreviewMonthRemainingContext,
    entryToSpend: importPreviewEntryToSpend,
};

const acknowledgementStates = [
    {
        key: 'unchecked',
        title: 'Potwierdzenie duplikatów: odznaczone',
        checked: false,
        error: undefined,
    },
    {
        key: 'checked',
        title: 'Potwierdzenie duplikatów: zaznaczone',
        checked: true,
        error: undefined,
    },
    {
        key: 'error',
        title: 'Potwierdzenie duplikatów: z błędem',
        checked: false,
        error: 'Potwierdź import zaznaczonych duplikatów.',
    },
];

const acknowledgementValues = ref<Record<string, boolean>>(
    Object.fromEntries(
        acknowledgementStates.map((state) => [state.key, state.checked]),
    ),
);

const submitBarStates = computed(() => [
    { key: 'ready', title: 'Pasek: gotowy', reason: null, processing: false },
    {
        key: 'blocked',
        title: 'Pasek: zablokowany z powodem',
        reason: t.operations.import_block_incomplete_rows.replace(
            ':count',
            '2',
        ),
        processing: false,
    },
    {
        key: 'processing',
        title: 'Pasek: w trakcie zatwierdzania (loading)',
        reason: null,
        processing: true,
    },
]);

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusz page-with-rows i sekcja rows-default poniżej.',
    },
    {
        state: 'hover',
        where: 'Zrzut z działającej aplikacji: screenshots/preview-hover-1280-light.png.',
    },
    {
        state: 'focus-visible',
        where: 'Zrzut po Tab z działającej aplikacji: screenshots/preview-focus-visible.png.',
    },
    {
        state: 'disabled',
        where: 'Sekcja rows-disabled (wiersz niepoprawny, checkbox wyłączony) i scenariusz submit-bar-blocked.',
    },
    {
        state: 'error',
        where: 'Scenariusze duplicate-error i errors (firstRowsError); w aplikacji screenshots/preview-duplicate-error.png.',
    },
    {
        state: 'empty',
        where: 'Scenariusz page-empty: strona bez wierszy.',
    },
    {
        state: 'loading',
        where: 'Scenariusz submit-bar-processing: spinner w przycisku. Skeleton N/A, bo strona nie ma deferred props.',
    },
];

type RowSection = {
    key: string;
    title: string;
    note: string;
    offset: number;
    indexes: number[];
    withWarnings: boolean;
    termDeposits: typeof termDeposits;
};

/**
 * Each section mounts the same fixture rows as the full pages, so it shifts the
 * row index: ImportPreviewRow builds element ids from it and they must stay unique.
 */
const rowSections: RowSection[] = [
    {
        key: 'rows-default',
        title: 'Wiersze: default',
        note: 'Dochód, wydatek, duplikat, para (oba rodzaje), wiersz niepoprawny, wiersz bez kwoty, ruch kapitału z odsetkami, zwrot i wydatek z podpowiedzią kategorii. Ostrzeżenia to-spend i month-remaining przy wydatku, ujemne saldo przy transferze własnym.',
        offset: 100,
        indexes: [0, 1, 2, 3, 8, 4, 5, 6, 7, 9],
        withWarnings: true,
        termDeposits,
    },
    {
        key: 'rows-hover',
        title: 'Wiersze: hover',
        note: 'Najechanie podświetla wiersz tłem secondary. To rytm listy, nie afordancja: wiersz nie jest klikalny.',
        offset: 200,
        indexes: [0, 1, 2],
        withWarnings: false,
        termDeposits,
    },
    {
        key: 'rows-focus',
        title: 'Wiersze: focus-visible',
        note: 'Przejdź Tabem: checkbox, select typu, CategorySelect, AccountSelect, select lokaty i pole odsetek pokazują pierścień, a przycisk poniżej własny pierścień.',
        offset: 300,
        indexes: [1, 3, 6],
        withWarnings: false,
        termDeposits,
    },
    {
        key: 'rows-disabled',
        title: 'Wiersze: disabled',
        note: 'Wiersz niepoprawny ma wyłączony checkbox i widoczny powód. Ruch kapitału bez lokat ma wyłączony select lokaty.',
        offset: 400,
        indexes: [4, 6],
        withWarnings: false,
        termDeposits: noTermDeposits,
    },
];

const sectionSelections = ref<Record<string, Record<number, RowSelection>>>(
    Object.fromEntries(
        rowSections.map((section) => {
            const base = importPreviewSelections();
            const shifted: Record<number, RowSelection> = {};

            for (const [index, selection] of Object.entries(base)) {
                shifted[Number(index) + section.offset] = selection;
            }

            if (section.key === 'rows-disabled') {
                shifted[406] = { ...shifted[406], term_deposit_id: '' };
            }

            return [section.key, shifted];
        }),
    ),
);

function sectionRows(section: RowSection): ImportRow[] {
    return section.indexes.flatMap((index) => {
        const row = importPreviewRows.find((item) => item.index === index);

        return row === undefined
            ? []
            : [{ ...row, index: index + section.offset }];
    });
}

const sampleErrors = {
    rows: { rows: 'Zaznacz co najmniej jeden wiersz do importu.' },
    rowType: { 'rows.2.type': 'Wybierz typ zaznaczonego wiersza.' },
};
</script>

<template>
    <Head title="Kitchen sink: Podgląd importu" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Kitchen sink: Podgląd importu</h1>
            <p class="meta text-muted-foreground">
                Strona deweloperska (tylko środowisko local). Krok 2 importu na
                prawdziwej stronie i jej częściach. Fixture'y nie istnieją w
                bazie, więc wysłanie formularza nie zadziała.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2 class="section-title">Macierz stanów</h2>
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th class="table-header pb-2.5 text-left">Stan</th>
                        <th class="table-header pb-2.5 text-left">
                            Gdzie widoczny
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in stateMatrix"
                        :key="item.state"
                        class="border-t align-top"
                    >
                        <td class="py-2 pr-4 font-mono">{{ item.state }}</td>
                        <td class="py-2">{{ item.where }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="space-y-2" data-scenario="page-with-rows">
            <h2 class="section-title">Strona z wierszami</h2>
            <OperationsImportPreview
                v-bind="pageProps"
                :rows="importPreviewRows"
            />
        </section>

        <section class="space-y-2" data-scenario="page-empty">
            <h2 class="section-title">Strona bez wierszy (stan pusty)</h2>
            <OperationsImportPreview v-bind="pageProps" :rows="[]" />
        </section>

        <section
            v-for="state in acknowledgementStates"
            :key="state.key"
            :data-scenario="`duplicate-${state.key}`"
            class="space-y-2"
        >
            <h2 class="section-title">{{ state.title }}</h2>
            <ImportPreviewDuplicateAcknowledgement
                v-model="acknowledgementValues[state.key]"
                :error="state.error"
            />
        </section>

        <section
            v-for="state in submitBarStates"
            :key="state.key"
            :data-scenario="`submit-bar-${state.key}`"
            class="space-y-2"
        >
            <h2 class="section-title">{{ state.title }}</h2>
            <ImportPreviewSubmitBar
                :reason="state.reason"
                :processing="state.processing"
            />
        </section>

        <section class="space-y-2" data-scenario="errors">
            <h2 class="section-title">Błędy serwera pod listą</h2>
            <p class="meta text-muted-foreground">
                `rows` i `rows.N.type` przez `firstRowsError`.
            </p>
            <InputError :message="firstRowsError(sampleErrors.rows)" />
            <InputError :message="firstRowsError(sampleErrors.rowType)" />
        </section>

        <section
            v-for="section in rowSections"
            :key="section.key"
            :data-scenario="section.key"
            class="space-y-2"
        >
            <h2 class="section-title">{{ section.title }}</h2>
            <p class="meta text-muted-foreground">{{ section.note }}</p>

            <ListSurface flush>
                <div class="overflow-x-auto">
                    <div class="import-preview-grid import-preview-header-row">
                        <span class="import-preview-cell-select">
                            {{ t.operations.import_select }}
                        </span>
                        <span class="import-preview-cell-date">
                            {{ t.operations.date }}
                        </span>
                        <span class="import-preview-cell-description">
                            {{ t.operations.description }}
                        </span>
                        <span class="import-preview-cell-amount">
                            {{ t.operations.amount }}
                        </span>
                        <span class="import-preview-cell-type">
                            {{ t.operations.type }}
                        </span>
                        <span class="import-preview-cell-classification">
                            {{ t.operations.import_classification }}
                        </span>
                        <span class="import-preview-cell-status">
                            {{ t.operations.import_status }}
                        </span>
                    </div>

                    <ImportPreviewRow
                        v-for="row in sectionRows(section)"
                        :key="row.index"
                        :row="row"
                        v-model:selection="
                            sectionSelections[section.key][row.index]
                        "
                        :account-balance-minor-units="
                            row.index - section.offset === 3
                                ? 50_000
                                : importAccount.current_balance_minor_units
                        "
                        :income-categories="incomeCategories"
                        :expense-categories="expenseCategories"
                        :other-accounts="otherAccounts"
                        :term-deposits="section.termDeposits"
                        :consumed-first-leg-keys="
                            consumedFirstLegKeys(sectionSelections[section.key])
                        "
                        :month-remaining-warnings="
                            section.withWarnings &&
                            row.index - section.offset === 1
                                ? sampleMonthRemainingWarning
                                : undefined
                        "
                        :to-spend-warning="
                            section.withWarnings &&
                            row.index - section.offset === 1
                                ? sampleToSpendWarning
                                : null
                        "
                    />
                </div>
            </ListSurface>

            <div v-if="section.key === 'rows-focus'" class="flex justify-end">
                <Button type="button">{{ t.operations.import_confirm }}</Button>
            </div>
        </section>
    </div>
</template>
