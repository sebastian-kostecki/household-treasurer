import { describe, expect, it } from 'vite-plus/test';
import {
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
    operationsReturnQuery,
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

describe('operationsReturnQuery', () => {
    const firstPages = {
        operations: 1,
        own_transfers: 1,
        capital_movements: 1,
    };

    it('has only undefined values for empty filters on the default section', () => {
        const { list } = operationsReturnQuery(
            EMPTY_OPERATIONS_FILTERS,
            'financial_operations',
            firstPages,
        );

        expect(Object.values(list).every((value) => value === undefined)).toBe(
            true,
        );
    });

    it('carries filters, section and pages above 1', () => {
        const { list } = operationsReturnQuery(
            { ...EMPTY_OPERATIONS_FILTERS, category_id: 7 },
            'own_transfers',
            { operations: 3, own_transfers: 2, capital_movements: 1 },
        );

        expect(list.category_id).toBe(7);
        expect(list.section).toBe('own_transfers');
        expect(list.operations_page).toBe(3);
        expect(list.own_transfers_page).toBe(2);
        expect(list.capital_movements_page).toBeUndefined();
    });

    it('omits page 1', () => {
        const { list } = operationsReturnQuery(
            EMPTY_OPERATIONS_FILTERS,
            'capital_movements',
            firstPages,
        );

        expect(list.operations_page).toBeUndefined();
        expect(list.own_transfers_page).toBeUndefined();
        expect(list.capital_movements_page).toBeUndefined();
    });
});
