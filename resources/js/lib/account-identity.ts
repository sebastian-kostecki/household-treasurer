export type AccountBank = 'mbank' | 'bnp_paribas' | 'other';

export type AccountTypeValue = 'bank' | 'cash';

export type AccountIdentityFields = {
    name: string;
    type: AccountTypeValue;
    bank: AccountBank | null;
    mark_url: string | null;
};

export const bankOptions: AccountBank[] = ['mbank', 'bnp_paribas', 'other'];

export function bankMarkPath(bank: AccountBank): string | null {
    if (bank === 'mbank') {
        return '/marks/mbank.jpg';
    }

    if (bank === 'bnp_paribas') {
        return '/marks/bnp-paribas.jpg';
    }

    return null;
}
