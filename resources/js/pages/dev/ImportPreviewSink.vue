<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
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
                    <div
                        class="import-preview-grid border-border/60 text-muted-foreground hidden border-b pb-2.5 text-[11px] font-medium tracking-wider uppercase xl:grid"
                    >
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
                            importAccount.current_balance_minor_units
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
    </div>
</template>
