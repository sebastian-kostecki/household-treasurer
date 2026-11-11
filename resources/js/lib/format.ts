/**
 * Format minor units as a Polish amount without the unit, e.g. 123456 -> "1 234,56"
 * (table cells; the unit goes in the panel footer, ADR-0037).
 */
export const MONEY_UNIT = 'zł';

export function formatMoneyAmount(minorUnits: number): string {
    const negative = minorUnits < 0;
    const absolute = Math.abs(minorUnits);
    const zl = Math.floor(absolute / 100);
    const gr = absolute % 100;
    const formattedZl = zl.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const sign = negative ? '-' : '';

    return `${sign}${formattedZl},${gr.toString().padStart(2, '0')}`;
}

/**
 * Round minor units to whole zloty for display, half away from zero, on
 * integers (ADR-0077). Never returns -0.
 */
function wholeZloty(minorUnits: number): number {
    const zloty =
        Math.sign(minorUnits) * Math.floor((Math.abs(minorUnits) + 50) / 100);

    return zloty === 0 ? 0 : zloty;
}

/**
 * Format minor units as whole zloty without the unit, e.g. 1234567 -> "12 346"
 * (the Report Trend tables, ADR-0077).
 */
export function formatWholeMoneyAmount(minorUnits: number): string {
    const zloty = wholeZloty(minorUnits);
    const formatted = Math.abs(zloty)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return zloty < 0 ? `-${formatted}` : formatted;
}

/**
 * Like formatWholeMoneyAmount, with the unit (the trend chart axis).
 */
export function formatWholeMoney(minorUnits: number): string {
    return `${formatWholeMoneyAmount(minorUnits)} ${MONEY_UNIT}`;
}

/**
 * Format minor units as Polish PLN string, e.g. 123456 -> "1 234,56 zł"
 */
export function formatMoney(minorUnits: number): string {
    return `${formatMoneyAmount(minorUnits)} ${MONEY_UNIT}`;
}

/**
 * Format minor units with an explicit plus for positive values,
 * e.g. 123456 -> "+1 234,56 zł". Negative and zero match formatMoney.
 */
export function formatSignedMoney(minorUnits: number): string {
    const formatted = formatMoney(minorUnits);

    return minorUnits > 0 ? `+${formatted}` : formatted;
}

/**
 * Like formatSignedMoney, without the unit (table cells, ADR-0037).
 */
export function formatSignedMoneyAmount(minorUnits: number): string {
    const formatted = formatMoneyAmount(minorUnits);

    return minorUnits > 0 ? `+${formatted}` : formatted;
}

/**
 * Format minor units for editable PLN input, e.g. 123456 -> "1234,56"
 */
export function minorUnitsToMoneyInput(minorUnits: number): string {
    const negative = minorUnits < 0;
    const absolute = Math.abs(minorUnits);
    const zl = Math.floor(absolute / 100);
    const gr = absolute % 100;
    const formatted = `${zl},${gr.toString().padStart(2, '0')}`;

    return negative ? `-${formatted}` : formatted;
}

/**
 * Parse Polish PLN input to integer minor units.
 */
export function parseMoneyInputToMinorUnits(value: string): number | null {
    const normalized = value.trim().replace(/\s/g, '').replace(',', '.');

    if (!/^-?\d+(\.\d{0,2})?$/.test(normalized)) {
        return null;
    }

    const negative = normalized.startsWith('-');
    const absolute = normalized.replace('-', '');
    const [zlPart, grPart = '0'] = absolute.split('.');
    const zl = Number.parseInt(zlPart, 10);
    const gr = Number.parseInt(grPart.padEnd(2, '0').slice(0, 2), 10);
    const minorUnits = zl * 100 + gr;

    if (!Number.isSafeInteger(minorUnits)) {
        return null;
    }

    return negative ? -minorUnits : minorUnits;
}

/**
 * Format a date as DD.MM.RRRR
 */
