/**
 * Format a share in tenths of a percent, e.g. 333 -> "33,3%", -133 -> "−13,3%";
 * null (no share) -> "—".
 */
export function formatShareTenths(value: number | null): string {
    if (value === null) {
        return '—';
    }

    const absolute = Math.abs(value);
    const whole = Math.floor(absolute / 10);
    const tenth = absolute % 10;
    const sign = value < 0 ? '−' : '';

    return `${sign}${whole},${tenth}%`;
}
