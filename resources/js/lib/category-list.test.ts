import { describe, expect, it } from 'vite-plus/test';
import {
    activeCountForm,
    categoryAllYearsOperationsQuery,
    categoryYearCells,
    categoryYearsRangeLabel,
    groupDeleteDescription,
} from './category-list';

describe('groupDeleteDescription', () => {
    const copy = {
        group_delete_description: 'Grupa zniknie.',
        group_delete_orphans_zero: 'Grupa jest pusta.',
        group_delete_orphans_one: '1 kategoria zostanie bez grupy.',
        group_delete_orphans_few: ':count kategorie zostaną bez grupy.',
        group_delete_orphans_many: ':count kategorii zostanie bez grupy.',
        group_delete_archived:
            'Zarchiwizowane kategorie grupy (:count) też stracą grupę.',
    };

    it.each([
        [0, 0, 'Grupa zniknie. Grupa jest pusta.'],
        [1, 0, 'Grupa zniknie. 1 kategoria zostanie bez grupy.'],
        [2, 0, 'Grupa zniknie. 2 kategorie zostaną bez grupy.'],
        [5, 0, 'Grupa zniknie. 5 kategorii zostanie bez grupy.'],
        [
            1,
            1,
            'Grupa zniknie. 1 kategoria zostanie bez grupy. Zarchiwizowane kategorie grupy (1) też stracą grupę.',
        ],
        // A group with archived Categories only is not empty.
        [
            0,
            2,
            'Grupa zniknie. Zarchiwizowane kategorie grupy (2) też stracą grupę.',
        ],
    ])(
        'describes %i active and %i archived Categories',
        (active, archived, expected) => {
            expect(groupDeleteDescription(active, archived, copy)).toBe(
                expected,
            );
        },
    );
});

describe('categoryAllYearsOperationsQuery', () => {
    it('filters by the list category id only, with no year or date range', () => {
        expect(categoryAllYearsOperationsQuery({ id: 7 })).toEqual({
            q: undefined,
            year: undefined,
            category_id: 7,
            account_id: undefined,
            term_deposit_id: undefined,
            date_from: undefined,
            date_to: undefined,
            section: undefined,
        });
    });
});

describe('categoryYearsRangeLabel', () => {
    it('collapses consecutive years into a range', () => {
        expect(
            categoryYearsRangeLabel([
                2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027,
            ]),
        ).toBe('2020–2027');
        expect(categoryYearsRangeLabel([2020, 2021, 2022, 2025])).toBe(
            '2020–2022 · 2025',
        );
    });

    it('separates single years with a dot', () => {
        expect(categoryYearsRangeLabel([2020, 2022, 2024, 2027])).toBe(
            '2020 · 2022 · 2024 · 2027',
        );
        expect(categoryYearsRangeLabel([2026])).toBe('2026');
    });

    it('is empty without years', () => {
        expect(categoryYearsRangeLabel([])).toBe('');
    });
});

describe('categoryYearCells', () => {
    it('merges the axis with the budget years, ascending and unique', () => {
        expect(categoryYearCells([2025, 2026, 2027], [2027, 2024])).toEqual([
            { year: 2024, short: '24', on: true },
            { year: 2025, short: '25', on: false },
            { year: 2026, short: '26', on: false },
            { year: 2027, short: '27', on: true },
        ]);
    });
});

describe('activeCountForm', () => {
    it.each([
        [0, 'many'],
        [1, 'one'],
        [2, 'few'],
        [4, 'few'],
        [5, 'many'],
        [12, 'many'],
        [14, 'many'],
        [22, 'few'],
        [41, 'many'],
    ] as const)('maps %i to %s', (count, form) => {
        expect(activeCountForm(count)).toBe(form);
    });
});
