export type FirstRunState = {
    hasAccount: boolean;
    hasBudget: boolean;
    hasCategories: boolean;
    executionStarted: boolean;
};

export type ShellState = {
    budgetYear: number | null;
    budgetYears: number[];
    firstRun: FirstRunState;
};

export type UiTranslations = {
    nav: {
        dashboard: string;
        budget: string;
        accounts: string;
        operations: string;
        settings: string;
        menu: string;
    };
    shell: {
        budget_year: string;
        no_budget_year: string;
        add_budget_year: string;
        select_year: string;
    };
    auth: Record<string, Record<string, string>>;
    settings: Record<string, string>;
    dashboard: Record<string, string>;
    budget: Record<string, string>;
    accounts: Record<string, string>;
    operations: Record<string, string>;
    common: Record<string, string>;
};
