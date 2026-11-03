export type OperationsCreateAvailability = {
    transfer: {
        enabled: boolean;
        reasonKey: 'transfer_requires_two_accounts' | null;
    };
    capitalMovement: {
        enabled: boolean;
        reasonKey: 'capital_movement_requires_account_and_term_deposit' | null;
    };
};

/**
 * Przelew własny wymaga dwóch kont, ruch kapitału konta i lokaty.
 * `reasonKey` wskazuje klucz tłumaczenia powodu blokady (null, gdy włączone).
 */
export function operationsCreateAvailability(
    accountsCount: number,
    termDepositsCount: number,
): OperationsCreateAvailability {
    const transferEnabled = accountsCount >= 2;
    const capitalMovementEnabled = accountsCount >= 1 && termDepositsCount >= 1;

    return {
        transfer: {
            enabled: transferEnabled,
            reasonKey: transferEnabled
                ? null
                : 'transfer_requires_two_accounts',
        },
        capitalMovement: {
            enabled: capitalMovementEnabled,
            reasonKey: capitalMovementEnabled
                ? null
                : 'capital_movement_requires_account_and_term_deposit',
        },
    };
}
