import { expect, test } from 'vite-plus/test';
import { withSelectedCategory } from './operations-category-options';

const options = [
    { id: 1, name: 'Jedzenie', icon: 'apple', color: '#0d9488' },
    { id: 2, name: 'Dom', icon: 'home', color: '#e03131' },
];
const holidays = {
    id: 9,
    name: 'Wakacje',
    icon: 'plane',
    color: '#1971c2',
    archived: true,
};

test('appends the selected archived Category with the suffix', () => {
    const result = withSelectedCategory(options, holidays, '(zarchiwizowana)');

    expect(result).toHaveLength(3);
    expect(result[2]).toEqual({
        id: 9,
        name: 'Wakacje (zarchiwizowana)',
        icon: 'plane',
        color: '#1971c2',
    });
});

test('drops the appended option once another one is selected', () => {
    expect(withSelectedCategory(options, null, '(zarchiwizowana)')).toEqual(
        options,
    );
});

test('leaves the options alone when the Category is among them', () => {
    const result = withSelectedCategory(
        options,
        { ...options[0], archived: false },
        '(zarchiwizowana)',
    );

    expect(result).toBe(options);
});

test('appends a not archived Category missing from the year without a suffix', () => {
    const result = withSelectedCategory(
        options,
        { ...holidays, archived: false },
        '(zarchiwizowana)',
    );

    expect(result[2].name).toBe('Wakacje');
});
