import { describe, expect, it } from 'vite-plus/test';
import {
    categoryOptionName,
    categorySections,
} from './category-filter-options';

const labels = { income: 'Dochody', expense: 'Wydatki' };
const option = (
    id: number,
    type: 'income' | 'expense',
    group: { id: number; name: string } | null = null,
    is_archived = false,
) => ({
    id,
    name: `C${id}`,
    type,
    icon: 'tag',
    color: '#0d9488',
    group,
    is_archived,
});

describe('categorySections', () => {
    it('puts income first and keeps the order of the server inside a section', () => {
        const sections = categorySections(
            [
                option(9, 'expense'),
                option(3, 'income'),
                option(2, 'expense'),
                option(1, 'income'),
            ],
            labels,
        );

        expect(sections.map((section) => section.label)).toEqual([
            'Dochody',
            'Wydatki',
        ]);
        expect(
            sections[0].blocks.map((block) =>
                block.kind === 'option' ? block.option.id : -1,
            ),
        ).toEqual([3, 1]);
        expect(
            sections[1].blocks.map((block) =>
                block.kind === 'option' ? block.option.id : -1,
            ),
        ).toEqual([9, 2]);
    });

    it('joins neighbours of one group into a labelled block', () => {
        const dom = { id: 7, name: 'Dom' };
        const [section] = categorySections(
            [
                option(1, 'expense', dom),
                option(2, 'expense', dom),
                option(3, 'expense'),
                option(4, 'expense', dom),
            ],
            labels,
        );

        expect(section.blocks.map((block) => block.kind)).toEqual([
            'group',
            'option',
            'group',
        ]);
        const first = section.blocks[0];

        expect(
            first.kind === 'group' ? first.options.map((item) => item.id) : [],
        ).toEqual([1, 2]);
    });

    it('leaves out a section without Categories', () => {
        const sections = categorySections([option(1, 'expense')], labels);

        expect(sections.map((section) => section.type)).toEqual(['expense']);
        expect(categorySections([], labels)).toEqual([]);
    });
});

describe('categoryOptionName', () => {
    it('appends the suffix only to an archived Category', () => {
        expect(
            categoryOptionName(
                option(1, 'expense', null, true),
                '(zarchiwizowana)',
            ),
        ).toBe('C1 (zarchiwizowana)');
        expect(
            categoryOptionName(option(1, 'expense'), '(zarchiwizowana)'),
        ).toBe('C1');
        expect(categoryOptionName(option(1, 'expense', null, true))).toBe('C1');
    });
});
