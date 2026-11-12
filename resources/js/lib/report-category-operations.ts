import { categoryOperationsQuery } from './dashboard-categories';
import {
    DEFAULT_OPERATIONS_SECTION,
    EMPTY_OPERATIONS_FILTERS,
    operationsListQuery,
} from './operations-list-query';

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Cel linku z nazwy kategorii w raporcie: operacje tej kategorii w okresie
 * raportu (miesiąc albo cały rok budżetu). `null`, gdy wiersz nie ma kwoty
 * w bieżącym okresie (istnieje tylko przez porównanie), więc nie ma dokąd
 * prowadzić. Wiersz bez `has_operations` (Trend) ma operacje w roku.
 */
export function reportCategoryOperationsQuery(
    category: { id: number; has_operations?: boolean },
    year: number,
    month: number | null,
): Record<string, string | number | undefined> | null {
    if (category.has_operations === false) {
        return null;
    }

    if (month === null) {
        return categoryOperationsQuery(category, year);
    }

    // UTC, żeby strefa czasowa przeglądarki nie przesunęła ostatniego dnia.
    const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();

    return operationsListQuery(
        {
            ...EMPTY_OPERATIONS_FILTERS,
            category_id: category.id,
            date_from: `${year}-${pad(month)}-01`,
            date_to: `${year}-${pad(month)}-${pad(lastDay)}`,
        },
        DEFAULT_OPERATIONS_SECTION,
    );
}

/**
 * Dostępna nazwa linku z szablonu `:name` / `:period`. Jedno przejście i
 * funkcja zastępująca: nazwa kategorii od użytkownika nie jest ani
 * interpretowana (`$&`), ani ponownie podstawiana (tekst `:period`).
 */
export function reportOperationsLinkLabel(
    template: string,
    name: string,
    period: string,
): string {
    return template.replace(/:(name|period)/g, (_, key: string) =>
        key === 'name' ? name : period,
    );
}
