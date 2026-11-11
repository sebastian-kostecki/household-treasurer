import {
    previewToSpendWarnings,
    type EntryToSpendInputs,
    type ToSpendEntryChange,
    type ToSpendWarning,
} from './entry-to-spend-warning';
import type { ImportRow, RowSelection } from './import-preview-rows';
import {
    interestSplitValid,
    parsedInterestAmountMinorUnits,
} from './import-preview-rows';

/**
 * Walks the selected import rows in index order. Each income, expense or
 * refund row with a category is compared against the month plan together with
 * the rows above it (`pending`); a row warns for every month it pushes below
 * zero, also a later month of an Irregular period. Interest of a capital movement adds an income
 * change to `pending` but never warns on its own row.
 */
export function computeImportToSpendWarnings(
    rows: ImportRow[],
    selections: Record<number, RowSelection>,
    inputs: EntryToSpendInputs,
): Record<number, ToSpendWarning[]> {
    const warnings: Record<number, ToSpendWarning[]> = {};
    const pending: ToSpendEntryChange[] = [];

    for (const row of rows) {
        warnings[row.index] = [];

        const selection = selections[row.index];

        if (!row.valid || !row.operation_date || selection === undefined) {
            continue;
        }

        if (!selection.selected) {
            continue;
        }

        if (selection.type === 'capital_movement') {
            const interestAmount = parsedInterestAmountMinorUnits(selection);

            if (
                selection.interest_category_id !== '' &&
                interestAmount !== null &&
                interestAmount > 0 &&
                interestSplitValid(row, selection)
            ) {
                pending.push({
                    operationDate: row.operation_date,
                    categoryId: selection.interest_category_id,
                    amountMinorUnits: interestAmount,
                    direction: 'add',
                });
            }

            continue;
        }

        if (
            selection.type !== 'income' &&
            selection.type !== 'expense' &&
            selection.type !== 'refund'
        ) {
            continue;
        }

        if (selection.category_id === '') {
            continue;
        }

        const amount = Math.abs(row.amount_minor_units ?? 0);
        const change: ToSpendEntryChange = {
            operationDate: row.operation_date,
            categoryId: selection.category_id,
            amountMinorUnits: selection.type === 'refund' ? -amount : amount,
            direction: 'add',
        };

        warnings[row.index] = previewToSpendWarnings(inputs, [change], pending);
        pending.push(change);
    }

    return warnings;
}
