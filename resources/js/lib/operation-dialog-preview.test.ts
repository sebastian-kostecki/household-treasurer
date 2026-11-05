import { describe, expect, it } from 'vite-plus/test';
import {
    buildOperationPreview,
    monthNamesLocativeFrom,
    toSpendRowLabel,
} from './operation-dialog-preview';

const monthNamesLocative = [
    'styczniu',
    'lutym',
    'marcu',
    'kwietniu',
    'maju',
    'czerwcu',
    'lipcu',
    'sierpniu',
    'wrześniu',
    'październiku',
    'listopadzie',
    'grudniu',
];
const texts = {
    toSpendLabel: 'Do wydania w :month :year',
    toSpendWarning: 'Do wydania poniżej zera.',
    monthNamesLocative,
};

function reading(label: string, warning: string | null = null) {
    return {
        label,
        beforeMinorUnits: 10,
        afterMinorUnits: -10,
        warning,
    };
}

describe('buildOperationPreview', () => {
    it('labels To Spend rows with the locative month and year', () => {
        expect(
            toSpendRowLabel(texts.toSpendLabel, monthNamesLocative, 2025, 12),
        ).toBe('Do wydania w grudniu 2025');
        expect(
            toSpendRowLabel(texts.toSpendLabel, monthNamesLocative, 2026, 1),
        ).toBe('Do wydania w styczniu 2026');
    });

    it('orders rows: To Spend by month, limit, account, term deposit', () => {
        const preview = buildOperationPreview({
            toSpendReadings: [
                {
                    year: 2026,
                    month: 1,
                    beforeMinorUnits: 5,
                    afterMinorUnits: 4,
                },
                {
                    year: 2025,
                    month: 12,
                    beforeMinorUnits: 3,
                    afterMinorUnits: 2,
                },
            ],
            termDepositBalance: reading('Lokata'),
            accountBalance: reading('Konto'),
            limit: reading('Limit'),
            texts,
        });

        expect(preview.rows.map((row) => row.label)).toEqual([
            'Do wydania w grudniu 2025',
            'Do wydania w styczniu 2026',
            'Limit',
            'Konto',
            'Lokata',
        ]);
    });

    it('pins each warning under its own row and Month Remaining to the block', () => {
        const preview = buildOperationPreview({
            toSpendReadings: [
                {
                    year: 2026,
                    month: 1,
                    beforeMinorUnits: 100,
                    afterMinorUnits: -50,
                },
                {
                    year: 2026,
                    month: 2,
                    beforeMinorUnits: 100,
                    afterMinorUnits: 90,
                },
            ],
            limit: reading('Limit', 'Ponad limit.'),
            accountBalance: reading('Konto', 'Saldo ujemne.'),
            termDepositBalance: reading('Lokata', 'Lokata ujemna.'),
            monthRemainingWarnings: ['Miesiąc ponad plan.'],
            texts,
        });

        expect(preview.rows.map((row) => row.warnings)).toEqual([
            ['Do wydania poniżej zera.'],
            [],
            ['Ponad limit.'],
            ['Saldo ujemne.'],
            ['Lokata ujemna.'],
        ]);
        expect(preview.warnings).toEqual(['Miesiąc ponad plan.']);
    });

    it('does not warn when To Spend is negative but not lower than before', () => {
        const preview = buildOperationPreview({
            toSpendReadings: [
                {
                    year: 2026,
                    month: 1,
                    beforeMinorUnits: -50,
                    afterMinorUnits: -50,
                },
            ],
            texts,
        });

        expect(preview.rows[0].warnings).toEqual([]);
    });

    it('returns nothing for empty input and skips readings without values', () => {
        expect(buildOperationPreview({ toSpendReadings: [], texts })).toEqual({
            rows: [],
            warnings: [],
        });

        const preview = buildOperationPreview({
            toSpendReadings: [],
            accountBalance: {
                label: 'Konto',
                beforeMinorUnits: null,
                afterMinorUnits: null,
                warning: null,
            },
            texts,
        });

        expect(preview.rows).toEqual([]);
    });
});

describe('monthNamesLocativeFrom', () => {
    it('reads the twelve locative names in calendar order', () => {
        const names = monthNamesLocativeFrom({
            month_name_locative_1: 'styczniu',
            month_name_locative_12: 'grudniu',
        });

        expect(names).toHaveLength(12);
        expect(names[0]).toBe('styczniu');
        expect(names[11]).toBe('grudniu');
    });
});
