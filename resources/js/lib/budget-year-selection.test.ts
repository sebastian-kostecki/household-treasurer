import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const error = vi.hoisted(() => vi.fn());
const on = vi.hoisted(() => vi.fn());

vi.mock('vue-sonner', () => ({ toast: { error } }));
vi.mock('@inertiajs/vue3', () => ({ router: { on } }));

import {
    listenForBudgetYearSelectionFailure,
    notifyBudgetYearSelectionFailed,
} from './budget-year-selection';

describe('notifyBudgetYearSelectionFailed', () => {
    beforeEach(() => {
        error.mockClear();
    });

    it('shows the message as an error toast', () => {
        notifyBudgetYearSelectionFailed('Nie udało się');

        expect(error).toHaveBeenCalledExactlyOnceWith('Nie udało się');
    });
});

describe('listenForBudgetYearSelectionFailure', () => {
    const handlers: Record<string, () => void> = {};
    const off = { httpException: vi.fn(), networkError: vi.fn() };

    beforeEach(() => {
        error.mockClear();
        off.httpException.mockClear();
        off.networkError.mockClear();
        on.mockReset();
        on.mockImplementation(
            (event: 'httpException' | 'networkError', handler: () => void) => {
                handlers[event] = handler;

                return off[event];
            },
        );
    });

    it('toasts on HTTP and network errors while a year selection is pending', () => {
        listenForBudgetYearSelectionFailure(() => true, 'Błąd roku');

        handlers.httpException();
        handlers.networkError();

        expect(error).toHaveBeenCalledTimes(2);
        expect(error).toHaveBeenCalledWith('Błąd roku');
    });

    it('stays silent when no year selection is pending', () => {
        listenForBudgetYearSelectionFailure(() => false, 'Błąd roku');

        handlers.httpException();
        handlers.networkError();

        expect(error).not.toHaveBeenCalled();
    });

    it('unsubscribes from both router events', () => {
        const stop = listenForBudgetYearSelectionFailure(
            () => true,
            'Błąd roku',
        );

        stop();

        expect(off.httpException).toHaveBeenCalledOnce();
        expect(off.networkError).toHaveBeenCalledOnce();
    });
});
