import type { ReconciliationPreviewState } from '@/composables/useBalanceReconciliationPreview';
import type {
    AccountRow,
    AccountsPageProps,
    ReconciliationOperation,
    ReconciliationPreview,
} from '@/types/accounts';

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
        can_reconcile: !overrides.is_archived,
        last_reconciliation: null,
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
    last_reconciliation: {
        reconciled_on: '2026-10-31',
        bank_balance_minor_units: 231000,
        balance_minor_units: 231000,
        difference_minor_units: 0,
        agrees: true,
    },
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
    last_reconciliation: {
        reconciled_on: '2026-11-05',
        bank_balance_minor_units: 10760,
        balance_minor_units: 12000,
        difference_minor_units: -1240,
        agrees: false,
    },
});

const overdrawn = account({
    id: 4,
    name: 'Karta kredytowa',
    bank: 'ing',
    mark_url: null,
    current_balance_minor_units: -35075,
    is_negative: true,
    can_delete: false,
    can_archive: true,
});

const archivedBank = account({
    id: 5,
    name: 'Stare konto Pekao',
    bank: 'pekao',
    mark_url: null,
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

const archivedNegative = account({
    id: 9,
    name: 'Zamknięta karta Pekao',
    bank: 'pekao',
    mark_url: null,
    current_balance_minor_units: -12000,
    is_negative: true,
    is_archived: true,
    can_delete: false,
    can_restore: true,
});

const archivedPositive = account({
    id: 10,
    name: 'Stare konto Revolut',
    bank: 'revolut',
    mark_url: '/marks/revolut.svg',
    current_balance_minor_units: 8000,
    is_archived: true,
    can_delete: false,
    can_restore: true,
});

const longNameBank = account({
    id: 11,
    name: 'Wspólne konto rodzinne na wydatki bieżące, rachunki i opłaty za przedszkole',
    bank: 'bnp_paribas',
    mark_url: '/marks/bnp-paribas.jpg',
    current_balance_minor_units: 1234567,
    can_delete: false,
    can_archive: true,
});

const longNameCash = account({
    id: 12,
    name: 'Gotówka w kopercie na nieprzewidziane wydatki domowe i drobne naprawy',
    type: 'cash',
    bank: null,
    current_balance_minor_units: 4550,
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
    {
        key: 'archived-negative',
        title: 'archived-negative — zarchiwizowane z ujemnym saldem (czerwone) obok dodatniego (bez tonu)',
        props: {
            accounts: [mbank, archivedNegative, archivedPositive],
            liquidity_total_minor_units: 241050,
            total_funds_minor_units: 241050,
            show_archived: true,
            archived_accounts_count: 2,
        },
    },
    {
        key: 'long-names',
        title: 'long-names — długa nazwa konta i najdłuższa nazwa banku, zawijanie przy 390 px',
        props: {
            accounts: [longNameBank, longNameCash],
            liquidity_total_minor_units: 1239117,
            total_funds_minor_units: 1239117,
            show_archived: false,
            archived_accounts_count: 0,
        },
    },
];

export type ReconciliationSinkScenario = {
    key: string;
    title: string;
    state: ReconciliationPreviewState;
    /** What is typed into „Saldo z banku” when the dialog opens. */
    initialBankBalance: string;
};

function sinkOperation(
    index: number,
    overrides: Partial<ReconciliationOperation> = {},
): ReconciliationOperation {
    return {
        key: `operation-${index}`,
        kind: 'expense',
        date: '2026-11-04',
        description: 'Zakupy spożywcze',
        amount_minor_units: -8420,
        ...overrides,
    };
}

const sinkPreview: ReconciliationPreview = {
    reconciled_on: '2026-11-13',
    balance_minor_units: 245050,
    previous_reconciled_on: '2026-10-31',
    existing_bank_balance_minor_units: null,
    operations: [
        sinkOperation(1, {
            kind: 'income',
            date: '2026-11-02',
            description: 'Wynagrodzenie listopad',
            amount_minor_units: 520000,
        }),
        sinkOperation(2),
        sinkOperation(3, {
            kind: 'own_transfer',
            date: '2026-11-07',
            description: 'Wypłata z bankomatu',
            amount_minor_units: -20000,
        }),
        sinkOperation(4, {
            kind: 'capital_movement',
            date: '2026-11-09',
            description: 'Powrót z lokaty',
            amount_minor_units: 100000,
        }),
        sinkOperation(5, {
            date: '2026-11-12',
            description:
                'Opłata za przedszkole i zajęcia dodatkowe za listopad, rata druga',
            amount_minor_units: -65000,
        }),
    ],
    operations_count: 5,
};

export const reconciliationSinkScenarios: ReconciliationSinkScenario[] = [
    {
        key: 'agrees',
        title: 'zgadza się — różnica 0,00',
        state: { status: 'loaded', preview: sinkPreview },
        initialBankBalance: '2450,50',
    },
    {
        key: 'differs',
        title: 'nie zgadza się — różnica −12,40 zł, podpowiedź pod odczytem',
        state: { status: 'loaded', preview: sinkPreview },
        initialBankBalance: '2438,10',
    },
    {
        key: 'unreadable-balance',
        title: 'saldo z banku nieczytelne — różnica „—”, zapis zablokowany',
        state: { status: 'loaded', preview: sinkPreview },
        initialBankBalance: '12,3,4',
    },
    {
        key: 'existing',
        title: 'ten dzień był już uzgodniony — ostrzeżenie o zastąpieniu',
        state: {
            status: 'loaded',
            preview: {
                ...sinkPreview,
                existing_bank_balance_minor_units: 240000,
            },
        },
        initialBankBalance: '2450,50',
    },
    {
        key: 'first-reconciliation',
        title: 'pierwsze uzgodnienie — wszystkie operacje do tego dnia',
        state: {
            status: 'loaded',
            preview: { ...sinkPreview, previous_reconciled_on: null },
        },
        initialBankBalance: '2450,50',
    },
    {
        key: 'no-operations',
        title: 'brak operacji od ostatniego uzgodnienia',
        state: {
            status: 'loaded',
            preview: { ...sinkPreview, operations: [], operations_count: 0 },
        },
        initialBankBalance: '2450,50',
    },
    {
        key: 'truncated',
        title: 'lista ucięta — 100 najnowszych z 340, przewijana',
        state: {
            status: 'loaded',
            preview: {
                ...sinkPreview,
                operations: Array.from({ length: 100 }, (_, index) =>
                    sinkOperation(index + 10, {
                        date: '2026-11-01',
                        amount_minor_units: -1000 - index,
                    }),
                ),
                operations_count: 340,
            },
        },
        initialBankBalance: '2450,50',
    },
    {
        key: 'loading',
        title: 'loading — liczenie salda na dzień',
        state: { status: 'loading' },
        initialBankBalance: '2450,50',
    },
    {
        key: 'error',
        title: 'error — serwer odrzucił dzień',
        state: {
            status: 'error',
            message: 'Data uzgodnienia nie może być z przyszłości.',
        },
        initialBankBalance: '2450,50',
    },
];
