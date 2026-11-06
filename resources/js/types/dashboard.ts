import type { DashboardNotice } from '@/components/DashboardNotices.vue';
import type { AccountBank } from '@/lib/account-identity';
import type {
    DashboardCategory,
    DashboardTypeTotals,
} from '@/lib/dashboard-categories';
import type { MonthPace } from '@/types/month-plan';

export type AccountSummary = {
    id: number;
    name: string;
    type: 'bank' | 'cash';
    bank: AccountBank | null;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

export type TermDepositSummary = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    end_date: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
};

export type BudgetSummary = {
    year: number;
    phase: 'draft' | 'execution';
};

export type PlanSummary = {
    income: DashboardTypeTotals;
    expense: DashboardTypeTotals;
    planned_balance_minor_units: number;
};

export type DashboardPageProps = {
    accounts: AccountSummary[];
    termDeposits: TermDepositSummary[];
    spendable_liquidity_minor_units: number;
    total_funds_minor_units: number;
    budget_year: number;
    budget: BudgetSummary | null;
    incomeCategories: DashboardCategory[];
    expenseCategories: DashboardCategory[];
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    year_elapsed_as_of: string | null;
    year_elapsed_percent: number | null;
    dashboardNotices: DashboardNotice[];
    month_to_spend: {
        state: 'proposal' | 'approved';
        to_spend_minor_units: number;
    } | null;
    month_pace: MonthPace | null;
    current_month: number | null;
    planSummary: PlanSummary | null;
};
