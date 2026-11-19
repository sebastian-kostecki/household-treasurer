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
                {},
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
                {},
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
                {},
            ),
        ).toEqual({ key: 'import_block_incomplete_rows', count: 1 });
    });

    it('blocks a complete duplicate until acknowledged', () => {
        const rows = [row(0, { import_duplicate: true })];
        const selections = { 0: selected(rows[0], { category_id: 5 }) };

        expect(
            importSubmitBlockReason(rows, selections, [], false, {}),
        ).toEqual({
            key: 'import_block_duplicates_unacknowledged',
        });
        expect(
            importSubmitBlockReason(rows, selections, [], true, {}),
        ).toBeNull();
    });

    it('treats a selection without a matching row as incomplete', () => {
        const rows = [row(0)];

        expect(
            importSubmitBlockReason(
                rows,
                { 9: selected(row(9), { category_id: 5 }) },
                [],
                false,
                {},
            ),
        ).toEqual({ key: 'import_block_incomplete_rows', count: 1 });
    });

    describe('budget year not in execution', () => {
        const states = { '2027': 'draft' } as const;
        const late = (index: number, date = '2027-01-05') =>
            row(index, { operation_date: date });

        it('blocks a selected expense in a draft year', () => {
            const rows = [late(0)];

            expect(
                importSubmitBlockReason(
                    rows,
                    { 0: selected(rows[0], { category_id: 5 }) },
                    [],
                    false,
                    states,
                ),
            ).toEqual({
                key: 'import_block_budget_year_not_in_execution',
                count: 1,
                years: [2027],
            });
        });

        it('does not block an own transfer or a capital movement without interest', () => {
            const rows = [late(0), late(1)];

            expect(
                importSubmitBlockReason(
                    rows,
                    {
                        0: selected(rows[0], {
                            type: 'own_transfer',
                            other_account_id: 3,
                        }),
                    },
                    [],
                    false,
                    states,
                ),
            ).toBeNull();
            expect(
                importSubmitBlockReason(
                    [rows[1]],
                    {
                        1: selected(rows[1], {
                            type: 'capital_movement',
                            category_id: 5,
                        }),
                    },
                    [{ id: 5 } as never],
                    false,
                    states,
                ),
            ).not.toEqual(
                expect.objectContaining({
                    key: 'import_block_budget_year_not_in_execution',
                }),
            );
        });

        it('blocks a capital movement with interest', () => {
            const rows = [late(0, '2027-01-05')];

            expect(
                importSubmitBlockReason(
                    rows,
                    {
                        0: selected(rows[0], {
                            type: 'capital_movement',
                            interest_amount_input: '10',
                        }),
                    },
                    [],
                    false,
                    states,
                ),
            ).toEqual({
                key: 'import_block_budget_year_not_in_execution',
                count: 1,
                years: [2027],
            });
        });

        it('blocks a missing year and ignores an execution year', () => {
            const rows = [late(0)];
            const selections = { 0: selected(rows[0], { category_id: 5 }) };

            expect(
                importSubmitBlockReason(rows, selections, [], false, {
                    '2027': 'missing',
                }),
            ).toMatchObject({
                key: 'import_block_budget_year_not_in_execution',
            });
            expect(
                importSubmitBlockReason(rows, selections, [], false, {
                    '2027': 'execution',
                }),
            ).toBeNull();
        });

        it('ignores an unselected row', () => {
            const rows = [late(0), row(1)];

            expect(
                importSubmitBlockReason(
                    rows,
                    {
                        0: emptySelection(rows[0]),
                        1: selected(rows[1], { category_id: 5 }),
                    },
                    [],
                    false,
                    states,
                ),
            ).toBeNull();
        });

        it('comes before incomplete rows and duplicates', () => {
            const rows = [late(0), row(1), late(2, '2027-02-01')];
            rows[2].import_duplicate = true;

            expect(
                importSubmitBlockReason(
                    rows,
                    {
                        0: selected(rows[0], { category_id: 5 }),
                        1: selected(rows[1]),
                        2: selected(rows[2], { category_id: 5 }),
                    },
                    [],
                    false,
                    states,
                ),
            ).toEqual({
                key: 'import_block_budget_year_not_in_execution',
                count: 2,
                years: [2027],
            });
        });

        it('lists the years ascending without repeats', () => {
            const rows = [
                late(0, '2027-01-05'),
                late(1, '2025-03-01'),
                late(2, '2027-02-01'),
            ];
            const selections = Object.fromEntries(
                rows.map((r) => [r.index, selected(r, { category_id: 5 })]),
            );

            expect(
                importSubmitBlockReason(rows, selections, [], false, {
                    '2025': 'missing',
                    '2027': 'draft',
                }),
            ).toEqual({
                key: 'import_block_budget_year_not_in_execution',
                count: 3,
                years: [2025, 2027],
            });
        });
    });
});
