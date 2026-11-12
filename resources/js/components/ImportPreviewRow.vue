<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import AccountAvatar from '@/components/AccountAvatar.vue';
import AccountSelect from '@/components/AccountSelect.vue';
import CategorySelect from '@/components/CategorySelect.vue';
import OperationDescriptionCell from '@/components/OperationDescriptionCell.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type { AccountPickerOption } from '@/lib/account-identity';
import { formatDate, formatMoney } from '@/lib/format';
import { importRowAmount } from '@/lib/import-row-amount';
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
import {
    importInvalidReasonText,
    importPairReasonText,
} from '@/lib/import-row-reasons';
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
    showsCategorySuggestion,
    suggestedCategoryId,
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
    toSpendWarnings,
} = defineProps<{
    row: ImportRow;
    accountBalanceMinorUnits: number;
    incomeCategories: CategoryRow[];
    expenseCategories: CategoryRow[];
    otherAccounts: AccountPickerOption[];
    termDeposits: TermDepositRow[];
    consumedFirstLegKeys: Set<string>;
    monthRemainingWarnings?: MonthRemainingWarningState;
    toSpendWarnings?: ToSpendWarning[];
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

const showsSuggestion = computed(
    () =>
        row.valid &&
        showsCategorySuggestion(
            row,
            selection.value,
            incomeCategories,
            expenseCategories,
        ),
);

const suggestionId = computed(() => `row-category-suggestion-${row.index}`);

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
        toSpend: (toSpendWarnings?.length ?? 0) > 0,
        categoryMonthRemaining: showCategoryMonthRemainingWarning.value,
        poolMonthRemaining: showPoolMonthRemainingWarning.value,
    }),
);

const rowDate = computed(() =>
    row.operation_date ? formatDate(row.operation_date) : '—',
);

const rowAmount = computed(() =>
    row.amount_minor_units === null
        ? null
        : importRowAmount(selection.value.type, row.amount_minor_units),
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

const PAIR_CANDIDATE_NONE = '__none__';

const selectedPairCandidateValue = computed(() => {
    const key = selectedPairCandidateKey(selection.value);

    return key === '' ? PAIR_CANDIDATE_NONE : key;
});

const selectedPairCandidate = computed(
    () =>
        pairPickerCandidates.value.find(
            (candidate) =>
                pairCandidateKey(candidate) ===
                selectedPairCandidateKey(selection.value),
        ) ?? null,
);

function pairCandidateValueToKey(value: string): string {
    return value === PAIR_CANDIDATE_NONE ? '' : value;
}

function onTypeChange(): void {
    selection.value.term_deposit_id = '';
    selection.value.interest_amount_input = '';
    selection.value.interest_category_id = '';

    if (typeLockedToOwnTransfer.value) {
        selection.value.type = 'own_transfer';
    }

    selection.value.category_id = suggestedCategoryId(
        row,
        selection.value.type,
        incomeCategories,
        expenseCategories,
    );

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
                    class="import-preview-select-check"
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
                <span class="table-header xl:sr-only">
                    {{ t.operations.date }}
                </span>
                <p class="tabular-nums">
                    {{ rowDate }}
                </p>
            </div>

            <div class="import-preview-cell-description">
                <span class="table-header xl:sr-only">
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
                :class="rowAmount?.className"
            >
                <span class="table-header xl:sr-only">
                    {{ t.operations.amount }}
                </span>
                <p>
                    {{ rowAmount?.text ?? '—' }}
                </p>
            </div>

            <div class="import-preview-cell-type">
                <span class="table-header xl:sr-only">
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
                <span class="table-header xl:sr-only">
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
                    <Select
                        v-if="pairPickerCandidates.length > 0"
                        :model-value="selectedPairCandidateValue"
                        @update:model-value="
                            onPairCandidateChange(
                                pairCandidateValueToKey(String($event)),
                            )
                        "
                    >
                        <SelectTrigger
                            :id="`row-pair-candidate-${row.index}`"
                            class="w-full"
                            :aria-label="
                                controlLabel(
                                    t.operations.import_pair_select_first_leg,
                                )
                            "
                        >
                            <SelectValue>
                                <span
                                    v-if="selectedPairCandidate"
                                    class="flex min-w-0 items-center gap-2"
                                >
                                    <AccountAvatar
                                        :type="
                                            selectedPairCandidate.account_type
                                        "
                                        :bank="
                                            selectedPairCandidate.account_bank
                                        "
                                        :mark_url="
                                            selectedPairCandidate.account_mark_url
                                        "
                                    />
                                    <span class="truncate">{{
                                        pairCandidateOptionLabel(
                                            selectedPairCandidate,
                                        )
                                    }}</span>
                                </span>
                                <span v-else class="text-muted-foreground">
                                    {{
                                        t.operations
                                            .import_pair_select_first_leg
                                    }}
                                </span>
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="PAIR_CANDIDATE_NONE">
                                {{ t.operations.import_pair_select_first_leg }}
                            </SelectItem>
                            <SelectItem
                                v-for="candidate in pairPickerCandidates"
                                :key="pairCandidateKey(candidate)"
                                :value="pairCandidateKey(candidate)"
                                :text-value="
                                    pairCandidateOptionLabel(candidate)
                                "
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <AccountAvatar
                                        :type="candidate.account_type"
                                        :bank="candidate.account_bank"
                                        :mark_url="candidate.account_mark_url"
                                    />
                                    <span>{{
                                        pairCandidateOptionLabel(candidate)
                                    }}</span>
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
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
                    <p class="meta text-muted-foreground">
                        {{ capitalMovementDirectionLabel() }}
                    </p>
                    <template v-if="showInterestOverlay">
                        <Label
                            :for="`row-interest-amount-${row.index}`"
                            class="form-field-label"
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
                    :aria-describedby="
                        showsSuggestion ? suggestionId : undefined
                    "
                    :disabled="classificationCategoriesEmpty"
                    :required="selection.selected"
                />
                <p
                    v-if="showsSuggestion"
                    :id="suggestionId"
                    class="meta text-muted-foreground"
                >
                    <span aria-hidden="true">✦</span>
                    {{ t.operations.import_category_suggestion }}
                </p>
            </div>

            <div class="import-preview-cell-status">
                <span class="table-header xl:sr-only">
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
                        variant="neutral"
                    >
                        {{ t.operations.import_row_valid }}
                    </Badge>
                </div>
                <div v-else>
                    <Badge variant="destructive-soft">
                        {{ t.operations.import_row_invalid }}
                    </Badge>
                </div>
            </div>
        </div>

        <div v-if="hasNotes" class="mt-2 space-y-2 px-0.5">
            <p
                v-if="showsPairBadge && matchingCandidate !== null"
                class="meta text-muted-foreground"
            >
                {{ importPairReasonText(matchingCandidate, t.operations) }}
            </p>
            <p
                v-if="!row.valid && row.invalid_reason"
                class="meta text-muted-foreground"
            >
                {{ importInvalidReasonText(row.invalid_reason, t.operations) }}
            </p>
            <Alert v-if="makesAccountNegative" variant="warning">
                <AlertDescription>
                    {{ t.operations.negative_balance_warning }}
                </AlertDescription>
            </Alert>
            <Alert
                v-for="toSpendWarning in toSpendWarnings ?? []"
                :key="`${toSpendWarning.year}-${toSpendWarning.month}`"
                variant="warning"
            >
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
