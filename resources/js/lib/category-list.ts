import {
    DEFAULT_OPERATIONS_SECTION,
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
} from './operations-list-query';

/** Limit opisu kategorii listy; ten sam co `max:500` w `listCategoryRules()`. */
export const CATEGORY_DESCRIPTION_MAX_LENGTH = 500;

/**
 * Cel linku „Operacje →” w widoku Kategorie: operacje tej kategorii listy we
 * wszystkich latach, więc bez zakresu dat ani roku.
 */
export function categoryAllYearsOperationsQuery(category: {
    id: number;
}): Record<string, string | number | undefined> {
    return operationsListQuery(
        { ...EMPTY_OPERATIONS_FILTERS, category_id: category.id },
        DEFAULT_OPERATIONS_SECTION,
    );
}

/** Lata, w których kategoria ma pozycję, np. „2026 · 2027”. */
export function categoryYearsLabel(years: number[]): string {
    return years.join(' · ');
}

/** Polska odmiana liczebnika: 1 aktywna, 2–4 aktywne, reszta aktywnych. */
export function activeCountForm(count: number): 'one' | 'few' | 'many' {
    if (count === 1) {
        return 'one';
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        !(lastTwoDigits >= 12 && lastTwoDigits <= 14)
    ) {
        return 'few';
    }

    return 'many';
}
