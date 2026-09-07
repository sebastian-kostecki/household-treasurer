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
 * Format a date as DD.MM.RRRR
 */
export function formatDate(date: Date | string): string {
    const value = typeof date === 'string' ? new Date(date) : date;
    const day = value.getDate().toString().padStart(2, '0');
    const month = (value.getMonth() + 1).toString().padStart(2, '0');
    const year = value.getFullYear();

    return `${day}.${month}.${year}`;
}
