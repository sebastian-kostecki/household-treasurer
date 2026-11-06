import type { ImportStartAccount } from '@/lib/import-start';

export const importStartAccounts: ImportStartAccount[] = [
    {
        id: 1,
        name: 'mBank osobiste',
        type: 'bank',
        bank: 'mbank',
        mark_url: '/marks/mbank.jpg',
        opening_date: '2026-01-01',
    },
    {
        id: 2,
        name: 'BNP Paribas oszczędnościowe',
        type: 'bank',
        bank: 'bnp_paribas',
        mark_url: '/marks/bnp-paribas.jpg',
        opening_date: '2026-01-01',
    },
    {
        id: 3,
        name: 'Gotówka',
        type: 'cash',
        bank: null,
        mark_url: null,
        opening_date: '2026-01-01',
    },
];

export const importStartNoAccounts: ImportStartAccount[] = [];

export const columnLabelsWithHeader = [
    'Data operacji',
    'Opis',
    'Kontrahent',
    'Kwota',
    'Wpływ',
    'Wypływ',
];

export const columnLabelsWithoutHeader = [
    'Kolumna 1',
    'Kolumna 2',
    'Kolumna 3',
    'Kolumna 4',
    'Kolumna 5',
    'Kolumna 6',
];

export const importStartErrors: Partial<Record<string, string>> = {
    has_header: 'Wybierz, czy plik ma nagłówek.',
    date_column: 'Wybierz kolumnę z datą.',
    amount_layout: 'Wybierz układ kwot.',
};

export const noImportStartErrors: Partial<Record<string, string>> = {};
