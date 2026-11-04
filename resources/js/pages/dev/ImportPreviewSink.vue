<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
import ImportPreviewSubmitBar from '@/components/ImportPreviewSubmitBar.vue';
import ListSurface from '@/components/ListSurface.vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    expenseCategories,
    importAccount,
    importPreviewRows,
    importPreviewSelections,
    incomeCategories,
    otherAccounts,
    sampleMonthRemainingWarning,
    sampleToSpendWarning,
    termDeposits,
} from '@/lib/import-preview-fixtures';
import { consumedFirstLegKeys } from '@/lib/import-pair-selection';
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

const selections = ref<Record<number, RowSelection>>(importPreviewSelections());
</script>

<template>
    <Head title="Kitchen sink: Podgląd importu" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:gap-8">
        <h1 class="text-xl font-semibold">Kitchen sink: Podgląd importu</h1>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Default</h2>

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
                        v-for="row in importPreviewRows"
                        :key="row.index"
                        :row="row"
                        v-model:selection="selections[row.index]"
                        :account-balance-minor-units="
                            row.index === 3
                                ? 50_000
                                : importAccount.current_balance_minor_units
                        "
                        :income-categories="incomeCategories"
                        :expense-categories="expenseCategories"
                        :other-accounts="otherAccounts"
                        :term-deposits="termDeposits"
                        :consumed-first-leg-keys="
                            consumedFirstLegKeys(selections)
                        "
                        :month-remaining-warnings="
                            row.index === 1
                                ? sampleMonthRemainingWarning
                                : undefined
                        "
                        :to-spend-warning="
                            row.index === 1 ? sampleToSpendWarning : null
                        "
                    />
                </div>
            </ListSurface>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Pasek zatwierdzenia</h2>
            <p class="text-muted-foreground text-xs">
                Bez powodu i dla każdego powodu blokady. Wiersze powyżej
                pokazują ostrzeżenia to-spend (wiersz 2), month-remaining
                (wiersz 2) i ujemne saldo (przelew własny, wiersz 4).
            </p>

            <div
                v-for="state in submitBarStates"
                :key="state.key"
                class="flex flex-col gap-1"
            >
                <h3 class="text-sm font-medium">{{ state.label }}</h3>
                <ImportPreviewSubmitBar
                    :reason="state.reason"
                    :processing="false"
                />
            </div>
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
