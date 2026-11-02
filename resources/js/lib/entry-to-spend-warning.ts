export type EntryToSpendCategory = {
    id: number;
    type: 'income' | 'expense';
    month_item_kind: 'bill' | 'running' | null;
};

export type EntryToSpendItem = {
    category_id: number;
    plan_minor_units: number;
    actual_minor_units: number;
    positive_amount_count: number;
};

export type EntryToSpendMonth = {
    month: number;
    items: EntryToSpendItem[];
};

export type EntryToSpendBudget = {
    year: number;
    categories: EntryToSpendCategory[];
    months: EntryToSpendMonth[];
};

export type EntryToSpendInputs = {
    budgets: EntryToSpendBudget[];
};
