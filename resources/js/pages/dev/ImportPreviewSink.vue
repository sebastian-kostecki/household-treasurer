<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
import ImportPreviewSubmitBar from '@/components/ImportPreviewSubmitBar.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    expenseCategories,
    importAccount,
    importPreviewRows,
    importPreviewSelections,
    incomeCategories,
    noTermDeposits,
    otherAccounts,
    sampleMonthRemainingWarning,
    sampleToSpendWarning,
    termDeposits,
} from '@/lib/import-preview-fixtures';
import { consumedFirstLegKeys } from '@/lib/import-pair-selection';
import { firstRowsError } from '@/lib/import-preview-errors';
import type { RowSelection } from '@/lib/import-preview-rows';

const t = useTranslations();

const contractRows = [
    { key: 'plain', label: 'Zwykły', rowClass: '' },
    {
        key: 'duplicate',
        label: 'Duplikat',
        rowClass: 'import-preview-row-duplicate',
    },
    { key: 'pair', label: 'Para', rowClass: 'import-preview-row-pair' },
];

const submitBarStates = computed(() => [
    { key: 'ready', label: 'Bez powodu', reason: null },
    {
        key: 'no-selection',
        label: 'Brak zaznaczenia',
        reason: t.operations.import_block_no_selection,
    },
    {
        key: 'incomplete',
        label: 'Niekompletne wiersze',
        reason: t.operations.import_block_incomplete_rows.replace(
            ':count',
            '2',
        ),
    },
    {
        key: 'duplicates',
        label: 'Niepotwierdzone duplikaty',
        reason: t.operations.import_block_duplicates_unacknowledged,
    },
]);

const matrixSections = [
    {
        key: 'default',
        title: 'Default',
        note: 'Dochód, wydatek, duplikat, para, wiersz niepoprawny, wiersz bez kwoty, ruch kapitału z odsetkami i transfer własny. Ostrzeżenia to-spend i month-remaining (wiersz 2) oraz ujemne saldo (transfer własny, wiersz 4).',
        indexes: [0, 1, 2, 3, 4, 5, 6],
        withWarnings: true,
        termDeposits,
    },
    {
        key: 'hover',
        title: 'Hover',
        note: 'Najechanie podświetla wiersz tłem foreground/3%. To rytm listy, nie afordancja: wiersz nie jest klikalny.',
        indexes: [0, 1, 2],
        withWarnings: false,
        termDeposits,
    },
    {
        key: 'focus',
        title: 'Focus-visible',
        note: 'Przejdź Tabem: checkbox, select typu, CategorySelect, AccountSelect, select lokaty i Input odsetek pokazują pierścień 3 px, a przycisk poniżej własny pierścień.',
        indexes: [1, 3, 6],
        withWarnings: false,
        termDeposits,
    },
    {
        key: 'disabled',
        title: 'Disabled',
        note: 'Wiersz niepoprawny ma wyłączony checkbox i widoczny powód. Ruch kapitału bez lokat ma wyłączony select lokaty.',
        indexes: [4, 6],
        withWarnings: false,
        termDeposits: noTermDeposits,
    },
];

const matrixSelections = ref<Record<string, Record<number, RowSelection>>>(
    Object.fromEntries(
        matrixSections.map((section) => {
            const sectionSelections = importPreviewSelections();

            if (section.key === 'disabled') {
                sectionSelections[6] = {
                    ...sectionSelections[6],
                    term_deposit_id: '',
                };
            }

            return [section.key, sectionSelections];
        }),
    ),
);

const sectionRows = (indexes: number[]) =>
    importPreviewRows.filter((row) => indexes.includes(row.index));

const sampleErrors = {
    rows: { rows: 'Zaznacz co najmniej jeden wiersz do importu.' },
    rowType: { 'rows.2.type': 'Wybierz typ zaznaczonego wiersza.' },
    duplicate: {
        duplicate_warning_acknowledged:
            'Potwierdź import zaznaczonych duplikatów.',
    },
};
</script>

