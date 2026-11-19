export type AuthYearMonth = 'past' | 'current' | 'future';

export type AuthYearProgress = {
    year: number;
    elapsedPercent: number;
    months: AuthYearMonth[];
};

const MS_PER_DAY = 86_400_000;

/**
 * Year block of the entry-screen panel (ADR-0056), computed from the App Day
 * (`shell.today`, 'YYYY-MM-DD'): the share of days elapsed (the current day counts) and the state of
 * each of the twelve months.
 */
export function authYearProgress(today: string): AuthYearProgress {
    const year = Number(today.slice(0, 4));
    const month = Number(today.slice(5, 7)) - 1;
    const day = Number(today.slice(8, 10));

    // Calendar days via UTC so a DST change cannot shift the count.
    const startOfYear = Date.UTC(year, 0, 1);
    const startOfNextYear = Date.UTC(year + 1, 0, 1);
    const dayOfYear =
        (Date.UTC(year, month, day) - startOfYear) / MS_PER_DAY + 1;
    const daysInYear = (startOfNextYear - startOfYear) / MS_PER_DAY;

    return {
        year,
        elapsedPercent: Math.floor((dayOfYear / daysInYear) * 100),
        months: Array.from({ length: 12 }, (_, index) => {
            if (index < month) {
                return 'past';
            }

            return index === month ? 'current' : 'future';
        }),
    };
}
