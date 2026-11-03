import type { AccountBank, AccountTypeValue } from '@/lib/account-identity';

export type AccountFormAccount = {
    id: number;
    name: string;
    type: AccountTypeValue;
    bank: AccountBank | null;
    opening_date: string;
    opening_balance_minor_units: number;
};

export type AccountRow = AccountFormAccount & {
    mark_url: string | null;
    current_balance_minor_units: number;
    is_negative: boolean;
    is_archived: boolean;
    can_delete: boolean;
    can_archive: boolean;
    can_restore: boolean;
};

export type AccountsPageProps = {
    accounts: AccountRow[];
    liquidity_total_minor_units: number;
    show_archived: boolean;
};
