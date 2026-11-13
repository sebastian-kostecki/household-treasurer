import type { AccountBank, AccountTypeValue } from '@/lib/account-identity';

export type AccountFormAccount = {
    id: number;
    name: string;
    type: AccountTypeValue;
    bank: AccountBank | null;
    opening_date: string;
    opening_balance_minor_units: number;
};

/**
 * The newest Balance Reconciliation of an Account, read against today's data
 * (ADR-0091); `agrees` is never stored.
 */
export type AccountLastReconciliation = {
    reconciled_on: string;
    bank_balance_minor_units: number;
    balance_minor_units: number;
    difference_minor_units: number;
    agrees: boolean;
};

export type AccountRow = AccountFormAccount & {
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
    can_reconcile: boolean;
    last_reconciliation: AccountLastReconciliation | null;
};

export type AccountsPageProps = {
    accounts: AccountRow[];
    liquidity_total_minor_units: number;
    total_funds_minor_units: number;
    show_archived: boolean;
    archived_accounts_count: number;
};

export type ReconciliationOperationKind =
    | 'income'
    | 'expense'
    | 'own_transfer'
    | 'capital_movement';

/** One operation of the Account in the reconciliation window; the amount is its effect on the Account. */
export type ReconciliationOperation = {
    key: string;
    kind: ReconciliationOperationKind;
    date: string;
    description: string;
    amount_minor_units: number;
};

/** What `accounts.reconciliations.preview` answers for one day (ADR-0091). */
export type ReconciliationPreview = {
    reconciled_on: string;
    balance_minor_units: number;
    previous_reconciled_on: string | null;
    existing_bank_balance_minor_units: number | null;
    operations: ReconciliationOperation[];
    operations_count: number;
};
