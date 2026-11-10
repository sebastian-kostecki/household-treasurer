import { router, useHttp, usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { useCreateBudgetDialog } from '@/composables/useCreateBudgetDialog';
import { requestOperationsIntent } from '@/composables/useOperationsIntent';
import { budgetYearSegments } from '@/lib/budget-year-segments';
import { categoryOperationsQuery } from '@/lib/dashboard-categories';
import type {
    PaletteCategories,
    PaletteCategory,
    PaletteItem,
} from '@/lib/command-palette-items';
import { operations } from '@/routes';
import { categories as categoriesRoute } from '@/routes/command-palette';
import { edit as editProfile } from '@/routes/profile';
import CsvImportController from '@/actions/App/Http/Controllers/CsvImportController';
import type { NavItem } from '@/types';
import type { ShellState } from '@/types/shell';

type CategoriesResponse = {
    budget_year: number | null;
    categories: PaletteCategory[];
};

const open = ref(false);

/** Opens the palette from the sidebar button and the ⌘K / Ctrl+K shortcut. */
export function openCommandPalette(): void {
    open.value = true;
}

/**
 * The one palette of the shell: shared `open` state, the categories read on
 * every open and the execution of a chosen item. Used once, by the host
 * component in the app layout.
 */
export function useCommandPalette(views: NavItem[]) {
    const page = usePage();
    const createBudgetDialog = useCreateBudgetDialog();
    const http = useHttp();
    const categories = ref<PaletteCategories>({ status: 'loading' });
    let requestId = 0;

    async function loadCategories(): Promise<void> {
        const currentRequest = ++requestId;

        categories.value = { status: 'loading' };

        try {
            const response = (await http.submit(
                categoriesRoute(),
            )) as CategoriesResponse;

            if (currentRequest !== requestId || !open.value) {
                return;
            }

            categories.value = {
                status: 'loaded',
                budgetYear: response.budget_year,
                items: response.categories,
            };
        } catch (error) {
            // A request cancelled on purpose is not a failure; anything else
            // is logged with its cause.
            if (error instanceof Error && error.name === 'HttpCancelledError') {
                return;
            }

            console.error('Command palette: loading categories failed', error);

            if (currentRequest === requestId && open.value) {
                categories.value = { status: 'error' };
            }
        }
    }

    watch(open, (isOpen) => {
        if (isOpen) {
            void loadCategories();
        } else {
            requestId++;
        }
    });

    function select(item: PaletteItem): void {
        open.value = false;

        switch (item.kind) {
            case 'view':
                router.visit(views[item.index].href);
                break;
            case 'add-operation':
                requestOperationsIntent('add-operation');
                break;
            case 'import':
                router.visit(CsvImportController.create.url());
                break;
            case 'add-budget-year':
                void nextTick(() => {
                    createBudgetDialog.openForYear(nextBudgetYear());
                });
                break;
            case 'settings':
                router.visit(editProfile());
                break;
            case 'category': {
                const state = categories.value;

                if (state.status === 'loaded' && state.budgetYear !== null) {
                    router.visit(
                        operations({
                            query: categoryOperationsQuery(
                                item.category,
                                state.budgetYear,
                            ),
                        }),
                    );
                }
                break;
            }
        }
    }

    function nextBudgetYear(): number | undefined {
        // Read at call time: every visit replaces `page.props`, so a `shell`
        // object kept from the layout's setup can be one visit old.
        const shell = page.props.shell as ShellState;

        return budgetYearSegments(
            shell.budgetYears,
            shell.budgetYear,
            new Date().getFullYear(),
        ).find((segment) => segment.kind === 'create')?.year;
    }

    return { open, categories, select };
}