<template>
    <Head title="Kitchen sink: Podgląd importu" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8">
        <h1 class="text-xl font-semibold">Kitchen sink: Podgląd importu</h1>

        <section
            v-for="section in matrixSections"
            :key="section.key"
            class="flex flex-col gap-3"
        >
            <h2 class="text-base font-semibold">{{ section.title }}</h2>
            <p class="text-muted-foreground text-xs">{{ section.note }}</p>

            <ListSurface>
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
                        v-for="row in sectionRows(section.indexes)"
                        :key="row.index"
                        :row="row"
                        v-model:selection="
                            matrixSelections[section.key][row.index]
                        "
                        :account-balance-minor-units="
                            row.index === 3
                                ? 50_000
                                : importAccount.current_balance_minor_units
                        "
                        :income-categories="incomeCategories"
                        :expense-categories="expenseCategories"
                        :other-accounts="otherAccounts"
                        :term-deposits="section.termDeposits"
                        :consumed-first-leg-keys="
                            consumedFirstLegKeys(matrixSelections[section.key])
                        "
                        :month-remaining-warnings="
                            section.withWarnings && row.index === 1
                                ? sampleMonthRemainingWarning
                                : undefined
                        "
                        :to-spend-warning="
                            section.withWarnings && row.index === 1
                                ? sampleToSpendWarning
                                : null
                        "
                    />
                </div>
            </ListSurface>

            <template v-if="section.key === 'focus'">
                <div class="flex justify-end">
                    <Button type="button">{{
                        t.operations.import_confirm
                    }}</Button>
                </div>
            </template>

            <template v-if="section.key === 'disabled'">
                <div
                    v-for="state in submitBarStates.slice(1)"
                    :key="state.key"
                    class="flex flex-col gap-1"
                >
                    <h3 class="text-sm font-medium">
                        Pasek: {{ state.label }}
                    </h3>
                    <ImportPreviewSubmitBar
                        :reason="state.reason"
                        :processing="false"
                    />
                </div>
            </template>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Error</h2>
            <p class="text-muted-foreground text-xs">
                Błędy serwera pod listą: `rows`, `rows.N.type` (przez
                `firstRowsError`) i `duplicate_warning_acknowledged`. Wiersz
                niepoprawny z powodem jest w sekcji Disabled i Default.
            </p>
            <InputError :message="firstRowsError(sampleErrors.rows)" />
            <InputError :message="firstRowsError(sampleErrors.rowType)" />
            <InputError
                :message="sampleErrors.duplicate.duplicate_warning_acknowledged"
            />
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Empty</h2>
            <p class="text-muted-foreground text-xs">
                Podgląd bez wierszy, tak jak w widoku.
            </p>
            <ListSurface>
                <IndexEmptyState
                    :title="t.operations.import_preview_empty"
                    :description="t.operations.import_preview_empty_description"
                >
                    <template #actions>
                        <Button variant="outline" as-child>
                            <span>
                                <ArrowLeft class="size-4" />
                                {{ t.operations.import_back }}
                            </span>
                        </Button>
                    </template>
                </IndexEmptyState>
            </ListSurface>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Loading</h2>
            <p class="text-muted-foreground text-xs">
                Pasek w trakcie zatwierdzania: spinner w przycisku, przycisk
                wyłączony.
            </p>
            <ImportPreviewSubmitBar :reason="null" :processing="true" />
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Kontrakt</h2>
            <p class="text-muted-foreground text-xs">
                Statyczne wiersze na klasach `import-preview-header-row`,
                `import-preview-row`, `-duplicate` i `-pair`. Nagłówek jest
                ukryty poniżej `xl`.
            </p>

            <ListSurface>
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

                    <div
                        v-for="contractRow in contractRows"
                        :key="contractRow.key"
                        class="import-preview-row"
                        :class="contractRow.rowClass"
                    >
                        <div class="import-preview-grid">
                            <div class="import-preview-cell-select">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.import_select }}
                                </span>
                            </div>
                            <div class="import-preview-cell-date">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.date }}
                                </span>
                                <p class="text-sm font-medium">2026-10-12</p>
                            </div>
                            <div class="import-preview-cell-description">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.description }}
                                </span>
                                <p class="text-sm font-medium">
                                    {{ contractRow.label }}
                                </p>
                            </div>
                            <div class="import-preview-cell-amount">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.amount }}
                                </span>
                                <p class="text-sm">-125,00</p>
                            </div>
                            <div class="import-preview-cell-type">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.type }}
                                </span>
                            </div>
                            <div class="import-preview-cell-classification">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.import_classification }}
                                </span>
                            </div>
                            <div class="import-preview-cell-status">
                                <span class="column-caption xl:sr-only">
                                    {{ t.operations.import_status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </ListSurface>
        </section>
    </div>
</template>
