import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ShellState, UiTranslations } from '@/types/shell';

export function useTranslations(): UiTranslations {
    const page = usePage();

    return page.props.translations as UiTranslations;
}

export function useShell(): ShellState {
    const page = usePage();

    return page.props.shell as ShellState;
}

export function useFirstRunChecklist() {
    const shell = useShell();

    return computed(() => [
        {
            key: 'hasAccount',
            labelKey: 'checklist_account' as const,
            done: shell.firstRun.hasAccount,
        },
        {
            key: 'hasBudget',
            labelKey: 'checklist_budget' as const,
            done: shell.firstRun.hasBudget,
        },
        {
            key: 'hasCategories',
            labelKey: 'checklist_categories' as const,
            done: shell.firstRun.hasCategories,
        },
        {
            key: 'executionStarted',
            labelKey: 'checklist_execution' as const,
            done: shell.firstRun.executionStarted,
        },
    ]);
}
