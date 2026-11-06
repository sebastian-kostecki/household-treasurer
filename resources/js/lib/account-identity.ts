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

const bankShortMarks: Record<AccountBank, string | null> = {
    alior: 'ALI',
    bnp_paribas: 'BNP',
    ing: 'ING',
    lokaty_facto: 'LF',
    mbank: 'mB',
    pekao: 'PEK',
    pko_bp: 'PKO',
    revolut: 'REV',
    santander: 'SAN',
    other: null,
};

/** Text for the account tile: „zł” for cash, the bank's short mark, null when the tile shows an icon. */
export function accountShortMark(
    account: Pick<AccountIdentityFields, 'type' | 'bank'>,
): string | null {
    if (account.type === 'cash') {
        return 'zł';
    }

    return account.bank === null ? null : bankShortMarks[account.bank];
}

/** Bank name from the `accounts.bank_*` translations; the enum value when a key is missing. */
export function bankLabel(
    accountTranslations: Record<string, string>,
    bank: AccountBank,
): string {
    return accountTranslations[`bank_${bank}`] ?? bank;
}

export type AccountAvatarContent =
    | { kind: 'logo'; src: string }
    | { kind: 'mark'; text: string }
    | { kind: 'icon' };

/** What the account avatar shows: the bank logo, else the short mark (also after an image error), else the icon. */
export function accountAvatarContent(
    account: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'>,
    imageFailed: boolean,
): AccountAvatarContent {
    if (account.type === 'bank' && account.mark_url !== null && !imageFailed) {
        return { kind: 'logo', src: account.mark_url };
    }

    const text = accountShortMark(account);

    return text === null ? { kind: 'icon' } : { kind: 'mark', text };
}
