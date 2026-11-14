import { describe, expect, it } from 'vite-plus/test';
import type { PeriodReportCategory, PeriodReportEntry } from '../types/reports';
import {
    rankReportCategories,
    rankReportGroups,
    reportLargestAmount,
    reportShareBarRatio,
} from './report-ranking';

function category(
    id: number,
    name: string,
    amount: number,
    hasOperations = true,
): PeriodReportCategory {
    return {
        id,
        name,
        icon: null,
        color: 'green',
        amount_minor_units: amount,
        share_tenths_percent: null,
        is_archived: false,
        has_operations: hasOperations,
        previous_minor_units: null,
        difference_minor_units: null,
    };
}

function loose(
    id: number,
    name: string,
    amount: number,
    hasOperations = true,
): PeriodReportEntry {
    return {
        kind: 'category',
        ...category(id, name, amount, hasOperations),
    };
}

function group(
    id: number,
    name: string,
    categories: PeriodReportCategory[],
): PeriodReportEntry {
    return {
        kind: 'group',
        id,
        name,
        amount_minor_units: categories.reduce(
            (sum, c) => sum + c.amount_minor_units,
            0,
        ),
        share_tenths_percent: null,
        has_operations: categories.some((c) => c.has_operations),
        previous_minor_units: null,
        difference_minor_units: null,
        categories,
    };
}

const entries: PeriodReportEntry[] = [
    loose(1, 'Czynsz', 180000),
    group(10, 'Codzienne', [
        category(2, 'Żywność', 90000),
        category(3, 'Prąd', 180000),
        category(4, 'Kino', 0, false),
    ]),
    loose(5, 'Zwrot', -2000),
    loose(6, 'Książki', 5000),
];

describe('rankReportCategories', () => {
    it('sorts every Category descending with positions and group names', () => {
        const ranked = rankReportCategories(entries);

        expect(ranked.map((row) => row.name)).toEqual([
            'Czynsz',
            'Prąd',
            'Żywność',
            'Książki',
            'Zwrot',
            'Kino',
        ]);
        expect(ranked.map((row) => row.position)).toEqual([1, 2, 3, 4, 5, 6]);
        expect(ranked.map((row) => row.groupName)).toEqual([
            null,
            'Codzienne',
            'Codzienne',
            null,
            null,
            'Codzienne',
        ]);
    });

    it('lets a tie go to the order of the list', () => {
        const ranked = rankReportCategories(entries);

        expect(ranked[0].name).toBe('Czynsz');
        expect(ranked[1].name).toBe('Prąd');
    });

    it('puts rows that exist only through the comparison last, in list order', () => {
        const ranked = rankReportCategories([
            loose(1, 'Kino', 0, false),
            loose(2, 'Paliwo', 0, false),
            loose(3, 'Czynsz', 100),
        ]);

        expect(ranked.map((row) => row.name)).toEqual([
            'Czynsz',
            'Kino',
            'Paliwo',
        ]);
    });
});

describe('rankReportGroups', () => {
    it('orders first-level rows and the Categories of a group by amount', () => {
        const ranked = rankReportGroups(entries);

        expect(ranked.map((entry) => entry.name)).toEqual([
            'Codzienne',
            'Czynsz',
            'Książki',
            'Zwrot',
        ]);

        const inGroup = ranked[0];
        expect(inGroup.kind).toBe('group');
        expect(
            inGroup.kind === 'group'
                ? inGroup.categories.map((c) => c.name)
                : [],
        ).toEqual(['Prąd', 'Żywność', 'Kino']);
    });

    it('does not change the input order', () => {
        rankReportGroups(entries);

        expect(entries.map((entry) => entry.name)).toEqual([
            'Czynsz',
            'Codzienne',
            'Zwrot',
            'Książki',
        ]);
    });
});

describe('reportLargestAmount', () => {
    it('is the largest Category flat and the largest first-level row in groups', () => {
        expect(reportLargestAmount(entries, 'categories')).toBe(180000);
        expect(reportLargestAmount(entries, 'groups')).toBe(270000);
    });

    it('is 0 when no amount is positive', () => {
        expect(reportLargestAmount([loose(1, 'Zwrot', -5)], 'categories')).toBe(
            0,
        );
    });
});

describe('reportShareBarRatio', () => {
    it('is the amount over the largest', () => {
        expect(reportShareBarRatio(50, 200)).toBe(0.25);
        expect(reportShareBarRatio(200, 200)).toBe(1);
    });

    it('is 0 for a non-positive amount or a non-positive largest', () => {
        expect(reportShareBarRatio(0, 200)).toBe(0);
        expect(reportShareBarRatio(-30, 200)).toBe(0);
        expect(reportShareBarRatio(50, 0)).toBe(0);
        expect(reportShareBarRatio(50, -10)).toBe(0);
    });
});
