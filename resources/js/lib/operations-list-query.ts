import type { OperationsListSection } from '@/components/OperationsActiveFilterChips.vue';
import type { OperationsListFilters } from '@/components/OperationsFilters.vue';

export const DEFAULT_OPERATIONS_SECTION: OperationsListSection =
    'financial_operations';

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
        category_name: filters.category_name ?? undefined,
        category_type: filters.category_type ?? undefined,
        account_id: filters.account_id ?? undefined,
        term_deposit_id: filters.term_deposit_id ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        section: section === DEFAULT_OPERATIONS_SECTION ? undefined : section,
    };
}
