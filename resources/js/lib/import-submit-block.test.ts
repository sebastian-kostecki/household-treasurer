import { describe, expect, it } from 'vite-plus/test';
import {
    emptySelection,
    type ImportRow,
    type RowSelection,
} from './import-preview-rows';
import { importSubmitBlockReason } from './import-submit-block';

function row(index: number, overrides: Partial<ImportRow> = {}): ImportRow {
    return {
        index,
        operation_date: '2026-10-12',
        description: 'Opis',
        counterparty: null,
        raw_statement_description: null,
        amount_minor_units: -1000,
        valid: true,
        invalid_reason: null,
        import_duplicate: false,
        import_pair_candidate: false,
        pair_candidates: [],
        ...overrides,
    };
}

function selected(
    source: ImportRow,
    overrides: Partial<RowSelection> = {},
): RowSelection {
    return { ...emptySelection(source), selected: true, ...overrides };
}

describe('importSubmitBlockReason', () => {
    it('blocks when nothing is selected', () => {
        const rows = [row(0)];

        expect(
            importSubmitBlockReason(
                rows,
                { 0: emptySelection(rows[0]) },
                [],
                false,
            ),
        ).toEqual({ key: 'import_block_no_selection' });
    });

    it('counts selected rows without classification', () => {
        const rows = [row(0), row(1)];

        expect(
            importSubmitBlockReason(
                rows,
                {
                    0: selected(rows[0], { category_id: 5 }),
                    1: selected(rows[1]),
                },
                [],
                false,
            ),
        ).toEqual({ key: 'import_block_incomplete_rows', count: 1 });
    });

    it('prefers incomplete rows over unacknowledged duplicates', () => {
        const rows = [row(0), row(1, { import_duplicate: true })];

        expect(
            importSubmitBlockReason(
                rows,
                {
                    0: selected(rows[0]),
                    1: selected(rows[1], { category_id: 5 }),
                },
                [],
                false,
            ),
        ).toEqual({ key: 'import_block_incomplete_rows', count: 1 });
    });

    it('blocks a complete duplicate until acknowledged', () => {
        const rows = [row(0, { import_duplicate: true })];
        const selections = { 0: selected(rows[0], { category_id: 5 }) };

        expect(importSubmitBlockReason(rows, selections, [], false)).toEqual({
            key: 'import_block_duplicates_unacknowledged',
        });
        expect(importSubmitBlockReason(rows, selections, [], true)).toBeNull();
    });

    it('treats a selection without a matching row as incomplete', () => {
        const rows = [row(0)];

        expect(
            importSubmitBlockReason(
                rows,
                { 9: selected(row(9), { category_id: 5 }) },
                [],
                false,
            ),
        ).toEqual({ key: 'import_block_incomplete_rows', count: 1 });
    });
});
