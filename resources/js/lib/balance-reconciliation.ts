import { formatDate, formatMoney } from './format';
import type {
    AccountLastReconciliation,
    ReconciliationOperationKind,
    ReconciliationPreview,
} from '../types/accounts';

/** The words of a reconciliation line, taken from `t.accounts` (`lang/pl/ui.php`). */
type ReconciliationWords = Record<string, string>;

/** Bank balance minus Balance on a Date: 0 means „zgadza się” (ADR-0091). */
export function reconciliationDifference(
    bankBalanceMinorUnits: number,
    balanceMinorUnits: number,
): number {
    return bankBalanceMinorUnits - balanceMinorUnits;
}

/** The day input accepts today at most and the Account opening date at least. */
export function reconciliationDayIsAllowed(
    day: string,
    openingDate: string,
    today: string,
): boolean {
    return (
        /^\d{4}-\d{2}-\d{2}$/.test(day) && day >= openingDate && day <= today
    );
}

/** „Uzgodniono 31.10.2026”, the lead of the line under an Account name. */
export function reconciliationLastLead(
    reading: AccountLastReconciliation,
    words: ReconciliationWords,
): string {
    return words.reconcile_last.replace(
        ':date',
        formatDate(reading.reconciled_on),
    );
}

/** „zgadza się” or „nie zgadza się, różnica −12,40 zł”. */
export function reconciliationLastVerdict(
    reading: AccountLastReconciliation,
    words: ReconciliationWords,
): string {
    return reading.agrees
        ? words.reconcile_last_agrees
        : words.reconcile_last_disagrees.replace(
              ':amount',
              formatMoney(reading.difference_minor_units),
          );
}

/** The heading over the operations list: since the previous reconciliation, or all. */
export function reconciliationOperationsHeading(
    preview: ReconciliationPreview,
    words: ReconciliationWords,
): string {
    return preview.previous_reconciled_on === null
        ? words.reconcile_operations_all
        : words.reconcile_operations_since.replace(
              ':date',
              formatDate(preview.previous_reconciled_on),
          );
}

/** „Pokazano 100 najnowszych z 340 operacji.”, or null when the list is whole. */
export function reconciliationTruncationNote(
    preview: ReconciliationPreview,
    words: ReconciliationWords,
): string | null {
    return preview.operations.length < preview.operations_count
        ? words.reconcile_operations_truncated
              .replace(':shown', String(preview.operations.length))
              .replace(':total', String(preview.operations_count))
        : null;
}

export function reconciliationKindLabel(
    kind: ReconciliationOperationKind,
    words: ReconciliationWords,
): string {
    return words[`reconcile_kind_${kind}`];
}
