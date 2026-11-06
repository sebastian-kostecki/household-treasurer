import type { AccountRow, AccountsPageProps } from '@/types/accounts';

export type AccountsScenario = {
    key: string;
    title: string;
    props: AccountsPageProps;
};

function account(overrides: Partial<AccountRow> & { id: number }): AccountRow {
    return {
        name: 'Konto',
        type: 'bank',
        bank: 'other',
        mark_url: null,
        opening_date: '2026-01-01',
        opening_balance_minor_units: 0,
        current_balance_minor_units: 0,
        is_negative: false,
        is_archived: false,
        can_delete: true,
        can_archive: false,
        can_restore: false,
        ...overrides,
    };
}

const mbank = account({
    id: 1,
    name: 'mBank osobiste',
    bank: 'mbank',
    mark_url: '/marks/mbank.jpg',
    opening_balance_minor_units: 100000,
    current_balance_minor_units: 245050,
    can_delete: false,
    can_archive: true,
});

const otherBank = account({
    id: 2,
    name: 'Konto oszczędnościowe',
    bank: 'other',
    current_balance_minor_units: 0,
});

const cash = account({
    id: 3,
    name: 'Gotówka',
    type: 'cash',
    bank: null,
    current_balance_minor_units: 12000,
});

const overdrawn = account({
    id: 4,
    name: 'Karta kredytowa',
    bank: 'ing',
    mark_url: '/marks/ing.svg',
    current_balance_minor_units: -35075,
    is_negative: true,
    can_delete: false,
    can_archive: true,
});

const archivedBank = account({
    id: 5,
    name: 'Stare konto Pekao',
    bank: 'pekao',
    mark_url: '/marks/pekao.svg',
    is_archived: true,
    can_delete: false,
    can_restore: true,
});

const archivedCash = account({
    id: 6,
    name: 'Stara skarbonka',
    type: 'cash',
    bank: null,
    is_archived: true,
    can_delete: false,
    can_restore: true,
});

export const accountsScenarios: AccountsScenario[] = [
    {
        key: 'default',
        title: 'default — salda dodatnie, zero i ujemne, mieszanka akcji',
        props: {
            accounts: [mbank, otherBank, cash, overdrawn],
            liquidity_total_minor_units: 221975,
            total_funds_minor_units: 1221975,
            show_archived: false,
            archived_accounts_count: 0,
        },
    },
    {
        key: 'with-archived',
        title: 'with-archived — filtr włączony, dwa zarchiwizowane',
        props: {
            accounts: [mbank, cash, archivedBank, archivedCash],
            liquidity_total_minor_units: 257050,
            total_funds_minor_units: 257050,
            show_archived: true,
            archived_accounts_count: 2,
        },
    },
    {
        key: 'zero-total',
        title: 'zero-total — suma i salda 0',
        props: {
            accounts: [
                account({ id: 7, name: 'Puste konto', bank: 'revolut' }),
                account({
                    id: 8,
                    name: 'Pusta gotówka',
                    type: 'cash',
                    bank: null,
                }),
            ],
            liquidity_total_minor_units: 0,
            total_funds_minor_units: 0,
            show_archived: false,
            archived_accounts_count: 0,
        },
    },
    {
        key: 'negative-total',
        title: 'negative-total — suma ujemna',
        props: {
            accounts: [overdrawn],
            liquidity_total_minor_units: -35075,
            total_funds_minor_units: -35075,
            show_archived: false,
            archived_accounts_count: 0,
        },
    },
    {
        key: 'empty-new-user',
        title: 'empty-new-user — brak kont',
        props: {
            accounts: [],
            liquidity_total_minor_units: 0,
            total_funds_minor_units: 0,
            show_archived: false,
            archived_accounts_count: 0,
        },
    },
    {
        key: 'all-archived',
        title: 'all-archived — wszystkie zarchiwizowane, filtr wyłączony',
        props: {
            accounts: [],
            liquidity_total_minor_units: 52000,
            total_funds_minor_units: 52000,
            show_archived: false,
            archived_accounts_count: 2,
        },
    },
];
