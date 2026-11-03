import { router } from '@inertiajs/vue3';

/**
 * Tylko wizyty GET na ścieżkę Operacji (filtry, paginacja), nie POST/PUT/DELETE
 * z dialogów. Inertia podaje metodę małymi literami, więc ją normalizujemy.
 */
export function isOperationsListVisit(
    visit: { method: string; url: URL },
    operationsPath: string,
): boolean {
    return (
        visit.method.toLowerCase() === 'get' &&
        visit.url.pathname === operationsPath
    );
}

/**
 * Zgłasza początek i koniec wizyty listy Operacji. Zwraca funkcję zdejmującą
 * oba nasłuchy.
 */
export function listenForOperationsListVisits(
    operationsPath: string,
    onBusyChange: (busy: boolean) => void,
): () => void {
    let busy = false;

    const offStart = router.on('start', (event) => {
        if (isOperationsListVisit(event.detail.visit, operationsPath)) {
            busy = true;
            onBusyChange(true);
        }
    });
    const offFinish = router.on('finish', () => {
        if (busy) {
            busy = false;
            onBusyChange(false);
        }
    });

    return () => {
        offStart();
        offFinish();
    };
}
