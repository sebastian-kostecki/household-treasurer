import type { Rhythm } from './rhythm';

export type CategoryListItem = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    /** The Category Group the Category stands in (ADR-0069), null when it has none. */
    group_id: number | null;
    /** Its place in the type's sequence, shared with the groups. */
    sort_order: number;
    description: string | null;
    /** The Rhythm in the current month (ADR-0070), null for an income Category. */
    rhythm: Rhythm | null;
    budget_years: number[];
    can_change_type: boolean;
    can_delete: boolean;
    archived_at: string | null;
};

/** A Category Group of one type; it has no plan and no settings (ADR-0063). */
export type CategoryGroupItem = {
    id: number;
    name: string;
    sort_order: number;
    /** Archived Categories in the group; the view lists only active ones under it. */
    archived_count: number;
};

export type CategoriesPageProps = {
    incomeCategories: CategoryListItem[];
    expenseCategories: CategoryListItem[];
    archivedCategories: CategoryListItem[];
    incomeGroups: CategoryGroupItem[];
    expenseGroups: CategoryGroupItem[];
    categoryAppearanceOptions: { icons: string[]; colors: string[] };
    /** The month a Rhythm change made on the list starts in (the current month). */
    rhythmChangeMonth: { year: number; month: number };
};
