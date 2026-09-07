/**
 * Format grosze as Polish PLN string, e.g. 123456 -> "1 234,56 zł"
 */
export function formatMoney(grosze: number): string {
    const negative = grosze < 0;
    const absolute = Math.abs(grosze);
    const zl = Math.floor(absolute / 100);
    const gr = absolute % 100;
    const formattedZl = zl.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    const sign = negative ? '-' : '';

    return `${sign}${formattedZl},${gr.toString().padStart(2, '0')} zł`;
}

/**
 * Format grosze for editable PLN input, e.g. 123456 -> "1234,56"
 */
export function groszeToMoneyInput(grosze: number): string {
    const negative = grosze < 0;
    const absolute = Math.abs(grosze);
    const zl = Math.floor(absolute / 100);
    const gr = absolute % 100;
    const formatted = `${zl},${gr.toString().padStart(2, '0')}`;

    return negative ? `-${formatted}` : formatted;
}

/**
 * Parse Polish PLN input to integer grosze.
 */
export function parseMoneyInputToGrosze(value: string): number | null {
    const normalized = value.trim().replace(/\s/g, '').replace(',', '.');

    if (!/^-?\d+(\.\d{0,2})?$/.test(normalized)) {
        return null;
    }

    const negative = normalized.startsWith('-');
    const absolute = normalized.replace('-', '');
    const [zlPart, grPart = '0'] = absolute.split('.');
    const zl = Number.parseInt(zlPart, 10);
    const gr = Number.parseInt(grPart.padEnd(2, '0').slice(0, 2), 10);
    const grosze = zl * 100 + gr;

    return negative ? -grosze : grosze;
}

/**
 * Format a date as DD.MM.RRRR
 */
export function formatDate(date: Date | string): string {
    const value = typeof date === 'string' ? new Date(date) : date;
    const day = value.getDate().toString().padStart(2, '0');
    const month = (value.getMonth() + 1).toString().padStart(2, '0');
    const year = value.getFullYear();

    return `${day}.${month}.${year}`;
}
