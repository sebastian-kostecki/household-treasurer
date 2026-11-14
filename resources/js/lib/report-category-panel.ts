import type { RankedReportCategory } from './report-ranking';
import { reportYearsCutLabel } from './report-years';
import type { ReportYearsCut } from './report-years';

/** The selected Category of the panel and the list it stands in. */
export type SelectedReportCategory = {
    category: RankedReportCategory;
    type: 'expense' | 'income';
};

/**
 * The Category the panel shows (ADR-0097): the requested one when the report
 * has it, otherwise the first expense of the Ranking, otherwise the first
 * income, otherwise none. The server only validates the request; the default
 * depends on the Ranking, so it is chosen here from the same lists.
 */
export function resolveSelectedCategory(
    requestedId: number | null,
    expenseRanking: RankedReportCategory[],
    incomeRanking: RankedReportCategory[],
): SelectedReportCategory | null {
    if (requestedId !== null) {
        const expense = expenseRanking.find((row) => row.id === requestedId);

        if (expense !== undefined) {
            return { category: expense, type: 'expense' };
        }

        const income = incomeRanking.find((row) => row.id === requestedId);

        if (income !== undefined) {
            return { category: income, type: 'income' };
        }
    }

    if (expenseRanking.length > 0) {
        return { category: expenseRanking[0], type: 'expense' };
    }

    if (incomeRanking.length > 0) {
        return { category: incomeRanking[0], type: 'income' };
    }

    return null;
}

export type ReportMonthBarState = 'past' | 'in-progress' | 'future';

export type ReportMonthBar = {
    /** 1 to 12. */
    month: number;
    state: ReportMonthBarState;
    /** The cell of the server; null is a month without an operation. */
    amountMinorUnits: number | null;
    /** Height of the bar, 0 to 1; a future, empty or non-positive month is 0. */
    ratio: number;
    /** The `highest_month` of the server; the client computes no tie. */
    isHighest: boolean;
};

/**
 * Twelve bars of the panel. Months up to `closedMonthCount` are past, the
 * month in progress is dashed, the rest are future and drawn empty even when
 * the server sent an amount for them (a current year is not cut, ADR-0074).
 * The scale is the largest positive month that is not future.
 */
export function reportMonthBars(
    months: (number | null)[],
    closedMonthCount: number,
    inProgressMonth: number | null,
    highestMonth: number | null,
): ReportMonthBar[] {
    const states = months.map((_, index): ReportMonthBarState => {
        const month = index + 1;

        if (month <= closedMonthCount) {
            return 'past';
        }

        return month === inProgressMonth ? 'in-progress' : 'future';
    });

    const largest = months.reduce<number>(
        (max, cell, index) =>
            states[index] !== 'future' && cell !== null && cell > max
                ? cell
                : max,
        0,
    );

    return months.map((cell, index) => ({
        month: index + 1,
        state: states[index],
        amountMinorUnits: cell,
        ratio:
            states[index] === 'future' ||
            cell === null ||
            cell <= 0 ||
            largest <= 0
                ? 0
                : Math.min(1, cell / largest),
        isHighest: highestMonth !== null && index + 1 === highestMonth,
    }));
}

/** "sty–paź" for the closed months, a single chip for one month, null for none. */
export function reportClosedRangeLabel(
    closedMonthCount: number,
    monthChips: readonly string[],
): string | null {
    if (closedMonthCount <= 0) {
        return null;
    }

    const last = monthChips[Math.min(closedMonthCount, 12) - 1];

    return closedMonthCount === 1 ? last : `${monthChips[0]}–${last}`;
}

function capitalize(text: string): string {
    return text.charAt(0).toLocaleUpperCase('pl-PL') + text.slice(1);
}

/**
 * "Listopad do 14.11 (w toku)" from `through` (`Y-m-d`); `template` is
 * `reports.panel_in_progress_row` with `:month` and `:date`.
 */
export function reportInProgressLabel(
    inProgressMonth: number,
    through: string,
    monthNames: readonly string[],
    template: string,
): string {
    const [, month, day] = through.split('-');

    return template
        .replace(':month', capitalize(monthNames[inProgressMonth - 1]))
        .replace(':date', `${day}.${month}`);
}

/** Entries of the `sr-only` list: "<month>: <amount>", never for a future month. */
export function reportMonthBarsAccessibleList(
    bars: ReportMonthBar[],
    monthNames: readonly string[],
    formatAmount: (minorUnits: number) => string,
    emptyLabel: string,
): string[] {
    return bars
        .filter((bar) => bar.state !== 'future')
        .map(
            (bar) =>
                `${capitalize(monthNames[bar.month - 1])}: ${
                    bar.amountMinorUnits === null
                        ? emptyLabel
                        : formatAmount(bar.amountMinorUnits)
                }`,
        );
}

export type ReportPanelCaptionLabels = {
    expense: string;
    income: string;
    /** Template with `:name`. */
    group: string;
    /** Template with `:rhythm`. */
    rhythm: string;
};

