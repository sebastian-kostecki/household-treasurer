import {
    rowBudgetYearNotice,
    rowNeedsBudgetInExecution,
    rowSelectionValid,
    type ImportBudgetYearStates,
    type ImportRow,
    type RowSelection,
    type TermDepositRow,
} from './import-preview-rows';

export type ImportSubmitBlockReason =
    | { key: 'import_block_no_selection' }
    | {
          key: 'import_block_budget_year_not_in_execution';
          count: number;
          years: number[];
      }
    | { key: 'import_block_incomplete_rows'; count: number }
    | { key: 'import_block_duplicates_unacknowledged' };

/**
 * The single reason the import cannot be submitted, by priority: nothing
 * selected, selected rows in a year without
 * an execution Budget, incomplete selected rows, unacknowledged selected duplicates.
 */
export function importSubmitBlockReason(
    rows: ImportRow[],
    selections: Record<number, RowSelection>,
    termDeposits: TermDepositRow[],
    duplicateAcknowledged: boolean,
    budgetYearStates: ImportBudgetYearStates,
): ImportSubmitBlockReason | null {
    const selected = Object.entries(selections).filter(
        ([, selection]) => selection.selected,
    );

    if (selected.length === 0) {
        return { key: 'import_block_no_selection' };
    }

    const years = new Set<number>();
    let notInExecutionCount = 0;

    for (const row of rows) {
        const selection = selections[row.index];

        if (
            selection !== undefined &&
            rowNeedsBudgetInExecution(row, selection, budgetYearStates)
        ) {
            notInExecutionCount++;
            years.add(
                rowBudgetYearNotice(row, selection, budgetYearStates)!.year,
            );
        }
    }

    if (notInExecutionCount > 0) {
        return {
            key: 'import_block_budget_year_not_in_execution',
            count: notInExecutionCount,
            years: [...years].sort((a, b) => a - b),
        };
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
