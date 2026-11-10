export type CategoryListItem = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    /** The Category Group the Category stands in (ADR-0066), null when it has none. */
    group_id: number | null;
    /** Its place in the type's sequence, shared with the groups. */
    sort_order: number;
    budget_years: number[];
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
};
