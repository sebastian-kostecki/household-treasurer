import { describe, expect, it } from 'vite-plus/test';
import { firstRowsError } from './import-preview-errors';

describe('firstRowsError', () => {
    it('returns undefined without errors', () => {
        expect(firstRowsError({})).toBeUndefined();
    });

    it('returns the rows error', () => {
        expect(firstRowsError({ rows: 'Rows are required.' })).toBe(
            'Rows are required.',
        );
    });

    it('returns the first row field error', () => {
        expect(firstRowsError({ 'rows.3.type': 'Type is required.' })).toBe(
            'Type is required.',
        );
    });

    it('prefers rows over row field errors', () => {
        expect(
            firstRowsError({
                'rows.3.type': 'Type is required.',
                rows: 'Rows are required.',
            }),
        ).toBe('Rows are required.');
    });

    it('ignores unrelated keys', () => {
        expect(
            firstRowsError({ duplicate_warning_acknowledged: 'Confirm.' }),
        ).toBeUndefined();
    });
});
