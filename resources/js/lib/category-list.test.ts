import { describe, expect, it } from 'vite-plus/test';
import {
    activeCountForm,
    categoryAllYearsOperationsQuery,
    categoryYearsLabel,
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

describe('categoryYearsLabel', () => {
    it('joins the years with a middle dot', () => {
        expect(categoryYearsLabel([2026, 2027])).toBe('2026 · 2027');
        expect(categoryYearsLabel([2026])).toBe('2026');
        expect(categoryYearsLabel([])).toBe('');
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
