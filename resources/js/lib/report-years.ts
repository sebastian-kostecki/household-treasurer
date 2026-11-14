/** Pure helpers of the Reports "Lata" view (ADR-0097); the server computes every number. */

export type ReportYearsCut = { month: number; day: number };

/** "Do 14 listopada"; `template` is `t.reports.years_range_same_day`, `monthGenitive` e.g. "listopada". */
export function reportYearsCutLabel(
    cut: ReportYearsCut,
    monthGenitive: string,
    template: string,
): string {
    return template
        .replace(':day', String(cut.day))
        .replace(':month', monthGenitive);
}

/** "14.11", "29.02" */
export function reportYearsCutShort(cut: ReportYearsCut): string {
    return `${cut.day}.${String(cut.month).padStart(2, '0')}`;
}

/** Tenths of a percent as "+19,4%", "−8,3%" (U+2212), "0,0%"; null is "—". */
export function formatYearsChange(tenths: number | null): string {
    if (tenths === null) {
        return '—';
    }

    const absolute = Math.abs(tenths);
    const text = `${Math.floor(absolute / 10)},${absolute % 10}%`;

    if (tenths > 0) {
        return `+${text}`;
    }

    return tenths < 0 ? `−${text}` : text;
}

export type ReportYearsSparkBar = {
    year: number;
    /** 0 to 1, relative to the highest amount of the row. */
    heightRatio: number;
    /** The year has no Budget Line ("—"). */
    isEmpty: boolean;
    isCurrent: boolean;
};

/**
 * One bar per column, scaled to the row's maximum. A missing year is empty, a
 * negative or zero amount is a zero-height bar, and a row without a positive
 * amount is all zeros.
 */
export function reportYearsSparkBars(
    cells: (number | null)[],
    columns: number[],
    currentYear: number | null,
): ReportYearsSparkBar[] {
    const maximum = Math.max(0, ...cells.map((cell) => cell ?? 0));

    return columns.map((year, index) => {
        const cell = cells[index] ?? null;

        return {
            year,
            heightRatio:
                cell === null || cell <= 0 || maximum === 0
                    ? 0
                    : cell / maximum,
            isEmpty: cell === null,
            isCurrent: year === currentYear,
        };
    });
}
