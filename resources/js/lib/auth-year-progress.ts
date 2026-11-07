export type AuthYearMonth = 'past' | 'current' | 'future';

export type AuthYearProgress = {
    year: number;
    elapsedPercent: number;
    months: AuthYearMonth[];
};

const MS_PER_DAY = 86_400_000;

/**
 * Year block of the entry-screen panel (ADR-0056), computed from the local
 * date: the share of days elapsed (the current day counts) and the state of
 * each of the twelve months.
 */
export function authYearProgress(today: Date): AuthYearProgress {
    const year = today.getFullYear();
    const month = today.getMonth();

    // Calendar days via UTC so a DST change cannot shift the count.
    const startOfYear = Date.UTC(year, 0, 1);
    const startOfNextYear = Date.UTC(year + 1, 0, 1);
    const dayOfYear =
        (Date.UTC(year, month, today.getDate()) - startOfYear) / MS_PER_DAY + 1;
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
