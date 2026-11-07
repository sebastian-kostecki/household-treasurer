<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import ImportPreviewDuplicateAcknowledgement from '@/components/ImportPreviewDuplicateAcknowledgement.vue';
import ImportPreviewRow from '@/components/ImportPreviewRow.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ImportPreviewSubmitBar from '@/components/ImportPreviewSubmitBar.vue';
import InputError from '@/components/InputError.vue';
import ListSurface from '@/components/ListSurface.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import {
    monthHasPlan,
    type EntryToSpendInputs,
} from '@/lib/entry-to-spend-warning';
import {
    duplicateAcknowledgementErrorText,
    firstRowsError,
} from '@/lib/import-preview-errors';
import { importSubmitBlockReason } from '@/lib/import-submit-block';
import { computeImportToSpendWarnings } from '@/lib/import-to-spend-warnings';
import {
    consumedFirstLegKeys,
    showsPairCandidateBadge,
    syncPairSelection,
} from '@/lib/import-pair-selection';
import {
    computeImportMonthRemainingWarnings,
    type MonthRemainingContext,
} from '@/lib/import-month-remaining-warnings';
import {
    emptySelection,
    initialSelection,
    type CategoryRow,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from '@/lib/import-preview-rows';
import { operations as operationsRoute } from '@/routes';

type OtherAccountRow = AccountPickerOption & {
    current_balance_minor_units: number;
};

const {
    account,
    rows,
    incomeCategories,
    expenseCategories,
    otherAccounts,
    termDeposits,
    monthRemainingContext,
    entryToSpend,
} = defineProps<{
    account: AccountPickerOption & { current_balance_minor_units: number };
    rows: ImportRow[];
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
    otherAccounts: OtherAccountRow[];
    termDeposits: TermDepositRow[];
    monthRemainingContext: MonthRemainingContext;
    entryToSpend: EntryToSpendInputs;
}>();

const t = useTranslations();
const duplicateWarningAcknowledged = ref(false);
const selections = ref<Record<number, RowSelection>>(
    Object.fromEntries(
        rows.map((row) => [
            row.index,
            initialSelection(row, incomeCategories, expenseCategories),
        ]),
    ),
);

const validRowCount = computed(() => rows.filter((row) => row.valid).length);
const invalidRowCount = computed(() => rows.filter((row) => !row.valid).length);
const duplicateRowCount = computed(
    () => rows.filter((row) => row.valid && row.import_duplicate).length,
);
const pairRowCount = computed(
    () =>
        rows.filter(
            (row) =>
                row.valid &&
                showsPairCandidateBadge(
                    row.import_pair_candidate,
                    row.pair_candidates,
                    consumedFirstLegKeysSet.value,
                    selections.value[row.index] ?? emptySelection(row),
                ),
        ).length,
);
const consumedFirstLegKeysSet = computed(() =>
    consumedFirstLegKeys(selections.value),
);
const selectedRowCount = computed(
    () =>
        Object.values(selections.value).filter(
            (selection) => selection.selected,
        ).length,
);
const selectedImportDuplicateCount = computed(
    () =>
        rows.filter(
            (row) =>
                row.import_duplicate &&
                selections.value[row.index]?.selected === true,
        ).length,
);
const requiresDuplicateAcknowledgement = computed(
    () => selectedImportDuplicateCount.value > 0,
);
watch(requiresDuplicateAcknowledgement, (required) => {
    if (!required) {
        duplicateWarningAcknowledged.value = false;
    }
});
const blockReason = computed(() => {
    const reason = importSubmitBlockReason(
        rows,
        selections.value,
        termDeposits,
        duplicateWarningAcknowledged.value,
    );

    if (reason === null) {
        return null;
    }

    const text = t.operations[reason.key];

    return reason.key === 'import_block_incomplete_rows'
        ? text.replace(':count', String(reason.count))
        : text;
});

const monthNameTranslations = computed(() =>
    Object.fromEntries(
        Array.from({ length: 12 }, (_, index) => {
            const month = index + 1;

            return [
                `month_name_${month}`,
                t.budget[`month_name_${month}` as keyof typeof t.budget],
            ];
        }),
    ),
);

const monthRemainingWarnings = computed(() =>
    computeImportMonthRemainingWarnings(
        rows,
        selections.value,
        expenseCategories,
        monthRemainingContext,
        monthNameTranslations.value,
    ),
);

const toSpendWarnings = computed(() =>
    computeImportToSpendWarnings(rows, selections.value, entryToSpend),
);

function refreshPairSelections(): void {
    for (const row of rows) {
        const selection = selections.value[row.index];

        if (selection === undefined) {
            continue;
        }

        syncPairSelection(
            row.pair_candidates,
            selection,
            consumedFirstLegKeysSet.value,
        );
    }
}

watch(consumedFirstLegKeysSet, () => {
    refreshPairSelections();
});
</script>

<template>
    <Head :title="t.operations.import_title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="page-title">
                    {{ t.operations.import_title }}
                </h1>
                <p class="meta text-muted-foreground">
                    {{ t.operations.import_preview_description }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="operationsRoute()">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    {{ t.operations.import_back }}
                </Link>
            </Button>
        </div>

        <ListSurface v-if="rows.length === 0">
            <IndexEmptyState
                :title="t.operations.import_preview_empty"
                :description="t.operations.import_preview_empty_description"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="CsvImportController.create.url()">
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            {{ t.operations.import_choose_other_file }}
                        </Link>
                    </Button>
                </template>
            </IndexEmptyState>
        </ListSurface>

        <template v-else>
            <ListSurface class="gap-4">
                <template #header>
                    <div class="space-y-1">
                        <h2 class="section-title">
                            {{ t.operations.import_summary_title }}
                        </h2>
                        <p class="meta text-muted-foreground">
                            {{
                                t.operations.import_summary_description.replace(
                                    ':account',
                                    account.name,
                                )
                            }}
                        </p>
                    </div>
                </template>

                <div class="flex flex-wrap gap-2">
                    <Badge variant="neutral">
                        {{
                            t.operations.import_valid_rows.replace(
                                ':count',
                                String(validRowCount),
                            )
                        }}
                    </Badge>
                    <Badge
                        v-if="invalidRowCount > 0"
                        variant="destructive-soft"
                    >
                        {{
                            t.operations.import_invalid_rows.replace(
                                ':count',
                                String(invalidRowCount),
                            )
                        }}
                    </Badge>
                    <Badge v-if="duplicateRowCount > 0" variant="outline">
                        {{
                            t.operations.import_duplicate_rows.replace(
                                ':count',
                                String(duplicateRowCount),
                            )
                        }}
                    </Badge>
                    <Badge v-if="pairRowCount > 0" variant="outline">
                        {{
                            t.operations.import_pair_rows.replace(
                                ':count',
                                String(pairRowCount),
                            )
                        }}
                    </Badge>
                    <p class="meta text-muted-foreground w-full">
                        {{ t.operations.import_duplicate_warning_body }}
                    </p>
                </div>
            </ListSurface>

            <Alert v-if="duplicateRowCount > 0" variant="warning">
                <AlertTriangle class="size-4" />
                <AlertTitle>{{
                    t.operations.import_duplicate_warning_title
                }}</AlertTitle>
                <AlertDescription>
                    {{
                        t.operations.import_duplicate_warning_found.replace(
                            ':count',
                            String(duplicateRowCount),
                        )
                    }}
                </AlertDescription>
            </Alert>

            <Alert v-if="pairRowCount > 0" variant="warning">
                <AlertTriangle class="size-4" />
                <AlertTitle>{{
                    t.operations.import_pair_warning_title
                }}</AlertTitle>
                <AlertDescription>
                    {{ t.operations.import_pair_warning_body }}
                </AlertDescription>
            </Alert>

            <Form
                v-bind="CsvImportController.store.form()"
                v-slot="{ errors, processing }"
                class="space-y-4"
            >
                <ListSurface flush>
                    <div class="overflow-x-auto">
                        <div
                            class="import-preview-grid import-preview-header-row"
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
                            v-for="row in rows"
                            :key="row.index"
                            :row="row"
                            v-model:selection="selections[row.index]"
                            :account-balance-minor-units="
                                account.current_balance_minor_units
                            "
                            :income-categories="incomeCategories"
                            :expense-categories="expenseCategories"
                            :other-accounts="otherAccounts"
                            :term-deposits="termDeposits"
                            :consumed-first-leg-keys="consumedFirstLegKeysSet"
                            :month-remaining-warnings="
                                monthHasPlan(
                                    entryToSpend,
                                    row.operation_date ?? '',
                                )
                                    ? undefined
                                    : monthRemainingWarnings[row.index]
                            "
                            :to-spend-warning="toSpendWarnings[row.index]"
                        />
                    </div>
                    <template #footer>
                        <p class="meta text-muted-foreground">
                            {{ t.common.amounts_in_zl }}
                        </p>
                    </template>
                </ListSurface>

                <ImportPreviewDuplicateAcknowledgement
                    v-if="requiresDuplicateAcknowledgement"
                    v-model="duplicateWarningAcknowledged"
                    :error="
                        duplicateAcknowledgementErrorText(
                            errors.duplicate_warning_acknowledged,
                            t.operations,
                        )
                    "
                />
                <InputError :message="firstRowsError(errors)" />

                <ImportPreviewSubmitBar
                    :reason="blockReason"
                    :processing="processing"
                />
            </Form>
        </template>
    </div>
</template>
