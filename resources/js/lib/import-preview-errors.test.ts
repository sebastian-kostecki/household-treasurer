import { describe, expect, it } from 'vite-plus/test';
import {
    duplicateAcknowledgementErrorText,
    firstRowsError,
} from './import-preview-errors';

describe('duplicateAcknowledgementErrorText', () => {
    const server =
        'Acknowledge the import duplicate warning before committing selected duplicates.';

    it('returns undefined without a server error', () => {
        expect(
            duplicateAcknowledgementErrorText(undefined, {
                import_duplicate_acknowledgement_required: 'Potwierdź.',
            }),
        ).toBeUndefined();
    });

    it('composes the Polish sentence instead of the server text', () => {
        expect(
            duplicateAcknowledgementErrorText(server, {
                import_duplicate_acknowledgement_required: 'Potwierdź.',
            }),
        ).toBe('Potwierdź.');
    });

    it('keeps the server text when the key is missing', () => {
        expect(duplicateAcknowledgementErrorText(server, {})).toBe(server);
    });
});

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
