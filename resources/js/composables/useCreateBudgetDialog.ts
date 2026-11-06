import { ref } from 'vue';

const open = ref(false);
const initialYear = ref<number | undefined>(undefined);

/**
 * State of the one "new budget" dialog of the shell (mounted in `AppSidebar`),
 * shared by the year switcher and the command palette.
 */
export function useCreateBudgetDialog() {
    function openForYear(year?: number): void {
        initialYear.value = year;
        open.value = true;
    }

    return { open, initialYear, openForYear };
}
