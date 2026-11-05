/**
 * Format minor units as a Polish amount without the unit, e.g. 123456 -> "1 234,56"
 * (table cells; the unit goes in the panel footer, ADR-0037).
 */
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
 * Format minor units as Polish PLN string, e.g. 123456 -> "1 234,56 zł"
 */
export function formatMoney(minorUnits: number): string {
    return `${formatMoneyAmount(minorUnits)} zł`;
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
 * Format minor units as signed Polish PLN for financial operations.
 */
export function formatFinancialOperationAmount(
    type: 'income' | 'expense',
    amountMinorUnits: number,
): string {
    const formatted = formatMoney(Math.abs(amountMinorUnits));

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

    return 'text-destructive';
}
