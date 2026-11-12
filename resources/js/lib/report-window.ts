import type { ReportRange, ReportWindow } from '@/types/reports';

/** The twelve month names (budget.month_name_1 … _12), January first. */
export function reportMonthNamesFrom(
    budgetTranslations: Record<string, string>,
): string[] {
    return Array.from(
        { length: 12 },
        (_, index) => budgetTranslations[`month_name_${index + 1}`] ?? '',
    );
}

/** "2025-11-01" -> "listopad 2025"; `monthNames` is January first. */
function monthYearLabel(date: string, monthNames: readonly string[]): string {
    const [year, month] = date.split('-');

    return `${monthNames[Number(month) - 1]} ${year}`;
}

/**
 * Zakres miesięcy "<miesiąc> <rok> – <miesiąc> <rok>" z dat `Y-m-d`; jeden
 * miesiąc daje jedną etykietę. `template` to `reports.window_range`.
 */
export function reportMonthRangeLabel(
    from: string,
    through: string,
    monthNames: readonly string[],
    template: string,
): string {
    const first = monthYearLabel(from, monthNames);
    const last = monthYearLabel(through, monthNames);

    if (first === last) {
        return first;
    }

    return template.replace(/:(from|through)/g, (_, key: string) =>
        key === 'from' ? first : last,
    );
}

/** Zakres całego okna 12 miesięcy, np. "listopad 2025 – październik 2026". */
export function reportWindowLabel(
    window: Pick<ReportWindow, 'from' | 'through'>,
    monthNames: readonly string[],
    template: string,
): string {
    return reportMonthRangeLabel(
        window.from,
        window.through,
        monthNames,
        template,
    );
}

/** The range of the category links of the window, with its label for `aria-label`. */
export function reportWindowRange(
    window: Pick<ReportWindow, 'from' | 'through'>,
    monthNames: readonly string[],
    template: string,
): ReportRange {
    return {
        from: window.from,
        through: window.through,
        label: reportWindowLabel(window, monthNames, template),
    };
}

/**
 * Notka pokrycia okna (ADR-0081): "Bez budżetu w realizacji: :missing. Kwoty
 * obejmują :covered (:count z 12 miesięcy)." Tylko przy częściowym pokryciu
 * (0 < covered_month_count < 12), inaczej `null`; zakresy przychodzą z serwera.
 */
export function reportCoverageNote(
    window: ReportWindow,
    monthNames: readonly string[],
    templates: { note: string; range: string },
): string | null {
    const {
        covered_from: coveredFrom,
        covered_through: coveredThrough,
        missing_from: missingFrom,
        missing_through: missingThrough,
        covered_month_count: count,
    } = window;

    if (
        count <= 0 ||
        count >= 12 ||
        coveredFrom === null ||
        coveredThrough === null ||
        missingFrom === null ||
        missingThrough === null
    ) {
        return null;
    }

    const values: Record<string, string> = {
        missing: reportMonthRangeLabel(
            missingFrom,
            missingThrough,
            monthNames,
            templates.range,
        ),
        covered: reportMonthRangeLabel(
            coveredFrom,
            coveredThrough,
            monthNames,
            templates.range,
        ),
        count: String(count),
    };

    return templates.note.replace(
        /:(missing|covered|count)/g,
        (_, key: string) => values[key] ?? '',
    );
}
