export type HandEntryPreviewKind =
    | 'income'
    | 'expense'
    | 'refund'
    | 'own_transfer'
    | 'capital_movement';

export type HandEntryPreviewAccount = {
    id: number;
    current_balance_minor_units: number;
};

export type HandEntryPreviewTermDeposit = {
    id: number;
    current_balance_minor_units: number;
};

export type HandEntryPreviewCategory = {
    id: number;
    planned_amount_minor_units: number;
    execution_minor_units: number;
};

export type HandEntryPreviewEditedRecord = {
    account_id: number;
    category_id?: number;
    signed_amount_minor_units: number;
    source_account_id?: number;
    term_deposit_id?: number;
    direction?: 'to_term_deposit' | 'from_term_deposit';
};

export type HandEntryPreviewProposal = {
    kind: HandEntryPreviewKind;
    account_id: number;
    category_id?: number;
    amount_minor_units: number;
    source_account_id?: number;
    term_deposit_id?: number;
    direction?: 'to_term_deposit' | 'from_term_deposit';
};

export type HandEntryPreviewInput = {
    mode: 'create' | 'edit';
    accounts: HandEntryPreviewAccount[];
    termDeposits?: HandEntryPreviewTermDeposit[];
    categories?: HandEntryPreviewCategory[];
    editedRecord?: HandEntryPreviewEditedRecord | null;
    proposal: HandEntryPreviewProposal;
};

export type HandEntryPreviewResult = {
    showNegativeBalanceWarning: boolean;
    showTermDepositNegativeBalanceWarning: boolean;
    availableLimitNowMinorUnits: number | null;
    availableLimitAfterMinorUnits: number | null;
    showOverLimitWarning: boolean;
};

function findAccount(
    accounts: HandEntryPreviewAccount[],
    accountId: number | undefined,
): HandEntryPreviewAccount | undefined {
    if (accountId === undefined) {
        return undefined;
    }

    return accounts.find((account) => account.id === accountId);
}

function findCategory(
    categories: HandEntryPreviewCategory[] | undefined,
    categoryId: number | undefined,
): HandEntryPreviewCategory | undefined {
    if (categoryId === undefined || categories === undefined) {
        return undefined;
    }

    return categories.find((category) => category.id === categoryId);
}

function findTermDeposit(
    termDeposits: HandEntryPreviewTermDeposit[] | undefined,
    termDepositId: number | undefined,
): HandEntryPreviewTermDeposit | undefined {
    if (termDepositId === undefined || termDeposits === undefined) {
        return undefined;
    }

    return termDeposits.find((termDeposit) => termDeposit.id === termDepositId);
}

function accountLiquidityAdjustmentMinorUnits(
    mode: 'create' | 'edit',
    editedRecord: HandEntryPreviewEditedRecord | null | undefined,
    selectedAccountId: number,
    originalAccountId: number,
    originalSignedEffectMinorUnits: number,
): number {
    if (mode !== 'edit' || editedRecord === null || editedRecord === undefined) {
        return 0;
    }

    if (selectedAccountId !== originalAccountId) {
        return 0;
    }

    return originalSignedEffectMinorUnits;
}

function categoryExecutionAdjustmentMinorUnits(
    mode: 'create' | 'edit',
    editedRecord: HandEntryPreviewEditedRecord | null | undefined,
    selectedCategoryId: number | undefined,
    originalCategoryId: number | undefined,
    originalSignedAmountMinorUnits: number,
): number {
    if (
        mode !== 'edit' ||
        editedRecord === null ||
        editedRecord === undefined ||
        selectedCategoryId === undefined ||
        originalCategoryId === undefined
    ) {
        return 0;
    }

    if (selectedCategoryId !== originalCategoryId) {
        return 0;
    }

    return originalSignedAmountMinorUnits;
}

function signedProposalAmountMinorUnits(
    kind: HandEntryPreviewKind,
    amountMinorUnits: number,
): number {
    const absoluteAmount = Math.abs(amountMinorUnits);

    if (kind === 'refund') {
        return -absoluteAmount;
    }

    return absoluteAmount;
}

