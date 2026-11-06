export type AccountBank =
    | 'alior'
    | 'bnp_paribas'
    | 'ing'
    | 'lokaty_facto'
    | 'mbank'
    | 'pekao'
    | 'pko_bp'
    | 'revolut'
    | 'santander'
    | 'other';

export type AccountTypeValue = 'bank' | 'cash';

export type AccountIdentityFields = {
    name: string;
    type: AccountTypeValue;
    bank: AccountBank | null;
    mark_url: string | null;
};

export type PrefixedAccountIdentityFields<Prefix extends string> = {
    [
        K in keyof AccountIdentityFields as `${Prefix}_${K}`
    ]: AccountIdentityFields[K];
};

export type AccountPickerOption = AccountIdentityFields & {
    id: number;
};

export const bankOptions: AccountBank[] = [
    'alior',
    'bnp_paribas',
    'ing',
    'lokaty_facto',
    'mbank',
    'pekao',
    'pko_bp',
    'revolut',
    'santander',
    'other',
];

const bankMarkPaths: Record<AccountBank, string | null> = {
    alior: '/marks/alior.svg',
    bnp_paribas: '/marks/bnp-paribas.jpg',
    ing: '/marks/ing.svg',
    lokaty_facto: '/marks/lokaty-facto.svg',
    mbank: '/marks/mbank.jpg',
    pekao: '/marks/pekao.svg',
    pko_bp: '/marks/pko-bp.svg',
    revolut: '/marks/revolut.svg',
    santander: '/marks/santander.svg',
    other: null,
};

export function bankMarkPath(bank: AccountBank): string | null {
    return bankMarkPaths[bank];
}

/** Bank name from the `accounts.bank_*` translations; the enum value when a key is missing. */
export function bankLabel(
    accountTranslations: Record<string, string>,
    bank: AccountBank,
): string {
    return accountTranslations[`bank_${bank}`] ?? bank;
}
