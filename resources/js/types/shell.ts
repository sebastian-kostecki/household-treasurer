export type FirstRunState = {
    hasAccount: boolean;
    hasBudget: boolean;
    hasCategories: boolean;
    executionStarted: boolean;
    showChecklist: boolean;
};

export type CopyableBudgetSource = {
    id: number;
    year: number;
};

export type ShellState = {
    budgetYear: number | null;
    budgetYears: number[];
    copyableBudgetSources: CopyableBudgetSource[];
    firstRun: FirstRunState;
};

export type UiTranslations = {
    nav: {
        dashboard: string;
        budget: string;
        accounts: string;
        term_deposits: string;
        operations: string;
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
    term_deposits: Record<string, string>;
    operations: Record<string, string>;
    common: Record<string, string>;
    landing: Record<string, string>;
};
