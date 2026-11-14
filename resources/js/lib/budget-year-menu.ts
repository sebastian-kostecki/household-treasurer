import type { BudgetYearEntry } from '@/types/shell';

/** How many of the newest years the "Ostatnie" group holds. */
const RECENT_YEARS = 3;

export type BudgetYearMenuEntry = {
    year: number;
    selected: boolean;
    /** The year is the current calendar year. */
    current: boolean;
    /** The Budget of the year is a draft. */
    draft: boolean;
};

export type BudgetYearMenu = {
    /** The 3 newest years; all years when there are 3 or fewer. */
    recent: BudgetYearMenuEntry[];
    /** The rest, newest first; empty when there are 3 or fewer years. */
    older: BudgetYearMenuEntry[];
    /** The entry of the selected year, null when it has no Budget. */
    selected: BudgetYearMenuEntry | null;
    createYear: number;
    /** The year the new draft takes its plan from (createYear - 1); null with no Budget. */
    createSourceYear: number | null;
};

/**
 * Content of the sidebar year menu: every year that has a Budget, unique and
 * newest first, split into recent and older, with the state of each year, and
 * the year "Dodaj rok" creates (the highest year + 1, or the current calendar
 * year when there is no Budget) together with its source year.
 */
export function budgetYearMenu(
    budgetYears: BudgetYearEntry[],
    selectedYear: number | null,
    currentYear: number,
): BudgetYearMenu {
    const phases = new Map<number, BudgetYearEntry['phase']>();

    for (const entry of budgetYears) {
        phases.set(entry.year, entry.phase);
    }

    const years = [...phases.keys()].sort((a, b) => b - a);
    const entries = years.map((year): BudgetYearMenuEntry => ({
        year,
        selected: year === selectedYear,
        current: year === currentYear,
        draft: phases.get(year) === 'draft',
    }));
    const highestYear = years[0];
    const grouped = entries.length > RECENT_YEARS;

    return {
        recent: grouped ? entries.slice(0, RECENT_YEARS) : entries,
        older: grouped ? entries.slice(RECENT_YEARS) : [],
        selected: entries.find((entry) => entry.selected) ?? null,
        createYear: highestYear === undefined ? currentYear : highestYear + 1,
        createSourceYear: highestYear ?? null,
    };
}

export type ReportYearSwitchParams = {
    report_category?: string;
    report_ranking?: 'groups';
    report_panel?: 'years';
};

/**
 * What the year switch carries from a Reports address into the year it opens:
 * the Category of `?category` when the Reports page would accept it (up to 18
 * digits, at least 1, sent without leading zeros, because the `integer` rule
 * rejects "007" and a Number would round a long id) `?ranking=groups` and `?panel=years` (the tab of the Category Panel, ADR-0101).
 * Anything else is left out, so it never fails the switch with a 422.
 */
export function reportYearSwitchParams(url: string): ReportYearSwitchParams {
    const params = new URL(url, 'http://localhost').searchParams;
    const result: ReportYearSwitchParams = {};
    const category = params.get('category');

    if (category !== null && /^\d{1,18}$/.test(category)) {
        const digits = category.replace(/^0+/, '');

        if (digits !== '') {
            result.report_category = digits;
        }
    }

    if (params.get('ranking') === 'groups') {
        result.report_ranking = 'groups';
    }

    if (params.get('panel') === 'years') {
        result.report_panel = 'years';
    }

    return result;
}
