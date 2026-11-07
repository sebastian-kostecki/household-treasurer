import { ref } from 'vue';

const open = ref(false);
const initialYear = ref<number | undefined>(undefined);

/**
 * State of the one "new budget" dialog of the shell (mounted in
 * `AppSidebarLayout`, outside `Sidebar`: on a phone the sidebar content lives
 * in a sheet and is unmounted while it is closed), shared by the year switcher
 * and the command palette.
 */
export function useCreateBudgetDialog() {
    function openForYear(year?: number): void {
        initialYear.value = year;
        open.value = true;
    }

    return { open, initialYear, openForYear };
}