/** "Wydatek · grupa Codzienne · rytm bieżąca"; income has no Rhythm, no group no group part. */
export function reportPanelCaption(
    type: 'expense' | 'income',
    groupName: string | null,
    rhythmLabel: string | null,
    labels: ReportPanelCaptionLabels,
): string {
    const parts = [type === 'expense' ? labels.expense : labels.income];

    // A function, not a string: a name with `$&` or `$'` is not a replacement pattern.
    if (groupName !== null) {
        parts.push(labels.group.replace(':name', () => groupName));
    }

    if (type === 'expense' && rhythmLabel !== null) {
        parts.push(labels.rhythm.replace(':rhythm', () => rhythmLabel));
    }

    return parts.join(' · ');
}

/** "Operacje: Kino, 2026" from the template with `:name` and `:year`, the name taken as typed. */
export function reportPanelOperationsLabel(
    template: string,
    categoryName: string,
    budgetYear: number,
): string {
    return template
        .replace(':name', () => categoryName)
        .replace(':year', String(budgetYear));
}

export type ReportPanelLayout = 'unknown' | 'aside' | 'sheet';

/**
 * Layout mode from the width of the page wrapper: `unknown` until the first
 * measure (width 0), then `aside` at or above the breakpoint, else `sheet`.
 */
export function reportPanelLayout(
    widthPx: number,
    breakpointPx: number,
): ReportPanelLayout {
    if (widthPx <= 0) {
        return 'unknown';
    }

    return widthPx >= breakpointPx ? 'aside' : 'sheet';
}

/** Pixels of a CSS length in `rem` ("60rem"); null for anything else. */
export function remTokenToPixels(
    value: string,
    rootFontSizePx: number,
): number | null {
    const match = /^\s*([0-9]*\.?[0-9]+)rem\s*$/.exec(value);

    return match === null ? null : Number(match[1]) * rootFontSizePx;
}

export type ReportYearBarState = 'amount' | 'none';

export type ReportYearBar = {
    year: number;
    /** Height of the bar, 0 to 1; a negative, zero or missing year is 0. */
    ratio: number;
    /** `none` is a year without a Budget Line (a short stroke, "—"). */
    state: ReportYearBarState;
    isCurrent: boolean;
};

/**
 * One bar per column of the panel's years tab (ADR-0101), scaled to the
 * largest positive amount of the row.
 */
export function reportYearBars(
    cells: (number | null)[],
    columns: number[],
    currentYear: number | null,
): ReportYearBar[] {
    const largest = Math.max(0, ...cells.map((cell) => cell ?? 0));

    return columns.map((year, index) => {
        const cell = cells[index] ?? null;

        return {
            year,
            ratio:
                cell === null || cell <= 0 || largest <= 0
                    ? 0
                    : Math.min(1, cell / largest),
            state: cell === null ? 'none' : 'amount',
            isCurrent: year === currentYear,
        };
    });
}

/** Entries of the `sr-only` list: "2020: 10 280,00 zł", a year without a line as "2021: <noLineLabel>"; all columns. */
export function reportYearBarsAccessibleList(
    bars: ReportYearBar[],
    cells: (number | null)[],
    formatAmount: (minorUnits: number) => string,
    noLineLabel: string,
): string[] {
    return bars.map((bar, index) => {
        const cell = cells[index] ?? null;

        return `${bar.year}: ${cell === null ? noLineLabel : formatAmount(cell)}`;
    });
}

/**
 * "Zmiana od 2022" from the template with `:year`; the first column when the
 * change is missing or starts there.
 */
export function reportPanelYearsChangeLabel(
    changeFirstYear: number | null,
    firstColumn: number,
    template: string,
): string {
    return template.replace(':year', String(changeFirstYear ?? firstColumn));
}

/**
 * "2025 · 14 230,00 zł", on a tie "2025, 2026 · 14 230,00 zł", "—" without a
 * highest year. The amount is the cell of the first highest year.
 */
export function reportPanelHighestYearsLabel(
    maxYears: number[],
    cells: (number | null)[],
    columns: number[],
    formatAmount: (minorUnits: number) => string,
): string {
    if (maxYears.length === 0) {
        return '—';
    }

    const cell = cells[columns.indexOf(maxYears[0])] ?? null;

    if (cell === null) {
        return '—';
    }

    return `${maxYears.join(', ')} · ${formatAmount(cell)}`;
}

export type ReportPanelYearsRangeLabels = {
    /** Template with `:date` ("Każdy rok do :date"). */
    sameDay: string;
    full: string;
};

/** "Każdy rok do 14 listopada" in `same_day` (the cut in the format of `@/lib/report-years`), "Pełne lata" in `full`. */
export function reportPanelYearsRangeLabel(
    mode: 'same_day' | 'full',
    cut: ReportYearsCut | null,
    monthGenitive: string | null,
    labels: ReportPanelYearsRangeLabels,
): string {
    if (mode === 'full' || cut === null || monthGenitive === null) {
        return labels.full;
    }

    return labels.sameDay.replace(
        ':date',
        reportYearsCutLabel(cut, monthGenitive, ':day :month'),
    );
}
