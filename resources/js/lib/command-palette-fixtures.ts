import type { PaletteCategories } from '@/components/CommandPalette.vue';

/** Fixed categories for the palette states of `/dev/ui/sidebar` (kitchen sink). */
export const paletteFixtureCategories: PaletteCategories = {
    status: 'loaded',
    budgetYear: 2026,
    items: [
        {
            id: 1,
            name: 'Dom',
            type: 'expense',
            icon: 'house',
            color: '#3b82f6',
            execution_minor_units: 2988000,
        },
        {
            id: 2,
            name: 'Żywność',
            type: 'expense',
            icon: 'shopping-cart',
            color: '#22c55e',
            execution_minor_units: 1254050,
        },
        {
            id: 3,
            name: 'Lokaty i oszczędności',
            type: 'expense',
            icon: 'piggy-bank',
            color: '#f59e0b',
            execution_minor_units: 50000,
        },
        {
            id: 4,
            name: 'Pensja',
            type: 'income',
            icon: 'wallet',
            color: '#14b8a6',
            execution_minor_units: 7200000,
        },
    ],
};
