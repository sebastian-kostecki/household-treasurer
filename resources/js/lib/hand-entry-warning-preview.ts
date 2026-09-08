export type HandEntryPreviewKind =
    | 'income'
    | 'expense'
    | 'refund'
    | 'own_transfer';

export type HandEntryPreviewAccount = {
    id: number;
    current_balance_grosze: number;
};

export type HandEntryPreviewCategory = {
    id: number;
    planned_amount_grosze: number;
    execution_grosze: number;
};

export type HandEntryPreviewEditedRecord = {
    account_id: number;
    category_id?: number;
    signed_amount_grosze: number;
    source_account_id?: number;
};

export type HandEntryPreviewProposal = {
    kind: HandEntryPreviewKind;
    account_id: number;
    category_id?: number;
    amount_grosze: number;
    source_account_id?: number;
};

export type HandEntryPreviewInput = {
    mode: 'create' | 'edit';
    accounts: HandEntryPreviewAccount[];
    categories?: HandEntryPreviewCategory[];
    editedRecord?: HandEntryPreviewEditedRecord | null;
    proposal: HandEntryPreviewProposal;
};

export type HandEntryPreviewResult = {
    showNegativeBalanceWarning: boolean;
    availableLimitNowGrosze: number | null;
    availableLimitAfterGrosze: number | null;
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

function accountLiquidityAdjustmentGrosze(
    mode: 'create' | 'edit',
    editedRecord: HandEntryPreviewEditedRecord | null | undefined,
    selectedAccountId: number,
    originalAccountId: number,
    originalSignedEffectGrosze: number,
): number {
    if (mode !== 'edit' || editedRecord === null || editedRecord === undefined) {
        return 0;
    }

    if (selectedAccountId !== originalAccountId) {
        return 0;
    }

    return originalSignedEffectGrosze;
}

function categoryExecutionAdjustmentGrosze(
    mode: 'create' | 'edit',
    editedRecord: HandEntryPreviewEditedRecord | null | undefined,
    selectedCategoryId: number | undefined,
    originalCategoryId: number | undefined,
    originalSignedAmountGrosze: number,
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

    return originalSignedAmountGrosze;
}

function signedProposalAmountGrosze(
    kind: HandEntryPreviewKind,
    amountGrosze: number,
): number {
    const absoluteAmount = Math.abs(amountGrosze);

    if (kind === 'refund') {
        return -absoluteAmount;
    }

    return absoluteAmount;
}

function predictAccountLiquidityGrosze(
    account: HandEntryPreviewAccount,
    adjustmentGrosze: number,
    kind: HandEntryPreviewKind,
    signedProposalAmountGrosze: number,
): number {
    if (kind === 'income') {
        return (
            account.current_balance_grosze +
            adjustmentGrosze +
            Math.abs(signedProposalAmountGrosze)
        );
    }

    return (
        account.current_balance_grosze +
        adjustmentGrosze -
        signedProposalAmountGrosze
    );
}

export function previewHandEntryWarnings(
    input: HandEntryPreviewInput,
): HandEntryPreviewResult {
    const { mode, accounts, categories, editedRecord, proposal } = input;
    const signedAmount = signedProposalAmountGrosze(
        proposal.kind,
        proposal.amount_grosze,
    );

    if (proposal.kind === 'own_transfer') {
        const sourceAccount = findAccount(accounts, proposal.source_account_id);
        const originalSourceAccountId = editedRecord?.source_account_id;
        const originalAmountGrosze = Math.abs(
            editedRecord?.signed_amount_grosze ?? 0,
        );
        const sourceAdjustment = accountLiquidityAdjustmentGrosze(
            mode,
            editedRecord,
            proposal.source_account_id ?? -1,
            originalSourceAccountId ?? -1,
            originalAmountGrosze,
        );
        const predictedBalance =
            sourceAccount === undefined
                ? null
                : predictAccountLiquidityGrosze(
                      sourceAccount,
                      sourceAdjustment,
                      'expense',
                      Math.abs(signedAmount),
                  );

        return {
            showNegativeBalanceWarning:
                predictedBalance !== null && predictedBalance < 0,
            availableLimitNowGrosze: null,
            availableLimitAfterGrosze: null,
            showOverLimitWarning: false,
        };
    }

    if (proposal.kind === 'income') {
        if (mode === 'create') {
            return {
                showNegativeBalanceWarning: false,
                availableLimitNowGrosze: null,
                availableLimitAfterGrosze: null,
                showOverLimitWarning: false,
            };
        }

        const account = findAccount(accounts, proposal.account_id);
        const originalAccountId = editedRecord?.account_id ?? -1;
        const originalAmountGrosze = Math.abs(
            editedRecord?.signed_amount_grosze ?? 0,
        );
        const adjustment = accountLiquidityAdjustmentGrosze(
            mode,
            editedRecord,
            proposal.account_id,
            originalAccountId,
            -originalAmountGrosze,
        );
        const predictedBalance =
            account === undefined
                ? null
                : predictAccountLiquidityGrosze(
                      account,
                      adjustment,
                      'income',
                      signedAmount,
                  );

        return {
            showNegativeBalanceWarning:
                predictedBalance !== null && predictedBalance < 0,
            availableLimitNowGrosze: null,
            availableLimitAfterGrosze: null,
            showOverLimitWarning: false,
        };
    }

    const account = findAccount(accounts, proposal.account_id);
    const category = findCategory(categories, proposal.category_id);
    const originalAccountId = editedRecord?.account_id ?? -1;
    const originalCategoryId = editedRecord?.category_id;
    const originalSignedAmount = editedRecord?.signed_amount_grosze ?? 0;
    const accountAdjustment = accountLiquidityAdjustmentGrosze(
        mode,
        editedRecord,
        proposal.account_id,
        originalAccountId,
        originalSignedAmount,
    );
    const executionAdjustment = categoryExecutionAdjustmentGrosze(
        mode,
        editedRecord,
        proposal.category_id,
        originalCategoryId,
        originalSignedAmount,
    );
    const predictedBalance =
        account === undefined
            ? null
            : predictAccountLiquidityGrosze(
                  account,
                  accountAdjustment,
                  'expense',
                  signedAmount,
              );
    const availableLimitNowGrosze =
        category === undefined
            ? null
            : category.planned_amount_grosze -
              category.execution_grosze +
              executionAdjustment;
    const availableLimitAfterGrosze =
        availableLimitNowGrosze === null
            ? null
            : availableLimitNowGrosze - signedAmount;

    return {
        showNegativeBalanceWarning:
            predictedBalance !== null && predictedBalance < 0,
        availableLimitNowGrosze,
        availableLimitAfterGrosze,
        showOverLimitWarning:
            proposal.kind === 'expense' &&
            availableLimitAfterGrosze !== null &&
            availableLimitAfterGrosze < 0,
    };
}