function predictAccountLiquidityMinorUnits(
    account: HandEntryPreviewAccount,
    adjustmentMinorUnits: number,
    kind: HandEntryPreviewKind,
    signedProposalAmountMinorUnits: number,
): number {
    if (kind === 'income') {
        return (
            account.current_balance_minor_units +
            adjustmentMinorUnits +
            Math.abs(signedProposalAmountMinorUnits)
        );
    }

    return (
        account.current_balance_minor_units +
        adjustmentMinorUnits -
        signedProposalAmountMinorUnits
    );
}

function termDepositLiquidityAdjustmentMinorUnits(
    mode: 'create' | 'edit',
    editedRecord: HandEntryPreviewEditedRecord | null | undefined,
    selectedTermDepositId: number,
    originalTermDepositId: number,
    originalSignedEffectMinorUnits: number,
): number {
    if (mode !== 'edit' || editedRecord === null || editedRecord === undefined) {
        return 0;
    }

    if (selectedTermDepositId !== originalTermDepositId) {
        return 0;
    }

    return originalSignedEffectMinorUnits;
}

function capitalMovementAccountSignedEffect(
    direction: 'to_term_deposit' | 'from_term_deposit' | undefined,
    amountMinorUnits: number,
): number {
    const absoluteAmount = Math.abs(amountMinorUnits);

    if (direction === 'from_term_deposit') {
        return absoluteAmount;
    }

    return -absoluteAmount;
}

function capitalMovementTermDepositSignedEffect(
    direction: 'to_term_deposit' | 'from_term_deposit' | undefined,
    amountMinorUnits: number,
): number {
    const absoluteAmount = Math.abs(amountMinorUnits);

    if (direction === 'from_term_deposit') {
        return -absoluteAmount;
    }

    return absoluteAmount;
}

