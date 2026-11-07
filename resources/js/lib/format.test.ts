import { describe, expect, it } from 'vite-plus/test';
import {
    financialOperationAmountClass,
    formatDayLabel,
    formatFinancialOperationAmount,
    formatLongDate,
    formatMoney,
    formatMoneyAmount,
    formatOperationDaySum,
    formatSignedMoney,
    formatSignedMoneyAmount,
    operationDaySumClass,
    parseMoneyInputToMinorUnits,
} from './format';

describe('parseMoneyInputToMinorUnits', () => {
    it('parses plain, spaced and signed amounts', () => {
        expect(parseMoneyInputToMinorUnits('1200,50')).toBe(120050);
        expect(parseMoneyInputToMinorUnits('1 234,56')).toBe(123456);
        expect(parseMoneyInputToMinorUnits('12,')).toBe(1200);
        expect(parseMoneyInputToMinorUnits('-50')).toBe(-5000);
    });

    it('rejects empty, malformed and multi-separator input', () => {
        expect(parseMoneyInputToMinorUnits('')).toBeNull();
        expect(parseMoneyInputToMinorUnits('-')).toBeNull();
        expect(parseMoneyInputToMinorUnits('12,3,4')).toBeNull();
        expect(parseMoneyInputToMinorUnits('1.234,56')).toBeNull();
    });

    it('rejects amounts beyond the safe integer range', () => {
        expect(parseMoneyInputToMinorUnits('9'.repeat(25))).toBeNull();
    });
});

describe('formatSignedMoney', () => {
    it('prefixes positive amounts with a plus', () => {
        expect(formatSignedMoney(123456)).toBe('+1 234,56 zł');
    });

    it('keeps the minus on negative amounts', () => {
        expect(formatSignedMoney(-5000)).toBe('-50,00 zł');
    });

    it('shows zero without a sign', () => {
        expect(formatSignedMoney(0)).toBe('0,00 zł');
    });
});

describe('formatSignedMoneyAmount', () => {
    it('signs positive amounts and omits the unit', () => {
        expect(formatSignedMoneyAmount(123456)).toBe('+1 234,56');
        expect(formatSignedMoneyAmount(-5000)).toBe('-50,00');
        expect(formatSignedMoneyAmount(0)).toBe('0,00');
    });
});

describe('formatMoneyAmount', () => {
    it('formats a positive amount without the unit', () => {
        expect(formatMoneyAmount(123456)).toBe('1 234,56');
    });

    it('formats zero', () => {
        expect(formatMoneyAmount(0)).toBe('0,00');
    });

    it('keeps the minus on a negative amount', () => {
        expect(formatMoneyAmount(-5000)).toBe('-50,00');
    });

    it('groups thousands and pads grosze', () => {
        expect(formatMoneyAmount(123456789)).toBe('1 234 567,89');
        expect(formatMoneyAmount(100005)).toBe('1 000,05');
    });
});

describe('formatMoney', () => {
    it('appends the unit to formatMoneyAmount', () => {
        expect(formatMoney(123456)).toBe('1 234,56 zł');
        expect(formatMoney(-5000)).toBe('-50,00 zł');
    });
});

describe('formatFinancialOperationAmount', () => {
    it('shows an expense with a minus and no unit', () => {
        expect(formatFinancialOperationAmount('expense', 123456)).toBe(
            '−1 234,56',
        );
    });

    it('shows an income with a plus and no unit', () => {
        expect(formatFinancialOperationAmount('income', 5000)).toBe('+50,00');
    });

    it('shows a refund (negative expense) with a plus', () => {
        expect(formatFinancialOperationAmount('expense', -5000)).toBe('+50,00');
    });
});

describe('financialOperationAmountClass', () => {
    it('keeps an expense in foreground, never destructive', () => {
        expect(financialOperationAmountClass('expense', 5000)).toBe(
            'text-foreground',
        );
    });

    it('colours an income and a refund as success', () => {
        expect(financialOperationAmountClass('income', 5000)).toBe(
            'text-success',
        );
        expect(financialOperationAmountClass('expense', -5000)).toBe(
            'text-success',
        );
    });
});

describe('formatOperationDaySum', () => {
    it('prefixes a positive sum with a plus', () => {
        expect(formatOperationDaySum(123456)).toBe('+1 234,56');
    });

    it('prefixes a negative sum with U+2212', () => {
        expect(formatOperationDaySum(-123456)).toBe('−1 234,56');
    });

    it('shows zero without a sign', () => {
        expect(formatOperationDaySum(0)).toBe('0,00');
    });
});

describe('operationDaySumClass', () => {
    it('uses success only above zero', () => {
        expect(operationDaySumClass(100)).toBe('text-success');
        expect(operationDaySumClass(0)).toBe('text-foreground');
        expect(operationDaySumClass(-100)).toBe('text-foreground');
    });
});

describe('formatLongDate', () => {
    it('writes the weekday, the day, the month name and the year in Polish', () => {
        expect(formatLongDate('2026-11-05')).toBe('czwartek, 5 listopada 2026');
    });
});

describe('formatDayLabel', () => {
    const today = new Date('2026-11-05T12:00:00');

    it('omits the year within the current year', () => {
        expect(formatDayLabel('2026-11-04', today)).toBe('środa, 4 listopada');
    });

    it('adds the year for another year', () => {
        expect(formatDayLabel('2025-12-31', today)).toBe(
            'środa, 31 grudnia 2025',
        );
    });
});
