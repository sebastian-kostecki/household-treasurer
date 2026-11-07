import {
    financialOperationAmountClass,
    formatFinancialOperationAmount,
    formatMoneyAmount,
} from './format';
import type { PairRowSelection } from './import-pair-selection';

/**
 * Text and colour class of an import row amount by the chosen type (ADR-0038):
 * income and refund are positive, expense is negative, transfers keep the file sign.
 * No unit in the cell; the panel footer says "Kwoty w zł" (ADR-0058).
 */
export function importRowAmount(
    type: PairRowSelection['type'],
    amountMinorUnits: number,
): { text: string; className: string } {
    const absolute = Math.abs(amountMinorUnits);

    switch (type) {
        case 'income':
            return {
                text: formatFinancialOperationAmount('income', absolute),
                className: financialOperationAmountClass('income', absolute),
            };
        case 'expense':
            return {
                text: formatFinancialOperationAmount('expense', absolute),
                className: financialOperationAmountClass('expense', absolute),
            };
        case 'refund':
            return {
                text: formatFinancialOperationAmount('expense', -absolute),
                className: financialOperationAmountClass('expense', -absolute),
            };
        case 'own_transfer':
        case 'capital_movement':
            return {
                text: formatMoneyAmount(amountMinorUnits),
                className: 'text-foreground',
            };
    }
}
