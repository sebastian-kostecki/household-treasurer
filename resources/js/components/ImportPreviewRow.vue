<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import AccountSelect from '@/components/AccountSelect.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoney } from '@/lib/format';
import {
    applyPairCandidateSelection,
    availablePairCandidates,
    locksTypeToOwnTransfer,
    matchingPairCandidate,
    pairCandidateKey,
    pairRecordPickerCandidates,
    selectedPairCandidateKey,
    showsPairCandidateBadge,
    syncPairSelection,
    uniquePairCandidate,
    type PairCandidate,
} from '@/lib/import-pair-selection';
import type { ToSpendWarning } from '@/lib/entry-to-spend-warning';
import type { MonthRemainingWarningState } from '@/lib/import-month-remaining-warnings';
import {
    categoriesForRow,
    descriptionVariant,
    eligibleTermDeposits,
    importRowHasNotes,
    isCreditRow,
    parsedInterestAmountMinorUnits,
    rowCategoriesEmpty,
    wouldMakeAccountNegative,
    type CategoryRow,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from '@/lib/import-preview-rows';

const {
    row,
    accountBalanceMinorUnits,
    incomeCategories,
    expenseCategories,
    otherAccounts,
    termDeposits,
    consumedFirstLegKeys,
    monthRemainingWarnings,
    toSpendWarning,
} = defineProps<{
    row: ImportRow;
    accountBalanceMinorUnits: number;
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
    otherAccounts: AccountPickerOption[];
    termDeposits: TermDepositRow[];
    consumedFirstLegKeys: Set<string>;
    monthRemainingWarnings?: MonthRemainingWarningState;
    toSpendWarning?: ToSpendWarning | null;
}>();

const selection = defineModel<RowSelection>('selection', { required: true });

const t = useTranslations();

const availableCandidates = computed(() =>
    availablePairCandidates(
        row.pair_candidates,
        consumedFirstLegKeys,
        selection.value,
    ),
);

const typeLockedToOwnTransfer = computed(() =>
    locksTypeToOwnTransfer(availableCandidates.value),
);

const uniqueCandidate = computed(() =>
    uniquePairCandidate(availableCandidates.value),
);

const matchingCandidate = computed(() =>
    matchingPairCandidate(row.pair_candidates, selection.value),
);

const pairPickerCandidates = computed(() =>
    pairRecordPickerCandidates(
        row.pair_candidates,
        selection.value,
        consumedFirstLegKeys,
    ),
);

const showsPairBadge = computed(() =>
    showsPairCandidateBadge(
        row.import_pair_candidate,
        row.pair_candidates,
        consumedFirstLegKeys,
        selection.value,
    ),
);

const rowEligibleTermDeposits = computed(() =>
    eligibleTermDeposits(row, termDeposits),
);

const showInterestOverlay = computed(
    () => selection.value.type === 'capital_movement' && isCreditRow(row),
);

const incomeCategoriesForRow = computed(() =>
    categoriesForRow(row, 'income', incomeCategories, expenseCategories),
);

const classificationCategories = computed(() =>
    categoriesForRow(
        row,
        selection.value.type,
        incomeCategories,
        expenseCategories,
    ),
);

const classificationCategoriesEmpty = computed(() =>
    rowCategoriesEmpty(
        row,
        selection.value.type,
        incomeCategories,
        expenseCategories,
    ),
);

const incomeCategoriesEmpty = computed(() =>
    rowCategoriesEmpty(row, 'income', incomeCategories, expenseCategories),
);

const makesAccountNegative = computed(() =>
    wouldMakeAccountNegative(row, selection.value, accountBalanceMinorUnits),
);

const showCategoryMonthRemainingWarning = computed(
    () => monthRemainingWarnings?.showCategoryWarning === true,
);

const showPoolMonthRemainingWarning = computed(
    () => monthRemainingWarnings?.showPoolWarning === true,
);

const monthRemainingPeriodLabel = computed(
    () => monthRemainingWarnings?.periodLabel ?? null,
);

const hasNotes = computed(() =>
    importRowHasNotes({
        invalidReason: !row.valid && row.invalid_reason !== null,
        pairReason: showsPairBadge.value && matchingCandidate.value !== null,
        negativeBalance: makesAccountNegative.value,
        toSpend: toSpendWarning != null,
        categoryMonthRemaining: showCategoryMonthRemainingWarning.value,
        poolMonthRemaining: showPoolMonthRemainingWarning.value,
    }),
);

const rowDate = computed(() =>
    row.operation_date ? formatDate(row.operation_date) : '—',
);

const rowDescription = computed(() => row.description ?? '—');

function controlLabel(column: string): string {
    return t.operations.import_row_control_label
        .replace(':column', column)
        .replace(':date', rowDate.value)
        .replace(':description', rowDescription.value);
}

const selectRowLabel = computed(() =>
    t.operations.import_select_row
        .replace(':date', rowDate.value)
        .replace(':description', rowDescription.value),
);

function emptyCategoryLabel(type: RowSelection['type']): string {
    return type === 'income'
        ? t.operations.no_income_category_for_year
        : t.operations.no_expense_category_for_year;
}

function capitalMovementDirectionLabel(): string {
    if ((row.amount_minor_units ?? 0) < 0) {
        return t.operations.capital_movement_to_term_deposit;
    }

    return t.operations.capital_movement_from_term_deposit;
}

function emptyTermDepositLabel(): string {
    return rowEligibleTermDeposits.value.length === 0
        ? t.operations.import_no_term_deposit_for_row
        : t.operations.import_term_deposit;
}

function pairCandidateOptionLabel(candidate: PairCandidate): string {
    const kindLabel =
        candidate.kind === 'financial_operation'
            ? t.operations.import_pair_kind_financial_operation
            : t.operations.import_pair_kind_own_transfer;

    return `${candidate.account_name} · ${formatDate(candidate.date)} · ${kindLabel} · ${candidate.description}`;
}

function onTypeChange(): void {
    selection.value.category_id = '';
    selection.value.term_deposit_id = '';
    selection.value.interest_amount_input = '';
    selection.value.interest_category_id = '';

    if (typeLockedToOwnTransfer.value) {
        selection.value.type = 'own_transfer';
    }

    if (selection.value.type === 'capital_movement') {
        selection.value.other_account_id = '';
        selection.value.pair_first_leg_kind = '';
        selection.value.pair_first_leg_id = '';

        return;
    }

    if (selection.value.type !== 'own_transfer') {
        selection.value.other_account_id = '';
        selection.value.pair_first_leg_kind = '';
        selection.value.pair_first_leg_id = '';

        return;
    }

    const candidate = uniqueCandidate.value;

    if (candidate !== null) {
        selection.value.other_account_id = candidate.account_id;
        selection.value.pair_first_leg_kind = candidate.kind;
        selection.value.pair_first_leg_id = candidate.id;

        return;
    }

    selection.value.other_account_id = '';
    selection.value.pair_first_leg_kind = '';
    selection.value.pair_first_leg_id = '';
}

function onOtherAccountChange(): void {
    syncPairSelection(
        row.pair_candidates,
        selection.value,
        consumedFirstLegKeys,
    );
}

function onPairCandidateChange(value: string): void {
    if (value === '') {
        return;
    }

    const candidate = availableCandidates.value.find(
        (option) => pairCandidateKey(option) === value,
    );

    if (candidate === undefined) {
        selection.value.pair_first_leg_kind = '';
        selection.value.pair_first_leg_id = '';

        return;
    }

    applyPairCandidateSelection(selection.value, candidate);
}
</script>

<template>
    <div
        class="import-preview-row"
        :class="{
            'import-preview-row-duplicate': row.valid && row.import_duplicate,
            'import-preview-row-pair':
                row.valid && showsPairBadge && !row.import_duplicate,
        }"
    >
        <div class="import-preview-grid">
            <div class="import-preview-cell-select flex items-start pt-0.5">
                <Checkbox
                    :id="`row-selected-${row.index}`"
                    v-model="selection.selected"
                    :disabled="!row.valid"
                    :aria-label="selectRowLabel"
                    class="size-6 rounded-md"
                >
                    <Check class="size-4" />
                </Checkbox>
                <input
                    type="hidden"
                    :name="`rows[${row.index}][index]`"
                    :value="row.index"
                />
                <input
                    type="hidden"
                    :name="`rows[${row.index}][selected]`"
                    :value="selection.selected ? '1' : '0'"
                />
            </div>

            <div class="import-preview-cell-date">
                <span class="column-caption xl:sr-only">
                    {{ t.operations.date }}
                </span>
                <p class="text-sm font-medium">
                    {{ rowDate }}
                </p>
            </div>

            <div class="import-preview-cell-description">
                <span class="column-caption xl:sr-only">
                    {{ t.operations.description }}
                </span>
                <OperationDescriptionCell
                    :variant="descriptionVariant(selection.type)"
                    :description="rowDescription"
                    :counterparty="row.counterparty"
                    :raw-statement-description="row.raw_statement_description"
                />
            </div>

            <div
                class="import-preview-cell-amount"
                :class="amountToneClass(row.amount_minor_units ?? 0)"
            >
                <span class="column-caption xl:sr-only">
                    {{ t.operations.amount }}
                </span>
                <p>
                    {{
                        row.amount_minor_units !== null
                            ? formatMoney(row.amount_minor_units)
                            : '—'
                    }}
                </p>
            </div>

            <div class="import-preview-cell-type">
                <span class="column-caption xl:sr-only">
                    {{ t.operations.type }}
                </span>
                <select
                    v-if="row.valid"
                    :id="`row-type-${row.index}`"
                    v-model="selection.type"
                    :name="`rows[${row.index}][type]`"
                    :aria-label="controlLabel(t.operations.type)"
                    class="native-select w-full"
                    @change="onTypeChange"
                >
                    <option value="income" :disabled="typeLockedToOwnTransfer">
                        {{ t.operations.type_income }}
                    </option>
                    <option value="expense" :disabled="typeLockedToOwnTransfer">
                        {{ t.operations.type_expense }}
                    </option>
                    <option value="refund" :disabled="typeLockedToOwnTransfer">
                        {{ t.operations.type_refund }}
                    </option>
                    <option
                        value="capital_movement"
                        :disabled="typeLockedToOwnTransfer"
                    >
                        {{ t.operations.type_capital_movement }}
                    </option>
                    <option value="own_transfer">
                        {{ t.operations.type_own_transfer }}
                    </option>
                </select>
            </div>

            <div class="import-preview-cell-classification">
                <span class="column-caption xl:sr-only">
                    {{ t.operations.import_classification }}
                </span>
                <div
                    v-if="row.valid && selection.type === 'own_transfer'"
                    class="space-y-2"
                >
                    <AccountSelect
                        :id="`row-other-account-${row.index}`"
                        :name="`rows[${row.index}][other_account_id]`"
                        v-model="selection.other_account_id"
                        :accounts="otherAccounts"
                        :placeholder="t.operations.import_other_account"
                        :aria-label="
                            controlLabel(t.operations.import_other_account)
                        "
                        class="w-full"
                        @update:model-value="onOtherAccountChange"
                    />
                    <select
                        v-if="pairPickerCandidates.length > 0"
                        :id="`row-pair-candidate-${row.index}`"
                        class="native-select w-full"
                        :aria-label="
                            controlLabel(
                                t.operations.import_pair_select_first_leg,
                            )
                        "
                        :value="selectedPairCandidateKey(selection)"
                        @change="
                            onPairCandidateChange(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">
                            {{ t.operations.import_pair_select_first_leg }}
                        </option>
                        <option
                            v-for="candidate in pairPickerCandidates"
                            :key="pairCandidateKey(candidate)"
                            :value="pairCandidateKey(candidate)"
                        >
                            {{ pairCandidateOptionLabel(candidate) }}
                        </option>
                    </select>
                    <input
                        v-if="selection.pair_first_leg_kind !== ''"
                        type="hidden"
                        :name="`rows[${row.index}][pair_first_leg_kind]`"
                        :value="selection.pair_first_leg_kind"
                    />
                    <input
                        v-if="selection.pair_first_leg_id !== ''"
                        type="hidden"
                        :name="`rows[${row.index}][pair_first_leg_id]`"
                        :value="selection.pair_first_leg_id"
                    />
                </div>
                <div
                    v-else-if="
                        row.valid && selection.type === 'capital_movement'
                    "
                    class="space-y-2"
                >
                    <select
                        :id="`row-term-deposit-${row.index}`"
                        v-model="selection.term_deposit_id"
                        :name="`rows[${row.index}][term_deposit_id]`"
                        class="native-select w-full"
                        :aria-label="
                            controlLabel(t.operations.import_term_deposit)
                        "
                        :disabled="rowEligibleTermDeposits.length === 0"
                        :required="selection.selected"
                    >
                        <option value="">
                            {{ emptyTermDepositLabel() }}
                        </option>
                        <option
                            v-for="termDeposit in rowEligibleTermDeposits"
                            :key="termDeposit.id"
                            :value="termDeposit.id"
                        >
                            {{ termDeposit.name }}
                        </option>
                    </select>
                    <p class="text-muted-foreground text-xs">
                        {{ capitalMovementDirectionLabel() }}
                    </p>
                    <template v-if="showInterestOverlay">
                        <Label
                            :for="`row-interest-amount-${row.index}`"
                            class="text-xs"
                        >
                            {{ t.operations.import_interest_amount }}
                        </Label>
                        <Input
                            :id="`row-interest-amount-${row.index}`"
                            v-model="selection.interest_amount_input"
                            inputmode="decimal"
                            :placeholder="t.operations.import_interest_amount"
                        />
                        <input
                            v-if="
                                parsedInterestAmountMinorUnits(selection) !==
                                    null &&
                                parsedInterestAmountMinorUnits(selection)! > 0
                            "
                            type="hidden"
                            :name="`rows[${row.index}][interest_amount_minor_units]`"
                            :value="parsedInterestAmountMinorUnits(selection)"
                        />
                        <CategorySelect
                            v-if="selection.interest_amount_input.trim() !== ''"
                            :id="`row-interest-category-${row.index}`"
                            :name="`rows[${row.index}][interest_category_id]`"
                            v-model="selection.interest_category_id"
                            :categories="incomeCategoriesForRow"
                            :placeholder="
                                incomeCategoriesEmpty
                                    ? t.operations.no_income_category_for_year
                                    : t.operations.import_interest_category
                            "
                            :aria-label="
                                controlLabel(
                                    t.operations.import_interest_category,
                                )
                            "
                            class="w-full"
                            :disabled="incomeCategoriesEmpty"
                        />
                    </template>
                </div>
                <CategorySelect
                    v-else-if="row.valid"
                    :id="`row-category-${row.index}`"
                    :name="`rows[${row.index}][category_id]`"
                    v-model="selection.category_id"
                    :categories="classificationCategories"
                    :placeholder="
                        classificationCategoriesEmpty
                            ? emptyCategoryLabel(selection.type)
                            : t.operations.select_category
                    "
                    :aria-label="
                        controlLabel(t.operations.import_classification)
                    "
                    class="w-full"
                    :disabled="classificationCategoriesEmpty"
                    :required="selection.selected"
                />
            </div>

            <div class="import-preview-cell-status">
                <span class="column-caption xl:sr-only">
                    {{ t.operations.import_status }}
                </span>
                <div v-if="row.valid" class="flex flex-wrap gap-1">
                    <Badge v-if="row.import_duplicate" variant="outline">
                        {{ t.operations.import_row_duplicate }}
                    </Badge>
                    <Badge v-if="showsPairBadge" variant="outline">
                        {{ t.operations.import_row_pair_candidate }}
                    </Badge>
                    <Badge
                        v-if="!row.import_duplicate && !showsPairBadge"
                        variant="secondary"
                    >
                        {{ t.operations.import_row_valid }}
                    </Badge>
                </div>
                <div v-else>
                    <Badge variant="destructive">
                        {{ t.operations.import_row_invalid }}
                    </Badge>
                </div>
            </div>
        </div>

        <div v-if="hasNotes" class="mt-2 space-y-2 px-0.5">
            <p
                v-if="showsPairBadge && matchingCandidate !== null"
                class="text-muted-foreground text-xs"
            >
                {{ matchingCandidate.reason }}
            </p>
            <p
                v-if="!row.valid && row.invalid_reason"
                class="text-muted-foreground text-xs"
            >
                {{ row.invalid_reason }}
            </p>
            <Alert v-if="makesAccountNegative" variant="warning">
                <AlertDescription>
                    {{ t.operations.negative_balance_warning }}
                </AlertDescription>
            </Alert>
            <Alert v-if="toSpendWarning" variant="warning">
                <AlertDescription>
                    {{
                        t.operations.to_spend_negative_warning
                            .replace(
                                ':amount',
                                formatMoney(
                                    toSpendWarning.toSpendAfterMinorUnits,
                                ),
                            )
                            .replace(
                                ':month_year',
                                `${t.budget[`month_name_${toSpendWarning.month}` as keyof typeof t.budget] ?? ''} ${toSpendWarning.year}`,
                            )
                    }}
                </AlertDescription>
            </Alert>
            <Alert
                v-if="
                    showCategoryMonthRemainingWarning &&
                    monthRemainingPeriodLabel !== null
                "
                variant="warning"
            >
                <AlertDescription>
                    {{
                        t.operations.over_month_category_warning.replace(
                            ':month_year',
                            monthRemainingPeriodLabel,
                        )
                    }}
                </AlertDescription>
            </Alert>
            <Alert
                v-if="
                    showPoolMonthRemainingWarning &&
                    monthRemainingPeriodLabel !== null
                "
                variant="warning"
            >
                <AlertDescription>
                    {{
                        t.operations.over_month_pool_warning.replace(
                            ':month_year',
                            monthRemainingPeriodLabel,
                        )
                    }}
                </AlertDescription>
            </Alert>
        </div>
    </div>
</template>