export function previewHandEntryWarnings(
    input: HandEntryPreviewInput,
): HandEntryPreviewResult {
    const { mode, accounts, termDeposits, categories, editedRecord, proposal } = input;
    const signedAmount = signedProposalAmountMinorUnits(
        proposal.kind,
        proposal.amount_minor_units,
    );

    if (proposal.kind === 'capital_movement') {
        const account = findAccount(accounts, proposal.account_id);
        const termDeposit = findTermDeposit(termDeposits, proposal.term_deposit_id);
        const accountSignedEffect = capitalMovementAccountSignedEffect(
            proposal.direction,
            proposal.amount_minor_units,
        );
        const termDepositSignedEffect = capitalMovementTermDepositSignedEffect(
            proposal.direction,
            proposal.amount_minor_units,
        );
        const originalAccountId = editedRecord?.account_id ?? -1;
        const originalTermDepositId = editedRecord?.term_deposit_id ?? -1;
        const originalAmountMinorUnits = Math.abs(
            editedRecord?.signed_amount_minor_units ?? 0,
        );
        const originalDirection = editedRecord?.direction ?? 'to_term_deposit';
        const originalAccountSignedEffect = capitalMovementAccountSignedEffect(
            originalDirection,
            originalAmountMinorUnits,
        );
        const originalTermDepositSignedEffect = capitalMovementTermDepositSignedEffect(
            originalDirection,
            originalAmountMinorUnits,
        );
        const accountAdjustment = accountLiquidityAdjustmentMinorUnits(
            mode,
            editedRecord,
            proposal.account_id,
            originalAccountId,
            originalAccountSignedEffect,
        );
        const termDepositAdjustment = termDepositLiquidityAdjustmentMinorUnits(
            mode,
            editedRecord,
            proposal.term_deposit_id ?? -1,
            originalTermDepositId,
            originalTermDepositSignedEffect,
        );
        const predictedAccountBalance =
            account === undefined
                ? null
                : account.current_balance_minor_units +
                  accountAdjustment +
                  accountSignedEffect;
        const predictedTermDepositBalance =
            termDeposit === undefined
                ? null
                : termDeposit.current_balance_minor_units +
                  termDepositAdjustment +
                  termDepositSignedEffect;

        return {
            showNegativeBalanceWarning:
                predictedAccountBalance !== null && predictedAccountBalance < 0,
            showTermDepositNegativeBalanceWarning:
                predictedTermDepositBalance !== null &&
                predictedTermDepositBalance < 0,
            availableLimitNowMinorUnits: null,
            availableLimitAfterMinorUnits: null,
            showOverLimitWarning: false,
        };
    }

    if (proposal.kind === 'own_transfer') {
        const sourceAccount = findAccount(accounts, proposal.source_account_id);
        const originalSourceAccountId = editedRecord?.source_account_id;
        const originalAmountMinorUnits = Math.abs(
            editedRecord?.signed_amount_minor_units ?? 0,
        );
        const sourceAdjustment = accountLiquidityAdjustmentMinorUnits(
            mode,
            editedRecord,
            proposal.source_account_id ?? -1,
            originalSourceAccountId ?? -1,
            originalAmountMinorUnits,
        );
        const predictedBalance =
            sourceAccount === undefined
                ? null
                : predictAccountLiquidityMinorUnits(
                      sourceAccount,
                      sourceAdjustment,
                      'expense',
                      Math.abs(signedAmount),
                  );

        return {
            showNegativeBalanceWarning:
                predictedBalance !== null && predictedBalance < 0,
            showTermDepositNegativeBalanceWarning: false,
            availableLimitNowMinorUnits: null,
            availableLimitAfterMinorUnits: null,
            showOverLimitWarning: false,
        };
    }

    if (proposal.kind === 'income') {
        if (mode === 'create') {
            return {
                showNegativeBalanceWarning: false,
                showTermDepositNegativeBalanceWarning: false,
                availableLimitNowMinorUnits: null,
                availableLimitAfterMinorUnits: null,
                showOverLimitWarning: false,
            };
        }

        const account = findAccount(accounts, proposal.account_id);
        const originalAccountId = editedRecord?.account_id ?? -1;
        const originalAmountMinorUnits = Math.abs(
            editedRecord?.signed_amount_minor_units ?? 0,
        );
        const adjustment = accountLiquidityAdjustmentMinorUnits(
            mode,
            editedRecord,
            proposal.account_id,
            originalAccountId,
            -originalAmountMinorUnits,
        );
        const predictedBalance =
            account === undefined
                ? null
                : predictAccountLiquidityMinorUnits(
                      account,
                      adjustment,
                      'income',
                      signedAmount,
                  );

        return {
            showNegativeBalanceWarning:
                predictedBalance !== null && predictedBalance < 0,
            showTermDepositNegativeBalanceWarning: false,
            availableLimitNowMinorUnits: null,
            availableLimitAfterMinorUnits: null,
            showOverLimitWarning: false,
        };
    }

    const account = findAccount(accounts, proposal.account_id);
    const category = findCategory(categories, proposal.category_id);
    const originalAccountId = editedRecord?.account_id ?? -1;
    const originalCategoryId = editedRecord?.category_id;
    const originalSignedAmount = editedRecord?.signed_amount_minor_units ?? 0;
    const accountAdjustment = accountLiquidityAdjustmentMinorUnits(
        mode,
        editedRecord,
        proposal.account_id,
        originalAccountId,
        originalSignedAmount,
    );
    const executionAdjustment = categoryExecutionAdjustmentMinorUnits(
        mode,
        editedRecord,
        proposal.category_id,
        originalCategoryId,
        originalSignedAmount,
    );
    const predictedBalance =
        account === undefined
            ? null
            : predictAccountLiquidityMinorUnits(
                  account,
                  accountAdjustment,
                  'expense',
                  signedAmount,
              );
    const availableLimitNowMinorUnits =
        category === undefined
            ? null
            : category.planned_amount_minor_units -
              category.execution_minor_units +
              executionAdjustment;
    const availableLimitAfterMinorUnits =
        availableLimitNowMinorUnits === null
            ? null
            : availableLimitNowMinorUnits - signedAmount;

    return {
        showNegativeBalanceWarning:
            predictedBalance !== null && predictedBalance < 0,
        showTermDepositNegativeBalanceWarning: false,
        availableLimitNowMinorUnits,
        availableLimitAfterMinorUnits,
        showOverLimitWarning:
            proposal.kind === 'expense' &&
            availableLimitAfterMinorUnits !== null &&
            availableLimitAfterMinorUnits < 0,
    };
}
