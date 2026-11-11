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
    /** The Rhythm in the current month (ADR-0072), null for an income Category. */
    rhythm: Rhythm | null;
    /** Opening Collected of the Irregular period in force (ADR-0073), null for any other Rhythm. */
    opening_collected_minor_units: number | null;
    /** The month the Irregular period in force started in, null for any other Rhythm. */
    irregular_since: { year: number; month: number } | null;
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

/** The preview of merging one Category into another (ADR-0076): `CategoryMergeReading::toArray()`. */
export type CategoryMergePreviewData = {
    source: { id: number; name: string; archived: boolean };
    target: { id: number; name: string };
    /** Budgets the source has a Budget Line in, oldest year first. */
    budgets: {
        year: number;
        phase: 'draft' | 'execution';
        operations_count: number;
        target_plan_before_minor_units: number;
        target_plan_after_minor_units: number;
    }[];
    operations_count: number;
    month_plan_items: {
        count: number;
        first: { year: number; month: number } | null;
        last: { year: number; month: number } | null;
    };
    /** Null when the source has no Collected and the target is not Irregular. */
    collected: {
        source_minor_units: number;
        target_minor_units: number | null;
        after_minor_units: number | null;
        since: { year: number; month: number };
    } | null;
    /** The year of the Budget Line an archived source gets back, null otherwise. */
    restored_budget_year: number | null;
};
