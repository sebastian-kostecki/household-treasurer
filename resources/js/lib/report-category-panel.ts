import type { RankedReportCategory } from './report-ranking';

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

    if (groupName !== null) {
        parts.push(labels.group.replace(':name', groupName));
    }

    if (type === 'expense' && rhythmLabel !== null) {
        parts.push(labels.rhythm.replace(':rhythm', rhythmLabel));
    }

    return parts.join(' · ');
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
