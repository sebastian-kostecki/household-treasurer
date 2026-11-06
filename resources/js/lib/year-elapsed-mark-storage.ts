export const YEAR_ELAPSED_MARK_KEY = 'dashboard-year-elapsed';

function defaultStorage(): Storage | null {
    return typeof window === 'undefined' ? null : (window.localStorage ?? null);
}

export function readYearElapsedMark(
    storage?: Pick<Storage, 'getItem'> | null,
): boolean {
    try {
        const source = storage === undefined ? defaultStorage() : storage;

        if (source === null) {
            return true;
        }

        return source.getItem(YEAR_ELAPSED_MARK_KEY) !== 'false';
    } catch (error) {
        console.warn('Could not read the year elapsed mark setting.', error);

        return true;
    }
}

export function writeYearElapsedMark(
    value: boolean,
    storage?: Pick<Storage, 'setItem'> | null,
): void {
    try {
        const target = storage === undefined ? defaultStorage() : storage;

        target?.setItem(YEAR_ELAPSED_MARK_KEY, value ? 'true' : 'false');
    } catch (error) {
        console.warn('Could not save the year elapsed mark setting.', error);
    }
}
