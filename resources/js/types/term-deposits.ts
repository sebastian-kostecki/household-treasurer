import type { AccountBank } from '@/lib/account-identity';

export type TermDepositFormTermDeposit = {
    id: number;
    name: string;
    bank: AccountBank;
    opening_date: string;
    end_date: string | null;
    opening_balance_minor_units: number;
    current_balance_minor_units: number;
};

export type TermDepositRow = TermDepositFormTermDeposit & {
    bank: AccountBank;
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

export type TermDepositsPageProps = {
    termDeposits: TermDepositRow[];
    term_deposits_total_minor_units: number;
    archived_term_deposits_total_minor_units: number;
    archived_term_deposits_count: number;
    show_archived: boolean;
};
