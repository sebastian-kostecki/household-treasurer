import type { AccountBank } from '@/lib/account-identity';
import type {
    TermDepositRow,
    TermDepositsPageProps,
} from '@/types/term-deposits';

export type TermDepositsScenario = {
    key: string;
    title: string;
    props: TermDepositsPageProps;
};

function termDeposit(
    id: number,
    name: string,
    bank: AccountBank,
    balance: number,
    overrides: Partial<TermDepositRow> = {},
): TermDepositRow {
    return {
        id,
        name,
        bank,
        opening_date: '2026-01-15',
        end_date: '2027-01-15',
        opening_balance_minor_units: Math.abs(balance) || 100000,
        current_balance_minor_units: balance,
        is_negative: balance < 0,
        is_archived: false,
        can_delete: false,
        can_archive: true,
        can_restore: false,
        ...overrides,
    };
}

function archived(
    id: number,
    name: string,
    bank: AccountBank,
    balance: number,
): TermDepositRow {
    return termDeposit(id, name, bank, balance, {
        is_archived: true,
        can_delete: false,
        can_archive: false,
        can_restore: true,
    });
}

const activeRows = [
    termDeposit(1, 'Lokata mBank 12M', 'mbank', 1250000, {
        can_delete: true,
    }),
    termDeposit(2, 'Lokata w Innym banku', 'other', 480000, {
        end_date: null,
    }),
    termDeposit(3, 'Lokata ING zerowa', 'ing', 0, {
        opening_balance_minor_units: 100000,
        can_delete: true,
    }),
    termDeposit(4, 'Lokata Pekao ujemna', 'pekao', -35000, {
        can_delete: true,
        can_archive: false,
    }),
];

export const termDepositsScenarios: TermDepositsScenario[] = [
    {
        key: 'default',
        title: 'default: aktywne lokaty, różne salda i daty',
        props: {
            termDeposits: activeRows,
            total_funds_minor_units: 2195000,
            term_deposits_total_minor_units: 1695000,
            archived_term_deposits_total_minor_units: 0,
            archived_term_deposits_count: 0,
            show_archived: false,
        },
    },
    {
        key: 'with-archived',
        title: 'with-archived: aktywne i zarchiwizowane (saldo dodatnie, zerowe, ujemne)',
        props: {
            termDeposits: [
                ...activeRows,
                archived(11, 'Zarchiwizowana dodatnia', 'mbank', 300000),
                archived(12, 'Zarchiwizowana zerowa', 'ing', 0),
                archived(13, 'Zarchiwizowana ujemna', 'other', -12000),
            ],
            total_funds_minor_units: 2483000,
            term_deposits_total_minor_units: 1983000,
            archived_term_deposits_total_minor_units: 288000,
            archived_term_deposits_count: 3,
            show_archived: true,
        },
    },
    {
        key: 'with-archived-negative',
        title: 'with-archived-negative: zarchiwizowane saldo ujemne, stopka sumy na czerwono',
        props: {
            termDeposits: [
                termDeposit(41, 'Lokata mBank 6M', 'mbank', 500000, {
                    can_delete: true,
                }),
                archived(42, 'Zarchiwizowana ujemna', 'other', -30000),
            ],
            total_funds_minor_units: 970000,
            term_deposits_total_minor_units: 470000,
            archived_term_deposits_total_minor_units: -30000,
            archived_term_deposits_count: 1,
            show_archived: true,
        },
    },
    {
        key: 'zero-total',
        title: 'zero-total: suma 0, salda 0',
        props: {
            termDeposits: [
                termDeposit(21, 'Lokata mBank pusta', 'mbank', 0, {
                    can_delete: true,
                }),
                termDeposit(22, 'Lokata ING pusta', 'ing', 0, {
                    end_date: null,
                }),
            ],
            total_funds_minor_units: 500000,
            term_deposits_total_minor_units: 0,
            archived_term_deposits_total_minor_units: 0,
            archived_term_deposits_count: 0,
            show_archived: false,
        },
    },
    {
        key: 'negative-total',
        title: 'negative-total: suma ujemna',
        props: {
            termDeposits: [
                termDeposit(31, 'Lokata Pekao ujemna', 'pekao', -80000),
                termDeposit(32, 'Lokata w Innym banku', 'other', 20000),
            ],
            total_funds_minor_units: 440000,
            term_deposits_total_minor_units: -60000,
            archived_term_deposits_total_minor_units: 0,
            archived_term_deposits_count: 0,
            show_archived: false,
        },
    },
    {
        key: 'empty-new-user',
        title: 'empty-new-user: brak lokat, suma 0',
        props: {
            termDeposits: [],
            total_funds_minor_units: 500000,
            term_deposits_total_minor_units: 0,
            archived_term_deposits_total_minor_units: 0,
            archived_term_deposits_count: 0,
            show_archived: false,
        },
    },
    {
        key: 'all-archived',
        title: 'all-archived: wszystkie lokaty zarchiwizowane, ukryte, suma ≠ 0',
        props: {
            termDeposits: [],
            total_funds_minor_units: 650000,
            term_deposits_total_minor_units: 150000,
            archived_term_deposits_total_minor_units: 150000,
            archived_term_deposits_count: 2,
            show_archived: false,
        },
    },
];
