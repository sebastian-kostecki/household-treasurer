import { computed, reactive, ref } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';

const state = vi.hoisted(() => ({ page: null as unknown }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => state.page,
}));

import { useFirstRunChecklist, useShell } from './useTranslations';

function makeShell(
    budgetYear: number,
    budgetYears: number[],
    hasBudget: boolean,
) {
    return {
        budgetYear,
        budgetYears,
        firstRun: {
            hasAccount: true,
            hasBudget,
            hasCategories: false,
            executionStarted: false,
            showChecklist: !hasBudget,
        },
    };
}

/**
 * Inertia replaces the whole page object on every visit; `props` is a
 * computed over it, so a held `shell` snapshot goes stale.
 */
function mockInertiaPage(initialShell: ReturnType<typeof makeShell>) {
    const page = ref({ props: { shell: initialShell } });

    state.page = reactive({ props: computed(() => page.value.props) });

    return {
        visit(shell: ReturnType<typeof makeShell>) {
            page.value = { props: { shell } };
        },
    };
}

describe('useShell', () => {
    it('reflects the shell of the current visit after the page object is replaced', () => {
        const inertia = mockInertiaPage(makeShell(2026, [2026], false));
        const shell = useShell();

        expect(shell.value.budgetYear).toBe(2026);

        inertia.visit(makeShell(2027, [2026, 2027], true));

        expect(shell.value.budgetYear).toBe(2027);
        expect(shell.value.budgetYears).toEqual([2026, 2027]);
    });
});

describe('useFirstRunChecklist', () => {
    it('recomputes when the shell firstRun changes with a new visit', () => {
        const inertia = mockInertiaPage(makeShell(2026, [2026], false));
        const checklist = useFirstRunChecklist();

        expect(checklist.value.find((i) => i.key === 'hasBudget')?.done).toBe(
            false,
        );

        inertia.visit(makeShell(2026, [2026], true));

        expect(checklist.value.find((i) => i.key === 'hasBudget')?.done).toBe(
            true,
        );
    });
});
