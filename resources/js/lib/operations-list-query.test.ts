import { describe, expect, it } from 'vite-plus/test';
import {
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
} from './operations-list-query';

describe('operationsListQuery', () => {
    it('omits null filters', () => {
        const query = operationsListQuery(
            { ...EMPTY_OPERATIONS_FILTERS, q: 'czynsz', account_id: 3 },
            'financial_operations',
        );

        expect(query.q).toBe('czynsz');
        expect(query.account_id).toBe(3);
        expect(query.year).toBeUndefined();
        expect(query.date_from).toBeUndefined();
        expect(query.term_deposit_id).toBeUndefined();
    });

    it('keeps a non-default section', () => {
        expect(
            operationsListQuery(EMPTY_OPERATIONS_FILTERS, 'own_transfers')
                .section,
        ).toBe('own_transfers');
        expect(
            operationsListQuery(EMPTY_OPERATIONS_FILTERS, 'capital_movements')
                .section,
        ).toBe('capital_movements');
    });

    it('leaves the default section out of the URL', () => {
        expect(
            operationsListQuery(
                EMPTY_OPERATIONS_FILTERS,
                'financial_operations',
            ).section,
        ).toBeUndefined();
    });

    it('never carries a page number', () => {
        const query = operationsListQuery(
            EMPTY_OPERATIONS_FILTERS,
            'own_transfers',
        );

        expect(Object.keys(query)).not.toContain('page');
    });
});
