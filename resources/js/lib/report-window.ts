import type { ReportWindow } from '@/types/reports';

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
