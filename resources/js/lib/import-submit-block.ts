import {
    rowSelectionValid,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from './import-preview-rows';

export type ImportSubmitBlockReason =
    | { key: 'import_block_no_selection' }
    | { key: 'import_block_incomplete_rows'; count: number }
    | { key: 'import_block_duplicates_unacknowledged' };

/**
 * The single reason the import cannot be submitted, by priority: nothing
 * selected, incomplete selected rows, unacknowledged selected duplicates.
 */
export function importSubmitBlockReason(
    rows: ImportRow[],
    selections: Record<number, RowSelection>,
    termDeposits: TermDepositRow[],
    duplicateAcknowledged: boolean,
): ImportSubmitBlockReason | null {
    const selected = Object.entries(selections).filter(
        ([, selection]) => selection.selected,
    );

    if (selected.length === 0) {
        return { key: 'import_block_no_selection' };
    }

    const incompleteCount = selected.filter(([index, selection]) => {
        const row = rows.find((candidate) => candidate.index === Number(index));

        return (
            row === undefined ||
            !rowSelectionValid(row, selection, termDeposits)
        );
    }).length;

    if (incompleteCount > 0) {
        return { key: 'import_block_incomplete_rows', count: incompleteCount };
    }

    const duplicateSelected = rows.some(
        (row) => row.import_duplicate && selections[row.index]?.selected,
    );

    if (duplicateSelected && !duplicateAcknowledged) {
        return { key: 'import_block_duplicates_unacknowledged' };
    }

    return null;
}
