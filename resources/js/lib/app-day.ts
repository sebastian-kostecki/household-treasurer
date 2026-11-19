/**
 * Pure helpers over the App Day (`shell.today`, 'YYYY-MM-DD', ADR-0103). They
 * take the day as an argument and never read the browser clock.
 */

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** The calendar year of the App Day. */
export function appDayYear(today: string): number {
    return Number(today.slice(0, 4));
}

/** The calendar month (1-12) of the App Day. */
export function appDayMonth(today: string): number {
    return Number(today.slice(5, 7));
}

/**
 * First and last day of the month `offset` months from the month of the App
 * Day (0 = this month, -1 = the previous one), as 'YYYY-MM-DD'.
 */
export function appDayMonthRange(
    today: string,
    offset: number,
): { from: string; to: string } {
    const monthIndex =
        appDayYear(today) * 12 + (appDayMonth(today) - 1) + offset;
    const year = Math.floor(monthIndex / 12);
    const month = (monthIndex % 12) + 1;
    // Day 0 of the next month is the last day of this one; UTC avoids a DST shift.
    const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();

    return {
        from: `${year}-${pad(month)}-01`,
        to: `${year}-${pad(month)}-${pad(lastDay)}`,
    };
}
