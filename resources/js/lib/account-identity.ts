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
    is_archived?: boolean;
};

export type TermDepositIdentityFields = {
    id: number;
    name: string;
    bank: AccountBank;
    mark_url: string | null;
    is_archived?: boolean;
};

/** Term Deposits as `AccountSelect` options: always a bank mark, never cash (ADR-0054). */
export function termDepositPickerOptions(
    deposits: TermDepositIdentityFields[],
): AccountPickerOption[] {
    return deposits.map((deposit) => ({
        id: deposit.id,
        name: deposit.name,
        type: 'bank',
        bank: deposit.bank,
        mark_url: deposit.mark_url,
        is_archived: deposit.is_archived,
    }));
}

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

/** Text for the account tile: the bank's short mark, null when the tile shows an icon (cash and other, ADR-0058). */
export function accountShortMark(
    account: Pick<AccountIdentityFields, 'type' | 'bank'>,
): string | null {
    if (account.type === 'cash') {
        return null;
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
    | { kind: 'icon'; icon: 'cash' | 'other' };

/** What the account avatar shows: the bank logo, else the short mark (also after an image error), else the icon (`Coins` for cash, `Building2` otherwise, ADR-0058). */
export function accountAvatarContent(
    account: Pick<AccountIdentityFields, 'type' | 'bank' | 'mark_url'>,
    imageFailed: boolean,
): AccountAvatarContent {
    if (account.type === 'cash') {
        return { kind: 'icon', icon: 'cash' };
    }

    if (account.type === 'bank' && account.mark_url !== null && !imageFailed) {
        return { kind: 'logo', src: account.mark_url };
    }

    const text = accountShortMark(account);

    return text === null
        ? { kind: 'icon', icon: 'other' }
        : { kind: 'mark', text };
}
