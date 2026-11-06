import { formatDate } from './format';
import type { PairCandidate } from './import-pair-selection';

/**
 * Polish sentence for a rejection code (`ImportRowInvalidReason` on the
 * server). An unknown code is returned as received.
 */
export function importInvalidReasonText(
    code: string,
    operations: Record<string, string>,
): string {
    return operations[`import_invalid_reason_${code}`] ?? code;
}

/**
 * Polish sentence for a pair candidate, built from its kind, account and date.
 */
export function importPairReasonText(
    candidate: PairCandidate,
    operations: Record<string, string>,
): string {
    const template = operations[`import_pair_reason_${candidate.kind}`] ?? '';

    return template
        .replace(':account', candidate.account_name)
        .replace(':date', formatDate(candidate.date));
}
