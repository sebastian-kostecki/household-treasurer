import { describe, expect, it } from 'vite-plus/test';
import {
    importRowHasNotes,
    type ImportRowNoteKind,
} from './import-preview-rows';

const kinds: ImportRowNoteKind[] = [
    'invalidReason',
    'pairReason',
    'negativeBalance',
    'toSpend',
    'categoryMonthRemaining',
    'poolMonthRemaining',
];

function flags(active?: ImportRowNoteKind): Record<ImportRowNoteKind, boolean> {
    return Object.fromEntries(
        kinds.map((kind) => [kind, kind === active]),
    ) as Record<ImportRowNoteKind, boolean>;
}

describe('importRowHasNotes', () => {
    it('is false when no note applies', () => {
        expect(importRowHasNotes(flags())).toBe(false);
    });

    it.each(kinds)('is true for %s alone', (kind) => {
        expect(importRowHasNotes(flags(kind))).toBe(true);
    });
});
