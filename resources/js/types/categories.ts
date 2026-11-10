export type CategoryListItem = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    description: string | null;
    budget_years: number[];
    can_change_type: boolean;
    can_delete: boolean;
    archived_at: string | null;
};

export type CategoriesPageProps = {
    incomeCategories: CategoryListItem[];
    expenseCategories: CategoryListItem[];
    archivedCategories: CategoryListItem[];
    categoryAppearanceOptions: { icons: string[]; colors: string[] };
};
