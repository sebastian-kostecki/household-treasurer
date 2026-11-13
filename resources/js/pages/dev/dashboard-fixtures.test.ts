import { expect, test } from 'vite-plus/test';
import { dashboardScenarios } from './dashboard-fixtures';

function scenario(key: string) {
    const found = dashboardScenarios.find((item) => item.key === key);

    if (!found) {
        throw new Error(`Missing scenario ${key}`);
    }

    return found.props;
}

test('scenario keys are unique and cover the state matrix', () => {
    const keys = dashboardScenarios.map((item) => item.key);

    expect(new Set(keys).size).toBe(keys.length);
    expect(keys).toEqual(
        expect.arrayContaining([
            'many-categories',
            'income-over-100',
            'overrun',
            'all-notice-kinds',
            'deposits-only',
            'month-start',
            'month-end',
            'pace-unknown',
            'no-month-plan',
        ]),
    );
});

test('many-categories shows more than five categories in each group', () => {
    const props = scenario('many-categories');

    expect(props.incomeCategories.length).toBeGreaterThan(5);
    expect(props.expenseCategories.length).toBeGreaterThan(5);
});

test('income-over-100 and overrun go past their plan', () => {
    const income = scenario('income-over-100');
    const overrun = scenario('overrun');

    expect(income.incomeTotals.remaining_minor_units).toBeLessThan(0);
    expect(overrun.expenseTotals.remaining_minor_units).toBeLessThan(0);
    expect(overrun.expenseCategories.some((item) => item.is_over_limit)).toBe(
        true,
    );
});

test('all-notice-kinds has eight kinds and more than six cards', () => {
    const notices = scenario('all-notice-kinds').dashboardNotices;

    expect(notices.length).toBeGreaterThan(6);
    expect(new Set(notices.map((notice) => notice.kind)).size).toBe(8);
});

test('deposits-only has term deposits and no accounts', () => {
    const props = scenario('deposits-only');

    expect(props.accounts).toEqual([]);
    expect(props.termDeposits.length).toBeGreaterThan(0);
});

test('month pace scenarios hit their edges', () => {
    expect(scenario('month-start').month_pace?.elapsed_day).toBe(1);

    const end = scenario('month-end').month_pace;
    expect(end?.elapsed_day).toBe(end?.days_in_month);

    expect(scenario('pace-unknown').month_pace?.percent).toBeNull();

    const none = scenario('no-month-plan');
    expect(none.month_to_spend).toBeNull();
    expect(none.month_pace).toBeNull();
});
