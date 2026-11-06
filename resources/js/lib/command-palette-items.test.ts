import { describe, expect, it } from 'vite-plus/test';
import {
    buildPaletteActions,
    foldDiacritics,
    paletteActionSearchValue,
    paletteCategorySearchValue,
    paletteShortcutLabel,
} from './command-palette-items';

const labels = {
    addOperation: 'Dodaj operację',
    importCsv: 'Importuj CSV',
    addBudgetYear: 'Dodaj rok budżetu',
    settings: 'Ustawienia',
};

const views = [
    { title: 'Dashboard', shortcut: '1' },
    { title: 'Budżet', shortcut: '2' },
];

describe('buildPaletteActions', () => {
    it('lists the views first, then the fixed actions', () => {
        const actions = buildPaletteActions(views, labels);

        expect(actions.map((action) => action.item.kind)).toEqual([
            'view',
            'view',
            'add-operation',
            'import',
            'add-budget-year',
            'settings',
        ]);
        expect(actions.map((action) => action.label)).toEqual([
            'Dashboard',
            'Budżet',
            'Dodaj operację',
            'Importuj CSV',
            'Dodaj rok budżetu',
            'Ustawienia',
        ]);
    });

    it('gives views their shortcut and the add action N', () => {
        const actions = buildPaletteActions(views, labels);

        expect(actions.map((action) => action.shortcut)).toEqual([
            '1',
            '2',
            'N',
            undefined,
            undefined,
            undefined,
        ]);
    });

    it('indexes the views by their position', () => {
        const actions = buildPaletteActions(views, labels);

        expect(actions[1].item).toEqual({ kind: 'view', index: 1 });
    });

    it('gives every action a unique key', () => {
        const keys = buildPaletteActions(views, labels).map((a) => a.key);

        expect(new Set(keys).size).toBe(keys.length);
    });
});

describe('search values', () => {
    it('searches an action by its label and a category by its name', () => {
        const [action] = buildPaletteActions(views, labels);

        expect(paletteActionSearchValue(action)).toBe('Dashboard');
        expect(
            paletteCategorySearchValue({
                id: 1,
                name: 'Dom',
                type: 'expense',
                icon: 'house',
                color: '#000000',
                execution_minor_units: 0,
            }),
        ).toBe('Dom');
    });

    it('folds Polish diacritics', () => {
        expect(foldDiacritics('Żywność')).toBe('Zywnosc');
        expect(foldDiacritics('Łódź gęś')).toBe('Lodz ges');
    });
});

describe('paletteShortcutLabel', () => {
    it('is ⌘K on Apple platforms', () => {
        expect(paletteShortcutLabel('MacIntel')).toBe('⌘K');
        expect(paletteShortcutLabel('iPhone')).toBe('⌘K');
    });

    it('is Ctrl+K elsewhere', () => {
        expect(paletteShortcutLabel('Win32')).toBe('Ctrl+K');
        expect(paletteShortcutLabel('Linux x86_64')).toBe('Ctrl+K');
        expect(paletteShortcutLabel('')).toBe('Ctrl+K');
    });
});
