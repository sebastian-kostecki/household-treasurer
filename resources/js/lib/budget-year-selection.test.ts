import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';

const error = vi.hoisted(() => vi.fn());

vi.mock('vue-sonner', () => ({ toast: { error } }));

import { notifyBudgetYearSelectionFailed } from './budget-year-selection';

describe('notifyBudgetYearSelectionFailed', () => {
    beforeEach(() => {
        error.mockClear();
    });

    it('shows the message as an error toast', () => {
        notifyBudgetYearSelectionFailed('Nie udało się');

        expect(error).toHaveBeenCalledExactlyOnceWith('Nie udało się');
    });
});
