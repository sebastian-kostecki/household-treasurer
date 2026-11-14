export type FirstRunState = {
    hasAccount: boolean;
    hasBudget: boolean;
    hasCategories: boolean;
    executionStarted: boolean;
    showChecklist: boolean;
};

export type BudgetYearEntry = {
    year: number;
    phase: 'draft' | 'execution';
};

export type ShellState = {
    budgetYear: number | null;
    budgetYears: BudgetYearEntry[];
    activeCategoryCount: number;
    firstRun: FirstRunState;
};

export type UiTranslations = {
    nav: {
        dashboard: string;
        budget: string;
        categories: string;
        month_plan: string;
        accounts: string;
        term_deposits: string;
        operations: string;
        reports: string;
        menu: string;
    };
    shell: {
        budget_year: string;
        add_budget_for_year: string;
        add_year: string;
        open_menu: string;
        search_and_go: string;
        select_year_failed: string;
        shortcuts_hint: string;
        year_current: string;
        year_draft: string;
        years_recent: string;
        years_older: string;
        add_year_source: string;
    };
    palette: Record<string, string>;
    auth: Record<string, Record<string, string>>;
    settings: Record<string, string>;
    dashboard: Record<string, string>;
    budget: Record<string, string>;
    month_plan: Record<string, string>;
    reports: Record<string, string>;
    accounts: Record<string, string>;
    categories: Record<string, string>;
    term_deposits: Record<string, string>;
    operations: Record<string, string>;
    common: Record<string, string>;
    landing: Record<string, string>;
};
