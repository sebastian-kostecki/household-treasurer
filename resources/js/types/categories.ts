export type CategoryListItem = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'income' | 'expense';
    budget_years: number[];
    archived_at: string | null;
};

export type CategoriesPageProps = {
    incomeCategories: CategoryListItem[];
    expenseCategories: CategoryListItem[];
    archivedCategories: CategoryListItem[];
};
