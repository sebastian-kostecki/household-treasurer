import type { ComputedRef, InjectionKey } from 'vue';
import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import type { OperationsListFilters } from '@/components/OperationsFilters.vue';

export const DEFAULT_OPERATIONS_SECTION: OperationsListSection =
    'financial_operations';

export const EMPTY_OPERATIONS_FILTERS: Readonly<OperationsListFilters> =
    Object.freeze({
        q: null,
        year: null,
        category_id: null,
        account_id: null,
        term_deposit_id: null,
        date_from: null,
        date_to: null,
    });

/**
 * Jedyne miejsce budowania query `/operations`: filtry (null pomijany) i
 * sekcja (domyślna pomijana, żeby URL był krótki). Bez numerów stron.
 */
export function operationsListQuery(
    filters: OperationsListFilters,
    section: OperationsListSection,
): Record<string, string | number | undefined> {
    return {
        q: filters.q ?? undefined,
        year: filters.year ?? undefined,
        category_id: filters.category_id ?? undefined,
        account_id: filters.account_id ?? undefined,
        term_deposit_id: filters.term_deposit_id ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        section: section === DEFAULT_OPERATIONS_SECTION ? undefined : section,
    };
}

export type OperationsReturnQuery = {
    list: Record<string, string | number | undefined>;
};

/**
 * Stan listy, z której wykonano zapis: filtry, zakładka i numery stron > 1.
 * Idzie w `?list[...]`, nigdy jako klucze najwyższego poziomu (wpadłyby do
 * danych formularza); serwer czyta tylko `list`.
 */
export function operationsReturnQuery(
    filters: OperationsListFilters,
    section: OperationsListSection,
    pages: {
        operations: number;
        own_transfers: number;
        capital_movements: number;
    },
): OperationsReturnQuery {
    return {
        list: {
            ...operationsListQuery(filters, section),
            operations_page:
                pages.operations > 1 ? pages.operations : undefined,
            own_transfers_page:
                pages.own_transfers > 1 ? pages.own_transfers : undefined,
            capital_movements_page:
                pages.capital_movements > 1
                    ? pages.capital_movements
                    : undefined,
        },
    };
}

export const OPERATIONS_RETURN_QUERY: InjectionKey<
    ComputedRef<OperationsReturnQuery>
> = Symbol('operations-return-query');
