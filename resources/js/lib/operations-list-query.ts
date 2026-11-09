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
