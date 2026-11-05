import { describe, expect, it } from 'vite-plus/test';
import { operationsListQuery } from './operations-list-query';

const emptyFilters = {
    q: null,
    year: null,
    category_name: null,
    category_type: null,
    account_id: null,
    term_deposit_id: null,
    date_from: null,
    date_to: null,
};

describe('operationsListQuery', () => {
    it('omits null filters', () => {
        const query = operationsListQuery(
            { ...emptyFilters, q: 'czynsz', account_id: 3 },
            'financial_operations',
        );

        expect(query.q).toBe('czynsz');
        expect(query.account_id).toBe(3);
        expect(query.year).toBeUndefined();
        expect(query.date_from).toBeUndefined();
        expect(query.term_deposit_id).toBeUndefined();
    });

    it('keeps a non-default section', () => {
        expect(operationsListQuery(emptyFilters, 'own_transfers').section).toBe(
            'own_transfers',
        );
        expect(
            operationsListQuery(emptyFilters, 'capital_movements').section,
        ).toBe('capital_movements');
    });

    it('leaves the default section out of the URL', () => {
        expect(
            operationsListQuery(emptyFilters, 'financial_operations').section,
        ).toBeUndefined();
    });

    it('never carries a page number', () => {
        const query = operationsListQuery(emptyFilters, 'own_transfers');

        expect(Object.keys(query)).not.toContain('page');
    });
});
