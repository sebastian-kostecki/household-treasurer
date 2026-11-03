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
    let inFlight = 0;

    const offStart = router.on('start', (event) => {
        if (isOperationsListVisit(event.detail.visit, operationsPath)) {
            inFlight++;
            onBusyChange(true);
        }
    });
    const offFinish = router.on('finish', (event) => {
        if (
            inFlight > 0 &&
            isOperationsListVisit(event.detail.visit, operationsPath)
        ) {
            inFlight--;

            if (inFlight === 0) {
                onBusyChange(false);
            }
        }
    });

    return () => {
        offStart();
        offFinish();
    };
}
