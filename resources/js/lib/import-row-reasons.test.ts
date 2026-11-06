import { describe, expect, it } from 'vite-plus/test';
import type { PairCandidate } from './import-pair-selection';
import {
    importInvalidReasonText,
    importPairReasonText,
} from './import-row-reasons';

const operations = {
    import_invalid_reason_future_date:
        'Data operacji nie może być w przyszłości.',
    import_pair_reason_financial_operation:
        ':account (:date): istniejący dochód lub wydatek stanie się tym transferem własnym.',
    import_pair_reason_own_transfer:
        ':account (:date): ten wiersz dołączy jako druga strona istniejącego transferu własnego.',
};

function candidate(kind: PairCandidate['kind']): PairCandidate {
    return {
        kind,
        id: 1,
        account_id: 2,
        account_name: 'Konto oszczędnościowe',
        account_type: 'bank',
        account_bank: 'mbank',
        account_mark_url: '/marks/mbank.jpg',
        date: '2026-11-02',
        description: 'Przelew',
        converts_financial_operation: kind === 'financial_operation',
    };
}

describe('importInvalidReasonText', () => {
    it('turns a rejection code into a Polish sentence', () => {
        expect(importInvalidReasonText('future_date', operations)).toBe(
            'Data operacji nie może być w przyszłości.',
        );
    });

    it('falls back to the raw value for an unknown code', () => {
        expect(
            importInvalidReasonText('Invalid or missing date.', operations),
        ).toBe('Invalid or missing date.');
    });
});

describe('importPairReasonText', () => {
    it('names the account and date for a financial operation', () => {
        expect(
            importPairReasonText(candidate('financial_operation'), operations),
        ).toBe(
            'Konto oszczędnościowe (02.11.2026): istniejący dochód lub wydatek stanie się tym transferem własnym.',
        );
    });

    it('names the account and date for an own transfer', () => {
        expect(
            importPairReasonText(candidate('own_transfer'), operations),
        ).toBe(
            'Konto oszczędnościowe (02.11.2026): ten wiersz dołączy jako druga strona istniejącego transferu własnego.',
        );
    });
});
