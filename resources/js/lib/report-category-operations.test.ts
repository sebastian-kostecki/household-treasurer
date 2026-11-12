import { describe, expect, it } from 'vite-plus/test';
import { categoryOperationsQuery } from './dashboard-categories';
import {
    reportCategoryOperationsQuery,
    reportOperationsLinkLabel,
} from './report-category-operations';

const food = { id: 5, has_operations: true };

describe('reportCategoryOperationsQuery', () => {
    it('points at the month for a month report', () => {
        expect(reportCategoryOperationsQuery(food, 2026, 3)).toEqual({
            q: undefined,
            year: undefined,
            category_id: 5,
            account_id: undefined,
            term_deposit_id: undefined,
            date_from: '2026-03-01',
            date_to: '2026-03-31',
            section: undefined,
        });
    });

    it('ends February on the 28th in an ordinary year', () => {
        expect(reportCategoryOperationsQuery(food, 2026, 2)?.date_to).toBe(
            '2026-02-28',
        );
    });

    it('ends February on the 29th in a leap year', () => {
        expect(reportCategoryOperationsQuery(food, 2028, 2)?.date_to).toBe(
            '2028-02-29',
        );
    });

    it('pads January and December with zeros', () => {
        const january = reportCategoryOperationsQuery(food, 2026, 1);
        const december = reportCategoryOperationsQuery(food, 2026, 12);

        expect([january?.date_from, january?.date_to]).toEqual([
            '2026-01-01',
            '2026-01-31',
        ]);
        expect([december?.date_from, december?.date_to]).toEqual([
            '2026-12-01',
            '2026-12-31',
        ]);
    });

    it('points at the whole budget year when there is no month', () => {
        expect(reportCategoryOperationsQuery(food, 2026, null)).toEqual(
            categoryOperationsQuery(food, 2026),
        );
        expect(reportCategoryOperationsQuery(food, 2026, null)).toMatchObject({
            category_id: 5,
            date_from: '2026-01-01',
            date_to: '2026-12-31',
        });
    });

    it('has no target for a row that exists only through the comparison', () => {
        const comparisonOnly = { id: 5, has_operations: false };

        expect(
            reportCategoryOperationsQuery(comparisonOnly, 2026, 3),
        ).toBeNull();
        expect(
            reportCategoryOperationsQuery(comparisonOnly, 2026, null),
        ).toBeNull();
    });

    it('treats a row without has_operations (a Trend row) as having operations', () => {
        expect(
            reportCategoryOperationsQuery({ id: 5 }, 2026, null),
        ).toMatchObject({ category_id: 5, date_from: '2026-01-01' });
    });

    it('leaves the other filters unset', () => {
        const month = reportCategoryOperationsQuery(food, 2026, 3);
        const year = reportCategoryOperationsQuery(food, 2026, null);

        for (const query of [month, year]) {
            expect(query?.q).toBeUndefined();
            expect(query?.year).toBeUndefined();
            expect(query?.account_id).toBeUndefined();
            expect(query?.term_deposit_id).toBeUndefined();
            expect(query?.section).toBeUndefined();
        }
    });
});

describe('reportOperationsLinkLabel', () => {
    const template = 'Operacje kategorii :name, :period';

    it('fills the name and the period', () => {
        expect(
            reportOperationsLinkLabel(template, 'Jedzenie', 'marzec 2026'),
        ).toBe('Operacje kategorii Jedzenie, marzec 2026');
    });

    it('keeps a user-supplied name literal', () => {
        expect(reportOperationsLinkLabel(template, 'A$&B', '2026')).toBe(
            'Operacje kategorii A$&B, 2026',
        );
        expect(
            reportOperationsLinkLabel(template, 'Raty :period', '2026'),
        ).toBe('Operacje kategorii Raty :period, 2026');
    });
});