export function formatDate(date: Date | string): string {
    const value =
        typeof date === 'string' ? new Date(`${date}T12:00:00`) : date;
    const day = value.getDate().toString().padStart(2, '0');
    const month = (value.getMonth() + 1).toString().padStart(2, '0');
    const year = value.getFullYear();

    return `${day}.${month}.${year}`;
}

/**
 * Format a date relative to today using Polish locale.
 */
export function formatRelativeDate(date: Date | string): string {
    const value =
        typeof date === 'string' ? new Date(`${date}T12:00:00`) : date;
    const today = new Date();
    const startOfToday = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    const startOfValue = new Date(
        value.getFullYear(),
        value.getMonth(),
        value.getDate(),
    );
    const diffDays = Math.round(
        (startOfValue.getTime() - startOfToday.getTime()) /
            (1000 * 60 * 60 * 24),
    );

    const formatter = new Intl.RelativeTimeFormat('pl', { numeric: 'auto' });

    const divisions: Array<{
        amount: number;
        unit: Intl.RelativeTimeFormatUnit;
    }> = [
        { amount: 365, unit: 'year' },
        { amount: 30, unit: 'month' },
        { amount: 7, unit: 'week' },
        { amount: 1, unit: 'day' },
    ];

    for (const division of divisions) {
        if (Math.abs(diffDays) >= division.amount || division.unit === 'day') {
            const relativeValue = Math.trunc(diffDays / division.amount);

            return formatter.format(relativeValue, division.unit);
        }
    }

    return formatter.format(0, 'day');
}

/**
 * Format a date as a full Polish date with weekday and year,
 * e.g. "czwartek, 5 listopada 2026".
 */
export function formatLongDate(date: Date | string): string {
    const value =
        typeof date === 'string' ? new Date(`${date}T12:00:00`) : date;

    return new Intl.DateTimeFormat('pl-PL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(value);
}

/**
 * Format a date as a Polish day label, e.g. "środa, 4 listopada"; the year is
 * added when it differs from the year of `today`.
 */
export function formatDayLabel(
    date: Date | string,
    today: Date = new Date(),
): string {
    const value =
        typeof date === 'string' ? new Date(`${date}T12:00:00`) : date;
    const options: Intl.DateTimeFormatOptions = {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    };

    if (value.getFullYear() !== today.getFullYear()) {
        options.year = 'numeric';
    }

    return new Intl.DateTimeFormat('pl-PL', options).format(value);
}

/**
 * Format minor units as a signed Polish amount for financial operations,
 * without the unit, e.g. "−1 234,56" (Operations list, ADR-0039, ADR-0058).
 */
export function formatFinancialOperationAmount(
    type: 'income' | 'expense',
    amountMinorUnits: number,
): string {
    const formatted = formatMoneyAmount(Math.abs(amountMinorUnits));

    if (type === 'income' || amountMinorUnits < 0) {
        return `+${formatted}`;
    }

    return `−${formatted}`;
}

/**
 * CSS class for financial operation amount colour.
 */
export function financialOperationAmountClass(
    type: 'income' | 'expense',
    amountMinorUnits: number,
): string {
    if (type === 'income' || amountMinorUnits < 0) {
        return 'text-success';
    }

    return 'text-foreground';
}

/**
 * Format a day sum without the unit (ADR-0058): "+1 234,56" above zero, "−1 234,56" (U+2212) below, "0,00" at zero.
 */
export function formatOperationDaySum(minorUnits: number): string {
    if (minorUnits > 0) {
        return `+${formatMoneyAmount(minorUnits)}`;
    }

    if (minorUnits < 0) {
        return `−${formatMoneyAmount(Math.abs(minorUnits))}`;
    }

    return formatMoneyAmount(0);
}

/**
 * CSS class for a day sum: income in `success`, otherwise `foreground`.
 */
export function operationDaySumClass(minorUnits: number): string {
    return minorUnits > 0 ? 'text-success' : 'text-foreground';
}
